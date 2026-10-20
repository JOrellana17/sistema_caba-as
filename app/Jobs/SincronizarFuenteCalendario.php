<?php

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

    public function __construct(public int $fuenteId) {}

    public function uniqueId(): string
    {
        return (string) $this->fuenteId;
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(ServicioSincronizacionCalendario $servicio): void
    {
        $fuente = FuenteCalendario::find($this->fuenteId);
        if ($fuente?->activa && $fuente->url && ! $servicio->sincronizar($fuente)) {
            throw new RuntimeException('La sincronización del calendario necesita un nuevo intento. Se conservan los bloqueos.');
        }
    }
}
