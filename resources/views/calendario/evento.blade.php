@extends('layouts.administracion')
@section('titulo', 'Evento de calendario')
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
    @can('reservaciones.ver')<a class="btn btn-outline-primary" href="{{ route('reservaciones.ver', $evento->reservacion_id) }}">Ver reservación vinculada</a>@endcan
@elseif ($evento->bloquea && $evento->origen !== 'cec')
    @can('calendario.vincular')
        @can('reservaciones.crear')@can('huespedes.buscar')<a class="btn btn-primary mb-3" href="{{ route('reservaciones.crear', ['evento_id' => $evento->id]) }}">Completar reservación con huésped y tarifa</a>@endcan @endcan
        @can('reservaciones.editar')
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
    @can('calendario.gestionar_bloqueos')<form class="mt-3" method="POST" action="{{ route('calendario.liberar', $evento) }}">@csrf<button class="btn btn-outline-danger" type="submit">Liberar este bloqueo CEC</button></form>@endcan
@endif
<a class="btn btn-outline-secondary mt-3" href="{{ route('calendario.ver', ['mes' => $evento->inicio->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->format('Y-m')]) }}">Volver al calendario</a>
@endsection
