@extends('layouts.administracion')
@section('titulo', 'Cabañas')
@section('contenido')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Cabañas{{ $eliminadas ? ' eliminadas' : '' }}</h1>
        <p class="text-body-secondary mb-0">Unidades de alojamiento, capacidades y tarifas.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @can('cabanas.crear')<a class="btn btn-primary" href="{{ route('cabanas.crear') }}">Nueva cabaña</a>@endcan
        @can('cabanas.restaurar')
            <a class="btn btn-outline-secondary" href="{{ route('cabanas.listar', $eliminadas ? [] : ['eliminadas' => 1]) }}">{{ $eliminadas ? 'Ver catálogo' : 'Ver eliminadas' }}</a>
        @endcan
    </div>
</div>
<form method="GET" action="{{ route('cabanas.listar') }}" class="card card-body mb-4">
    @if ($eliminadas)<input type="hidden" name="eliminadas" value="1">@endif
    <div class="row g-3 align-items-end">
        <div class="col-lg-5">
            <label class="form-label" for="buscar">Buscar por nombre</label>
            <input type="search" id="buscar" name="buscar" value="{{ $filtros['buscar'] ?? '' }}" maxlength="120" class="form-control">
        </div>
        <div class="col-md-4 col-lg-3">
            <label class="form-label" for="estado">Estado operativo</label>
            <select class="form-select" name="estado" id="estado">
                <option value="">Todos</option>
                @foreach (\App\Models\Cabana::ESTADOS as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(($filtros['estado'] ?? '') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 col-lg-2">
            <label class="form-label" for="activa">Activa</label>
            <select class="form-select" name="activa" id="activa">
                <option value="">Todas</option>
                <option value="1" @selected(($filtros['activa'] ?? '') === '1')>Sí</option>
                <option value="0" @selected(($filtros['activa'] ?? '') === '0')>No</option>
            </select>
        </div>
        <div class="col-md-4 col-lg-2"><button class="btn btn-primary w-100" type="submit">Buscar</button></div>
    </div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <caption class="px-3">El estado operativo no confirma disponibilidad para unas fechas.</caption>
            <thead><tr><th scope="col">Nombre</th><th scope="col">Capacidad base / máxima</th><th scope="col">Precio base (HNL)</th><th scope="col">Estado</th><th scope="col">Activa</th><th scope="col">Acciones</th></tr></thead>
            <tbody>
            @forelse ($cabanas as $cabana)
                <tr>
                    <td>{{ $cabana->nombre }}</td>
                    <td>{{ $cabana->capacidad_base }} / {{ $cabana->capacidad_maxima }}</td>
                    <td>{{ $cabana->precio_base }}</td>
                    <td><span class="badge text-bg-secondary">{{ \App\Models\Cabana::ESTADOS[$cabana->estado] }}</span></td>
                    <td>{{ $cabana->activa ? 'Sí' : 'No' }}</td>
                    <td>
                        <div class="d-flex flex-wrap gap-2">
                            @if ($eliminadas)
                                    <form method="POST" action="{{ route('cabanas.restaurar', $cabana) }}" data-confirmar="¿Restaurar esta cabaña con sus datos y estado anteriores?">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-primary" type="submit">Restaurar<span class="visually-hidden"> {{ $cabana->nombre }}</span></button>
                                    </form>
                            @else
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('cabanas.ver', $cabana) }}">Ver<span class="visually-hidden"> {{ $cabana->nombre }}</span></a>
                                @if ($puedeEditar)<a class="btn btn-sm btn-outline-secondary" href="{{ route('cabanas.editar', $cabana) }}">Editar<span class="visually-hidden"> {{ $cabana->nombre }}</span></a>@endif
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-5 text-body-secondary">No hay cabañas que coincidan con estos filtros.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $cabanas->links() }}</div>
@endsection
