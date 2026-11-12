{{-- permisos: Formulario reutilizado para alta y edición; conserva entradas tras errores de validación. --}}
@extends('layouts.administracion')
@section('titulo', $permiso->exists ? 'Editar permiso' : 'Crear permiso')
{{-- Contenido específico de la pantalla insertado en la plantilla compartida. --}}
@section('contenido')
<h1 class="h2 mb-4">{{ $permiso->exists ? 'Editar permiso' : 'Crear permiso' }}</h1>
<div class="card border-0 shadow-sm"><div class="card-body p-4">
{{-- Envía los datos a la ruta indicada; los campos y reglas se revalidan en el servidor. --}}
<form method="POST" action="{{ $permiso->exists ? route('permisos.actualizar', $permiso) : route('permisos.guardar') }}">
@csrf @if ($permiso->exists) @method('PUT') @endif

<x-campo nombre="codigo" etiqueta="Código" :valor="$permiso->codigo" maxlength="120" required />
<p class="small text-body-secondary">Formato módulo.acción, con letras minúsculas sin tildes y guion bajo. Los permisos nuevos no habilitan funciones que aún no existen.</p>
<x-campo nombre="descripcion" etiqueta="Descripción (opcional)" :valor="$permiso->descripcion" maxlength="500" />
<button class="btn btn-primary" type="submit">Guardar permiso</button>
<a class="btn btn-outline-secondary" href="{{ route('inicio') }}">Cancelar</a>
</form>
</div></div>
@endsection
