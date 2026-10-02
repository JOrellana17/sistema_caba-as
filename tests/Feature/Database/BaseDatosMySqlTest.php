<?php

use Tests\Support\BaseDatosMySql;

test('rechaza destinos ajenos antes de crear o modificar una base de pruebas', function (array $cambios) {
    $configuracion = array_replace(config('database.connections.mysql'), $cambios);

    expect(fn () => BaseDatosMySql::crear($configuracion))->toThrow(RuntimeException::class, 'Las pruebas requieren MySQL');
})->with([
    'base operativa' => [['database' => 'sistema_cabanas']],
    'otra base existente' => [['database' => 'otra_base']],
    'prefijo sin propiedad' => [['database' => 'sistema_cabanas_pruebas_0000000000000000']],
    'url que evita el destino' => [['url' => 'mysql://example.test/base_ajena']],
    'otro motor' => [['driver' => 'pgsql']],
]);

test('rechaza crear esquemas fuera del entorno testing', function () {
    app()->detectEnvironment(fn () => 'local');

    try {
        expect(fn () => BaseDatosMySql::crear(config('database.connections.mysql')))->toThrow(RuntimeException::class, 'Las pruebas requieren MySQL');
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }
});
