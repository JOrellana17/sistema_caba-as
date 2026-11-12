<?php

// Controlador HTTP de Perfil. Coordina solicitudes, servicios y respuestas; las rutas declaran los
// permisos de cada operación.

namespace App\Http\Controllers;

use App\Http\Requests\CredencialesPerfilRequest;
use App\Services\ServicioPerfil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PerfilController extends Controller
{
    // Carga los datos y roles del usuario autenticado para mostrar su perfil sin aceptar un
    // identificador de otra cuenta.
    public function ver(Request $solicitud): View
    {
        return view('perfil.ver', ['usuario' => $solicitud->user()->load('roles')]);
    }

    // Entrega las credenciales validadas al servicio personal y muestra la confirmación sin devolver
    // valores secretos.
    public function cambiar(CredencialesPerfilRequest $solicitud, ServicioPerfil $perfil): RedirectResponse
    {
        $perfil->cambiar($solicitud, $solicitud->validated());

        return redirect()->route('perfil.ver')->with('estado', 'Credenciales actualizadas. Las otras sesiones fueron revocadas.');
    }
}
