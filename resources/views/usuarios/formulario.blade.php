{{-- usuarios: Formulario reutilizado para alta y edición; conserva entradas tras errores de validación. --}}
@extends('layouts.administracion')
@section('titulo', $usuario->exists ? 'Editar usuario' : 'Crear usuario')
{{-- Contenido específico de la pantalla insertado en la plantilla compartida. --}}
@section('contenido')
<h1 class="h2 mb-4">{{ $usuario->exists ? 'Editar usuario' : 'Crear usuario' }}</h1>
<div class="card border-0 shadow-sm"><div class="card-body p-4">
{{-- Envía los datos a la ruta indicada; los campos y reglas se revalidan en el servidor. --}}
<form method="POST" action="{{ $usuario->exists ? route('usuarios.actualizar', $usuario) : route('usuarios.guardar') }}">
    {{-- Incluye el token de sesión que protege la petición contra envíos desde otros sitios. --}}
    @csrf
    @if ($usuario->exists) @method('PUT') @endif
    <x-campo nombre="nombre" etiqueta="Nombre completo" :valor="$usuario->nombre" maxlength="255" required />
    <x-campo nombre="usuario" etiqueta="Nombre de usuario" :valor="$usuario->usuario" maxlength="100" autocomplete="off" required />
    @unless ($usuario->exists)<x-generar-credencial tipo="usuario" contexto="crear" />@endunless
    <p class="text-body-secondary small">Usa letras minúsculas sin tildes, números, punto, guion o guion bajo.</p>
    <x-campo nombre="correo" etiqueta="Correo electrónico (opcional)" tipo="email" :valor="$usuario->correo" maxlength="255" />
    @unless ($usuario->exists)
        <x-campo nombre="contrasena" etiqueta="Contraseña" tipo="password" autocomplete="new-password" minlength="12" required />
        <x-campo nombre="contrasena_confirmation" etiqueta="Confirmar contraseña" tipo="password" autocomplete="new-password" required />
        <x-generar-credencial tipo="contrasena" contexto="crear" />
        <x-campo nombre="pin" etiqueta="PIN opcional (seis dígitos)" tipo="password" inputmode="numeric" maxlength="6" autocomplete="off" />
        <x-campo nombre="pin_confirmation" etiqueta="Confirmar PIN" tipo="password" inputmode="numeric" maxlength="6" autocomplete="off" />
        <x-generar-credencial tipo="pin" contexto="crear" />
        <p class="text-body-secondary small">Al menos 12 caracteres y hasta 72 bytes. La nueva cuenta se crea sin roles ni permisos.</p>
    @endunless
    <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Guardar usuario</button><a class="btn btn-outline-secondary" href="{{ route('inicio') }}">Cancelar</a></div>
</form>
</div></div>
@endsection
