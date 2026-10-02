<?php

use App\Models\Reservacion;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\PermisosSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\BaseDatosMySql;

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing') {
    exit("Ejecuta este comando con APP_ENV=testing y DB_DATABASE=sistema_cabanas_pruebas.\n");
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$base = null;
$servidor = null;
$navegador = null;

try {
    $base = BaseDatosMySql::crear(config('database.connections.mysql'));
    $base->conectar();
    if (Artisan::call('migrate', ['--force' => true]) !== 0) {
        throw new RuntimeException('No se completaron las migraciones de pruebas.');
    }
    (new PermisosSeeder)->run();
    $clave = bin2hex(random_bytes(18));
    $usuario = Usuario::factory()->create(['usuario' => 'admin_interfaz', 'nombre' => 'Administrador de prueba', 'contrasena' => $clave]);
    $usuario->roles()->attach(Rol::where('codigo', 'administrador')->sole());
    $socket = stream_socket_server('tcp://127.0.0.1:0', $numero, $mensaje);
    if (! $socket) {
        throw new RuntimeException('No se pudo reservar puerto local.');
    }
    $puerto = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $url = 'http://127.0.0.1:'.$puerto;
    $directorio = storage_path('app/private/pruebas-interfaz/'.substr($base->nombre, -16));
    if (! mkdir($directorio, 0700, true) && ! is_dir($directorio)) {
        throw new RuntimeException('No se pudo crear el directorio de evidencias.');
    }
    $configuracion = config('database.connections.mysql');
    $entorno = array_merge(getenv(), [
        'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_URL' => $url,
        'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $base->nombre, 'DB_URL' => '',
        'DB_HOST' => $configuracion['host'], 'DB_PORT' => (string) $configuracion['port'],
        'DB_USERNAME' => $configuracion['username'], 'DB_PASSWORD' => $configuracion['password'],
        'DB_SOCKET' => $configuracion['unix_socket'],
        'SESSION_DRIVER' => 'database', 'SESSION_COOKIE' => 'cec_interfaz_prueba', 'SESSION_DOMAIN' => '', 'SESSION_SECURE_COOKIE' => 'false',
        'CACHE_STORE' => 'database', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
    ]);
    $servidor = proc_open([PHP_BINARY, '-S', '127.0.0.1:'.$puerto, '-t', public_path(), base_path('tests/Support/servidor_interfaz.php')], [
        0 => ['pipe', 'r'], 1 => ['file', $directorio.'/servidor.log', 'a'], 2 => ['file', $directorio.'/servidor.log', 'a'],
    ], $canalesServidor, base_path(), $entorno, ['bypass_shell' => true]);
    if (! is_resource($servidor)) {
        throw new RuntimeException('No se pudo iniciar el servidor de pruebas.');
    }
    fclose($canalesServidor[0]);
    $navegador = proc_open(['node', base_path('tests/browser/interfaz-cec.mjs')], [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $canales, base_path(), null, ['bypass_shell' => true]);
    if (! is_resource($navegador)) {
        throw new RuntimeException('No se pudo iniciar la prueba de navegador.');
    }
    // Credenciales ficticias aleatorias por stdin, nunca por argumentos o archivos.
    fwrite($canales[0], json_encode(['url' => $url, 'usuario' => $usuario->usuario, 'clave' => $clave, 'directorio' => $directorio]));
    fclose($canales[0]);
    $salida = stream_get_contents($canales[1]);
    $errores = stream_get_contents($canales[2]);
    fclose($canales[1]);
    fclose($canales[2]);
    $codigo = proc_close($navegador);
    $navegador = null;
    echo $salida;
    if ($errores !== '') {
        echo $errores;
    }
    if ($codigo !== 0) {
        throw new RuntimeException('La prueba de navegador fallo.');
    }
    $reservacion = Reservacion::orderBy('id')->firstOrFail();
    if ($reservacion->estado !== 'cancelada' || $reservacion->subtotal !== '271.50'
        || DB::table('huespedes')->count() !== 2 || DB::table('cabanas')->count() !== 1) {
        throw new RuntimeException('Los datos guardados no coinciden con la prueba de interfaz.');
    }
    $segunda = Reservacion::orderByDesc('id')->firstOrFail();
    if (Reservacion::count() !== 2 || $segunda->huesped_id !== 2 || $segunda->subtotal !== '135.75'
        || $segunda->estado !== 'por_confirmar' || DB::table('huespedes')->where('id', 2)->value('numero_identidad') !== null) {
        throw new RuntimeException('El alta rapida no conservo el mismo huesped sin identidad.');
    }
    echo json_encode(['persistencia' => 'verificada', 'estado_final' => $reservacion->estado, 'subtotal' => $reservacion->subtotal, 'huespedes' => 2, 'reservaciones' => 2, 'cabanas' => 1, 'base_aislada' => true], JSON_PRETTY_PRINT)."\n";
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).": prueba de interfaz incompleta.\n");
    $codigo = 1;
} finally {
    foreach ([$navegador, $servidor] as $proceso) {
        if (is_resource($proceso)) {
            proc_terminate($proceso);
            proc_close($proceso);
        }
    }
    DB::purge('mysql');
    $base?->eliminar();
}
exit($codigo ?? 0);
