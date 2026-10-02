<?php

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
    public function __construct(private ServicioAuditoria $auditoria, private ServicioBloqueoCabanas $bloqueo) {}

    public function guardar(Request $solicitud, array $datos, ?Cabana $cabana = null): Cabana
    {
        $permiso = $cabana ? 'cabanas.editar' : 'cabanas.crear';
        $this->autorizar($solicitud, $permiso);
        $rutaImagen = null;

        try {
            if ($solicitud->hasFile('imagen')) {
                $rutaImagen = $solicitud->file('imagen')->store('imagenes', 'cabanas');
                if (! $rutaImagen) {
                    throw ValidationException::withMessages(['imagen' => 'No se pudo guardar la imagen. Inténtalo de nuevo.']);
                }
            }

            return DB::transaction(function () use ($solicitud, $datos, $cabana, $permiso, $rutaImagen) {
                if ($cabana) {
                    $this->bloqueo->adquirir([$cabana->id]);
                }
                $this->autorizar($solicitud, $permiso);
                $registro = $cabana ? Cabana::lockForUpdate()->findOrFail($cabana->id) : new Cabana;
                if ($cabana && $registro->reservaciones()->whereIn('estado', ['por_confirmar', 'pendiente', 'confirmada', 'hospedada'])->where('cantidad_huespedes', '>', $datos['capacidad_maxima'])->exists()) {
                    throw ValidationException::withMessages(['capacidad_maxima' => 'La capacidad no puede quedar por debajo de una reservación vigente.']);
                }
                $anteriores = $registro->toArray();
                $registro->fill(Arr::only($datos, [
                    'nombre', 'descripcion', 'capacidad_base', 'capacidad_maxima',
                    'precio_base', 'precio_persona_adicional', 'precio_base_usd', 'precio_persona_adicional_usd', 'notas',
                ]));

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
                $this->auditoria->registrarCambio($solicitud, 'cabanas', $cabana ? 'actualizado' : 'creado', $registro->id, $anteriores, $registro->toArray());

                return $registro;
            });
        } catch (Throwable $error) {
            if ($rutaImagen) {
                try {
                    Storage::disk('cabanas')->delete($rutaImagen);
                } catch (FilesystemException $errorLimpieza) {
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

    public function cambiarEstado(Request $solicitud, Cabana $cabana, array $datos): void
    {
        $this->modificar($solicitud, $cabana, 'cabanas.cambiar_estado', 'estado_actualizado', function (Cabana $registro) use ($datos) {
            $registro->forceFill(Arr::only($datos, ['estado', 'activa']))->save();
        });
    }

    public function eliminar(Request $solicitud, Cabana $cabana): void
    {
        $this->modificar($solicitud, $cabana, 'cabanas.eliminar', 'eliminado', function (Cabana $registro) {
            if ($registro->reservaciones()->whereIn('estado', ['por_confirmar', 'pendiente', 'confirmada', 'hospedada'])->exists()) {
                throw ValidationException::withMessages(['cabana' => 'Cancela o reasigna las reservaciones vigentes antes de eliminar la cabaña.']);
            }
            $registro->delete();
        });
    }

    public function restaurar(Request $solicitud, Cabana $cabana): void
    {
        $this->modificar($solicitud, $cabana, 'cabanas.restaurar', 'restaurado', function (Cabana $registro) {
            abort_unless($registro->trashed(), 404);
            $registro->restore();
        }, true);
    }

    private function modificar(Request $solicitud, Cabana $cabana, string $permiso, string $accion, callable $operacion, bool $conEliminadas = false): void
    {
        DB::transaction(function () use ($solicitud, $cabana, $permiso, $accion, $operacion, $conEliminadas) {
            $this->bloqueo->adquirir([$cabana->id]);
            $this->autorizar($solicitud, $permiso);
            $registro = Cabana::query()->when($conEliminadas, fn ($consulta) => $consulta->withTrashed())
                ->lockForUpdate()->findOrFail($cabana->id);
            $anteriores = $registro->toArray();
            $operacion($registro);
            $this->auditoria->registrarCambio($solicitud, 'cabanas', $accion, $registro->id, $anteriores, $registro->toArray());
        });
    }

    private function autorizar(Request $solicitud, string $permiso): void
    {
        $actor = $solicitud->user()?->fresh();
        abort_unless($actor, 403);
        Gate::forUser($actor)->authorize($permiso);
    }
}
