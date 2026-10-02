<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuscarHuespedesRequest;
use App\Http\Requests\CondicionHuespedRequest;
use App\Http\Requests\ConsultarHuespedesRequest;
use App\Http\Requests\GuardarHuespedRequest;
use App\Models\Huesped;
use App\Models\RegistroAuditoria;
use App\Services\ServicioBusquedaHuespedes;
use App\Services\ServicioHuespedes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class HuespedController extends Controller
{
    public function __construct(private ServicioHuespedes $huespedes, private ServicioBusquedaHuespedes $busqueda) {}

    public function listar(ConsultarHuespedesRequest $solicitud): View
    {
        $filtros = $solicitud->validated();
        $eliminados = $solicitud->boolean('eliminados');
        if ($eliminados) {
            Gate::authorize('huespedes.restaurar');
        }
        $consulta = Huesped::query();
        if ($eliminados) {
            $consulta->onlyTrashed();
        }
        foreach (['activo', 'lista_negra', 'frecuente', 'vip'] as $campo) {
            if ($solicitud->filled($campo)) {
                $consulta->where($campo, $solicitud->boolean($campo));
            }
        }
        if ($solicitud->filled('buscar')) {
            Gate::authorize('huespedes.buscar');
            $this->busqueda->filtrar($consulta, $filtros['buscar']);
        } else {
            $consulta->orderBy('nombre_busqueda')->orderBy('id');
        }

        return view('huespedes.listar', [
            'huespedes' => $consulta->paginate(20)->withQueryString(),
            'filtros' => $filtros,
            'eliminados' => $eliminados,
            'puedeEditar' => $solicitud->user()->can('huespedes.editar'),
        ]);
    }

    public function buscador(): View
    {
        return view('huespedes.buscador');
    }

    public function buscar(BuscarHuespedesRequest $solicitud): JsonResponse
    {
        // El negocio autoriza identidad completa a quienes pueden buscar.
        return response()->json($this->busqueda->buscar($solicitud->validated('termino_busqueda'), true))
            ->header('Cache-Control', 'private, no-store');
    }

    public function crear(): View
    {
        return view('huespedes.formulario', ['huesped' => new Huesped]);
    }

    public function guardar(GuardarHuespedRequest $solicitud): RedirectResponse
    {
        $huesped = $this->huespedes->guardar($solicitud, $solicitud->validated());

        return $this->volver($solicitud, $huesped);
    }

    public function guardarDesdeFormulario(GuardarHuespedRequest $solicitud): JsonResponse
    {
        $huesped = $this->huespedes->guardar($solicitud, $solicitud->validated());

        return response()->json(['huesped' => $this->busqueda->resumir($huesped, true)], 201)
            ->header('Cache-Control', 'private, no-store');
    }

    public function ver(Request $solicitud, Huesped $huesped): View
    {
        $historial = $solicitud->user()->can('auditoria.ver')
            ? RegistroAuditoria::with('usuario:id,nombre,deleted_at')->where('modulo', 'huespedes')
                ->where('registro_id', $huesped->id)->latest('id')->paginate(20)
            : null;

        $estancias = $solicitud->user()->can('reservaciones.ver')
            ? $huesped->reservaciones()->with('cabana:id,nombre,deleted_at')
                ->orderByDesc('fecha_entrada')->orderByDesc('id')->paginate(10, ['*'], 'pagina_estancias')->withQueryString()
            : null;

        return view('huespedes.ver', compact('huesped', 'historial', 'estancias'));
    }

    public function editar(Huesped $huesped): View
    {
        return view('huespedes.formulario', compact('huesped'));
    }

    public function actualizar(GuardarHuespedRequest $solicitud, Huesped $huesped): RedirectResponse
    {
        $this->huespedes->guardar($solicitud, $solicitud->validated(), $huesped);

        return $this->volver($solicitud, $huesped);
    }

    public function estado(CondicionHuespedRequest $solicitud, Huesped $huesped): RedirectResponse
    {
        $this->huespedes->cambiarCondicion($solicitud, $huesped, 'activo', $solicitud->boolean('valor'));

        return $this->volver($solicitud, $huesped);
    }

    public function listaNegra(CondicionHuespedRequest $solicitud, Huesped $huesped): RedirectResponse
    {
        $this->huespedes->cambiarCondicion($solicitud, $huesped, 'lista_negra', $solicitud->boolean('valor'));

        return $this->volver($solicitud, $huesped);
    }

    public function frecuente(CondicionHuespedRequest $solicitud, Huesped $huesped): RedirectResponse
    {
        $this->huespedes->cambiarCondicion($solicitud, $huesped, 'frecuente', $solicitud->boolean('valor'));

        return $this->volver($solicitud, $huesped);
    }

    public function eliminar(Request $solicitud, Huesped $huesped): RedirectResponse
    {
        $this->huespedes->eliminar($solicitud, $huesped);

        return $this->volver($solicitud);
    }

    public function restaurar(Request $solicitud, Huesped $huesped): RedirectResponse
    {
        $this->huespedes->restaurar($solicitud, $huesped);

        return $this->volver($solicitud, $huesped);
    }

    private function volver(Request $solicitud, ?Huesped $huesped = null): RedirectResponse
    {
        $destino = $solicitud->user()->can('huespedes.ver')
            ? ($huesped ? route('huespedes.ver', $huesped) : route('huespedes.listar'))
            : route('inicio');

        return redirect($destino)->with('estado', 'Operación realizada correctamente.');
    }
}
