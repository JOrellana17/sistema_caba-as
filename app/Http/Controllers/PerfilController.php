<?php

namespace App\Http\Controllers;

use App\Http\Requests\CredencialesPerfilRequest;
use App\Services\ServicioPerfil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PerfilController extends Controller
{
    public function ver(Request $solicitud): View
    {
        return view('perfil.ver', ['usuario' => $solicitud->user()->load('roles')]);
    }

    public function cambiar(CredencialesPerfilRequest $solicitud, ServicioPerfil $perfil): RedirectResponse
    {
        $perfil->cambiar($solicitud, $solicitud->validated());

        return redirect()->route('perfil.ver')->with('estado', 'Credenciales actualizadas. Las otras sesiones fueron revocadas.');
    }
}
