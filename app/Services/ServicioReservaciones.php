<?php

namespace App\Services;

use App\Models\Cabana;
use App\Models\Reservacion;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServicioReservaciones
{
    public function __construct(
        private ServicioDisponibilidad $disponibilidad,
        private ServicioBloqueoCabanas $bloqueo,
        private ServicioPreciosReservacion $precios,
        private ServicioSeleccionHuesped $huespedes,
        private ServicioAuditoria $auditoria,
    ) {}

    public function guardar(Request $solicitud, array $datos, ?Reservacion $reservacion = null): Reservacion
    {
        return DB::transaction(function () use ($solicitud, $datos, $reservacion) {
            $cabanas = $this->bloqueo->adquirir(array_filter([$reservacion?->cabana_id, $datos['cabana_id']]));
            $this->autorizar($solicitud, $reservacion ? 'reservaciones.editar' : 'reservaciones.crear');
            $this->autorizar($solicitud, 'huespedes.buscar');
            $registro = $reservacion ? $this->releer($reservacion) : new Reservacion;
            if ($reservacion && ! in_array($registro->estado, ['por_confirmar', 'pendiente', 'confirmada'], true)) {
                $this->error('estado', 'Solo puedes reagendar antes de registrar la entrada.');
            }
            $this->huespedes->validar((int) $datos['huesped_id']);
            $cabana = $cabanas->get((int) $datos['cabana_id']);
            if (! $cabana || $cabana->trashed()) {
                $this->error('cabana_id', 'Selecciona una cabaña existente que no esté eliminada.');
            }
            $this->autorizarHorarios($solicitud, $datos['hora_entrada'], $datos['hora_salida']);
            $this->exigirDisponibilidad($cabana, $datos, $reservacion ? $registro : null);
            $anteriores = $registro->toArray();
            $periodo = $this->disponibilidad->periodo($datos['fecha_entrada'], $datos['fecha_salida'], $datos['hora_entrada'], $datos['hora_salida']);
            $precios = $this->precios->calcular($cabana, $datos, $reservacion ? $registro : null);
            $registro->fill(Arr::only($datos, ['cabana_id', 'huesped_id', 'fecha_entrada', 'fecha_salida', 'cantidad_huespedes', 'notas']));
            $registro->forceFill($precios);
            $registro->forceFill(['entrada_prevista' => $periodo['entrada'], 'salida_prevista' => $periodo['salida']]);
            if (! $reservacion) {
                $registro->forceFill(['codigo' => (string) Str::uuid(), 'estado' => $datos['estado_inicial'], 'creado_por' => $solicitud->user()->id]);
            }
            $registro->save();
            $this->auditoria->registrarCambio($solicitud, 'reservaciones', $reservacion ? 'reagendada' : 'creada', $registro->id, $anteriores, $registro->toArray());

            return $registro;
        }, 3);
    }

    public function cambiarEstado(Request $solicitud, Reservacion $reservacion, string $destino, array $datos = []): void
    {
        $permisos = [
            'pendiente' => 'editar', 'confirmada' => 'confirmar', 'hospedada' => 'registrar_entrada',
            'finalizada' => 'registrar_salida', 'cancelada' => 'cancelar', 'no_presentado' => 'marcar_no_presentado',
        ];
        abort_unless(isset($permisos[$destino]), 403);
        $origenes = [
            'pendiente' => ['por_confirmar'],
            'confirmada' => ['por_confirmar', 'pendiente'],
            'hospedada' => ['confirmada'],
            'finalizada' => ['hospedada'],
            'cancelada' => ['por_confirmar', 'pendiente', 'confirmada'],
            'no_presentado' => ['por_confirmar', 'pendiente', 'confirmada'],
        ];
        $this->modificar($solicitud, $reservacion, 'reservaciones.'.$permisos[$destino], $destino, function (Reservacion $registro) use ($solicitud, $destino, $datos, $origenes) {
            if (! in_array($registro->estado, $origenes[$destino], true)) {
                $this->error('estado', 'La transición solicitada no corresponde al estado actual de la reservación.');
            }
            $ahora = CarbonImmutable::now('UTC');
            if ($destino === 'no_presentado' && $ahora->lessThan($registro->salida_prevista)) {
                $this->error('estado', 'No presentado solo se permite al cumplirse la hora de salida prevista.');
            }
            if ($destino === 'hospedada' && $ahora->lessThan($registro->entrada_prevista)) {
                $this->error('estado', 'Para una entrada anticipada, administración debe autorizar primero la hora de entrada.');
            }
            if ($destino === 'hospedada' && $ahora->greaterThanOrEqualTo($registro->salida_prevista)) {
                $this->error('estado', 'La estancia prevista ya terminó. Reagenda antes de registrar la entrada.');
            }
            if ($destino === 'finalizada' && $ahora->greaterThan($registro->salida_prevista)) {
                $this->autorizar($solicitud, 'reservaciones.autorizar_salida_tardia');
            }
            if (in_array($destino, ['confirmada', 'hospedada'], true)) {
                $this->huespedes->validar($registro->huesped_id);
                $this->exigirDisponibilidad(Cabana::findOrFail($registro->cabana_id), $this->datosPeriodo($registro), $registro, $destino === 'hospedada');
            }
            $camposFecha = [
                'confirmada' => 'fecha_confirmacion', 'hospedada' => 'fecha_entrada_registrada',
                'finalizada' => 'fecha_salida_registrada', 'cancelada' => 'fecha_cancelacion', 'no_presentado' => 'fecha_no_presentado',
            ];
            $registro->estado = $destino;
            if (isset($camposFecha[$destino])) {
                $registro->setAttribute($camposFecha[$destino], $ahora);
            }
            if ($destino === 'cancelada') {
                $registro->forceFill(['cancelado_por' => $solicitud->user()->id, 'motivo_cancelacion' => $datos['motivo_cancelacion'] ?? null]);
            }
            $registro->save();
        });
    }

    public function horarios(Request $solicitud, Reservacion $reservacion, array $datos): void
    {
        $this->modificar($solicitud, $reservacion, 'reservaciones.editar', 'horario_autorizado', function (Reservacion $registro) use ($solicitud, $datos) {
            if (! in_array($registro->estado, ['por_confirmar', 'pendiente', 'confirmada', 'hospedada'], true)) {
                $this->error('estado', 'No puedes cambiar horarios de una reservación terminada.');
            }
            $periodoAnterior = $this->datosPeriodo($registro);
            $horaEntrada = $datos['hora_entrada'] ?? $periodoAnterior['hora_entrada'];
            $horaSalida = $datos['hora_salida'] ?? $periodoAnterior['hora_salida'];
            if ($registro->estado === 'hospedada' && $horaEntrada !== $periodoAnterior['hora_entrada']) {
                $this->error('hora_entrada', 'La entrada ya fue registrada.');
            }
            if ($horaEntrada !== $periodoAnterior['hora_entrada']) {
                $this->autorizar($solicitud, 'reservaciones.autorizar_entrada_temprana');
            }
            if ($horaSalida !== $periodoAnterior['hora_salida']) {
                $this->autorizar($solicitud, 'reservaciones.autorizar_salida_tardia');
            }
            $this->autorizarHorarios($solicitud, $horaEntrada, $horaSalida);
            $periodoAnterior['hora_entrada'] = $horaEntrada;
            $periodoAnterior['hora_salida'] = $horaSalida;
            $this->exigirDisponibilidad(Cabana::findOrFail($registro->cabana_id), $periodoAnterior, $registro);
            $periodo = $this->disponibilidad->periodo($periodoAnterior['fecha_entrada'], $periodoAnterior['fecha_salida'], $horaEntrada, $horaSalida);
            $registro->forceFill(['entrada_prevista' => $periodo['entrada'], 'salida_prevista' => $periodo['salida']])->save();
        });
    }

    public function eliminar(Request $solicitud, Reservacion $reservacion): void
    {
        $this->modificar($solicitud, $reservacion, 'reservaciones.eliminar', 'eliminada', function (Reservacion $registro) {
            if (! in_array($registro->estado, ['por_confirmar', 'pendiente', 'cancelada', 'no_presentado'], true)) {
                $this->error('estado', 'Cancela primero la reservación. Las estancias registradas se conservan.');
            }
            $registro->delete();
        });
    }

    private function modificar(Request $solicitud, Reservacion $reservacion, string $permiso, string $accion, callable $operacion): void
    {
        DB::transaction(function () use ($solicitud, $reservacion, $permiso, $accion, $operacion) {
            $this->bloqueo->adquirir([$reservacion->cabana_id]);
            $this->autorizar($solicitud, $permiso);
            $registro = $this->releer($reservacion);
            $anteriores = $registro->toArray();
            $operacion($registro);
            $this->auditoria->registrarCambio($solicitud, 'reservaciones', $accion, $registro->id, $anteriores, $registro->toArray());
        }, 3);
    }

    private function releer(Reservacion $anterior): Reservacion
    {
        $registro = Reservacion::lockForUpdate()->findOrFail($anterior->id);
        if ($registro->cabana_id !== $anterior->cabana_id) {
            $this->error('cabana_id', 'Otro operador cambió la cabaña. Recarga la reservación y vuelve a intentarlo.');
        }

        return $registro;
    }

    private function datosPeriodo(Reservacion $registro): array
    {
        return [
            'fecha_entrada' => $registro->fecha_entrada->format('Y-m-d'),
            'fecha_salida' => $registro->fecha_salida->format('Y-m-d'),
            'hora_entrada' => $registro->entrada_prevista->setTimezone(ServicioDisponibilidad::ZONA_HORARIA)->format('H:i'),
            'hora_salida' => $registro->salida_prevista->setTimezone(ServicioDisponibilidad::ZONA_HORARIA)->format('H:i'),
            'cantidad_huespedes' => $registro->cantidad_huespedes,
        ];
    }

    private function exigirDisponibilidad(Cabana $cabana, array $datos, ?Reservacion $excluida, bool $entradaReal = false): void
    {
        if (! $this->disponibilidad->verificar($cabana, $datos['fecha_entrada'], $datos['fecha_salida'], (int) $datos['cantidad_huespedes'], $excluida, $datos['hora_entrada'], $datos['hora_salida'], $entradaReal)) {
            $this->error('cabana_id', 'La cabaña no está disponible para estas fechas, horarios y cantidad de huéspedes.');
        }
    }

    private function autorizarHorarios(Request $solicitud, string $horaEntrada, string $horaSalida): void
    {
        if ($horaEntrada > '14:00' || $horaSalida < '11:00') {
            $this->error('hora_entrada', 'Usa el horario habitual, una entrada temprana o una salida tardía.');
        }
        if ($horaEntrada !== '14:00') {
            $this->autorizar($solicitud, 'reservaciones.autorizar_entrada_temprana');
        }
        if ($horaSalida !== '11:00') {
            $this->autorizar($solicitud, 'reservaciones.autorizar_salida_tardia');
        }
    }

    private function autorizar(Request $solicitud, string $permiso): void
    {
        $actor = $solicitud->user()?->fresh();
        abort_unless($actor, 403);
        Gate::forUser($actor)->authorize($permiso);
    }

    private function error(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}
