@extends('layouts.administracion')
@section('titulo', 'Mi perfil')
@section('contenido')
<h1 class="h2 mb-4">Mi perfil</h1>
<section class="card border-0 shadow-sm mb-4"><div class="card-body">
<dl class="row mb-0"><dt class="col-sm-3">Nombre</dt><dd class="col-sm-9">{{ $usuario->nombre }}</dd>
<dt class="col-sm-3">Usuario</dt><dd class="col-sm-9">{{ $usuario->usuario }}</dd>
<dt class="col-sm-3">Correo</dt><dd class="col-sm-9">{{ $usuario->correo ?: 'Sin registrar' }}</dd>
<dt class="col-sm-3">Roles</dt><dd class="col-sm-9">{{ $usuario->roles->pluck('nombre')->join(', ') ?: 'Sin roles' }}</dd></dl>
<a href="{{ route('sesiones.propias') }}" class="btn btn-outline-primary">Mis sesiones</a>
</div></section>
<p class="text-body-secondary">Por seguridad, confirma tu contraseña actual. Al guardar se cerrarán las otras sesiones de tu cuenta.</p>
<div class="row g-4">
<section class="col-lg-6"><div class="card h-100"><div class="card-body">
<h2 class="h5">Cambiar contraseña</h2>
<form method="POST" action="{{ route('perfil.credenciales') }}">@csrf @method('PUT')
<input type="hidden" name="operacion" value="contrasena">
<label for="actual-contrasena" class="form-label">Contraseña actual</label><input id="actual-contrasena" class="form-control mb-3" type="password" name="contrasena_actual" autocomplete="current-password" required>
<x-campo nombre="contrasena" etiqueta="Nueva contraseña" tipo="password" minlength="12" autocomplete="new-password" required />
<x-campo nombre="contrasena_confirmation" etiqueta="Confirmar contraseña" tipo="password" autocomplete="new-password" required />
<x-generar-credencial tipo="contrasena" contexto="perfil" />
<p class="small text-body-secondary">Al menos 12 caracteres y hasta 72 bytes.</p>
<button class="btn btn-primary" type="submit">Cambiar contraseña</button>
</form></div></div></section>
<section class="col-lg-6"><div class="card h-100"><div class="card-body">
<h2 class="h5">{{ $usuario->pin ? 'Cambiar PIN' : 'Configurar PIN opcional' }}</h2>
<p class="text-body-secondary">El PIN permite iniciar sesión en lugar de la contraseña.</p>
<form method="POST" action="{{ route('perfil.credenciales') }}">@csrf @method('PUT')<input type="hidden" name="operacion" value="pin">
<label for="actual-pin" class="form-label">Contraseña actual</label><input id="actual-pin" class="form-control mb-3" type="password" name="contrasena_actual" autocomplete="current-password" required>
<x-campo nombre="pin" etiqueta="PIN de seis dígitos" tipo="password" inputmode="numeric" maxlength="6" autocomplete="off" required />
<x-campo nombre="pin_confirmation" etiqueta="Confirmar PIN" tipo="password" inputmode="numeric" maxlength="6" autocomplete="off" required />
<x-generar-credencial tipo="pin" contexto="perfil" />
<button class="btn btn-primary" type="submit">Guardar PIN</button></form>
@if ($usuario->pin)
<form class="mt-4" method="POST" action="{{ route('perfil.credenciales') }}">@csrf @method('PUT')<input type="hidden" name="operacion" value="retirar_pin">
<label for="actual-retirar" class="form-label">Contraseña actual para retirar el PIN</label><input id="actual-retirar" class="form-control mb-3" name="contrasena_actual" type="password" autocomplete="current-password" required>
<button class="btn btn-outline-danger" type="submit">Retirar PIN</button></form>
@endif
</div></div></section></div>
@endsection
