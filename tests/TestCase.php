<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Support\BaseDatosMySql;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== BaseDatosMySql::PREFIJO
            || ! empty(config('database.connections.mysql.url'))) {
            throw new RuntimeException('Las pruebas requieren MySQL y el prefijo de pruebas separado del sistema.');
        }

        Http::preventStrayRequests();
        $base = BaseDatosMySql::principal(config('database.connections.mysql'));
        $base->conectar();
        if (! $base->migrada) {
            $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
            $base->migrada = true;
        }

        $conexion = DB::connection('mysql');
        $conexion->beginTransaction();
        $this->beforeApplicationDestroyed(function () use ($conexion) {
            $conexion->rollBack(0);
            $conexion->disconnect();
        });
    }
}
