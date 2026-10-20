<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarCalendarioRequest;
use App\Models\Cabana;
use App\Models\EventoCalendario;
use App\Models\Reservacion;
use App\Services\ServicioDisponibilidad;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class CalendarioController extends Controller
{
    public function ver(ConsultarCalendarioRequest $solicitud): View
    {
        $filtros = $solicitud->validated();
        $mes = CarbonImmutable::createFromFormat('!Y-m', $filtros['mes'] ?? now(ServicioDisponibilidad::ZONA_HORARIA)->format('Y-m'), ServicioDisponibilidad::ZONA_HORARIA);
        $fin = $mes->addMonth();
        $reservas = Reservacion::with(['cabana:id,nombre,deleted_at', 'eventoCalendario'])
            ->where('entrada_prevista', '<', $fin->utc())->where('salida_prevista', '>', $mes->utc())
            ->when($filtros['cabana_id'] ?? null, fn ($consulta, $id) => $consulta->where('cabana_id', $id))
            ->when($filtros['estado'] ?? null, fn ($consulta, $estado) => $consulta->where('estado', $estado))
            ->when($filtros['origen'] ?? null, function ($consulta, $origen) {
                if ($origen === 'cec') {
                    $consulta->whereDoesntHave('eventoCalendario');
                } else {
                    $consulta->whereHas('eventoCalendario', fn ($eventos) => $eventos->where('origen', $origen));
                }
            })->orderBy('entrada_prevista')->orderBy('id')->limit(2001)->get();
        $externos = EventoCalendario::with(['cabana:id,nombre,deleted_at', 'fuente:id,nombre,proveedor,activa,ultima_correcta,estado'])
            ->where(fn ($consulta) => $consulta->where('bloquea', true)->orWhere('discrepancia', true))
            ->where(fn ($consulta) => $consulta->whereNull('reservacion_id')->orWhere('discrepancia', true)->orWhere('conflicto', true))
            ->where('inicio', '<', $fin->utc())->where('fin', '>', $mes->utc())
            ->when($filtros['cabana_id'] ?? null, fn ($consulta, $id) => $consulta->where('cabana_id', $id))
            ->when($filtros['origen'] ?? null, fn ($consulta, $origen) => $consulta->where('origen', $origen))
            ->when($filtros['estado'] ?? null, fn ($consulta) => $consulta->whereRaw('1 = 0'))
            ->orderBy('inicio')->orderBy('id')->limit(2001)->get();
        abort_if($reservas->count() > 2000 || $externos->count() > 2000, 422, 'Hay demasiados registros. Filtra por cabaña.');
        $dias = [];
        for ($dia = $mes; $dia->lessThan($fin); $dia = $dia->addDay()) {
            $dias[] = [
                'fecha' => $dia,
                'reservas' => $reservas->filter(fn ($reserva) => $reserva->entrada_prevista->lessThan($dia->addDay()->utc()) && $reserva->salida_prevista->greaterThan($dia->utc())),
                'eventos' => $externos->filter(fn ($evento) => $evento->inicio->lessThan($dia->addDay()->utc()) && $evento->fin->greaterThan($dia->utc())),
            ];
        }

        return view('calendario.ver', [
            'mes' => $mes, 'dias' => $dias, 'filtros' => $filtros,
            'cabanas' => Cabana::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }
}
