<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarCabanasRequest;
use App\Http\Requests\EstadoCabanaRequest;
use App\Http\Requests\GuardarCabanaRequest;
use App\Models\Cabana;
use App\Services\ServicioCabanas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CabanaController extends Controller
{
    public function __construct(private ServicioCabanas $cabanas) {}

    public function listar(ConsultarCabanasRequest $solicitud): View
    {
        $filtros = $solicitud->validated();
        $eliminadas = $solicitud->boolean('eliminadas');
        if ($eliminadas) {
            Gate::authorize('cabanas.restaurar');
        }

        $consulta = Cabana::query();
        if ($eliminadas) {
            $consulta->onlyTrashed();
        }
        if ($solicitud->filled('buscar')) {
            // Escapar comodines: buscar "%" o "_" debe tratarlos como texto.
            $texto = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filtros['buscar']);
            $consulta->whereRaw("LOWER(nombre) LIKE LOWER(?) ESCAPE '!'", ['%'.$texto.'%']);
        }
        if ($solicitud->filled('estado')) {
            $consulta->where('estado', $filtros['estado']);
        }
        if ($solicitud->filled('activa')) {
            $consulta->where('activa', $solicitud->boolean('activa'));
        }

        return view('cabanas.listar', [
            'cabanas' => $consulta->orderBy('nombre')->orderBy('id')->paginate(20)->withQueryString(),
            'filtros' => $filtros,
            'eliminadas' => $eliminadas,
            'puedeEditar' => $solicitud->user()->can('cabanas.editar'),
        ]);
    }

    public function crear(): View
    {
        return view('cabanas.formulario', ['cabana' => new Cabana]);
    }

    public function guardar(GuardarCabanaRequest $solicitud): RedirectResponse
    {
        $this->cabanas->guardar($solicitud, $solicitud->validated());

        return $this->volver($solicitud);
    }

    public function ver(Cabana $cabana): View
    {
        $cabana->load('creador:id,nombre,deleted_at');

        return view('cabanas.ver', compact('cabana'));
    }

    public function editar(Cabana $cabana): View
    {
        return view('cabanas.formulario', compact('cabana'));
    }

    public function actualizar(GuardarCabanaRequest $solicitud, Cabana $cabana): RedirectResponse
    {
        $this->cabanas->guardar($solicitud, $solicitud->validated(), $cabana);

        return $this->volver($solicitud);
    }

    public function estado(EstadoCabanaRequest $solicitud, Cabana $cabana): RedirectResponse
    {
        $this->cabanas->cambiarEstado($solicitud, $cabana, $solicitud->validated());

        return $this->volver($solicitud);
    }

    public function eliminar(Request $solicitud, Cabana $cabana): RedirectResponse
    {
        $this->cabanas->eliminar($solicitud, $cabana);

        return $this->volver($solicitud);
    }

    public function restaurar(Request $solicitud, Cabana $cabana): RedirectResponse
    {
        $this->cabanas->restaurar($solicitud, $cabana);

        return $this->volver($solicitud);
    }

    public function imagen(Cabana $cabana): StreamedResponse
    {
        $disco = Storage::disk('cabanas');
        abort_unless($cabana->imagen_principal && $disco->exists($cabana->imagen_principal), 404);

        return $disco->response($cabana->imagen_principal, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function volver(Request $solicitud): RedirectResponse
    {
        return redirect()->route($solicitud->user()->can('cabanas.ver') ? 'cabanas.listar' : 'inicio')
            ->with('estado', 'Operación realizada correctamente.');
    }
}
