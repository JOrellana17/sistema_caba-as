<?php

// Controlador HTTP de Cabana. Coordina solicitudes, servicios y respuestas; las rutas declaran los
// permisos de cada operación.

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
    // Recibe ServicioCabanas mediante el contenedor de Laravel para reutilizarlas durante la
    // operación.
    public function __construct(private ServicioCabanas $cabanas) {}

    // Consulta y pagina los registros de Cabana para su listado; conserva el orden y los filtros
    // definidos en esta acción.
    public function listar(ConsultarCabanasRequest $solicitud): View
    {
        // Usa solo los filtros aprobados por el Form Request, no todos los parámetros recibidos.
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

    // Prepara el formulario de alta de Cabana con un modelo nuevo; mostrar el formulario todavía no
    // guarda un registro.
    public function crear(): View
    {
        return view('cabanas.formulario', ['cabana' => new Cabana]);
    }

    // Entrega los datos validados de Cabana al servicio responsable de guardarlos y devuelve la
    // respuesta de confirmación.
    public function guardar(GuardarCabanaRequest $solicitud): RedirectResponse
    {
        $this->cabanas->guardar($solicitud, $solicitud->validated());

        return $this->volver($solicitud);
    }

    // Carga el detalle de Cabana y sus relaciones necesarias para presentar la ficha.
    public function ver(Cabana $cabana): View
    {
        $cabana->load('creador:id,nombre,deleted_at');
        $cabana->loadExists(['aseosPendientes as en_aseo']);

        return view('cabanas.ver', compact('cabana'));
    }

    // Presenta el formulario de Cabana con el registro resuelto por Laravel para su edición.
    public function editar(Cabana $cabana): View
    {
        return view('cabanas.formulario', compact('cabana'));
    }

    // Envía el registro de Cabana y los datos validados al servicio para aplicar una actualización
    // autorizada.
    public function actualizar(GuardarCabanaRequest $solicitud, Cabana $cabana): RedirectResponse
    {
        $this->cabanas->guardar($solicitud, $solicitud->validated(), $cabana);

        return $this->volver($solicitud);
    }

    // Envía el cambio de condición de Cabana al servicio mediante su operación autorizada.
    public function estado(EstadoCabanaRequest $solicitud, Cabana $cabana): RedirectResponse
    {
        $this->cabanas->cambiarEstado($solicitud, $cabana, $solicitud->validated());

        return $this->volver($solicitud);
    }

    // Delega la eliminación de Cabana al servicio, donde se verifican permisos y restricciones antes
    // de modificar los datos.
    public function eliminar(Request $solicitud, Cabana $cabana): RedirectResponse
    {
        $this->cabanas->eliminar($solicitud, $cabana);

        return $this->volver($solicitud);
    }

    // Solicita restaurar Cabana mediante el servicio y devuelve confirmación al destino permitido.
    public function restaurar(Request $solicitud, Cabana $cabana): RedirectResponse
    {
        $this->cabanas->restaurar($solicitud, $cabana);

        return $this->volver($solicitud);
    }

    // Entrega la imagen del disco privado solo si existe; aplica cabeceras contra caché e
    // interpretación de contenido distinta de su tipo.
    public function imagen(Cabana $cabana): StreamedResponse
    {
        $disco = Storage::disk('cabanas');
        abort_unless($cabana->imagen_principal && $disco->exists($cabana->imagen_principal), 404);

        return $disco->response($cabana->imagen_principal, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    // Selecciona un destino que el usuario pueda consultar y agrega un mensaje de confirmación a la
    // siguiente respuesta.
    private function volver(Request $solicitud): RedirectResponse
    {
        return redirect()->route($solicitud->user()->can('cabanas.ver') ? 'cabanas.listar' : 'inicio')
            ->with('estado', 'Operación realizada correctamente.');
    }
}
