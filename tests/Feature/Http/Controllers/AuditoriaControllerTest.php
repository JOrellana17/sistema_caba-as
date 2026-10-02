<?php

use App\Models\Permiso;
use App\Models\RegistroAuditoria;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ServicioAuditoria;
use Illuminate\Http\Request;

test('filtra la auditoria por modulo accion actor y fechas', function () {
    $administrador = crearAdministradorDePrueba();
    $registro = RegistroAuditoria::create([
        'usuario_id' => $administrador->id, 'modulo' => 'usuarios', 'accion' => 'prueba_filtrada',
    ]);
    $registro->created_at = '2026-09-20 12:00:00';
    $registro->save();
    RegistroAuditoria::create(['modulo' => 'roles', 'accion' => 'registro_excluido']);

    $this->actingAs($administrador)->get(route('auditoria.listar', [
        'modulo' => 'usuarios', 'accion' => 'prueba_filtrada', 'usuario_id' => $administrador->id,
        'desde' => '2026-09-20', 'hasta' => '2026-09-20',
    ]))->assertSee('prueba_filtrada')->assertDontSee('registro_excluido');
    $this->get(route('auditoria.listar', ['hasta' => '2026-09-20']))->assertSee('prueba_filtrada');
    $this->get(route('auditoria.listar', ['desde' => '2026-09-21', 'hasta' => '2026-09-20']))->assertSessionHasErrors('hasta');
});

test('mantiene el autor eliminado y escapa los textos del detalle', function () {
    $administrador = crearAdministradorDePrueba();
    $autor = Usuario::factory()->create(['nombre' => 'Autor anterior']);
    $texto = '<script>alert("prueba")</script>';
    $registro = RegistroAuditoria::create([
        'usuario_id' => $autor->id, 'modulo' => 'usuarios', 'accion' => 'actualizado',
        'agente_usuario' => $texto, 'valores_nuevos' => ['nombre' => $texto],
    ]);
    $autor->delete();

    $this->actingAs($administrador)->get(route('auditoria.ver', $registro))
        ->assertSee('Autor anterior')->assertSee($texto)->assertDontSee($texto, false);
});

test('la auditoria no admite modificaciones ni borrado por HTTP', function () {
    $administrador = crearAdministradorDePrueba();
    $registro = RegistroAuditoria::create(['modulo' => 'usuarios', 'accion' => 'creado']);

    $this->actingAs($administrador)->put(route('auditoria.ver', $registro), [])->assertMethodNotAllowed();
    $this->delete(route('auditoria.ver', $registro))->assertMethodNotAllowed();

    $this->assertModelExists($registro);
});

test('el servicio de auditoria descarta credenciales y datos ajenos al cambio', function () {
    $administrador = crearAdministradorDePrueba();
    $solicitud = Request::create('/usuarios', 'POST');
    $solicitud->setUserResolver(fn () => $administrador);
    $datos = ['nombre' => 'Nombre visible', 'contrasena' => 'secreto_prueba', 'remember_token' => 'token_prueba', 'correo' => 'privado@example.test'];

    app(ServicioAuditoria::class)->registrarCambio($solicitud, 'usuarios', 'actualizado', $administrador->id, $datos, $datos);

    $registro = RegistroAuditoria::sole();
    expect($registro->valores_nuevos)->toBe(['nombre' => 'Nombre visible']);
    expect($registro->valores_anteriores)->toBe(['nombre' => 'Nombre visible']);
});

test('renderiza las pantallas administrativas y escapa nombres de usuarios', function () {
    $administrador = crearAdministradorDePrueba();
    $usuario = Usuario::factory()->create(['nombre' => '<script>alert("nombre")</script>']);
    $rol = Rol::where('codigo', 'administrador')->sole();
    $permiso = Permiso::where('codigo', 'usuarios.ver')->sole();

    foreach ([
        route('usuarios.listar'), route('usuarios.crear'), route('usuarios.ver', $usuario), route('usuarios.editar', $usuario),
        route('roles.listar'), route('roles.crear'), route('roles.ver', $rol), route('roles.editar', $rol),
        route('permisos.listar'), route('permisos.crear'), route('permisos.editar', $permiso), route('auditoria.listar'),
    ] as $ruta) {
        $this->actingAs($administrador)->get($ruta)->assertOk();
    }
    $this->get(route('usuarios.ver', $usuario))->assertSee($usuario->nombre)->assertDontSee($usuario->nombre, false);
});
