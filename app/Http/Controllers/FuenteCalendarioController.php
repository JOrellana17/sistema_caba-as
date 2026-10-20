<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarFuenteCalendarioRequest;
use App\Http\Requests\GuardarFuenteCalendarioRequest;
use App\Jobs\SincronizarFuenteCalendario;
use App\Models\Cabana;
use App\Models\EnlaceCalendario;
use App\Models\FuenteCalendario;
use App\Models\SincronizacionCalendario;
use App\Services\ServicioArchivoCalendario;
use App\Services\ServicioGestionCalendario;
use App\Services\ServicioLecturaCalendario;
use App\Services\ServicioSincronizacionCalendario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class FuenteCalendarioController extends Controller
{
    public function __construct(private ServicioGestionCalendario $gestion) {}

    public function listar(): View
    {
        abort_unless(Gate::any(['calendario.administrar_fuentes', 'calendario.importar', 'calendario.gestionar_enlaces', 'calendario.exportar', 'calendario.gestionar_bloqueos', 'calendario.sincronizar']), 403);

        return view('calendario.fuentes', [
            'fuentes' => FuenteCalendario::with('cabana:id,nombre,deleted_at')->orderBy('id')->paginate(20),
            'cabanas' => Cabana::orderBy('nombre')->get(['id', 'nombre']),
            'enlaces' => EnlaceCalendario::with('cabana:id,nombre,deleted_at')->latest('id')->limit(100)->get(),
            'historial' => SincronizacionCalendario::latest('id')->limit(20)->get(),
        ]);
    }

    public function guardar(GuardarFuenteCalendarioRequest $solicitud): RedirectResponse
    {
        $this->gestion->fuente($solicitud, $solicitud->validated());

        return redirect()->route('calendario.fuentes')->with('estado', 'Fuente guardada inactiva. Previsualiza su archivo o URL antes de activarla.');
    }

    public function editar(FuenteCalendario $fuente): View
    {
        return view('calendario.editar-fuente', compact('fuente'));
    }

    public function actualizar(ActualizarFuenteCalendarioRequest $solicitud, FuenteCalendario $fuente): RedirectResponse
    {
        $this->gestion->actualizarFuente($solicitud, $fuente, $solicitud->validated());

        return redirect()->route('calendario.fuentes')->with('estado', 'Fuente actualizada y suspendida. Previsualiza para activarla; sus bloqueos se conservan.');
    }

    public function previsualizar(Request $solicitud, FuenteCalendario $fuente, ServicioLecturaCalendario $lector, ServicioArchivoCalendario $archivos, ServicioSincronizacionCalendario $sincronizacion): View
    {
        $solicitud->validate(['archivo' => ['nullable', 'file', 'max:2048']]);
        try {
            if ($solicitud->hasFile('archivo')) {
                $contenido = file_get_contents($solicitud->file('archivo')->getRealPath());
            } else {
                abort_unless($fuente->url, 422, 'Sube un archivo ICS o configura una URL.');
                // La previsualización necesita una lectura completa, no un 304.
                $lecturaFuente = clone $fuente;
                $lecturaFuente->etag = null;
                $lecturaFuente->ultima_modificacion = null;
                $contenido = $lector->descargar($lecturaFuente)['contenido'];
            }
            $eventos = $archivos->analizar($contenido, $fuente->interpretacion_fechas, $fuente->zona_horaria);
        } catch (Throwable) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo previsualizar. Comprueba URL, conexión, archivo, fechas y recurrencias. No se cambió disponibilidad.']);
        }
        $eventos = $sincronizacion->previsualizar($fuente, $eventos);
        $confirmacion = Str::random(40);
        $solicitud->session()->put('ical_previsualizacion', [
            'fuente_id' => $fuente->id, 'version' => $fuente->version, 'contenido' => Crypt::encryptString($contenido), 'desde_url' => ! $solicitud->hasFile('archivo'),
            'confirmacion' => $confirmacion, 'vence' => now()->addMinutes(10)->timestamp,
        ]);

        return view('calendario.previsualizacion', compact('fuente', 'eventos', 'confirmacion'));
    }

    public function importar(Request $solicitud, FuenteCalendario $fuente, ServicioSincronizacionCalendario $sincronizacion): RedirectResponse
    {
        $solicitud->validate(['confirmacion' => ['required', 'string', 'size:40']]);
        $previa = $solicitud->session()->get('ical_previsualizacion');
        abort_unless($previa && $previa['fuente_id'] === $fuente->id && $previa['version'] === $fuente->version
            && $previa['vence'] >= now()->timestamp && hash_equals($previa['confirmacion'], $solicitud->input('confirmacion')), 422, 'La previsualización cambió o venció.');
        if (! $fuente->activa) {
            Gate::authorize('calendario.administrar_fuentes');
            $fuente = $this->gestion->activar($solicitud, $fuente, true);
        }
        try {
            if ($previa['desde_url']) {
                if (! $sincronizacion->sincronizar($fuente)) {
                    throw new \RuntimeException;
                }
            } else {
                $sincronizacion->aplicar($fuente, Crypt::decryptString($previa['contenido']), $solicitud);
            }
        } catch (\RuntimeException) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo aplicar. Un archivo no puede cambiar un bloqueo externo existente; comprueba el cambio en la URL de origen.']);
        }
        $solicitud->session()->forget('ical_previsualizacion');

        return redirect()->route('calendario.ver')->with('estado', 'Eventos importados. Las bajas solo se comprueban leyendo la fuente original por URL.');
    }

    public function suspender(Request $solicitud, FuenteCalendario $fuente): RedirectResponse
    {
        $this->gestion->activar($solicitud, $fuente, false);

        return redirect()->route('calendario.fuentes')->with('estado', 'Fuente suspendida. Sus bloqueos se conservan.');
    }

    public function sincronizar(Request $solicitud, FuenteCalendario $fuente): RedirectResponse
    {
        abort_unless($fuente->activa && $fuente->url, 422, 'Activa una fuente con URL antes de sincronizar.');
        SincronizarFuenteCalendario::dispatch($fuente->id);

        return back()->with('estado', 'Sincronización enviada a la cola. Consulta su resultado en Fuentes.');
    }
}
