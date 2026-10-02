<?php

use App\Models\Cabana;
use App\Models\Huesped;
use App\Models\Reservacion;
use Illuminate\Support\Facades\DB;
use Tests\Support\BaseDatosMySql;

test('dos procesos MySQL que confirman la misma cabana solo consiguen una confirmacion', function () {
    $base = BaseDatosMySql::crear(config('database.connections.mysql'));
    $base->conectar('prueba_concurrencia');
    DB::setDefaultConnection('prueba_concurrencia');
    $procesos = [];
    $leerLinea = function ($canal): string {
        $limite = hrtime(true) + 15_000_000_000;
        $salida = '';
        do {
            $salida .= (string) fgets($canal);
            if (str_contains($salida, "\n")) {
                return trim($salida);
            }
            if (feof($canal)) {
                throw new RuntimeException('El proceso de prueba termino antes de responder.');
            }
            usleep(5000);
        } while (hrtime(true) < $limite);

        throw new RuntimeException('El proceso de prueba no respondio dentro del limite.');
    };

    try {
        $this->artisan('migrate', ['--database' => 'prueba_concurrencia', '--force' => true])->assertExitCode(0);
        $usuario = crearAdministradorDePrueba();
        $cabana = Cabana::factory()->create();
        $huesped = Huesped::factory()->create();
        $reservaciones = Reservacion::factory()->count(2)->for($cabana, 'cabana')->for($huesped, 'huesped')->create(['estado' => 'pendiente']);
        $configuracion = config('database.connections.prueba_concurrencia');
        $entorno = array_merge(getenv(), [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '',
            'DB_DATABASE' => $base->nombre, 'CEC_BASE_PRUEBA' => $base->nombre,
            'DB_HOST' => $configuracion['host'], 'DB_PORT' => (string) $configuracion['port'],
            'DB_USERNAME' => $configuracion['username'], 'DB_PASSWORD' => $configuracion['password'],
            'DB_SOCKET' => $configuracion['unix_socket'],
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        ]);
        foreach ($reservaciones as $reservacion) {
            $proceso = proc_open([PHP_BINARY, base_path('tests/Support/confirmar_reservacion.php'), $base->nombre, (string) $usuario->id, (string) $reservacion->id], [
                0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
            ], $canales, base_path(), $entorno, ['bypass_shell' => true]);
            $this->assertIsResource($proceso);
            stream_set_blocking($canales[1], false);
            $procesos[] = [$proceso, $canales];
        }
        foreach ($procesos as [$proceso, $canales]) {
            expect($leerLinea($canales[1]))->toBe('listo');
        }
        foreach ($procesos as [$proceso, $canales]) {
            fwrite($canales[0], "continuar\n");
            fclose($canales[0]);
        }
        $resultados = [];
        foreach ($procesos as [$proceso, $canales]) {
            $resultados[] = $leerLinea($canales[1]);
            $errores = stream_get_contents($canales[2]);
            fclose($canales[1]);
            fclose($canales[2]);
            expect(proc_close($proceso))->toBe(0);
            expect($errores)->toBe('');
        }
        $procesos = [];
        sort($resultados);
        expect($resultados)->toBe(['confirmada', 'rechazada']);
        expect(Reservacion::where('estado', 'confirmada')->count())->toBe(1);
        $this->assertDatabaseCount('registros_auditoria', 1);
    } finally {
        foreach ($procesos as [$proceso, $canales]) {
            if (is_resource($proceso)) {
                proc_terminate($proceso);
            }
            foreach ($canales as $canal) {
                if (is_resource($canal)) {
                    fclose($canal);
                }
            }
            if (is_resource($proceso)) {
                proc_close($proceso);
            }
        }
        DB::setDefaultConnection('mysql');
        DB::purge('prueba_concurrencia');
        $base->eliminar();
    }
});
