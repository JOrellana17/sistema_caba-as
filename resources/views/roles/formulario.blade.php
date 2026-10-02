@extends('layouts.administracion')
@section('titulo', $rol->exists ? 'Editar rol' : 'Crear rol')
@section('contenido')
<h1 class="h2 mb-4">{{ $rol->exists ? 'Editar rol' : 'Crear rol' }}</h1>
<div class="card border-0 shadow-sm"><div class="card-body p-4">
<form method="POST" action="{{ $rol->exists ? route('roles.actualizar', $rol) : route('roles.guardar') }}">
@csrf @if ($rol->exists) @method('PUT') @endif
<x-campo nombre="nombre" etiqueta="Nombre" :valor="$rol->nombre" maxlength="100" required />
<x-campo nombre="codigo" etiqueta="Código" :valor="$rol->codigo" maxlength="80" required />
<p class="small text-body-secondary">Letras minúsculas sin tildes, números y guion bajo.</p>
<x-campo nombre="descripcion" etiqueta="Descripción (opcional)" :valor="$rol->descripcion" maxlength="500" />
<button class="btn btn-primary" type="submit">Guardar rol</button>
<a class="btn btn-outline-secondary" href="{{ route('inicio') }}">Cancelar</a>
</form>
</div></div>
@endsection
