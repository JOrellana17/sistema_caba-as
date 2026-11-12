{{-- calendario: Presentación de evento. --}}
@extends('layouts.administracion')
@section('titulo', 'Evento de calendario')
{{-- Contenido específico de la pantalla insertado en la plantilla compartida. --}}
@section('contenido')
<h1 class="h3">{{ $evento->cabana?->nombre }} · Evento de {{ strtoupper($evento->origen) }}</h1>
<p>{{ $evento->inicio->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->locale('es')->isoFormat('dddd D [de] MMMM YYYY · HH:mm') }} → {{ $evento->fin->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->locale('es')->isoFormat('dddd D [de] MMMM YYYY · HH:mm') }}</p>
<p>{{ $evento->bloquea ? 'Bloqueo vigente' : 'Bloqueo retirado en su origen' }} · {{ $evento->estado }}</p>
@if ($evento->conflicto)<p class="alert alert-danger">Existe un cruce con otra reservación o bloqueo. El personal debe revisarlo; no se ha eliminado ninguna operación.</p>@endif
@if ($evento->discrepancia)<p class="alert alert-warning">El evento y su reservación vinculada presentan una discrepancia. La reservación local conserva sus datos y su bloqueo hasta revisión del personal.</p>@endif
@if ($evento->origen !== 'cec')
<p class="alert alert-info">Este bloqueo se libera exclusivamente en {{ strtoupper($evento->origen) }}. CEC comprueba la baja por sincronización; ningún administrador puede desbloquearlo manualmente aquí.</p>
<p>Fuente: {{ $evento->fuente?->nombre }} · {{ $evento->fuente?->activa ? 'Activa' : 'Suspendida; los bloqueos se conservan' }}</p>
@endif
@if ($evento->reservacion_id)
    {{-- Muestra esta acción solo si el usuario tiene autorización; el servidor vuelve a comprobarla al recibir la petición. --}}
    @can('reservaciones.ver')<a class="btn btn-outline-primary" href="{{ route('reservaciones.ver', $evento->reservacion_id) }}">Ver reservación vinculada</a>@endcan
@elseif ($evento->bloquea && $evento->origen !== 'cec')
    {{-- Muestra esta acción solo si el usuario tiene autorización; el servidor vuelve a comprobarla al recibir la petición. --}}
    @can('calendario.vincular')
        {{-- Muestra esta acción solo si el usuario tiene autorización; el servidor vuelve a comprobarla al recibir la petición. --}}
        @can('reservaciones.crear')@can('huespedes.buscar')<a class="btn btn-primary mb-3" href="{{ route('reservaciones.crear', ['evento_id' => $evento->id]) }}">Completar reservación con huésped y tarifa</a>@endcan @endcan
        {{-- Muestra esta acción solo si el usuario tiene autorización; el servidor vuelve a comprobarla al recibir la petición. --}}
        @can('reservaciones.editar')
        {{-- Envía los datos a la ruta indicada; los campos y reglas se revalidan en el servidor. --}}
        <form class="card card-body mt-3" method="POST" action="{{ route('calendario.vincular', $evento) }}">@csrf
                <label class="form-label" for="reservacion_id">Reservación existente con la misma cabaña y horarios</label>
        <select class="form-select mb-3" id="reservacion_id" name="reservacion_id" required><option value="">Selecciona una reservación</option>@foreach ($candidatas as $candidata)<option value="{{ $candidata->id }}">{{ $candidata->codigo }} · {{ \App\Models\Reservacion::ESTADOS[$candidata->estado] }}</option>@endforeach</select>
        @if ($candidatas->isEmpty())<p>No hay reservaciones compatibles sin vínculo. Puedes completar una nueva con los datos reales del huésped y la tarifa.</p>@endif
        <button class="btn btn-outline-primary" type="submit" @disabled($candidatas->isEmpty())>Vincular reservación existente</button>
        </form>
        @endcan
    @endcan
@endif
@if ($evento->origen === 'cec' && $evento->bloquea)
    {{-- Muestra esta acción solo si el usuario tiene autorización; el servidor vuelve a comprobarla al recibir la petición. --}}
    @can('calendario.gestionar_bloqueos')<form class="mt-3" method="POST" action="{{ route('calendario.liberar', $evento) }}">@csrf<button class="btn btn-outline-danger" type="submit">Liberar este bloqueo CEC</button></form>@endcan
@endif
<a class="btn btn-outline-secondary mt-3" href="{{ route('calendario.ver', ['mes' => $evento->inicio->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->format('Y-m')]) }}">Volver al calendario</a>
@endsection
