<?php

namespace App\Http\Controllers;

use App\Models\EventoCalendario;
use App\Models\Reservacion;
use App\Services\ServicioGestionCalendario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventoCalendarioController extends Controller
{
    public function ver(Request $solicitud, EventoCalendario $evento): View
    {
        $evento->load(['cabana:id,nombre,deleted_at', 'fuente', 'reservacion']);

        $candidatas = collect();
        if ($solicitud->user()->can('calendario.vincular') && $solicitud->user()->can('reservaciones.editar') && ! $evento->reservacion_id) {
            $candidatas = Reservacion::where('cabana_id', $evento->cabana_id)
                ->where('entrada_prevista', $evento->inicio)->where('salida_prevista', $evento->fin)
                ->whereIn('estado', ['pendiente', 'confirmada'])->doesntHave('eventoCalendario')
                ->orderBy('id')->limit(100)->get(['id', 'codigo', 'estado']);
        }

        return view('calendario.evento', compact('evento', 'candidatas'));
    }

    public function bloquear(Request $solicitud, ServicioGestionCalendario $gestion): RedirectResponse
    {
        $datos = $solicitud->validate(['cabana_id' => ['required', 'integer', 'exists:cabanas,id'],
            'inicio' => ['required', 'date_format:Y-m-d\\TH:i'], 'fin' => ['required', 'date_format:Y-m-d\\TH:i', 'after:inicio']]);
        $gestion->bloqueo($solicitud, (int) $datos['cabana_id'], $datos['inicio'], $datos['fin']);

        return redirect()->route('calendario.ver')->with('estado', 'Bloqueo CEC creado con período definido.');
    }

    public function liberar(Request $solicitud, EventoCalendario $evento, ServicioGestionCalendario $gestion): RedirectResponse
    {
        $gestion->liberar($solicitud, $evento);

        return redirect()->route('calendario.ver')->with('estado', 'Bloqueo CEC liberado. Se conservan las demás causas de indisponibilidad.');
    }

    public function vincular(Request $solicitud, EventoCalendario $evento, ServicioGestionCalendario $gestion): RedirectResponse
    {
        $datos = $solicitud->validate(['reservacion_id' => ['required', 'integer', 'exists:reservaciones,id']]);
        $gestion->vincular($solicitud, $evento, Reservacion::findOrFail($datos['reservacion_id']));

        return back()->with('estado', 'Reservación vinculada. El bloqueo conserva su origen.');
    }
}
