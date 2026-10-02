<?php

use App\Models\Cabana;
use App\Models\RegistroAuditoria;
use App\Models\Usuario;
use App\Services\ServicioAuditoria;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToWriteFile;

function datosCabanaDePrueba(array $cambios = []): array
{
    return array_replace([
        'nombre' => 'Cabaña del lago',
        'descripcion' => 'Descripción de prueba.',
        'capacidad_base' => 2,
        'capacidad_maxima' => 5,
        'precio_base' => '123.45',
        'precio_persona_adicional' => '12.30',
        'estado' => 'disponible',
        'activa' => '1',
        'notas' => 'Nota interna de prueba.',
    ], $cambios);
}

test('crea y edita cabanas conservando identidad creador y tarifas exactas con auditoria', function () {
    $administrador = crearAdministradorDePrueba();
    $this->actingAs($administrador)->post(route('cabanas.guardar'), datosCabanaDePrueba())
        ->assertRedirectToRoute('cabanas.listar')->assertSessionHasNoErrors();

    $cabana = Cabana::sole();
    expect($cabana->creado_por)->toBe($administrador->id)
        ->and($cabana->precio_base)->toBe('123.45')
        ->and($cabana->precio_persona_adicional)->toBe('12.30');
    $slug = $cabana->slug;
    $datos = Arr::except(datosCabanaDePrueba(['nombre' => 'Nombre actualizado', 'precio_base' => '140.10']), ['estado', 'activa']);
    $this->put(route('cabanas.actualizar', $cabana), $datos)->assertRedirect()->assertSessionHasNoErrors();

    expect($cabana->fresh()->slug)->toBe($slug)
        ->and($cabana->fresh()->precio_base)->toBe('140.10')
        ->and($cabana->fresh()->creador->id)->toBe($administrador->id);
    $registro = RegistroAuditoria::where('modulo', 'cabanas')->where('accion', 'actualizado')->sole();
    expect($registro->usuario_id)->toBe($administrador->id)
        ->and($registro->valores_anteriores['precio_base'])->toBe('123.45')
        ->and($registro->valores_nuevos['precio_base'])->toBe('140.10')
        ->and($registro->toJson())->not->toContain('Nota interna de prueba.', 'Descripción de prueba.');

    $this->get(route('cabanas.listar'))->assertOk()->assertSee('Nombre actualizado');
    $this->get(route('cabanas.ver', $cabana))->assertOk()->assertSee('140.10')->assertSee('Nota interna de prueba.');
    $this->get(route('cabanas.editar', $cabana))->assertOk()->assertSee('Nombre actualizado');
    $this->get(route('cabanas.crear'))->assertOk()->assertSee('Nueva cabaña');
});

test('genera slugs distintos sin cambiar nombres proporcionados ni inventar tarifas', function () {
    $administrador = crearAdministradorDePrueba();
    $this->actingAs($administrador)->post(route('cabanas.guardar'), datosCabanaDePrueba())->assertSessionHasNoErrors();
    $this->post(route('cabanas.guardar'), datosCabanaDePrueba())->assertSessionHasNoErrors();

    expect(Cabana::pluck('slug')->unique())->toHaveCount(2);
    expect(Cabana::pluck('nombre')->unique()->all())->toBe(['Cabaña del lago']);
    $this->post(route('cabanas.guardar'), Arr::except(datosCabanaDePrueba(), ['precio_base', 'precio_persona_adicional']))
        ->assertSessionHasErrors(['precio_base', 'precio_persona_adicional']);
    $this->assertDatabaseCount('cabanas', 2);
});

test('rechaza datos de cabana invalidos sin escribir registros ni auditoria', function (string $campo, mixed $valor) {
    $administrador = crearAdministradorDePrueba();
    $this->actingAs($administrador)->post(route('cabanas.guardar'), datosCabanaDePrueba([$campo => $valor]))
        ->assertSessionHasErrors($campo);
    $this->assertDatabaseCount('cabanas', 0);
    $this->assertDatabaseCount('registros_auditoria', 0);
})->with([
    'nombre vacío' => ['nombre', ''],
    'nombre malformado' => ['nombre', ['nombre']],
    'capacidad cero' => ['capacidad_base', 0],
    'capacidad negativa' => ['capacidad_base', -1],
    'capacidad fraccionaria' => ['capacidad_base', 1.5],
    'máxima menor que base' => ['capacidad_maxima', 1],
    'capacidad fuera de almacenamiento' => ['capacidad_maxima', 65536],
    'precio negativo' => ['precio_base', '-1.00'],
    'precio con redondeo' => ['precio_base', '1.234'],
    'precio con exponente' => ['precio_base', '1e2'],
    'precio fuera de almacenamiento' => ['precio_base', '10000000000.00'],
    'precio malformado' => ['precio_base', ['1.00']],
    'adicional sin precio' => ['precio_persona_adicional', null],
    'adicional negativo' => ['precio_persona_adicional', '-1'],
    'estado ajeno' => ['estado', 'limpieza'],
    'activación malformada' => ['activa', 'sí'],
    'autor inyectado' => ['creado_por', 123],
    'ruta de imagen inyectada' => ['imagen_principal', '../secreto'],
    'eliminación inyectada' => ['deleted_at', '2026-09-30'],
    'slug inyectado' => ['slug', 'externo'],
]);

test('permite precios cero explicitos y capacidades iguales sin redondear', function () {
    $this->actingAs(crearAdministradorDePrueba())->post(route('cabanas.guardar'), datosCabanaDePrueba([
        'precio_base' => '0', 'precio_persona_adicional' => '0.00', 'capacidad_maxima' => 2,
    ]))->assertSessionHasNoErrors();
    expect(Cabana::sole()->precio_base)->toBe('0.00')
        ->and(Cabana::sole()->precio_persona_adicional)->toBe('0.00');
});

test('el personal asigna cada estado operativo manualmente y activa o desactiva', function (string $estado) {
    $administrador = crearAdministradorDePrueba();
    $operador = Usuario::factory()->create();
    otorgarPermisosDePrueba($operador, ['cabanas.cambiar_estado']);
    $cabana = Cabana::factory()->create(['creado_por' => $administrador->id]);

    $this->actingAs($operador)->put(route('cabanas.estado', $cabana), ['estado' => $estado, 'activa' => false, 'precio_base' => '999.00'])
        ->assertRedirectToRoute('inicio')->assertSessionHasNoErrors();
    expect($cabana->fresh()->estado)->toBe($estado)
        ->and($cabana->fresh()->activa)->toBeFalse()
        ->and($cabana->fresh()->precio_base)->toBe('123.45');
    $registro = RegistroAuditoria::where('accion', 'estado_actualizado')->sole();
    expect($registro->usuario_id)->toBe($operador->id)
        ->and($registro->valores_anteriores['activa'])->toBeTrue()
        ->and($registro->valores_nuevos['estado'])->toBe($estado);

    $this->put(route('cabanas.estado', $cabana), ['estado' => 'disponible', 'activa' => true])->assertSessionHasNoErrors();
    expect($cabana->fresh()->activa)->toBeTrue()->and($cabana->fresh()->estado)->toBe('disponible');
})->with(['disponible', 'ocupada', 'reservada', 'mantenimiento', 'bloqueada']);

test('editar datos no permite cambiar estado ni activacion aunque se tenga permiso de edicion', function (array $cambio) {
    crearAdministradorDePrueba();
    $operador = Usuario::factory()->create();
    otorgarPermisosDePrueba($operador, ['cabanas.editar']);
    $cabana = Cabana::factory()->create();
    $datos = array_merge(Arr::except(datosCabanaDePrueba(), ['estado', 'activa']), $cambio);

    $this->actingAs($operador)->put(route('cabanas.actualizar', $cabana), $datos)->assertSessionHasErrors(array_keys($cambio));
    expect($cabana->fresh()->activa)->toBeTrue()->and($cabana->fresh()->estado)->toBe('disponible');
    $this->assertDatabaseCount('registros_auditoria', 0);
})->with([[['estado' => 'bloqueada']], [['activa' => false]]]);

test('rechaza un cambio operativo malformado sin alterar la cabana', function () {
    $cabana = Cabana::factory()->create();
    $this->actingAs(crearAdministradorDePrueba())->put(route('cabanas.estado', $cabana), ['estado' => ['reservada'], 'activa' => 'quizás'])
        ->assertSessionHasErrors(['estado', 'activa']);
    expect($cabana->fresh()->estado)->toBe('disponible')->and($cabana->fresh()->activa)->toBeTrue();
});

test('todas las operaciones de cabanas requieren autenticacion y su permiso especifico', function (string $metodo, string $ruta, bool $identificador) {
    $cabana = Cabana::factory()->create();
    $url = route($ruta, $identificador ? $cabana : []);
    $this->{$metodo}($url)->assertRedirectToRoute('sesion.mostrar');
    $this->actingAs(Usuario::factory()->create())->{$metodo}($url)->assertForbidden();
    $this->assertDatabaseCount('registros_auditoria', 0);
})->with([
    ['get', 'cabanas.listar', false],
    ['get', 'cabanas.crear', false],
    ['post', 'cabanas.guardar', false],
    ['get', 'cabanas.ver', true],
    ['get', 'cabanas.editar', true],
    ['put', 'cabanas.actualizar', true],
    ['put', 'cabanas.estado', true],
    ['delete', 'cabanas.eliminar', true],
    ['post', 'cabanas.restaurar', true],
    ['get', 'cabanas.imagen', true],
]);

test('una cuenta de consulta no modifica el catalogo ni accede a eliminadas', function () {
    crearAdministradorDePrueba();
    $lector = Usuario::factory()->create();
    otorgarPermisosDePrueba($lector, ['cabanas.ver']);
    $cabana = Cabana::factory()->create();

    $this->actingAs($lector)->get(route('cabanas.ver', $cabana))->assertOk()
        ->assertDontSee('Guardar estado y activación')->assertDontSee('Editar datos')->assertDontSee('Eliminar cabaña');
    $this->get(route('cabanas.listar'))->assertOk()->assertDontSee('Nueva cabaña')->assertDontSee('Ver eliminadas');
    $this->put(route('cabanas.actualizar', $cabana), datosCabanaDePrueba())->assertForbidden();
    $this->put(route('cabanas.estado', $cabana), ['estado' => 'reservada', 'activa' => false])->assertForbidden();
    $this->delete(route('cabanas.eliminar', $cabana))->assertForbidden();
    $this->get(route('cabanas.listar', ['eliminadas' => 1]))->assertForbidden();
    $this->assertDatabaseCount('registros_auditoria', 0);
});

test('alta y edicion con permisos directos funcionan sin conceder acceso de consulta', function () {
    crearAdministradorDePrueba();
    $operador = Usuario::factory()->create();
    otorgarPermisosDePrueba($operador, ['cabanas.crear', 'cabanas.editar']);

    $this->actingAs($operador)->get(route('cabanas.crear'))->assertOk();
    $this->post(route('cabanas.guardar'), datosCabanaDePrueba())->assertRedirectToRoute('inicio')->assertSessionHasNoErrors();
    $cabana = Cabana::sole();
    $this->get(route('cabanas.editar', $cabana))->assertOk();
    $this->put(route('cabanas.actualizar', $cabana), Arr::except(datosCabanaDePrueba(['nombre' => 'Editada']), ['estado', 'activa']))
        ->assertRedirectToRoute('inicio')->assertSessionHasNoErrors();
    $this->get(route('cabanas.ver', $cabana))->assertForbidden();
    expect($cabana->fresh()->nombre)->toBe('Editada');
});

test('elimina logicamente y restaura la misma cabana conservando datos e imagen', function () {
    $administrador = crearAdministradorDePrueba();
    Storage::fake('cabanas');
    $ruta = UploadedFile::fake()->image('foto.jpg')->store('imagenes', 'cabanas');
    $cabana = Cabana::factory()->create(['imagen_principal' => $ruta, 'estado' => 'mantenimiento', 'activa' => false]);
    $datos = $cabana->fresh()->toArray();

    $this->actingAs($administrador)->delete(route('cabanas.eliminar', $cabana))->assertRedirect()->assertSessionHasNoErrors();
    $this->assertSoftDeleted($cabana);
    Storage::disk('cabanas')->assertExists($ruta);
    $this->get(route('cabanas.listar'))->assertDontSee($cabana->nombre);
    $this->get(route('cabanas.listar', ['eliminadas' => 1]))->assertOk()->assertSee($cabana->nombre)->assertSee('Restaurar');
    $this->get(route('cabanas.ver', $cabana))->assertNotFound();
    $this->get(route('cabanas.imagen', $cabana))->assertNotFound();
    $this->put(route('cabanas.actualizar', $cabana), Arr::except(datosCabanaDePrueba(), ['estado', 'activa']))->assertNotFound();

    $this->post(route('cabanas.restaurar', $cabana))->assertRedirect()->assertSessionHasNoErrors();
    $restaurada = $cabana->fresh();
    expect($restaurada->trashed())->toBeFalse()
        ->and(Arr::except($restaurada->toArray(), ['updated_at']))->toBe(Arr::except($datos, ['updated_at']));
    $this->assertDatabaseCount('cabanas', 1);
    expect(RegistroAuditoria::pluck('accion')->all())->toBe(['eliminado', 'restaurado']);
    $this->post(route('cabanas.restaurar', $cabana))->assertNotFound();
});

test('busca texto literal filtra estado y actividad y pagina el catalogo', function () {
    $administrador = crearAdministradorDePrueba();
    Cabana::factory()->count(21)->create(['nombre' => 'Unidad de prueba', 'creado_por' => $administrador->id]);
    $buscada = Cabana::factory()->create(['nombre' => 'Especial 50%_!', 'estado' => 'bloqueada', 'activa' => false]);

    $this->actingAs($administrador)->get(route('cabanas.listar', ['buscar' => '%_!', 'estado' => 'bloqueada', 'activa' => 0]))
        ->assertOk()->assertViewHas('cabanas', fn ($cabanas) => $cabanas->total() === 1 && $cabanas->first()->id === $buscada->id);
    $this->get(route('cabanas.listar', ['buscar' => 'unidad']))
        ->assertOk()->assertViewHas('cabanas', fn ($cabanas) => $cabanas->total() === 21 && $cabanas->count() === 20);
    $this->get(route('cabanas.listar', ['buscar' => 'unidad', 'page' => 2]))
        ->assertOk()->assertViewHas('cabanas', fn ($cabanas) => $cabanas->count() === 1);
    $this->get(route('cabanas.listar', ['buscar' => "' OR 1=1 --"]))->assertOk()
        ->assertViewHas('cabanas', fn ($cabanas) => $cabanas->total() === 0);
    $this->get(route('cabanas.listar', ['buscar' => ['malformado'], 'estado' => 'otro']))
        ->assertSessionHasErrors(['buscar', 'estado']);
});

test('escapa contenido de cabanas en listado detalle y formulario', function () {
    $cabana = Cabana::factory()->create([
        'nombre' => '<script>alert(1)</script>',
        'descripcion' => '<img src=x onerror=alert(1)>',
        'notas' => '</textarea><script>alert(2)</script>',
    ]);
    $this->actingAs(crearAdministradorDePrueba())->get(route('cabanas.listar'))
        ->assertSee($cabana->nombre)->assertDontSee($cabana->nombre, false);
    foreach (['cabanas.ver', 'cabanas.editar'] as $ruta) {
        $this->get(route($ruta, $cabana))->assertOk()->assertSee($cabana->notas)
            ->assertDontSee($cabana->notas, false)->assertDontSee($cabana->descripcion, false);
    }
});

test('guarda reemplaza y sirve imagenes privadas sin perder la imagen al editar solo texto', function () {
    Storage::fake('cabanas');
    $administrador = crearAdministradorDePrueba();
    $this->actingAs($administrador)->post(route('cabanas.guardar'), datosCabanaDePrueba([
        'imagen' => UploadedFile::fake()->image('nombre-original.jpg', 100, 80),
    ]))->assertSessionHasNoErrors();
    $cabana = Cabana::sole();
    $rutaAnterior = $cabana->imagen_principal;
    expect($rutaAnterior)->not->toContain('nombre-original');
    Storage::disk('cabanas')->assertExists($rutaAnterior);
    $this->get(route('cabanas.imagen', $cabana))->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->put(route('cabanas.actualizar', $cabana), Arr::except(datosCabanaDePrueba(), ['estado', 'activa']))->assertSessionHasNoErrors();
    expect($cabana->fresh()->imagen_principal)->toBe($rutaAnterior);
    $this->put(route('cabanas.actualizar', $cabana), Arr::except(datosCabanaDePrueba([
        'imagen' => UploadedFile::fake()->image('otra.png'),
    ]), ['estado', 'activa']))->assertSessionHasNoErrors();
    $nuevaRuta = $cabana->fresh()->imagen_principal;
    expect($nuevaRuta)->not->toBe($rutaAnterior);
    Storage::disk('cabanas')->assertExists([$rutaAnterior, $nuevaRuta]);
    $this->get(route('cabanas.imagen', $cabana))->assertOk()->assertHeader('Content-Type', 'image/png');
    expect(RegistroAuditoria::where('accion', 'actualizado')->latest('id')->first()->valores_anteriores['imagen_principal'])->toBe($rutaAnterior);
});

test('rechaza imagenes falsas ejecutables svg sobredimensionadas y demasiado grandes', function (string $tipo) {
    Storage::fake('cabanas');
    $imagen = match ($tipo) {
        'falsa' => UploadedFile::fake()->createWithContent('foto.jpg', '<?php echo "ejecutable";'),
        'extension' => UploadedFile::fake()->image('foto.php')->mimeType('image/jpeg'),
        'svg' => UploadedFile::fake()->createWithContent('foto.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        'dimensiones' => UploadedFile::fake()->image('foto.jpg', 3001, 1),
        'peso' => UploadedFile::fake()->image('foto.jpg')->size(2049),
    };
    $this->actingAs(crearAdministradorDePrueba())->post(route('cabanas.guardar'), datosCabanaDePrueba(['imagen' => $imagen]))
        ->assertSessionHasErrors('imagen');
    $this->assertDatabaseCount('cabanas', 0);
    expect(Storage::disk('cabanas')->allFiles())->toBe([]);
})->with(['falsa', 'extension', 'svg', 'dimensiones', 'peso']);

test('revierte datos e imagen nueva si falla la auditoria conservando la imagen anterior', function () {
    Storage::fake('cabanas');
    $rutaAnterior = UploadedFile::fake()->image('anterior.jpg')->store('imagenes', 'cabanas');
    $cabana = Cabana::factory()->create(['imagen_principal' => $rutaAnterior]);
    $nombreAnterior = $cabana->nombre;
    $this->mock(ServicioAuditoria::class)->shouldReceive('registrarCambio')->once()->andThrow(new RuntimeException('Fallo de auditoría de prueba'));
    $this->withoutExceptionHandling()->actingAs(crearAdministradorDePrueba());

    expect(fn () => $this->put(route('cabanas.actualizar', $cabana), Arr::except(datosCabanaDePrueba([
        'imagen' => UploadedFile::fake()->image('nueva.png'),
    ]), ['estado', 'activa'])))->toThrow(RuntimeException::class, 'Fallo de auditoría de prueba');

    expect($cabana->fresh()->nombre)->toBe($nombreAnterior)
        ->and($cabana->fresh()->imagen_principal)->toBe($rutaAnterior)
        ->and(Storage::disk('cabanas')->allFiles())->toBe([$rutaAnterior]);
    $this->assertDatabaseCount('registros_auditoria', 0);
});

test('conserva datos de formulario ante errores de validacion', function () {
    $this->actingAs(crearAdministradorDePrueba())->from(route('cabanas.crear'))
        ->post(route('cabanas.guardar'), datosCabanaDePrueba(['capacidad_maxima' => 1]))->assertRedirectToRoute('cabanas.crear');
    $this->get(route('cabanas.crear'))->assertOk()->assertSee('Cabaña del lago')
        ->assertSee('Nota interna de prueba.')->assertSee('123.45')->assertSee('capacidad máxima debe ser igual o mayor');
});

test('muestra un error recuperable sin guardar cabana cuando el almacenamiento falla', function () {
    $administrador = crearAdministradorDePrueba();
    Storage::shouldReceive('disk')->with('cabanas')->once()
        ->andThrow(UnableToWriteFile::atLocation('imagenes/prueba.jpg'));

    $this->actingAs($administrador)->post(route('cabanas.guardar'), datosCabanaDePrueba([
        'imagen' => UploadedFile::fake()->image('foto.jpg'),
    ]))->assertSessionHasErrors(['imagen' => 'No se pudo guardar la imagen. Inténtalo de nuevo.']);

    $this->assertDatabaseCount('cabanas', 0);
    $this->assertDatabaseCount('registros_auditoria', 0);
});

test('sirve webp y responde sin exponer rutas cuando falta la imagen', function () {
    Storage::fake('cabanas');
    $this->actingAs(crearAdministradorDePrueba())->post(route('cabanas.guardar'), datosCabanaDePrueba([
        'imagen' => UploadedFile::fake()->image('foto.webp'),
    ]))->assertSessionHasNoErrors();
    $cabana = Cabana::sole();
    $this->get(route('cabanas.imagen', $cabana))->assertOk()->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('Cache-Control', 'no-store, private');
    Storage::disk('cabanas')->delete($cabana->imagen_principal);
    $this->get(route('cabanas.imagen', $cabana))->assertNotFound()->assertDontSee($cabana->imagen_principal);
});
