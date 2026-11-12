<?php

// Servicio de aseo cabanas. Centraliza las operaciones descritas en sus métodos para que los
// controladores reutilicen las mismas reglas.

namespace App\Services;

use App\Models\AseoCabana;
use App\Models\Reservacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

class ServicioAseoCabanas
{
    // Recibe ServicioBloqueoCabanas, ServicioAuditoria mediante el contenedor de Laravel para
    // reutilizarlas durante la operación.
    public function __construct(private ServicioBloqueoCabanas $bloqueo, private ServicioAuditoria $auditoria) {}

    // Abre un ciclo de aseo al finalizar una estancia, dentro de la transacción de salida. Evita crear
    // un segundo ciclo para la misma reservación.
    /** Operación interna del cierre autorizado, dentro de su transacción y bloqueo de cabaña. */
    public function iniciar(Request $solicitud, Reservacion $reservacion): void
    {
        if (DB::transactionLevel() < 1 || $reservacion->estado !== 'finalizada') {
            throw new LogicException('El aseo requiere finalizar la estancia dentro de una transacción.');
        }
        if (AseoCabana::where('reservacion_id', $reservacion->id)->exists()) {
            return;
        }
        $aseo = new AseoCabana;
        $aseo->forceFill([
            'cabana_id' => $reservacion->cabana_id, 'reservacion_id' => $reservacion->id, 'iniciado_en' => now('UTC'),
        ])->save();
        $this->auditoria->registrarCambio($solicitud, 'aseo', 'iniciado', $aseo->id, [], $aseo->toArray());
    }

    // Confirma un ciclo válido una sola vez, bajo bloqueo de cabaña y permisos explícitos. Devuelve
    // false si ya estaba confirmado y true si acaba de confirmarlo.
    public function confirmar(Request $solicitud, AseoCabana $aseo): bool
    {
        // Las escrituras de este bloque se confirman juntas; una excepción revierte sus cambios en la base de datos.
        return DB::transaction(function () use ($solicitud, $aseo) {
            $cabanas = $this->bloqueo->adquirir([$aseo->cabana_id]);
            // Consulta los privilegios actuales del actor antes de ejecutar una modificación.
            $actor = $solicitud->user()?->fresh();
            abort_unless($actor, 403);
            Gate::forUser($actor)->authorize('aseo.ver');
            Gate::forUser($actor)->authorize('aseo.confirmar');
            $registro = AseoCabana::lockForUpdate()->findOrFail($aseo->id);
            $cabana = $cabanas->get($registro->cabana_id);
            $reservacion = Reservacion::withTrashed()->find($registro->reservacion_id);
            if ($registro->cabana_id !== $aseo->cabana_id || ! $cabana || $cabana->trashed()
                || ! $reservacion || $reservacion->trashed() || $reservacion->estado !== 'finalizada'
                || $reservacion->cabana_id !== $registro->cabana_id) {
                throw ValidationException::withMessages(['aseo' => 'El aseo no corresponde a una estancia finalizada vigente. Recarga la vista.']);
            }
            if ($registro->confirmado_en !== null) {
                return false;
            }
            // Conserva los valores previos para describir el cambio en la auditoría.
            $anteriores = $registro->toArray();
            $registro->forceFill(['confirmado_en' => now('UTC'), 'confirmado_por' => $actor->id])->save();
            $this->auditoria->registrarCambio($solicitud, 'aseo', 'confirmado', $registro->id, $anteriores, $registro->toArray());

            return true;
        }, 3);
    }
}
