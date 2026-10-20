@extends('layouts.administracion')
@section('titulo', $reservacion->exists ? 'Reagendar reservación' : 'Nueva reservación')
@section('contenido')
<h1 class="h3 mb-4">{{ $reservacion->exists ? 'Reagendar reservación' : 'Nueva reservación' }}</h1>
<form method="POST" action="{{ $reservacion->exists ? route('reservaciones.actualizar', $reservacion) : route('reservaciones.guardar') }}" class="card card-body">
    @csrf
    @if (! $reservacion->exists && request('evento_id', old('evento_id')))
        <input type="hidden" name="evento_id" value="{{ request('evento_id', old('evento_id')) }}">
        <p class="alert alert-info">Completa la reservación del evento externo. Su bloqueo conserva el origen y las fechas; guardar no lo libera.</p>
    @endif
    @if ($reservacion->exists) @method('PUT') @endif
    <x-buscador-huesped :seleccion="$seleccion" :alta-en-formulario="true" />
    <div class="mb-3">
        <label for="cabana_id" class="form-label">Cabaña</label>
        <select class="form-select" id="cabana_id" name="cabana_id" required>
            <option value="">Selecciona una cabaña</option>
            @php($cabanaSeleccionada = is_scalar(old('cabana_id', $reservacion->cabana_id)) ? (string) old('cabana_id', $reservacion->cabana_id) : '')
            @foreach ($cabanas as $cabana)
            <option value="{{ $cabana->id }}" @selected($cabanaSeleccionada === (string) $cabana->id)>{{ $cabana->nombre }} · Máximo {{ $cabana->capacidad_maxima }} huéspedes</option>
            @endforeach
        </select>
    </div>
    <div class="row">
        <div class="col-md-6"><x-campo nombre="fecha_entrada" etiqueta="Fecha de entrada" tipo="date" :valor="$reservacion->fecha_entrada?->format('Y-m-d')" required /></div>
        <div class="col-md-6"><x-campo nombre="fecha_salida" etiqueta="Fecha de salida" tipo="date" :valor="$reservacion->fecha_salida?->format('Y-m-d')" required /></div>
        <div class="col-md-6">
            <x-campo nombre="hora_entrada" etiqueta="Hora de entrada (habitual: 14:00)" tipo="time" :valor="$horaEntrada" required />
        </div>
        <div class="col-md-6">
            <x-campo nombre="hora_salida" etiqueta="Hora de salida (habitual: 11:00)" tipo="time" :valor="$horaSalida" required />
        </div>
        <div class="col-md-6"><x-campo nombre="cantidad_huespedes" etiqueta="Cantidad de huéspedes" tipo="number" :valor="$reservacion->cantidad_huespedes" min="1" max="65535" step="1" required /></div>
        <div class="col-md-6 mb-3">
            <label class="form-label" for="moneda">Moneda</label>
            <select class="form-select" id="moneda" name="moneda" required>
                <option value="">Selecciona una moneda</option>
                <option value="HNL" @selected(old('moneda', $reservacion->moneda) === 'HNL')>Lempiras (HNL)</option>
                <option value="USD" @selected(old('moneda', $reservacion->moneda) === 'USD')>Dólares (USD)</option>
            </select>
        </div>
    </div>
    <p class="form-text">Las tarifas de cada moneda son independientes. El alojamiento se calcula por noche: precio base más precio por cada persona que exceda la capacidad base. Los horarios excepcionales requieren autorización administrativa.</p>
    @if ($reservacion->exists)
        <p>Tarifa aplicada: {{ $reservacion->moneda }} {{ $reservacion->precio_base }} por noche; {{ $reservacion->precio_persona_adicional }} por persona adicional; capacidad base aplicada: {{ $reservacion->capacidad_base_aplicada }}.</p>
        <div class="mb-3">
            <label for="tarifa" class="form-label">Tarifa al reagendar</label>
            <select class="form-select" id="tarifa" name="tarifa" required>
                <option value="conservar" @selected(old('tarifa', 'conservar') === 'conservar')>Mantener tarifa y capacidad base originales</option>
                <option value="actualizar" @selected(old('tarifa') === 'actualizar')>Usar tarifa y capacidad base actuales de la cabaña y moneda elegidas</option>
            </select>
        </div>
    @else
        <p class="alert alert-info">La reservación se guardará como <strong>Pendiente</strong> y no bloqueará disponibilidad. Al confirmarla se comprobará de nuevo la cabaña y se bloqueará la estancia.</p>
    @endif
    <div class="mb-3">
        <label for="notas" class="form-label">Notas internas</label>
        <textarea name="notas" id="notas" class="form-control" maxlength="5000" rows="3">{{ is_string(old('notas', $reservacion->notas)) ? old('notas', $reservacion->notas) : '' }}</textarea>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">{{ $reservacion->exists ? 'Guardar reprogramación' : 'Guardar reservación' }}</button>
        <a class="btn btn-outline-secondary" href="{{ auth()->user()->can('reservaciones.ver') ? route('reservaciones.listar') : route('inicio') }}">Volver</a>
    </div>
</form>
@endsection
