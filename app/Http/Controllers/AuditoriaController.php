<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarAuditoriaRequest;
use App\Models\RegistroAuditoria;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AuditoriaController extends Controller
{
    public function listar(ConsultarAuditoriaRequest $solicitud): View
    {
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

    public function ver(RegistroAuditoria $registro): View
    {
        $registro->load('usuario:id,nombre,deleted_at');

        return view('auditoria.ver', compact('registro'));
    }
}
