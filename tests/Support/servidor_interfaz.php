<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;

// Router exclusivo del servidor temporal de pruebas visuales.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'mysql'
    || ! preg_match('/\Asistema_cabanas_pruebas_[a-f0-9]{16}\z/', (string) getenv('DB_DATABASE'))) {
    http_response_code(503);
    exit('Servidor de pruebas sin destino aislado.');
}
$publico = realpath(__DIR__.'/../../public');
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$archivo = realpath($publico.'/'.urldecode($ruta ?? '/'));
if ($archivo !== false && str_starts_with($archivo, $publico.DIRECTORY_SEPARATOR) && is_file($archivo)) {
    return false;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== getenv('DB_DATABASE')) {
    http_response_code(503);
    exit('Destino no valido.');
}
// Usar el build sin alterar public/hot ni el Vite abierto por el usuario.
$app->make(Vite::class)->useHotFile(__DIR__.'/sin_hot_interfaz');
$app->handleRequest(Request::capture());
