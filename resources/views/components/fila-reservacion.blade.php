@props(['reservacion'])
@php
    $entrada = $reservacion->entrada_prevista->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->locale('es');
    $salida = $reservacion->salida_prevista->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->locale('es');
@endphp
<li class="list-group-item py-3" data-fila-reservacion="{{ $reservacion->codigo }}">
    <div class="row g-3 align-items-center">
        <div class="col-12 col-lg-4">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <h4 class="h6 mb-0 text-break">{{ $reservacion->cabana?->nombre ?? 'Cabaña por revisar' }}</h4>
                <x-estado-reservacion :estado="$reservacion->estado" />
            </div>
            @canany(['huespedes.ver', 'huespedes.buscar'])
                <p class="mb-1 text-break">{{ $reservacion->huesped?->nombre ?? 'Huésped por revisar' }}</p>
            @endcanany
            <p class="small text-body-secondary text-break mb-0">{{ $reservacion->codigo }}</p>
        </div>
        <div class="col-12 col-sm-5 col-lg-3"><strong>Entra</strong> {{ $entrada->isoFormat('dddd D [de] MMMM [de] YYYY · HH:mm') }}</div>
        <div class="col-12 col-sm-2 col-lg-1"><strong>Está</strong> {{ $reservacion->noches }} {{ $reservacion->noches === 1 ? 'noche' : 'noches' }}</div>
        <div class="col-12 col-sm-5 col-lg-3"><strong>Sale</strong> {{ $salida->isoFormat('dddd D [de] MMMM [de] YYYY · HH:mm') }}</div>
        <div class="col-12 col-lg-1"><a class="btn btn-sm btn-outline-primary" href="{{ route('reservaciones.ver', $reservacion) }}">Ver<span class="visually-hidden"> reservación {{ $reservacion->codigo }}</span></a></div>
    </div>
</li>
