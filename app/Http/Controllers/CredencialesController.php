<?php

// Controlador HTTP de Credenciales. Coordina solicitudes, servicios y respuestas; las rutas declaran
// los permisos de cada operación.

namespace App\Http\Controllers;

use App\Http\Requests\GenerarCredencialesRequest;
use App\Services\ServicioGeneradorCredenciales;
use Illuminate\Http\JsonResponse;

class CredencialesController extends Controller
{
    // Devuelve la sugerencia autorizada como JSON con no-store, sin persistir una cuenta ni conceder
    // privilegios.
    public function generar(GenerarCredencialesRequest $solicitud, ServicioGeneradorCredenciales $generador): JsonResponse
    {
        return response()->json(['valor' => $generador->generar($solicitud->validated())])
            ->header('Cache-Control', 'no-store, private');
    }
}
