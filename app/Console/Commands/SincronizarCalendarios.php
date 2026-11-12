<?php

// Comando Artisan: Recorre por lotes las fuentes activas cuya consulta corresponde y encola trabajos
// individuales de sincronización.

namespace App\Console\Commands;

use App\Jobs\SincronizarFuenteCalendario;
use App\Models\FuenteCalendario;
use Illuminate\Console\Command;

class SincronizarCalendarios extends Command
{
    protected $signature = 'calendario:sincronizar';

    protected $description = 'Encola las fuentes iCalendar activas cuya consulta está pendiente';

    // Recorre por lotes las fuentes activas cuya consulta corresponde y encola trabajos individuales
    // de sincronización.
    public function handle(): int
    {
        FuenteCalendario::where('activa', true)->whereNotNull('url')
            ->where(fn ($consulta) => $consulta->whereNull('proxima_consulta')->orWhere('proxima_consulta', '<=', now()))
            ->orderBy('id')->chunkById(100, function ($fuentes) {
                foreach ($fuentes as $fuente) {
                    SincronizarFuenteCalendario::dispatch($fuente->id);
                }
            });
        $this->info('Fuentes pendientes enviadas a la cola.');

        return self::SUCCESS;
    }
}
