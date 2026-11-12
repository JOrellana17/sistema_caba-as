<?php

// Trabajo único en cola para sincronizar una fuente iCalendar con reintentos limitados.

namespace App\Jobs;

use App\Models\FuenteCalendario;
use App\Services\ServicioSincronizacionCalendario;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class SincronizarFuenteCalendario implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 50;

    public int $uniqueFor = 300;

    // Conserva únicamente el identificador de la fuente para releer su estado vigente cuando la cola
    // ejecute el trabajo.
    public function __construct(public int $fuenteId) {}

    // Identifica el trabajo por fuente para que la cola no duplique una sincronización pendiente.
    public function uniqueId(): string
    {
        return (string) $this->fuenteId;
    }

    // Define las esperas crecientes entre intentos para evitar insistir inmediatamente sobre un
    // proveedor con errores.
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    // Relee la fuente al ejecutar el trabajo y sincroniza únicamente si sigue activa y tiene URL; un
    // fallo solicita reintento conservando bloqueos.
    public function handle(ServicioSincronizacionCalendario $servicio): void
    {
        $fuente = FuenteCalendario::find($this->fuenteId);
        if ($fuente?->activa && $fuente->url && ! $servicio->sincronizar($fuente)) {
            throw new RuntimeException('La sincronización del calendario necesita un nuevo intento. Se conservan los bloqueos.');
        }
    }
}
