<?php

// Servicio de cabanas. Centraliza las operaciones descritas en sus métodos para que los controladores
// reutilicen las mismas reglas.

namespace App\Services;

use App\Models\Cabana;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
use Throwable;

class ServicioCabanas
{
    // Recibe ServicioAuditoria, ServicioBloqueoCabanas, ServicioGestionCalendario mediante el
    // contenedor de Laravel para reutilizarlas durante la operación.
    public function __construct(private ServicioAuditoria $auditoria, private ServicioBloqueoCabanas $bloqueo, private ServicioGestionCalendario $calendario) {}

    // Guarda datos y auditoría de la cabaña en una transacción. Gestiona la nueva imagen, conserva la
    // anterior ante fallos y la retira únicamente después de confirmar el reemplazo.
    public function guardar(Request $solicitud, array $datos, ?Cabana $cabana = null): Cabana
    {
        // Traduce el selector de camas al atributo persistido, manteniendo null como información sin registrar.
        if (array_key_exists('cantidad_camas', $datos)) {
            $datos['camas'] = $datos['cantidad_camas'] === null ? null : array_values($datos['tipos_camas'] ?? []);
        }
        $permiso = $cabana ? 'cabanas.editar' : 'cabanas.crear';
        $this->autorizar($solicitud, $permiso);
        $rutaImagen = null;

        try {
            // Guarda primero el archivo nuevo; la transacción posterior decide si su referencia queda vigente.
            if ($solicitud->hasFile('imagen')) {
                $rutaImagen = $solicitud->file('imagen')->store('imagenes', 'cabanas');
                if (! $rutaImagen) {
                    throw ValidationException::withMessages(['imagen' => 'No se pudo guardar la imagen. Inténtalo de nuevo.']);
                }
            }

            // Las escrituras de este bloque se confirman juntas; una excepción revierte sus cambios en la base de datos.
            return DB::transaction(function () use ($solicitud, $datos, $cabana, $permiso, $rutaImagen) {
                if ($cabana) {
                    $this->bloqueo->adquirir([$cabana->id]);
                }
                $this->autorizar($solicitud, $permiso);
                $registro = $cabana ? Cabana::lockForUpdate()->findOrFail($cabana->id) : new Cabana;
                if ($cabana && $registro->reservaciones()->whereIn('estado', ['pendiente', 'confirmada', 'hospedada'])->where('cantidad_huespedes', '>', $datos['capacidad_maxima'])->exists()) {
                    throw ValidationException::withMessages(['capacidad_maxima' => 'La capacidad no puede quedar por debajo de una reservación vigente.']);
                }
                // Conserva los valores previos para describir el cambio en la auditoría.
                $anteriores = $registro->toArray();
                // Limita la asignación a datos editables; estado y datos de creación tienen un tratamiento separado.
                $registro->fill(Arr::only($datos, [
                    'nombre', 'descripcion', 'capacidad_base', 'capacidad_maxima',
                    'precio_base', 'precio_persona_adicional', 'precio_base_usd', 'precio_persona_adicional_usd', 'notas',
                    'camas', 'tiene_refrigerador', 'tiene_aire_acondicionado',
                ]));
                if (is_array($registro->camas) && ! array_is_list($registro->camas)) {
                    $registro->camas = array_map('intval', $registro->camas);
                }

                if (! $cabana) {
                    $registro->forceFill([
                        'slug' => Str::limit(Str::slug($datos['nombre']) ?: 'cabana', 120, '').'-'.Str::uuid(),
                        'creado_por' => $solicitud->user()->id,
                        'estado' => $datos['estado'],
                        'activa' => $datos['activa'],
                    ]);
                }
                if ($rutaImagen !== null) {
                    $registro->imagen_principal = $rutaImagen;
                }
                $registro->save();
                if (! $cabana) {
                    $this->calendario->bloqueoEstado($solicitud, $registro, $datos);
                }
                $this->auditoria->registrarCambio($solicitud, 'cabanas', $cabana ? 'actualizado' : 'creado', $registro->id, $anteriores, $registro->toArray());

                $anterior = $anteriores['imagen_principal'] ?? null;
                if ($rutaImagen && $anterior && $anterior !== $rutaImagen) {
                    // La limpieza física se retrasa hasta confirmar la transacción externa; no se borra la imagen anterior si los datos se revierten.
                    DB::afterCommit(function () use ($anterior) {
                        try {
                            // Solo retira rutas internas sin referencias, contando también las cabañas eliminadas lógicamente.
                            if (str_starts_with($anterior, 'imagenes/') && ! str_contains($anterior, '..')
                                && ! Cabana::withTrashed()->where('imagen_principal', $anterior)->exists()) {
                                if (! Storage::disk('cabanas')->delete($anterior)) {
                                    report(new \RuntimeException('No se pudo retirar una imagen sustituida.'));
                                }
                            }
                        } catch (Throwable $error) {
                            report($error);
                        }
                    });
                }

                return $registro;
            });
        } catch (Throwable $error) {
            // Si falla el guardado, intenta retirar únicamente la imagen nueva y conserva la excepción original.
            if ($rutaImagen) {
                try {
                    if (! Storage::disk('cabanas')->delete($rutaImagen)) {
                        report(new \RuntimeException('No se pudo retirar la imagen nueva tras revertir el guardado.'));
                    }
                } catch (Throwable $errorLimpieza) {
                    report($errorLimpieza);
                }
            }
            if ($error instanceof FilesystemException) {
                report($error);
                throw ValidationException::withMessages(['imagen' => 'No se pudo guardar la imagen. Inténtalo de nuevo.']);
            }

            throw $error;
        }
    }

    // Actualiza el estado operativo y la activación con el permiso correspondiente; registra un
    // bloqueo de calendario si se indicó un período.
    public function cambiarEstado(Request $solicitud, Cabana $cabana, array $datos): void
    {
        $this->modificar($solicitud, $cabana, 'cabanas.cambiar_estado', 'estado_actualizado', function (Cabana $registro) use ($solicitud, $datos) {
            $registro->forceFill(Arr::only($datos, ['estado', 'activa']))->save();
            $this->calendario->bloqueoEstado($solicitud, $registro, $datos);
        });
    }

    // Elimina lógicamente la cabaña solo cuando no tiene reservaciones vigentes, bloqueos de
    // calendario ni aseos pendientes. Conserva su imagen para restaurarla.
    public function eliminar(Request $solicitud, Cabana $cabana): void
    {
        $this->modificar($solicitud, $cabana, 'cabanas.eliminar', 'eliminado', function (Cabana $registro) {
            if ($registro->reservaciones()->whereIn('estado', ['pendiente', 'confirmada', 'hospedada'])->exists()) {
                throw ValidationException::withMessages(['cabana' => 'Cancela o reasigna las reservaciones vigentes antes de eliminar la cabaña.']);
            }
            if ($registro->eventosCalendario()->where('bloquea', true)->exists()) {
                throw ValidationException::withMessages(['cabana' => 'Hay bloqueos de calendario vigentes. Libéralos en su origen antes de eliminar la cabaña.']);
            }
            if ($registro->aseosPendientes()->exists()) {
                throw ValidationException::withMessages(['cabana' => 'Confirma el aseo pendiente antes de eliminar la cabaña.']);
            }
            // SoftDeletes fija deleted_at; no retira la imagen ni destruye el registro recuperable.
            $registro->delete();
        });
    }

    // Retira deleted_at de una cabaña eliminada; exige el permiso de restauración y conserva los datos
    // y la imagen almacenados.
    public function restaurar(Request $solicitud, Cabana $cabana): void
    {
        $this->modificar($solicitud, $cabana, 'cabanas.restaurar', 'restaurado', function (Cabana $registro) {
            abort_unless($registro->trashed(), 404);
            $registro->restore();
        }, true);
    }

    // Reúne bloqueo, autorización, relectura y auditoría de una modificación de cabaña dentro de la
    // misma transacción.
    private function modificar(Request $solicitud, Cabana $cabana, string $permiso, string $accion, callable $operacion, bool $conEliminadas = false): void
    {
        // Las escrituras de este bloque se confirman juntas; una excepción revierte sus cambios en la base de datos.
        DB::transaction(function () use ($solicitud, $cabana, $permiso, $accion, $operacion, $conEliminadas) {
            $this->bloqueo->adquirir([$cabana->id]);
            $this->autorizar($solicitud, $permiso);
            $registro = Cabana::query()->when($conEliminadas, fn ($consulta) => $consulta->withTrashed())
                ->lockForUpdate()->findOrFail($cabana->id);
            // Conserva los valores previos para describir el cambio en la auditoría.
            $anteriores = $registro->toArray();
            $operacion($registro);
            $this->auditoria->registrarCambio($solicitud, 'cabanas', $accion, $registro->id, $anteriores, $registro->toArray());
        });
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
}
