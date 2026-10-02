@extends('layouts.administracion')
@section('titulo', 'Reservaciones')
@section('contenido')
<div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
    <h1 class="h3">Reservaciones</h1>
    @can('reservaciones.crear')@can('huespedes.buscar')<a class="btn btn-primary" href="{{ route('reservaciones.crear') }}">Nueva reservación</a>@endcan
    @endcan
</div>
<form method="GET" action="{{ route('reservaciones.listar') }}" class="card card-body mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="estado" class="form-label">Estado</label>
            <select name="estado" id="estado" class="form-select">
                <option value="">Todos</option>
                @foreach (\App\Models\Reservacion::ESTADOS as $valor => $etiqueta)
                <option value="{{ $valor }}" @selected(($filtros['estado'] ?? '') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3"><label class="form-label" for="desde">Entrada desde</label><input type="date" class="form-control" name="desde" id="desde" value="{{ $filtros['desde'] ?? '' }}"></div>
        <div class="col-md-3"><label class="form-label" for="hasta">Entrada hasta</label><input type="date" class="form-control" name="hasta" id="hasta" value="{{ $filtros['hasta'] ?? '' }}"></div>
        <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filtrar</button></div>
    </div>
</form>
<div class="card table-responsive">
<table class="table align-middle mb-0">
    <caption class="px-3">Por confirmar y pendiente no bloquean la cabaña.</caption>
    <thead><tr><th scope="col">Cabaña</th><th scope="col">Entrada / salida</th><th scope="col">Huéspedes</th><th scope="col">Estado</th><th scope="col">Alojamiento</th><th scope="col">Acciones</th></tr></thead>
    <tbody>
    @forelse ($reservaciones as $reservacion)
        <tr>
            <td>{{ $reservacion->cabana->nombre }}</td>
            <td>{{ $reservacion->entrada_prevista->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->format('d/m/Y H:i') }}<br>{{ $reservacion->salida_prevista->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->format('d/m/Y H:i') }}</td>
            <td>{{ $reservacion->cantidad_huespedes }}</td>
            <td><span class="badge text-bg-secondary">{{ \App\Models\Reservacion::ESTADOS[$reservacion->estado] }}</span></td>
            <td>{{ $reservacion->moneda }} {{ $reservacion->subtotal }}</td>
            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('reservaciones.ver', $reservacion) }}">Ver<span class="visually-hidden"> {{ $reservacion->codigo }}</span></a></td>
        </tr>
    @empty
        <tr><td colspan="6" class="text-center py-4">No hay reservaciones que coincidan con los filtros.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<div class="mt-3">{{ $reservaciones->links() }}</div>
@endsection
