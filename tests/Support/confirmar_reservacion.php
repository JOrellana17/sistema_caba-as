<?php

use App\Models\Reservacion;
use App\Models\Usuario;
use App\Services\ServicioReservaciones;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Proceso exclusivo de pruebas: nunca acepta la base operativa ni credenciales por argumentos.
if (getenv('APP_ENV') !== 'testing' || ! isset($argv[1], $argv[2], $argv[3])
    || ! preg_match('/\Asistema_cabanas_pruebas_[a-f0-9]{16}\z/', $argv[1])
    || getenv('CEC_BASE_PRUEBA') !== $argv[1]) {
    exit(2);
}

require __DIR__.'/../../vendor/autoload.php';

try {
    $app = require __DIR__.'/../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if (! $app->environment('testing') || config('database.default') !== 'mysql'
        || config('database.connections.mysql.database') !== $argv[1]
        || ! empty(config('database.connections.mysql.url'))) {
        exit(2);
    }
    DB::statement('SET SESSION innodb_lock_wait_timeout = 5');
    $usuario = Usuario::findOrFail((int) $argv[2]);
    $reservacion = Reservacion::findOrFail((int) $argv[3]);
    $solicitud = Request::create('/reservaciones/confirmar', 'PUT');
    $solicitud->setUserResolver(fn () => $usuario);
    fwrite(STDOUT, "listo\n");
    if (trim((string) fgets(STDIN)) !== 'continuar') {
        exit(2);
    }
    $app->make(ServicioReservaciones::class)->cambiarEstado($solicitud, $reservacion, 'confirmada');
    fwrite(STDOUT, "confirmada\n");
} catch (ValidationException $error) {
    fwrite(STDOUT, isset($error->errors()['cabana_id']) ? "rechazada\n" : "error-validacion\n");
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error));
    exit(1);
}
