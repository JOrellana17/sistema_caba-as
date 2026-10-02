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
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <caption class="px-3">La disponibilidad se volverá a comprobar al guardar la reservación.</caption>
            <thead><tr><th scope="col">Cabaña disponible</th><th scope="col">Capacidad base / máxima</th></tr></thead>
            <tbody>
            @forelse ($cabanas as $cabana)
                <tr><td>{{ $cabana->nombre }}</td><td>{{ $cabana->capacidad_base }} / {{ $cabana->capacidad_maxima }}</td></tr>
            @empty
                <tr><td colspan="2" class="text-center py-4">No hay cabañas disponibles para estas fechas y cantidad de huéspedes.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $cabanas->links() }}</div>
@else
<p>Selecciona las fechas y la cantidad de huéspedes para consultar.</p>
@endif
@endsection
