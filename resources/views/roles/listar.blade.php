{{-- roles: Listado con filtros, acciones autorizadas y navegación entre resultados. --}}
@extends('layouts.administracion')
@section('titulo', 'Roles')
{{-- Contenido específico de la pantalla insertado en la plantilla compartida. --}}
@section('contenido')
<div class="d-flex justify-content-between flex-wrap gap-3 mb-4">
<h1 class="h2">Roles</h1>
{{-- Muestra esta acción solo si el usuario tiene autorización; el servidor vuelve a comprobarla al recibir la petición. --}}
@can('roles.crear')<div><a class="btn btn-primary" href="{{ route('roles.crear') }}">Crear rol</a></div>@endcan
</div>
<div class="card border-0 shadow-sm"><div class="table-responsive">
{{-- Organiza los datos en columnas para comparar registros y localizar sus acciones. --}}
<table class="table align-middle mb-0"><caption class="px-3">Roles del sistema</caption>
<thead><tr><th scope="col">Código</th><th scope="col">Nombre</th><th scope="col">Descripción</th><th scope="col">Acciones</th></tr></thead>
<tbody>@forelse ($roles as $rol)
<tr><td>{{ $rol->codigo }}</td><td>{{ $rol->nombre }}</td><td>{{ $rol->descripcion ?? 'Sin descripción' }}</td>
<td><div class="d-flex flex-wrap gap-2">
<a class="btn btn-outline-primary btn-sm" href="{{ route('roles.ver', $rol) }}">Ver permisos</a>
@if (! in_array($rol->codigo, ['administrador', 'aseador'], true))
{{-- Muestra esta acción solo si el usuario tiene autorización; el servidor vuelve a comprobarla al recibir la petición. --}}
@can('roles.editar')<a class="btn btn-outline-secondary btn-sm" href="{{ route('roles.editar', $rol) }}">Editar</a>@endcan
@endif
{{-- Muestra esta acción solo si el usuario tiene autorización; el servidor vuelve a comprobarla al recibir la petición. --}}
@can('roles.eliminar')
@if (! in_array($rol->codigo, ['administrador', 'aseador'], true))
{{-- Envía los datos a la ruta indicada; los campos y reglas se revalidan en el servidor. --}}
<form method="POST" action="{{ route('roles.eliminar', $rol) }}" data-confirmar="¿Eliminar este rol?">@csrf @method('DELETE')
<button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button></form>
@endif
@endcan
</div></td></tr>
@empty<tr><td colspan="4" class="text-center py-4">No hay registros.</td></tr>@endforelse</tbody>
</table></div></div>
<div class="mt-3">{{ $roles->links() }}</div>
@endsection
