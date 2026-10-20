<?php

namespace App\Services;

use App\Models\Cabana;
use App\Models\EnlaceCalendario;
use App\Models\EventoCalendario;
use App\Models\FuenteCalendario;
use App\Models\Reservacion;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServicioGestionCalendario
{
    public function __construct(private ServicioBloqueoCabanas $bloqueo, private ServicioAuditoria $auditoria, private ServicioUrlCalendario $urls) {}

    public function fuente(Request $solicitud, array $datos): FuenteCalendario
    {
        Gate::forUser($solicitud->user()->fresh())->authorize('calendario.administrar_fuentes');
        if (! empty($datos['url'])) {
            $this->urls->validar($datos['url']);
        }

        return DB::transaction(function () use ($solicitud, $datos) {
            $cabana = $this->bloqueo->adquirir([$datos['cabana_id']])->get((int) $datos['cabana_id']);
            abort_unless($cabana && ! $cabana->trashed(), 422);
            $fuente = FuenteCalendario::create($datos + ['activa' => false]);
            $this->auditoria->registrarCambio($solicitud, 'calendario', 'fuente_creada', $fuente->id, [], $fuente->toArray());

            return $fuente;
        });
    }

    public function activar(Request $solicitud, FuenteCalendario $fuente, bool $activa): FuenteCalendario
    {
        return DB::transaction(function () use ($solicitud, $fuente, $activa) {
            $this->bloqueo->adquirir([$fuente->cabana_id]);
            Gate::forUser($solicitud->user()->fresh())->authorize('calendario.administrar_fuentes');
            $registro = FuenteCalendario::lockForUpdate()->findOrFail($fuente->id);
            if ($registro->version !== $fuente->version) {
                throw ValidationException::withMessages(['fuente' => 'La fuente cambió. Vuelve a previsualizar.']);
            }
            $anteriores = $registro->toArray();
            $registro->update(['activa' => $activa, 'version' => $registro->version + 1, 'proxima_consulta' => now(),
                'etag' => null, 'ultima_modificacion' => null]);
            // La suspensión no prueba ninguna baja ni elimina eventos.
            $registro->eventos()->where('bloquea', true)->update(['ausencias' => 0, 'ausente_desde' => null]);
            $this->auditoria->registrarCambio($solicitud, 'calendario', $activa ? 'fuente_activada' : 'fuente_suspendida', $registro->id, $anteriores, $registro->toArray());

            return $registro;
        });
    }

    public function actualizarFuente(Request $solicitud, FuenteCalendario $fuente, array $datos): void
    {
        Gate::forUser($solicitud->user()->fresh())->authorize('calendario.administrar_fuentes');
        if (! empty($datos['url'])) {
            $this->urls->validar($datos['url']);
        } else {
            unset($datos['url']);
        }
        DB::transaction(function () use ($solicitud, $fuente, $datos) {
            $this->bloqueo->adquirir([$fuente->cabana_id]);
            $registro = FuenteCalendario::lockForUpdate()->findOrFail($fuente->id);
            if ($registro->version !== $fuente->version) {
                throw ValidationException::withMessages(['fuente' => 'La fuente cambió. Recarga sus datos.']);
            }
            $anteriores = $registro->toArray();
            $registro->fill($datos)->forceFill(['activa' => false, 'version' => $registro->version + 1,
                'etag' => null, 'ultima_modificacion' => null, 'proxima_consulta' => null])->save();
            $registro->eventos()->where('bloquea', true)->update(['ausencias' => 0, 'ausente_desde' => null]);
            $this->auditoria->registrarCambio($solicitud, 'calendario', 'fuente_actualizada', $registro->id, $anteriores, $registro->toArray());
        }, 3);
    }

    public function bloqueo(Request $solicitud, int $cabanaId, string $inicio, string $fin): EventoCalendario
    {
        Gate::forUser($solicitud->user()->fresh())->authorize('calendario.gestionar_bloqueos');

        return $this->crearBloqueo($solicitud, $cabanaId, $inicio, $fin);
    }

    public function bloqueoEstado(Request $solicitud, Cabana $cabana, array $datos): void
    {
        Gate::forUser($solicitud->user()->fresh())->authorize($cabana->wasRecentlyCreated ? 'cabanas.crear' : 'cabanas.cambiar_estado');
        if (! empty($datos['bloqueo_inicio']) && ! empty($datos['bloqueo_fin'])) {
            $this->crearBloqueo($solicitud, $cabana->id, $datos['bloqueo_inicio'], $datos['bloqueo_fin']);
        }
    }

    private function crearBloqueo(Request $solicitud, int $cabanaId, string $inicio, string $fin): EventoCalendario
    {

        $desde = CarbonImmutable::parse($inicio, ServicioDisponibilidad::ZONA_HORARIA)->utc();
        $hasta = CarbonImmutable::parse($fin, ServicioDisponibilidad::ZONA_HORARIA)->utc();
        if ($hasta->lessThanOrEqualTo($desde)) {
            throw ValidationException::withMessages(['fin' => 'El fin debe ser posterior al inicio del bloqueo.']);
        }

        return DB::transaction(function () use ($solicitud, $cabanaId, $desde, $hasta) {
            $cabana = $this->bloqueo->adquirir([$cabanaId])->get($cabanaId);
            abort_unless($cabana && ! $cabana->trashed(), 422);
            $uid = 'cec-bloqueo-'.Str::uuid().'@calendario.cec';
            $evento = EventoCalendario::create(['cabana_id' => $cabanaId, 'origen' => 'cec', 'uid' => $uid, 'identidad' => hash('sha256', $uid),
                'inicio' => $desde, 'fin' => $hasta, 'bloquea' => true, 'estado' => 'activo']);
            $this->auditoria->registrarCambio($solicitud, 'calendario', 'bloqueo_creado', $evento->id, [], $evento->toArray());

            return $evento;
        });
    }

    public function liberar(Request $solicitud, EventoCalendario $evento): void
    {
        DB::transaction(function () use ($solicitud, $evento) {
            $this->bloqueo->adquirir([$evento->cabana_id]);
            Gate::forUser($solicitud->user()->fresh())->authorize('calendario.gestionar_bloqueos');
            $registro = EventoCalendario::lockForUpdate()->findOrFail($evento->id);
            abort_unless($registro->origen === 'cec' && $registro->fuente_id === null, 403, 'Libera este bloqueo en su plataforma de origen.');
            $anteriores = $registro->toArray();
            $registro->update(['bloquea' => false, 'estado' => 'retirado']);
            $this->auditoria->registrarCambio($solicitud, 'calendario', 'bloqueo_liberado', $registro->id, $anteriores, $registro->toArray());
        });
    }

    public function vincular(Request $solicitud, EventoCalendario $evento, Reservacion $reservacion): void
    {
        DB::transaction(function () use ($solicitud, $evento, $reservacion) {
            $this->bloqueo->adquirir([$evento->cabana_id, $reservacion->cabana_id]);
            Gate::forUser($solicitud->user()->fresh())->authorize('calendario.vincular');
            Gate::forUser($solicitud->user()->fresh())->authorize('reservaciones.editar');
            $registro = EventoCalendario::lockForUpdate()->findOrFail($evento->id);
            $reserva = Reservacion::lockForUpdate()->findOrFail($reservacion->id);
            if (! in_array($reserva->estado, ['pendiente', 'confirmada'], true) || $registro->origen === 'cec' || ! $registro->bloquea || $registro->reservacion_id || $reserva->eventoCalendario()->exists()
                || $reserva->cabana_id !== $registro->cabana_id || ! $reserva->entrada_prevista->equalTo($registro->inicio)
                || ! $reserva->salida_prevista->equalTo($registro->fin)) {
                throw ValidationException::withMessages(['reservacion_id' => 'Vincula una reservación libre de vínculos, de la misma cabaña y fechas exactas.']);
            }
            $anteriores = $registro->toArray();
            $registro->update(['reservacion_id' => $reserva->id, 'discrepancia' => false, 'conflicto' => false]);
            $this->auditoria->registrarCambio($solicitud, 'calendario', 'evento_vinculado', $registro->id, $anteriores, $registro->toArray());
        });
    }

    public function enlace(Request $solicitud, array $datos): string
    {
        Gate::forUser($solicitud->user()->fresh())->authorize('calendario.gestionar_enlaces');
        $token = bin2hex(random_bytes(32));
        DB::transaction(function () use ($solicitud, $datos, $token) {
            $enlace = EnlaceCalendario::create($datos + ['token_hash' => hash('sha256', $token)]);
            $this->auditoria->registrarCambio($solicitud, 'calendario', 'enlace_creado', $enlace->id, [], $enlace->toArray());
        });

        return route('calendario.feed', ['token' => $token]);
    }

    public function revocar(Request $solicitud, EnlaceCalendario $enlace): void
    {
        Gate::forUser($solicitud->user()->fresh())->authorize('calendario.gestionar_enlaces');
        DB::transaction(function () use ($solicitud, $enlace) {
            $registro = EnlaceCalendario::lockForUpdate()->findOrFail($enlace->id);
            $anteriores = $registro->toArray();
            $registro->update(['activo' => false]);
            $this->auditoria->registrarCambio($solicitud, 'calendario', 'enlace_revocado', $registro->id, $anteriores, $registro->toArray());
        });
    }
}
