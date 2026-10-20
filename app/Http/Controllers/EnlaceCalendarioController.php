<?php

namespace App\Http\Controllers;

use App\Models\EnlaceCalendario;
use App\Models\Reservacion;
use App\Services\ServicioExportacionCalendario;
use App\Services\ServicioGestionCalendario;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class EnlaceCalendarioController extends Controller
{
    public function guardar(Request $solicitud, ServicioGestionCalendario $gestion): RedirectResponse
    {
        $datos = $solicitud->validate([
            'cabana_id' => ['nullable', 'integer', 'exists:cabanas,id'],
            'destino' => ['required', Rule::in(['airbnb', 'booking', 'general'])],
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);
        abort_if($datos['destino'] !== 'general' && empty($datos['cabana_id']), 422, 'Cada anuncio necesita el calendario de una sola cabaña.');

        return redirect()->route('calendario.fuentes')->with('enlace_ical', $gestion->enlace($solicitud, $datos))->with('estado', 'Copia el enlace ahora. Se muestra una sola vez.');
    }

    public function revocar(Request $solicitud, EnlaceCalendario $enlace, ServicioGestionCalendario $gestion): RedirectResponse
    {
        $gestion->revocar($solicitud, $enlace);

        return back()->with('estado', 'Enlace revocado; genera otro para rotar el acceso.');
    }

    public function feed(string $token, ServicioExportacionCalendario $exportacion): Response
    {
        $enlace = EnlaceCalendario::where('token_hash', hash('sha256', $token))->where('activo', true)->firstOrFail();

        return $this->respuesta($exportacion->feed($enlace));
    }

    public function descargar(Request $solicitud, ServicioExportacionCalendario $exportacion): Response
    {
        $datos = $solicitud->validate([
            'cabana_id' => ['nullable', 'integer', 'exists:cabanas,id'],
            'reservacion_id' => ['nullable', 'integer', 'exists:reservaciones,id'],
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);
        $individual = empty($datos['reservacion_id']) ? null : Reservacion::findOrFail($datos['reservacion_id']);

        return $this->respuesta($exportacion->generar(isset($datos['cabana_id']) ? (int) $datos['cabana_id'] : null, CarbonImmutable::parse($datos['desde']), CarbonImmutable::parse($datos['hasta']), $individual));
    }

    private function respuesta(string $contenido): Response
    {
        return response($contenido, 200, ['Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="calendario-cec.ics"', 'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff']);
    }
}
