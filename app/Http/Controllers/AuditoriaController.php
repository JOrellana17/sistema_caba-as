<?php

// Controlador HTTP de Auditoria. Coordina solicitudes, servicios y respuestas; las rutas declaran los
// permisos de cada operación.

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarAuditoriaRequest;
use App\Models\RegistroAuditoria;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AuditoriaController extends Controller
{
    // Consulta y pagina los registros de Auditoria para su listado; conserva el orden y los filtros
    // definidos en esta acción.
    public function listar(ConsultarAuditoriaRequest $solicitud): View
    {
        // Usa solo los filtros aprobados por el Form Request, no todos los parámetros recibidos.
        $filtros = $solicitud->validated();
        $consulta = RegistroAuditoria::with('usuario:id,nombre,deleted_at');
        foreach (['modulo', 'accion', 'usuario_id'] as $campo) {
            if (! empty($filtros[$campo])) {
                $consulta->where($campo, $filtros[$campo]);
            }
        }
        if (! empty($filtros['desde'])) {
            $consulta->where('created_at', '>=', $filtros['desde'].' 00:00:00');
        }
        if (! empty($filtros['hasta'])) {
            $consulta->where('created_at', '<', Carbon::parse($filtros['hasta'])->addDay()->startOfDay());
        }

        return view('auditoria.listar', [
            'registros' => $consulta->orderByDesc('id')->paginate(25)->withQueryString(),
            'filtros' => $filtros,
        ]);
    }

    // Carga el detalle de Auditoria y sus relaciones necesarias para presentar la ficha.
    public function ver(RegistroAuditoria $registro): View
    {
        $registro->load('usuario:id,nombre,deleted_at');

        return view('auditoria.ver', compact('registro'));
    }
}
