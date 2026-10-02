@extends('layouts.administracion')
@section('titulo', 'Detalle del rol')
@section('contenido')
<h1 class="h2">{{ $rol->nombre }}</h1>
<p class="text-body-secondary">{{ $rol->descripcion }}</p>
@if ($rol->codigo === 'administrador')<div class="alert alert-info">El rol Administrador está protegido y conserva todos los permisos del sistema.</div>@endif
<div class="card border-0 shadow-sm"><div class="card-body">
    <h2 class="h5">Permisos del rol</h2>
    @if ($rol->codigo !== 'administrador' && auth()->user()->can('roles.gestionar_permisos'))
        <form method="POST" action="{{ route('roles.permisos', $rol) }}">@csrf @method('PUT')
            <x-seleccion-permisos :permisos="$permisos" :seleccionados="$rol->permisos->modelKeys()" />
            <button class="btn btn-primary" type="submit">Guardar permisos</button>
        </form>
    @else
        <ul class="mb-0">@forelse ($rol->permisos as $permiso)<li>{{ $permiso->codigo }}</li>@empty<li>Sin permisos asignados.</li>@endforelse</ul>
    @endif
</div></div>
@endsection
