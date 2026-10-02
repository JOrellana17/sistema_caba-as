@extends('layouts.administracion')
@section('titulo', 'Permisos')
@section('contenido')
<div class="d-flex justify-content-between flex-wrap gap-3 mb-4">
<h1 class="h2">Permisos</h1>
@can('permisos.crear')<div><a class="btn btn-primary" href="{{ route('permisos.crear') }}">Crear permiso</a></div>@endcan
</div>
<div class="card border-0 shadow-sm"><div class="table-responsive">
<table class="table align-middle mb-0"><caption class="px-3">Permisos del sistema</caption>
<thead><tr><th scope="col">Código</th><th scope="col">Descripción</th><th scope="col">Acciones</th></tr></thead>
<tbody>@forelse ($permisos as $permiso)
<tr><td>{{ $permiso->codigo }}</td><td>{{ $permiso->descripcion ?? 'Sin descripción' }}</td>
<td><div class="d-flex flex-wrap gap-2">

@can('permisos.editar')<a class="btn btn-outline-secondary btn-sm" href="{{ route('permisos.editar', $permiso) }}">Editar</a>@endcan
@can('permisos.eliminar')
@if (! $permiso->protegido)
<form method="POST" action="{{ route('permisos.eliminar', $permiso) }}" data-confirmar="¿Eliminar este permiso?">@csrf @method('DELETE')
<button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button></form>
@endif
@endcan
</div></td></tr>
@empty<tr><td colspan="4" class="text-center py-4">No hay registros.</td></tr>@endforelse</tbody>
</table></div></div>
<div class="mt-3">{{ $permisos->links() }}</div>
@endsection
