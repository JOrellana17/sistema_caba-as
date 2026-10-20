<?php

namespace App\Services;

use App\Models\EventoCalendario;
use App\Models\FuenteCalendario;
use App\Models\Reservacion;
use App\Models\SincronizacionCalendario;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ServicioSincronizacionCalendario
{
    public function __construct(
        private ServicioLecturaCalendario $lector,
        private ServicioArchivoCalendario $archivos,
        private ServicioBloqueoCabanas $bloqueo,
        private ServicioAuditoria $auditoria,
    ) {}

    public function sincronizar(FuenteCalendario $fuente): bool
    {
        return (bool) Cache::lock('ical-fuente-'.$fuente->id, 120)->get(function () use ($fuente) {
            $versionEsperada = $fuente->version;
            $fuente = $fuente->fresh();
            if (! $fuente?->activa || $fuente->version !== $versionEsperada) {
                return false;
            }
            try {
                $lectura = $this->lector->descargar($fuente);
                if ($lectura['sin_cambios']) {
                    // 304 no confirma otra ausencia y tampoco rompe una comprobación.
                    return DB::transaction(function () use ($fuente) {
                        $this->bloqueo->adquirir([$fuente->cabana_id]);
                        $actual = FuenteCalendario::lockForUpdate()->find($fuente->id);
                        if (! $actual?->activa || $actual->version !== $fuente->version) {
                            return false;
                        }
                        $actual->update(['proxima_consulta' => now()->addMinutes(config('icalendario.intervalo_minutos'))]);

                        return true;
                    }, 3);
                }
                $this->aplicar($fuente, $lectura['contenido'], null, $lectura + ['origen_verificado' => true]);

                return true;
            } catch (Throwable) {
                DB::transaction(function () use ($fuente) {
                    $this->bloqueo->adquirir([$fuente->cabana_id]);
                    $actual = FuenteCalendario::lockForUpdate()->find($fuente->id);
                    if (! $actual || $actual->version !== $fuente->version) {
                        return;
                    }
                    $mensaje = 'No se pudo comprobar el calendario. Se conservan los bloqueos; revisa conexión y formato.';
                    $actual->update(['estado' => 'error', 'error' => $mensaje, 'proxima_consulta' => now()->addMinutes(30)]);
                    $actual->eventos()->where('bloquea', true)->update(['ausencias' => 0, 'ausente_desde' => null]);
                    SincronizacionCalendario::create(['fuente_id' => $fuente->id, 'estado' => 'error', 'error' => $mensaje]);
                });

                return false;
            }
        });
    }

    public function aplicar(FuenteCalendario $fuente, string $contenido, ?Request $solicitud = null, array $lectura = []): void
    {
        $eventos = $this->archivos->analizar($contenido, $fuente->interpretacion_fechas, $fuente->zona_horaria);
        DB::transaction(function () use ($fuente, $eventos, $solicitud, $lectura) {
            $this->bloqueo->adquirir([$fuente->cabana_id]);
            $actual = FuenteCalendario::lockForUpdate()->findOrFail($fuente->id);
            if (! $actual->activa || $actual->version !== $fuente->version) {
                throw new RuntimeException('La fuente cambió o está inactiva. Previsualiza de nuevo.');
            }
            $ahora = CarbonImmutable::now('UTC');
            $vistos = [];
            foreach ($eventos as $identidad => $datos) {
                if ($this->esPropio($datos['uid'])) {
                    continue;
                }
                if ($datos['cancelada']) {
                    // CANCELLED se reconcilia como ausencia comprobada del UID original.
                    continue;
                }
                $vistos[] = $identidad;
                $registro = EventoCalendario::firstOrNew(['fuente_id' => $actual->id, 'identidad' => $identidad]);
                if ($registro->exists && $registro->uid !== $datos['uid']) {
                    throw new RuntimeException('Colisión de identidad; la fuente necesita revisión.');
                }
                if ($registro->exists && ! ($lectura['origen_verificado'] ?? false)
                    && (! $registro->inicio->equalTo($datos['inicio']) || ! $registro->fin->equalTo($datos['fin']))) {
                    throw new RuntimeException('Un archivo no puede cambiar un bloqueo externo existente. Comprueba el cambio leyendo la URL de origen.');
                }
                $anteriores = $registro->toArray();
                $registro->fill(['cabana_id' => $actual->cabana_id, 'origen' => $actual->proveedor,
                    'uid' => $datos['uid'], 'inicio' => $datos['inicio'], 'fin' => $datos['fin'],
                    'fecha_completa' => $datos['fecha_completa'], 'bloquea' => true,
                    'estado' => 'activo', 'ausencias' => 0, 'ausente_desde' => null, 'visto_en' => $ahora]);
                $vinculada = $registro->reservacion;
                $registro->discrepancia = $vinculada && ($vinculada->trashed() || in_array($vinculada->estado, ['cancelada', 'no_presentado'], true) || $vinculada->cabana_id !== $registro->cabana_id
                    || ! $vinculada->entrada_prevista->equalTo($registro->inicio)
                    || ! $vinculada->salida_prevista->equalTo($registro->fin));
                $registro->conflicto = Reservacion::where('cabana_id', $actual->cabana_id)
                    ->whereIn('estado', Reservacion::ESTADOS_QUE_BLOQUEAN)
                    ->when($registro->reservacion_id, fn ($consulta) => $consulta->whereKeyNot($registro->reservacion_id))
                    ->where('entrada_prevista', '<', $registro->fin)->where('salida_prevista', '>', $registro->inicio)->exists()
                    || EventoCalendario::where('cabana_id', $actual->cabana_id)->where('bloquea', true)
                        ->when($registro->exists, fn ($consulta) => $consulta->whereKeyNot($registro->id))
                        ->where('inicio', '<', $registro->fin)->where('fin', '>', $registro->inicio)->exists();
                $registro->save();
                if (array_diff_assoc($this->resumen($registro->toArray()), $this->resumen($anteriores))) {
                    $this->auditoria->registrarCambio($solicitud ?? Request::create('/sistema/icalendario'), 'calendario', 'evento_sincronizado', $registro->id, $anteriores, $registro->toArray());
                }
            }
            $retirados = 0;
            foreach (($lectura['origen_verificado'] ?? false) ? $actual->eventos()->where('bloquea', true)->whereNotIn('identidad', $vistos)->get() : [] as $ausente) {
                $canceladaExplicita = ($eventos[$ausente->identidad]['cancelada'] ?? false)
                    && ($eventos[$ausente->identidad]['uid'] ?? null) === $ausente->uid;
                $cubierta = $actual->cobertura_desde && $actual->cobertura_hasta
                    && $ausente->inicio->greaterThanOrEqualTo(CarbonImmutable::parse($actual->cobertura_desde->format('Y-m-d'), ServicioDisponibilidad::ZONA_HORARIA)->startOfDay()->utc())
                    && $ausente->fin->lessThanOrEqualTo(CarbonImmutable::parse($actual->cobertura_hasta->format('Y-m-d'), ServicioDisponibilidad::ZONA_HORARIA)->addDay()->startOfDay()->utc());
                if (! $cubierta && ! $canceladaExplicita) {
                    continue;
                }
                if (! $ausente->ausente_desde) {
                    $ausente->update(['ausencias' => 1, 'ausente_desde' => $ahora]);

                    continue;
                }
                if ($ausente->ausente_desde->addMinutes(config('icalendario.intervalo_minutos'))->greaterThan($ahora)) {
                    continue;
                }
                $anteriores = $ausente->toArray();
                $ausente->update(['bloquea' => false, 'estado' => 'retirado', 'ausencias' => 2, 'discrepancia' => $ausente->reservacion_id !== null]);
                $retirados++;
                $this->auditoria->registrarCambio($solicitud ?? Request::create('/sistema/icalendario'), 'calendario', 'baja_verificada', $ausente->id, $anteriores, $ausente->toArray());
            }
            $actual->update(['ultima_correcta' => $ahora, 'proxima_consulta' => $ahora->addMinutes(config('icalendario.intervalo_minutos')),
                'estado' => 'correcta', 'error' => null, 'etag' => $lectura['etag'] ?? null, 'ultima_modificacion' => $lectura['ultima_modificacion'] ?? null]);
            SincronizacionCalendario::create(['fuente_id' => $actual->id, 'estado' => 'correcta', 'completa' => true, 'procesados' => count($vistos), 'retirados' => $retirados]);
        }, 3);
    }

    public function previsualizar(FuenteCalendario $fuente, array $eventos): array
    {
        $anteriores = $fuente->eventos()->get()->keyBy('identidad');
        $reservas = Reservacion::where('cabana_id', $fuente->cabana_id)->whereIn('estado', Reservacion::ESTADOS_QUE_BLOQUEAN)->get(['id', 'entrada_prevista', 'salida_prevista']);
        $bloqueos = EventoCalendario::where('cabana_id', $fuente->cabana_id)->where('bloquea', true)->get(['id', 'inicio', 'fin']);
        foreach ($eventos as $identidad => &$datos) {
            $anterior = $anteriores->get($identidad);
            $datos['conflicto'] = false;
            if ($this->esPropio($datos['uid'])) {
                $datos['accion'] = 'Copia CEC: se conserva el original';
            } elseif ($datos['cancelada']) {
                $datos['accion'] = 'Cancelación externa: pendiente de comprobar por URL';
            } else {
                $datos['accion'] = ! $anterior ? 'Nuevo bloqueo' : (($anterior->inicio->equalTo($datos['inicio']) && $anterior->fin->equalTo($datos['fin'])) ? 'Ya registrado' : 'Cambio: comprobar por URL de origen');
                $datos['conflicto'] = $reservas->contains(fn ($reserva) => $reserva->id !== $anterior?->reservacion_id && $reserva->entrada_prevista->lessThan($datos['fin']) && $reserva->salida_prevista->greaterThan($datos['inicio']))
                    || $bloqueos->contains(fn ($bloqueo) => $bloqueo->id !== $anterior?->id && $bloqueo->inicio->lessThan($datos['fin']) && $bloqueo->fin->greaterThan($datos['inicio']));
            }
        }
        unset($datos);

        return $eventos;
    }

    private function esPropio(string $uid): bool
    {
        if (preg_match('/^cec-reserva-([a-f0-9-]{36})@calendario\.cec$/D', $uid, $coincidencia)) {
            return Reservacion::withTrashed()->where('codigo', $coincidencia[1])->exists();
        }

        return EventoCalendario::where('origen', 'cec')->where('identidad', hash('sha256', $uid))->exists();
    }

    private function resumen(array $datos): array
    {
        return array_intersect_key($datos, array_flip(['inicio', 'fin', 'bloquea', 'estado', 'conflicto', 'discrepancia']));
    }
}
