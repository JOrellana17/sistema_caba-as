<?php

// Comando Artisan: Invoca el cierre automático de estancias vencidas y comunica cuántas se
// finalizaron.

namespace App\Console\Commands;

use App\Services\ServicioReservaciones;
use Illuminate\Console\Command;

class FinalizarReservaciones extends Command
{
    protected $signature = 'reservaciones:finalizar-vencidas';

    protected $description = 'Finaliza las estancias hospedadas cuya salida prevista ya se cumplió';

    // Invoca el cierre automático de estancias vencidas y comunica cuántas se finalizaron.
    public function handle(ServicioReservaciones $reservaciones): int
    {
        $cantidad = $reservaciones->finalizarVencidas();
        $this->info("Reservaciones finalizadas automáticamente: {$cantidad}.");

        return self::SUCCESS;
    }
}
