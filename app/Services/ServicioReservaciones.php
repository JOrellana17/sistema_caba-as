<?php

// Servicio de reservaciones. Centraliza las operaciones descritas en sus métodos para que los
// controladores reutilicen las mismas reglas.

namespace App\Services;

use App\Models\Cabana;
use App\Models\EventoCalendario;
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
    // Recibe ServicioDisponibilidad, ServicioBloqueoCabanas, ServicioPreciosReservacion,
    // ServicioSeleccionHuesped, ServicioAuditoria, ServicioAseoCabanas mediante el contenedor de
    // Laravel para reutilizarlas durante la operación.
    public function __construct(
        private ServicioDisponibilidad $disponibilidad,
        private ServicioBloqueoCabanas $bloqueo,
        private ServicioPreciosReservacion $precios,
        private ServicioSeleccionHuesped $huespedes,
        private ServicioAuditoria $auditoria,
        private ServicioAseoCabanas $aseo,
    ) {}

    // Crea o reagenda bajo bloqueo de las cabañas afectadas. Revalida huésped, permisos,
    // disponibilidad y vínculo externo, y guarda una copia de los precios aplicados junto con la
    // auditoría.
    public function guardar(Request $solicitud, array $datos, ?Reservacion $reservacion = null): Reservacion
    {
        // Las escrituras de este bloque se confirman juntas; una excepción revierte sus cambios en la base de datos.
        return DB::transaction(function () use ($solicitud, $datos, $reservacion) {
            $cabanas = $this->bloqueo->adquirir(array_filter([$reservacion?->cabana_id, $datos['cabana_id']]));
            $this->autorizar($solicitud, $reservacion ? 'reservaciones.editar' : 'reservaciones.crear');
            $this->autorizar($solicitud, 'huespedes.buscar');
            $registro = $reservacion ? $this->releer($reservacion) : new Reservacion;
            if ($reservacion && ! in_array($registro->estado, ['pendiente', 'confirmada'], true)) {
                $this->error('estado', 'Solo puedes reagendar antes de registrar la entrada.');
            }
            $this->huespedes->validar((int) $datos['huesped_id']);
            $cabana = $cabanas->get((int) $datos['cabana_id']);
            if (! $cabana || $cabana->trashed()) {
                $this->error('cabana_id', 'Selecciona una cabaña existente que no esté eliminada.');
            }
            $this->autorizarHorarios($solicitud, $datos['hora_entrada'], $datos['hora_salida']);
            $evento = null;
            if (! empty($datos['evento_id'])) {
                $this->autorizar($solicitud, 'calendario.vincular');
                $evento = EventoCalendario::lockForUpdate()->findOrFail($datos['evento_id']);
                $periodoEvento = $this->disponibilidad->periodo($datos['fecha_entrada'], $datos['fecha_salida'], $datos['hora_entrada'], $datos['hora_salida']);
                if ($reservacion || $evento->reservacion_id || ! $evento->bloquea || $evento->origen === 'cec'
                    || $evento->cabana_id !== $cabana->id || ! $evento->inicio->equalTo($periodoEvento['entrada']) || ! $evento->fin->equalTo($periodoEvento['salida'])) {
                    $this->error('evento_id', 'El evento ya cambió o no corresponde a la cabaña y horarios. Vuelve al calendario.');
                }
            }
            $this->exigirDisponibilidad($cabana, $datos, $reservacion ? $registro : null, false, $evento);
            // Conserva los valores previos para describir el cambio en la auditoría.
            $anteriores = $registro->toArray();
            $periodo = $this->disponibilidad->periodo($datos['fecha_entrada'], $datos['fecha_salida'], $datos['hora_entrada'], $datos['hora_salida']);
            $precios = $this->precios->calcular($cabana, $datos, $reservacion ? $registro : null);
            $registro->fill(Arr::only($datos, ['cabana_id', 'huesped_id', 'fecha_entrada', 'fecha_salida', 'cantidad_huespedes', 'notas']));
            $registro->forceFill($precios);
            $registro->forceFill(['entrada_prevista' => $periodo['entrada'], 'salida_prevista' => $periodo['salida']]);
            if (! $reservacion) {
                $registro->forceFill(['codigo' => (string) Str::uuid(), 'estado' => 'pendiente', 'creado_por' => $solicitud->user()->id]);
            }
            $registro->save();
            if ($evento) {
                $anterioresEvento = $evento->toArray();
                $evento->update(['reservacion_id' => $registro->id, 'discrepancia' => false, 'conflicto' => false]);
                $this->auditoria->registrarCambio($solicitud, 'calendario', 'evento_completado', $evento->id, $anterioresEvento, $evento->toArray());
            }
            $this->revisarEvento($solicitud, $registro);
            $this->auditoria->registrarCambio($solicitud, 'reservaciones', $reservacion ? 'reagendada' : 'creada', $registro->id, $anteriores, $registro->toArray());

            return $registro;
        }, 3);
    }

    // Aplica exclusivamente transiciones admitidas, permisos por destino y restricciones horarias.
    // Registra la fecha del movimiento y abre el aseo al finalizar la estancia.
    public function cambiarEstado(Request $solicitud, Reservacion $reservacion, string $destino, array $datos = []): void
    {
        $permisos = [
            'confirmada' => 'confirmar', 'hospedada' => 'registrar_entrada',
            'finalizada' => 'registrar_salida', 'cancelada' => 'cancelar', 'no_presentado' => 'marcar_no_presentado',
        ];
        abort_unless(isset($permisos[$destino]), 403);
        $origenes = [
            'confirmada' => ['pendiente'],
            'hospedada' => ['confirmada'],
            'finalizada' => ['hospedada'],
            'cancelada' => ['pendiente', 'confirmada'],
            'no_presentado' => ['pendiente', 'confirmada'],
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
            if ($destino === 'finalizada') {
                $this->aseo->iniciar($solicitud, $registro);
            }
        });
    }

    // Autoriza cambios de entrada o salida en reservaciones activas, vuelve a comprobar disponibilidad
    // y evita alterar una entrada ya registrada.
    public function horarios(Request $solicitud, Reservacion $reservacion, array $datos): void
    {
        $this->modificar($solicitud, $reservacion, 'reservaciones.editar', 'horario_autorizado', function (Reservacion $registro) use ($solicitud, $datos) {
            if (! in_array($registro->estado, Reservacion::ESTADOS_ACTIVOS, true)) {
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

    // Elimina lógicamente solo reservaciones pendientes, canceladas o no presentadas; las estancias
    // registradas deben conservarse.
    public function eliminar(Request $solicitud, Reservacion $reservacion): void
    {
        $this->modificar($solicitud, $reservacion, 'reservaciones.eliminar', 'eliminada', function (Reservacion $registro) {
            if (! in_array($registro->estado, ['pendiente', 'cancelada', 'no_presentado'], true)) {
                $this->error('estado', 'Cancela primero la reservación. Las estancias registradas se conservan.');
            }
            $registro->delete();
        });
    }

    // Procesa por lotes las estancias hospedadas cuyo horario previsto venció. Las relee bajo bloqueo,
    // finaliza y genera el aseo y la auditoría sin inventar una hora de salida registrada.
    public function finalizarVencidas(): int
    {
        $limite = CarbonImmutable::now('UTC');
        $finalizadas = 0;
        Reservacion::where('estado', 'hospedada')->where('salida_prevista', '<=', $limite)
            ->select(['id', 'cabana_id'])->chunkById(100, function ($registros) use ($limite, &$finalizadas) {
                foreach ($registros as $candidata) {
                    // Las escrituras de este bloque se confirman juntas; una excepción revierte sus cambios en la base de datos.
                    $finalizadas += DB::transaction(function () use ($candidata, $limite) {
                        $this->bloqueo->adquirir([$candidata->cabana_id]);
                        $registro = Reservacion::lockForUpdate()->find($candidata->id);
                        if (! $registro || $registro->cabana_id !== $candidata->cabana_id
                            || $registro->estado !== 'hospedada' || $registro->salida_prevista->greaterThan($limite)) {
                            return 0;
                        }
                        // Conserva los valores previos para describir el cambio en la auditoría.
                        $anteriores = $registro->toArray();
                        $registro->estado = 'finalizada';
                        $registro->save();
                        $this->aseo->iniciar(new Request, $registro);
                        $this->auditoria->registrarCambio(new Request, 'reservaciones', 'finalizada_automaticamente', $registro->id, $anteriores, $registro->toArray());

                        return 1;
                    }, 3);
                }
            });

        return $finalizadas;
    }

    // Centraliza transacción, bloqueo de cabaña, autorización, relectura de reservación, revisión del
    // vínculo externo y auditoría.
    private function modificar(Request $solicitud, Reservacion $reservacion, string $permiso, string $accion, callable $operacion): void
    {
        // Las escrituras de este bloque se confirman juntas; una excepción revierte sus cambios en la base de datos.
        DB::transaction(function () use ($solicitud, $reservacion, $permiso, $accion, $operacion) {
            $this->bloqueo->adquirir([$reservacion->cabana_id]);
            $this->autorizar($solicitud, $permiso);
            $registro = $this->releer($reservacion);
            // Conserva los valores previos para describir el cambio en la auditoría.
            $anteriores = $registro->toArray();
            $operacion($registro);
            $this->revisarEvento($solicitud, $registro);
            $this->auditoria->registrarCambio($solicitud, 'reservaciones', $accion, $registro->id, $anteriores, $registro->toArray());
        }, 3);
    }

    // Marca discrepancias cuando la reservación vinculada ya no coincide con la ocupación externa; no
    // altera silenciosamente el evento de origen.
    private function revisarEvento(Request $solicitud, Reservacion $registro): void
    {
        $evento = $registro->eventoCalendario()->lockForUpdate()->first();
        if (! $evento) {
            return;
        }
        $discrepancia = ! $evento->bloquea || $registro->trashed() || in_array($registro->estado, ['cancelada', 'no_presentado'], true)
            || $registro->cabana_id !== $evento->cabana_id || ! $registro->entrada_prevista->equalTo($evento->inicio)
            || ! $registro->salida_prevista->equalTo($evento->fin);
        if ($evento->discrepancia !== $discrepancia) {
            // Conserva los valores previos para describir el cambio en la auditoría.
            $anteriores = $evento->toArray();
            $evento->update(['discrepancia' => $discrepancia]);
            $this->auditoria->registrarCambio($solicitud, 'calendario', 'vinculo_revisado', $evento->id, $anteriores, $evento->toArray());
        }
    }

    // Recupera la reservación bajo bloqueo y rechaza una cabaña modificada por otro operador para
    // evitar trabajar con datos obsoletos.
    private function releer(Reservacion $anterior): Reservacion
    {
        $registro = Reservacion::lockForUpdate()->findOrFail($anterior->id);
        if ($registro->cabana_id !== $anterior->cabana_id) {
            $this->error('cabana_id', 'Otro operador cambió la cabaña. Recarga la reservación y vuelve a intentarlo.');
        }

        return $registro;
    }

    // Obtiene fechas, ocupación y horas locales de una reservación para reutilizar las reglas de
    // disponibilidad.
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

    // Comprueba las condiciones de entrada real cuando corresponde y convierte la falta de
    // disponibilidad en un error asociado a la cabaña.
    private function exigirDisponibilidad(Cabana $cabana, array $datos, ?Reservacion $excluida, bool $entradaReal = false, ?EventoCalendario $evento = null): void
    {
        if ($entradaReal) {
            $this->disponibilidad->exigirEntradaHabilitada($cabana, $excluida);
        }
        if (! $this->disponibilidad->verificar($cabana, $datos['fecha_entrada'], $datos['fecha_salida'], (int) $datos['cantidad_huespedes'], $excluida, $datos['hora_entrada'], $datos['hora_salida'], $entradaReal, $evento)) {
            $this->error('cabana_id', 'La cabaña no está disponible para estas fechas, horarios y cantidad de huéspedes.');
        }
    }

    // Aplica los permisos específicos para entrada temprana y salida tardía respecto del horario
    // habitual definido en este servicio.
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

    // Relee la cuenta del actor y exige el permiso indicado mediante Gate; impide que una llamada
    // directa al servicio omita la autorización.
    private function autorizar(Request $solicitud, string $permiso): void
    {
        // Consulta los privilegios actuales del actor antes de ejecutar una modificación.
        $actor = $solicitud->user()?->fresh();
        abort_unless($actor, 403);
        Gate::forUser($actor)->authorize($permiso);
    }

    // Interrumpe el caso de uso con un error de validación asociado al campo indicado; Laravel lo
    // devuelve al formulario.
    private function error(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}
