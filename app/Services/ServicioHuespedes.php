<?php

namespace App\Services;

use App\Models\Huesped;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ServicioHuespedes
{
    public function __construct(private ServicioAuditoria $auditoria) {}

    public function guardar(Request $solicitud, array $datos, ?Huesped $huesped = null): Huesped
    {
        try {
            return DB::transaction(function () use ($solicitud, $datos, $huesped) {
                $this->autorizar($solicitud, $huesped ? 'huespedes.editar' : 'huespedes.crear');
                $registro = $huesped ? Huesped::lockForUpdate()->findOrFail($huesped->id) : new Huesped;
                $anteriores = $registro->toArray();
                $registro->fill(Arr::only($datos, ['nombre', 'telefono', 'numero_identidad', 'nacionalidad', 'notas', 'notas_incidentes', 'vip']));
                if (! $huesped) {
                    $registro->forceFill(['creado_por' => $solicitud->user()->id, 'activo' => true, 'frecuente' => false, 'lista_negra' => false]);
                }
                $campos = array_values(array_intersect(array_keys($registro->getDirty()), ['nombre', 'telefono', 'numero_identidad', 'nacionalidad', 'notas', 'notas_incidentes', 'vip']));
                $registro->save();
                $this->auditoria->registrarCambio($solicitud, 'huespedes', $huesped ? 'actualizado' : 'creado', $registro->id,
                    $anteriores, array_merge($registro->toArray(), ['campos_modificados' => $campos]));

                return $registro;
            });
        } catch (UniqueConstraintViolationException $error) {
            throw ValidationException::withMessages(['numero_identidad' => 'La identidad ya está registrada. Busca la ficha existente o solicita su restauración.']);
        }
    }

    public function cambiarCondicion(Request $solicitud, Huesped $huesped, string $campo, bool $valor): void
    {
        $permisos = ['activo' => 'huespedes.cambiar_estado', 'lista_negra' => 'huespedes.cambiar_lista_negra', 'frecuente' => 'huespedes.marcar_frecuente'];
        abort_unless(isset($permisos[$campo]), 404);
        $this->modificar($solicitud, $huesped, $permisos[$campo], $campo.'_actualizado',
            fn (Huesped $registro) => $registro->forceFill([$campo => $valor])->save());
    }

    public function eliminar(Request $solicitud, Huesped $huesped): void
    {
        $this->modificar($solicitud, $huesped, 'huespedes.eliminar', 'eliminado', fn (Huesped $registro) => $registro->delete());
    }

    public function restaurar(Request $solicitud, Huesped $huesped): void
    {
        $this->modificar($solicitud, $huesped, 'huespedes.restaurar', 'restaurado', function (Huesped $registro) {
            abort_unless($registro->trashed(), 404);
            $registro->restore();
        }, true);
    }

    private function modificar(Request $solicitud, Huesped $huesped, string $permiso, string $accion, callable $operacion, bool $conEliminados = false): void
    {
        DB::transaction(function () use ($solicitud, $huesped, $permiso, $accion, $operacion, $conEliminados) {
            $this->autorizar($solicitud, $permiso);
            $registro = Huesped::query()->when($conEliminados, fn ($consulta) => $consulta->withTrashed())->lockForUpdate()->findOrFail($huesped->id);
            $anteriores = $registro->toArray();
            $operacion($registro);
            $this->auditoria->registrarCambio($solicitud, 'huespedes', $accion, $registro->id, $anteriores, $registro->toArray());
        });
    }

    private function autorizar(Request $solicitud, string $permiso): void
    {
        $actor = $solicitud->user()?->fresh();
        abort_unless($actor, 403);
        Gate::forUser($actor)->authorize($permiso);
    }
}
