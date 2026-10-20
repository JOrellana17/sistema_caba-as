@extends('layouts.administracion')
@section('titulo', 'Inicio')
@section('contenido')
<h1 class="h2 mb-2">Inicio</h1>
<p class="text-body-secondary mb-4">Bienvenido, {{ auth()->user()->nombre }}.</p>
@can('reservaciones.crear')
    @can('huespedes.buscar')
        <a class="btn btn-primary mb-4" href="{{ route('reservaciones.crear') }}">Nueva reservación</a>
    @endcan
@endcan
@can('reservaciones.ver')
<section aria-labelledby="titulo-reservaciones">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-2">
        <h2 id="titulo-reservaciones" class="h3 mb-0">Reservaciones activas</h2>
        <a class="btn btn-outline-primary" href="{{ route('reservaciones.listar') }}">Ver todas las reservaciones</a>
    </div>
    <p class="text-body-secondary mb-4">Pendientes, confirmadas y hospedadas con entrada desde hoy, ordenadas por fecha. Hora de Honduras.</p>
    @forelse ($grupos as $fecha => $registros)
        <section class="mb-4" aria-labelledby="fecha-{{ $loop->index }}">
            <h3 id="fecha-{{ $loop->index }}" class="h5 mb-3">{{ $registros->first()->fecha_entrada->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</h3>
            <ul class="list-group">
                @foreach ($registros as $reservacion)
                    <x-fila-reservacion :reservacion="$reservacion" />
                @endforeach
            </ul>
        </section>
    @empty
        <p class="text-body-secondary py-3">No hay reservaciones activas con entrada desde hoy.</p>
    @endforelse
    @if ($reservaciones->hasPages())
        <p class="small text-body-secondary">Un día puede continuar en la página siguiente.</p>
        {{ $reservaciones->links() }}
    @endif
</section>
@endcan
@endsection
