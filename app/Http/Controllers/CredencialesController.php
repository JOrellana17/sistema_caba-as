<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerarCredencialesRequest;
use App\Services\ServicioGeneradorCredenciales;
use Illuminate\Http\JsonResponse;

class CredencialesController extends Controller
{
    public function generar(GenerarCredencialesRequest $solicitud, ServicioGeneradorCredenciales $generador): JsonResponse
    {
        return response()->json(['valor' => $generador->generar($solicitud->validated())])
            ->header('Cache-Control', 'no-store, private');
    }
}
