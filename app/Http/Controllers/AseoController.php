<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmarAseoRequest;
use App\Models\AseoCabana;
use App\Models\Cabana;
use App\Services\ServicioAseoCabanas;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AseoController extends Controller
{
    public function listar(): View
    {
        $cabanas = Cabana::select(['id', 'nombre', 'estado', 'activa'])
            ->with([
                'aseosPendientes' => fn ($consulta) => $consulta->select(['id', 'cabana_id', 'reservacion_id', 'iniciado_en'])->orderBy('id'),
                'ultimoAseo' => fn ($consulta) => $consulta->select(['aseos_cabanas.id', 'aseos_cabanas.cabana_id', 'aseos_cabanas.confirmado_en']),
            ])
            ->withExists(['aseosPendientes as en_aseo'])
            ->orderByDesc('en_aseo')->orderBy('nombre')->orderBy('id')->paginate(20);

        return view('aseo.listar', compact('cabanas'));
    }

    public function confirmar(ConfirmarAseoRequest $solicitud, AseoCabana $aseo, ServicioAseoCabanas $servicio): RedirectResponse
    {
        $confirmado = $servicio->confirmar($solicitud, $aseo);

        return redirect()->route('aseo.listar')->with('estado', $confirmado
            ? 'Aseo confirmado. Se retiró el bloqueo por aseo; los demás impedimentos de entrada se conservan.'
            : 'Este aseo ya estaba confirmado. No se modificó ningún otro ciclo.');
    }
}
