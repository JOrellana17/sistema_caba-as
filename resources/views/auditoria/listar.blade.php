@extends('layouts.administracion')
@section('titulo', 'Auditoría')
@section('contenido')
<h1 class="h2 mb-4">Auditoría</h1>
<form method="GET" action="{{ route('auditoria.listar') }}" class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-4"><x-campo nombre="modulo" etiqueta="Módulo" :valor="$filtros['modulo'] ?? ''" maxlength="80" /></div>
            <div class="col-md-4"><x-campo nombre="accion" etiqueta="Acción" :valor="$filtros['accion'] ?? ''" maxlength="80" /></div>
            <div class="col-md-4"><x-campo nombre="usuario_id" etiqueta="Identificador de usuario" tipo="number" :valor="$filtros['usuario_id'] ?? ''" min="1" /></div>
            <div class="col-md-6"><x-campo nombre="desde" etiqueta="Desde" tipo="date" :valor="$filtros['desde'] ?? ''" /></div>
            <div class="col-md-6"><x-campo nombre="hasta" etiqueta="Hasta" tipo="date" :valor="$filtros['hasta'] ?? ''" /></div>
        </div>
        <button class="btn btn-primary">Filtrar</button> <a class="btn btn-outline-secondary" href="{{ route('auditoria.listar') }}">Limpiar filtros</a>
    </div>
</form>
<div class="card border-0 shadow-sm"><div class="table-responsive">
<table class="table align-middle mb-0">
    <caption class="px-3">Historial de operaciones</caption>
    <thead><tr><th scope="col">Fecha ({{ config('app.timezone') }})</th><th scope="col">Usuario</th><th scope="col">Módulo</th><th scope="col">Acción</th><th scope="col">Detalle</th></tr></thead>
    <tbody>@forelse ($registros as $registro)
        <tr><td>{{ $registro->created_at->format('d/m/Y H:i:s') }}</td><td>{{ $registro->usuario?->nombre ?? 'Sin usuario autenticado / consola' }}</td><td>{{ $registro->modulo }}</td><td>{{ $registro->accion }}</td>
        <td><a class="btn btn-outline-primary btn-sm" href="{{ route('auditoria.ver', $registro) }}">Ver registro</a></td></tr>
    @empty<tr><td colspan="5" class="text-center py-4">No hay registros para estos filtros.</td></tr>@endforelse</tbody>
</table>
</div></div>
<div class="mt-3">{{ $registros->links() }}</div>
@endsection
