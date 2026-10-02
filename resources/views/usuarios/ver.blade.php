@extends('layouts.administracion')
@section('titulo', 'Cuenta de usuario')
@section('contenido')
<div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
    <div><h1 class="h2">{{ $usuario->nombre }}</h1><p class="text-body-secondary mb-0">{{ $usuario->usuario ?? 'Usuario sin asignar' }} · {{ $usuario->activo ? 'Activo' : 'Inactivo' }}</p></div>
    @can('usuarios.editar')<div><a class="btn btn-primary" href="{{ route('usuarios.editar', $usuario) }}">Editar datos</a></div>@endcan
</div>
<div class="card border-0 shadow-sm mb-4"><div class="card-body">
    <dl class="row mb-0"><dt class="col-sm-3">Correo</dt><dd class="col-sm-9">{{ $usuario->correo ?? 'Sin registrar' }}</dd>
    <dt class="col-sm-3">Roles</dt><dd class="col-sm-9">{{ $usuario->roles->pluck('nombre')->join(', ') ?: 'Sin roles' }}</dd>
    <dt class="col-sm-3">Permisos directos</dt><dd class="col-sm-9">{{ $usuario->permisos->pluck('codigo')->join(', ') ?: 'Sin permisos directos' }}</dd></dl>
</div></div>
@can('usuarios.gestionar_roles')
<section class="card border-0 shadow-sm mb-4"><div class="card-body">
    <h2 class="h5">Asignar roles</h2>
    <form method="POST" action="{{ route('usuarios.roles', $usuario) }}">@csrf @method('PUT')
        @foreach ($roles as $rol)
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="rol-{{ $rol->id }}" name="roles[]" value="{{ $rol->id }}" @checked(in_array($rol->id, is_array(old('roles', $usuario->roles->modelKeys())) ? old('roles', $usuario->roles->modelKeys()) : []))>
                <label class="form-check-label" for="rol-{{ $rol->id }}">{{ $rol->nombre }}</label>
            </div>
        @endforeach
        <button class="btn btn-primary mt-2" type="submit">Guardar roles</button>
    </form>
</div></section>
@endcan
@can('usuarios.gestionar_permisos')
<section class="card border-0 shadow-sm mb-4"><div class="card-body">
    <h2 class="h5">Asignar permisos directos</h2>
    <form method="POST" action="{{ route('usuarios.permisos', $usuario) }}">@csrf @method('PUT')
        <x-seleccion-permisos :permisos="$permisos" :seleccionados="$usuario->permisos->modelKeys()" />
        <p class="small text-body-secondary">Los permisos recibidos por roles se mantienen aunque retires un permiso directo.</p>
        <button class="btn btn-primary" type="submit">Guardar permisos directos</button>
    </form>
</div></section>
@endcan
<div class="row g-4">
@can('usuarios.restablecer_contrasena')
<section class="col-12 col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <h2 class="h5">Restablecer contraseña</h2>
    <form method="POST" action="{{ route('usuarios.contrasena', $usuario) }}">@csrf @method('PUT')
        <x-campo nombre="contrasena" etiqueta="Nueva contraseña" tipo="password" autocomplete="new-password" minlength="12" required />
        <x-campo nombre="contrasena_confirmation" etiqueta="Confirmar contraseña" tipo="password" autocomplete="new-password" required />
        <p class="text-body-secondary small">Al menos 12 caracteres y hasta 72 bytes. Las sesiones anteriores dejarán de ser válidas.</p>
        <button class="btn btn-primary" type="submit">Restablecer contraseña</button>
    </form>
</div></div></section>
@endcan
<section class="col-12 col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <h2 class="h5">Estado y acceso</h2>
    @can('usuarios.cambiar_estado')
        <form method="POST" action="{{ route('usuarios.estado', $usuario) }}" class="mb-4">@csrf @method('PUT')
            <input type="hidden" name="activo" value="{{ $usuario->activo ? 0 : 1 }}">
            <button type="submit" class="btn btn-outline-secondary">{{ $usuario->activo ? 'Desactivar cuenta' : 'Activar cuenta' }}</button>
        </form>
    @endcan
    @can('usuarios.eliminar')
        <p class="text-body-secondary">Eliminar la cuenta impide su acceso y conserva el historial de auditoría.</p>
        <form method="POST" action="{{ route('usuarios.eliminar', $usuario) }}" data-confirmar="¿Eliminar esta cuenta?">@csrf @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">Eliminar cuenta</button>
        </form>
    @endcan
</div></div></section>
</div>
@endsection
