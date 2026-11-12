<?php

// Servicio de huespedes. Centraliza las operaciones descritas en sus métodos para que los
// controladores reutilicen las mismas reglas.

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
    // Recibe ServicioAuditoria mediante el contenedor de Laravel para reutilizarlas durante la
    // operación.
    public function __construct(private ServicioAuditoria $auditoria) {}

    // Guarda la ficha del huésped sin aceptar campos de condición administrados por otras acciones.
    // Convierte una colisión de identidad en un error recuperable y registra los campos modificados.
    public function guardar(Request $solicitud, array $datos, ?Huesped $huesped = null): Huesped
    {
        try {
            // Las escrituras de este bloque se confirman juntas; una excepción revierte sus cambios en la base de datos.
            return DB::transaction(function () use ($solicitud, $datos, $huesped) {
                $this->autorizar($solicitud, $huesped ? 'huespedes.editar' : 'huespedes.crear');
                $registro = $huesped ? Huesped::lockForUpdate()->findOrFail($huesped->id) : new Huesped;
                // Conserva los valores previos para describir el cambio en la auditoría.
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

    // Traduce una condición permitida a su permiso explícito y modifica solo ese campo: activo, lista
    // negra o frecuente.
    public function cambiarCondicion(Request $solicitud, Huesped $huesped, string $campo, bool $valor): void
    {
        $permisos = ['activo' => 'huespedes.cambiar_estado', 'lista_negra' => 'huespedes.cambiar_lista_negra', 'frecuente' => 'huespedes.marcar_frecuente'];
        abort_unless(isset($permisos[$campo]), 404);
        $this->modificar($solicitud, $huesped, $permisos[$campo], $campo.'_actualizado',
            fn (Huesped $registro) => $registro->forceFill([$campo => $valor])->save());
    }

    // Elimina lógicamente la ficha autorizada, conservando sus relaciones históricas y dejando
    // registro de la operación.
    public function eliminar(Request $solicitud, Huesped $huesped): void
    {
        $this->modificar($solicitud, $huesped, 'huespedes.eliminar', 'eliminado', fn (Huesped $registro) => $registro->delete());
    }

    // Restaura una ficha eliminada después de autorizar la acción; los datos originales continúan
    // asociados al mismo identificador.
    public function restaurar(Request $solicitud, Huesped $huesped): void
    {
        $this->modificar($solicitud, $huesped, 'huespedes.restaurar', 'restaurado', function (Huesped $registro) {
            abort_unless($registro->trashed(), 404);
            $registro->restore();
        }, true);
    }

    // Bloquea y relee la ficha, ejecuta la modificación autorizada y guarda su auditoría en una sola
    // transacción.
    private function modificar(Request $solicitud, Huesped $huesped, string $permiso, string $accion, callable $operacion, bool $conEliminados = false): void
    {
        // Las escrituras de este bloque se confirman juntas; una excepción revierte sus cambios en la base de datos.
        DB::transaction(function () use ($solicitud, $huesped, $permiso, $accion, $operacion, $conEliminados) {
            $this->autorizar($solicitud, $permiso);
            $registro = Huesped::query()->when($conEliminados, fn ($consulta) => $consulta->withTrashed())->lockForUpdate()->findOrFail($huesped->id);
            // Conserva los valores previos para describir el cambio en la auditoría.
            $anteriores = $registro->toArray();
            $operacion($registro);
            $this->auditoria->registrarCambio($solicitud, 'huespedes', $accion, $registro->id, $anteriores, $registro->toArray());
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
