@extends('layouts.administracion')
@section('titulo', 'Roles')
@section('contenido')
<div class="d-flex justify-content-between flex-wrap gap-3 mb-4">
<h1 class="h2">Roles</h1>
@can('roles.crear')<div><a class="btn btn-primary" href="{{ route('roles.crear') }}">Crear rol</a></div>@endcan
</div>
<div class="card border-0 shadow-sm"><div class="table-responsive">
<table class="table align-middle mb-0"><caption class="px-3">Roles del sistema</caption>
<thead><tr><th scope="col">Código</th><th scope="col">Nombre</th><th scope="col">Descripción</th><th scope="col">Acciones</th></tr></thead>
<tbody>@forelse ($roles as $rol)
<tr><td>{{ $rol->codigo }}</td><td>{{ $rol->nombre }}</td><td>{{ $rol->descripcion ?? 'Sin descripción' }}</td>
<td><div class="d-flex flex-wrap gap-2">
<a class="btn btn-outline-primary btn-sm" href="{{ route('roles.ver', $rol) }}">Ver permisos</a>
@can('roles.editar')<a class="btn btn-outline-secondary btn-sm" href="{{ route('roles.editar', $rol) }}">Editar</a>@endcan
@can('roles.eliminar')
@if ($rol->codigo !== 'administrador')
<form method="POST" action="{{ route('roles.eliminar', $rol) }}" data-confirmar="¿Eliminar este rol?">@csrf @method('DELETE')
<button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button></form>
@endif
@endcan
</div></td></tr>
@empty<tr><td colspan="4" class="text-center py-4">No hay registros.</td></tr>@endforelse</tbody>
</table></div></div>
<div class="mt-3">{{ $roles->links() }}</div>
@endsection
