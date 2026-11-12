{{-- roles: Detalle del registro y operaciones disponibles para el usuario actual. --}}
@extends('layouts.administracion')
@section('titulo', 'Detalle del rol')
{{-- Contenido específico de la pantalla insertado en la plantilla compartida. --}}
@section('contenido')
<h1 class="h2">{{ $rol->nombre }}</h1>
<p class="text-body-secondary">{{ $rol->descripcion }}</p>
@if ($rol->codigo === 'administrador')<div class="alert alert-info">El rol Administrador está protegido y conserva todos los permisos del sistema.</div>@endif
@if ($rol->codigo === 'aseador')<div class="alert alert-info">El rol Aseador está protegido y solo permite consultar y confirmar el aseo. Se asigna sin otros roles ni permisos directos.</div>@endif
<div class="card border-0 shadow-sm"><div class="card-body">
    <h2 class="h5">Permisos del rol</h2>
    @if (! in_array($rol->codigo, ['administrador', 'aseador'], true) && auth()->user()->can('roles.gestionar_permisos'))
        {{-- Envía los datos a la ruta indicada; los campos y reglas se revalidan en el servidor. --}}
        <form method="POST" action="{{ route('roles.permisos', $rol) }}">@csrf @method('PUT')
            <x-seleccion-permisos :permisos="$permisos" :seleccionados="$rol->permisos->modelKeys()" />
            <button class="btn btn-primary" type="submit">Guardar permisos</button>
        </form>
    @else
        <ul class="mb-0">@forelse ($rol->permisos as $permiso)<li>{{ $permiso->codigo }}</li>@empty<li>Sin permisos asignados.</li>@endforelse</ul>
    @endif
</div></div>
@endsection
