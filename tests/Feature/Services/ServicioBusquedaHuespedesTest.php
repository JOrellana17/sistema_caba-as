<?php

use App\Models\Huesped;
use App\Models\Usuario;
use App\Services\ServicioBusquedaHuespedes;
use App\Services\ServicioSeleccionHuesped;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

test('encuentra nombres sin tildes telefono sin separadores e identidad con ceros', function (string $termino) {
    $huesped = Huesped::factory()->create(['nombre' => 'María José Núñez', 'telefono' => '+504 (0012)-3456', 'numero_identidad' => '00-AB-0123']);
    $resultado = app(ServicioBusquedaHuespedes::class)->buscar($termino, true);
    expect(array_column($resultado['resultados'], 'id'))->toBe([$huesped->id]);
    expect($resultado['resultados'][0]['telefono'])->toBe('+504 (0012)-3456')
        ->and($resultado['resultados'][0]['numero_identidad'])->toBe('00-AB-0123');
})->with(['maria jose', 'NUÑEZ', 'josé', '50400123456', '(0012)-3456', '00ab0123', '00-AB']);

test('no consulta huespedes para terminos vacios o insuficientes', function (string $termino) {
    $consultas = [];
    DB::listen(function ($consulta) use (&$consultas) {
        $consultas[] = $consulta->sql;
    });
    expect(app(ServicioBusquedaHuespedes::class)->buscar($termino, true))->toBe(['resultados' => [], 'hay_mas' => false]);
    expect($consultas)->toBe([]);
})->with(['', '  ', '-_()', 'ab', '0-0', 'á']);

test('prioriza identidad y telefono exactos sin confundir prefijos internacionales', function () {
    $creador = Usuario::factory()->create();
    $parcial = Huesped::factory()->create(['nombre' => 'A parcial', 'telefono' => '+504 001234', 'creado_por' => $creador->id]);
    $telefono = Huesped::factory()->create(['nombre' => 'Z teléfono', 'telefono' => '001-234', 'creado_por' => $creador->id]);
    $identidad = Huesped::factory()->create(['nombre' => 'Y identidad', 'numero_identidad' => '001234', 'creado_por' => $creador->id]);
    $servicio = app(ServicioBusquedaHuespedes::class);
    expect(array_column($servicio->buscar('001234', true)['resultados'], 'id'))->toBe([$identidad->id, $telefono->id, $parcial->id]);
    expect(array_column($servicio->buscar('504001234', true)['resultados'], 'id'))->toBe([$parcial->id]);
});

test('permite coincidencias compartidas limita resultados y excluye eliminados', function () {
    $creador = Usuario::factory()->create();
    Huesped::factory()->count(22)->create(['nombre' => 'Cliente compartido', 'telefono' => '0001234', 'creado_por' => $creador->id]);
    Huesped::factory()->create(['nombre' => 'Cliente eliminado', 'deleted_at' => now(), 'creado_por' => $creador->id]);
    $resultado = app(ServicioBusquedaHuespedes::class)->buscar('cliente', true);
    expect($resultado['resultados'])->toHaveCount(20)->and($resultado['hay_mas'])->toBeTrue();
    expect(array_unique(array_column($resultado['resultados'], 'id')))->toHaveCount(20);
    expect(app(ServicioBusquedaHuespedes::class)->buscar('eliminado', true)['resultados'])->toBe([]);
});

test('trata comodines como texto literal y no expone notas ni columnas auxiliares', function () {
    $huesped = Huesped::factory()->create(['nombre' => 'Ana%_!', 'notas' => 'Privado', 'notas_incidentes' => 'Incidente privado', 'numero_identidad' => '000999']);
    Huesped::factory()->create(['nombre' => 'Ana ordinaria']);
    $servicio = app(ServicioBusquedaHuespedes::class);
    $resultado = $servicio->buscar('Ana%_!', false);
    expect(array_column($resultado['resultados'], 'id'))->toBe([$huesped->id]);
    expect($resultado['resultados'][0])->toHaveKeys(['id', 'nombre', 'telefono', 'activo', 'lista_negra', 'numero_identidad', 'identidad_visible'])
        ->not->toHaveKeys(['notas', 'notas_incidentes', 'identidad_busqueda', 'creado_por']);
    expect($resultado['resultados'][0]['numero_identidad'])->toBeNull()
        ->and($resultado['resultados'][0]['identidad_visible'])->toBeFalse();
    expect($servicio->buscar("' OR 1=1 --", true)['resultados'])->toBe([]);
});

test('revalida seleccion y admite restringidos sin modificar sus condiciones ni duplicar', function () {
    $huesped = Huesped::factory()->create(['activo' => false, 'lista_negra' => true]);
    $seleccion = app(ServicioSeleccionHuesped::class)->validar($huesped->id);
    expect($seleccion->id)->toBe($huesped->id)->and($seleccion->activo)->toBeFalse()->and($seleccion->lista_negra)->toBeTrue();
    $this->assertDatabaseCount('huespedes', 1);
    $huesped->delete();
    expect(fn () => app(ServicioSeleccionHuesped::class)->validar($huesped->id))->toThrow(ValidationException::class);
    expect(fn () => app(ServicioSeleccionHuesped::class)->validar(99999))->toThrow(ValidationException::class);
});

test('conserva letras distintas en documentos mientras ignora tildes solo en nombres', function () {
    $conTilde = Huesped::factory()->create(['nombre' => 'María documento uno', 'telefono' => '800000000', 'numero_identidad' => 'DOC-É001']);
    $sinTilde = Huesped::factory()->create(['nombre' => 'Mario documento dos', 'telefono' => '900000000', 'numero_identidad' => 'DOC-E001']);
    $buscador = app(ServicioBusquedaHuespedes::class);

    expect(array_column($buscador->buscar('doc-é001', true)['resultados'], 'id'))->toBe([$conTilde->id]);
    expect(array_column($buscador->buscar('doc-e001', true)['resultados'], 'id'))->toBe([$sinTilde->id]);
    expect(array_column($buscador->buscar('maria', true)['resultados'], 'id'))->toBe([$conTilde->id]);
});
