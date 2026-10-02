@extends('layouts.administracion')
@section('titulo', 'Huéspedes')
@section('contenido')
<div class="d-flex flex-wrap justify-content-between gap-3 align-items-center mb-4">
    <h1 class="h3 mb-0">Huéspedes{{ $eliminados ? ' eliminados' : '' }}</h1>
    <div class="d-flex flex-wrap gap-2">
        @can('huespedes.crear')<a class="btn btn-primary" href="{{ route('huespedes.crear') }}">Nuevo huésped</a>@endcan
        @can('huespedes.restaurar')<a class="btn btn-outline-secondary" href="{{ route('huespedes.listar', $eliminados ? [] : ['eliminados' => 1]) }}">{{ $eliminados ? 'Ver catálogo' : 'Ver eliminados' }}</a>@endcan
    </div>
</div>
<form method="GET" action="{{ route('huespedes.listar') }}" class="card card-body mb-4">
    @if ($eliminados)<input type="hidden" name="eliminados" value="1">@endif
    @can('huespedes.buscar')
        <x-campo nombre="buscar" etiqueta="Nombre, teléfono o identidad (al menos tres letras o números)" :valor="$filtros['buscar'] ?? ''" maxlength="120" />
    @endcan
    <div class="row g-3 align-items-end">
        @foreach (['activo' => 'Activo', 'lista_negra' => 'Lista negra', 'frecuente' => 'Frecuente', 'vip' => 'VIP'] as $campo => $etiqueta)
            <div class="col-sm-6 col-lg-2">
                <label for="{{ $campo }}" class="form-label">{{ $etiqueta }}</label>
                <select class="form-select" id="{{ $campo }}" name="{{ $campo }}">
                    <option value="">Todos</option>
                    <option value="1" @selected(($filtros[$campo] ?? '') === '1')>Sí</option>
                    <option value="0" @selected(($filtros[$campo] ?? '') === '0')>No</option>
                </select>
            </div>
        @endforeach
        <div class="col-lg-4 d-flex gap-2"><button class="btn btn-primary" type="submit">Filtrar</button><a href="{{ route('huespedes.listar', $eliminados ? ['eliminados' => 1] : []) }}" class="btn btn-outline-secondary">Limpiar filtros</a></div>
    </div>
</form>
<div class="card"><div class="table-responsive">
    <table class="table align-middle mb-0">
        <caption class="px-3">Fichas de huéspedes. Los registros inactivos o en lista negra requieren atención al seleccionarlos.</caption>
        <thead><tr><th scope="col">Nombre</th><th scope="col">Teléfono</th><th scope="col">Identidad</th><th scope="col">Condición</th><th scope="col">Acciones</th></tr></thead>
        <tbody>
        @forelse ($huespedes as $huesped)
            <tr>
                <td>{{ $huesped->nombre }}</td><td>{{ $huesped->telefono ?? 'Sin registrar' }}</td><td>{{ $huesped->numero_identidad ?? 'Sin registrar' }}</td>
                <td><x-condicion-huesped :huesped="$huesped" /></td>
                <td><div class="d-flex flex-wrap gap-2">
                    @if ($eliminados)
                        <form method="POST" action="{{ route('huespedes.restaurar', $huesped) }}" data-confirmar="¿Restaurar esta ficha con sus condiciones anteriores?">
                            @csrf <button class="btn btn-sm btn-outline-primary" type="submit">Restaurar<span class="visually-hidden"> {{ $huesped->nombre }}</span></button>
                        </form>
                    @else
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('huespedes.ver', $huesped) }}">Ver<span class="visually-hidden"> {{ $huesped->nombre }}</span></a>
                        @if ($puedeEditar)<a class="btn btn-sm btn-outline-secondary" href="{{ route('huespedes.editar', $huesped) }}">Editar<span class="visually-hidden"> {{ $huesped->nombre }}</span></a>@endif
                    @endif
                </div></td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center py-4">No hay huéspedes que coincidan con estos filtros.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $huespedes->links() }}</div>
@endsection
