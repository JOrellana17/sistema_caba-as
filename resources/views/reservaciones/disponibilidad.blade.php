@extends('layouts.administracion')
@section('titulo', 'Disponibilidad')
@section('contenido')
<h1 class="h3 mb-3">Consultar disponibilidad</h1>
<p class="text-body-secondary">Entrada a las 14:00 y salida a las 11:00, hora de Honduras.</p>
<form method="GET" action="{{ url()->current() }}" class="card card-body mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label for="fecha_entrada" class="form-label">Fecha de entrada</label>
            <input type="date" class="form-control" id="fecha_entrada" name="fecha_entrada" value="{{ old('fecha_entrada', $filtros['fecha_entrada'] ?? '') }}" required>
        </div>
        <div class="col-md-3">
            <label for="fecha_salida" class="form-label">Fecha de salida</label>
            <input type="date" class="form-control" id="fecha_salida" name="fecha_salida" value="{{ old('fecha_salida', $filtros['fecha_salida'] ?? '') }}" required>
        </div>
        <div class="col-md-3">
            <label for="cantidad_huespedes" class="form-label">Cantidad de huéspedes</label>
            <input type="number" class="form-control" id="cantidad_huespedes" name="cantidad_huespedes" value="{{ old('cantidad_huespedes', $filtros['cantidad_huespedes'] ?? '') }}" min="1" max="65535" required>
        </div>
        <div class="col-md-3"><button type="submit" class="btn btn-primary w-100">Consultar</button></div>
    </div>
</form>
@if ($cabanas !== null)
<p>Disponibilidad del {{ $filtros['fecha_entrada'] }} a las 14:00 al {{ $filtros['fecha_salida'] }} a las 11:00 para {{ $filtros['cantidad_huespedes'] }} huésped(es).</p>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h2 class="h5 mb-0">Cabañas para las fechas seleccionadas</h2>
    <span class="badge text-bg-secondary">{{ $cabanas->total() }} resultado(s)</span>
</div>
<p class="small text-body-secondary">La disponibilidad se volverá a comprobar al guardar la reservación. El aseo indica la condición actual de la cabaña; debe confirmarse antes de registrar una entrada.</p>
<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
    @forelse ($cabanas as $cabana)
        <div class="col">
            <article class="card h-100 {{ $cabana->en_aseo ? 'border-warning' : '' }}" aria-labelledby="disponibilidad-cabana-{{ $cabana->id }}" data-cabana-disponible="{{ $cabana->id }}">
                <div class="card-body d-flex flex-column gap-3">
                    <h3 class="h5 mb-0 text-break" id="disponibilidad-cabana-{{ $cabana->id }}">{{ $cabana->nombre }}</h3>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge text-bg-success">Disponible para reservar</span>
                        <span class="badge {{ $cabana->en_aseo ? 'text-bg-warning' : 'text-bg-secondary' }}">{{ $cabana->en_aseo ? 'En aseo' : 'Sin aseo pendiente' }}</span>
                    </div>
                    <dl class="row mb-0">
                        <dt class="col-8">Capacidad base</dt><dd class="col-4 text-end mb-1">{{ $cabana->capacidad_base }}</dd>
                        <dt class="col-8">Capacidad máxima</dt><dd class="col-4 text-end mb-0">{{ $cabana->capacidad_maxima }}</dd>
                    </dl>
                    <div>
                        <h4 class="h6">Equipamiento</h4>
                        <x-equipamiento-cabana :cabana="$cabana" />
                    </div>
                    @if ($cabana->en_aseo)
                        <p class="small text-body-secondary mb-0">Requiere confirmar el aseo antes de registrar la entrada. Puedes reservar para las fechas consultadas.</p>
                    @endif
                    @can('cabanas.ver')
                        <a class="btn btn-outline-primary mt-auto" href="{{ route('cabanas.ver', $cabana) }}">Ver detalles de la cabaña<span class="visually-hidden"> {{ $cabana->nombre }}</span></a>
                    @endcan
                </div>
            </article>
        </div>
    @empty
        <div class="col"><p class="alert alert-info mb-0">No hay cabañas disponibles para estas fechas y cantidad de huéspedes.</p></div>
    @endforelse
</div>
<div class="mt-3">{{ $cabanas->links() }}</div>
@else
<p>Selecciona las fechas y la cantidad de huéspedes para consultar.</p>
@endif
@endsection
