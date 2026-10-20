@extends('layouts.administracion')
@section('titulo', 'Reservación')
@section('contenido')
<h1 class="h3 mb-3">Reservación</h1>
<p class="text-body-secondary">Código: {{ $reservacion->codigo }}</p>
@if ($reservacion->eventoCalendario)
    <div class="alert alert-info">Esta reservación está vinculada a {{ strtoupper($reservacion->eventoCalendario->origen) }}. Cancelarla o reagendarla en CEC conserva el bloqueo externo hasta comprobar su cambio en el origen.
        @can('reservaciones.ver_calendario')<a href="{{ route('calendario.evento', $reservacion->eventoCalendario) }}">Revisar evento externo</a>@endcan
    </div>
    @if ($reservacion->eventoCalendario->discrepancia)<p class="alert alert-warning">Hay una discrepancia con el calendario de origen. Revisa ambas operaciones; CEC conserva las fechas e importes de esta reservación.</p>@endif
@endif
<div class="card card-body mb-4">
    <dl class="row mb-0">
        <dt class="col-md-3">Cabaña</dt><dd class="col-md-9">{{ $reservacion->cabana->nombre }}</dd>
        <dt class="col-md-3">Estado</dt><dd class="col-md-9">{{ \App\Models\Reservacion::ESTADOS[$reservacion->estado] }}</dd>
        <dt class="col-md-3">Entrada prevista</dt><dd class="col-md-9">{{ $reservacion->entrada_prevista->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->format('d/m/Y H:i') }}</dd>
        <dt class="col-md-3">Salida prevista</dt><dd class="col-md-9">{{ $reservacion->salida_prevista->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->format('d/m/Y H:i') }}</dd>
        @if ($reservacion->fecha_entrada_registrada)
            <dt class="col-md-3">Entrada registrada</dt><dd class="col-md-9">{{ $reservacion->fecha_entrada_registrada->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->format('d/m/Y H:i') }}</dd>
        @endif
        @if ($reservacion->fecha_salida_registrada)
            <dt class="col-md-3">Salida registrada</dt><dd class="col-md-9">{{ $reservacion->fecha_salida_registrada->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->format('d/m/Y H:i') }}</dd>
        @endif
        <dt class="col-md-3">Cantidad de huéspedes</dt><dd class="col-md-9">{{ $reservacion->cantidad_huespedes }}</dd>
        <dt class="col-md-3">Tarifa aplicada</dt><dd class="col-md-9">{{ $reservacion->moneda }} {{ $reservacion->precio_base }} por noche + {{ $reservacion->precio_persona_adicional }} por persona adicional. Capacidad base: {{ $reservacion->capacidad_base_aplicada }}.</dd>
        <dt class="col-md-3">Subtotal de alojamiento</dt><dd class="col-md-9">{{ $reservacion->moneda }} {{ $reservacion->subtotal }} · {{ $reservacion->noches }} noche(s)</dd>
        @if ($reservacion->relationLoaded('huesped'))
            <dt class="col-md-3">Cliente</dt><dd class="col-md-9">{{ $reservacion->huesped->nombre }} · {{ $reservacion->huesped->telefono }} · {{ $reservacion->huesped->numero_identidad ?? 'Sin registrar' }}
            @if (! $reservacion->huesped->activo) <span class="badge text-bg-warning">Inactivo</span> @endif
            @if ($reservacion->huesped->lista_negra) <span class="badge text-bg-danger">Lista negra</span> @endif
            @if ($reservacion->huesped->trashed()) <span class="badge text-bg-secondary">Eliminado</span> @endif
            </dd>
        @endif
    </dl>
    @if ($reservacion->notas)<p class="mt-3 mb-0 text-break">{{ $reservacion->notas }}</p>@endif
    @if ($reservacion->motivo_cancelacion)<p class="mt-3 mb-0">Motivo de cancelación: {{ $reservacion->motivo_cancelacion }}</p>@endif
    @if ($reservacion->estado === 'finalizada' && ! $reservacion->fecha_salida_registrada)
        <p class="alert alert-info mt-3 mb-0">La estancia está finalizada. No se registró una hora de salida física.</p>
    @endif
    @if (in_array($reservacion->estado, ['pendiente']))
        <p class="alert alert-warning mt-3 mb-0">Esta solicitud está pendiente de confirmar. Por sí sola no bloquea disponibilidad; un evento externo vinculado conserva su propio bloqueo.</p>
    @endif
</div>
@can('calendario.exportar')
    @if (in_array($reservacion->estado, ['confirmada', 'hospedada'], true) && ! $reservacion->eventoCalendario)
        <a class="btn btn-outline-secondary mb-3" href="{{ route('calendario.descargar', ['reservacion_id' => $reservacion->id, 'desde' => $reservacion->fecha_entrada->format('Y-m-d'), 'hasta' => $reservacion->fecha_salida->format('Y-m-d')]) }}">Descargar ICS de esta reservación</a>
    @endif
@endcan
<div class="d-flex flex-wrap gap-2 mb-4">
    @can('reservaciones.editar')
        @if (in_array($reservacion->estado, ['pendiente', 'confirmada']))
            @can('huespedes.buscar')<a class="btn btn-outline-primary" href="{{ route('reservaciones.editar', $reservacion) }}">Reagendar o editar</a>@endcan
        @endif
    @endcan
    @can('reservaciones.confirmar')
        @if (in_array($reservacion->estado, ['pendiente']))
            <form method="POST" action="{{ route('reservaciones.confirmar', $reservacion) }}" data-confirmar="¿Confirmar y bloquear la cabaña?">@csrf @method('PUT')<button class="btn btn-primary">Confirmar</button></form>
        @endif
    @endcan
    @can('reservaciones.registrar_entrada')
        @if ($reservacion->estado === 'confirmada')
            <form method="POST" action="{{ route('reservaciones.entrada', $reservacion) }}">@csrf @method('PUT')<button class="btn btn-primary">Registrar entrada</button></form>
        @endif
    @endcan
    @can('reservaciones.registrar_salida')
        @if ($reservacion->estado === 'hospedada')
            <form method="POST" action="{{ route('reservaciones.salida', $reservacion) }}">@csrf @method('PUT')<button class="btn btn-primary">Registrar salida</button></form>
        @endif
    @endcan
    @can('reservaciones.marcar_no_presentado')
        @if (in_array($reservacion->estado, ['pendiente', 'confirmada']))
            <form method="POST" action="{{ route('reservaciones.no_presentado', $reservacion) }}" data-confirmar="¿Marcar como no presentado? Solo se permite desde la salida prevista.">@csrf @method('PUT')<button class="btn btn-outline-secondary">No presentado</button></form>
        @endif
    @endcan
    @can('reservaciones.eliminar')
        @if (in_array($reservacion->estado, ['pendiente', 'cancelada', 'no_presentado']))
            <form method="POST" action="{{ route('reservaciones.eliminar', $reservacion) }}" data-confirmar="¿Eliminar lógicamente esta reservación?">@csrf @method('DELETE')<button class="btn btn-outline-danger">Eliminar</button></form>
        @endif
    @endcan
</div>
@can('reservaciones.cancelar')
    @if (in_array($reservacion->estado, ['pendiente', 'confirmada']))
        <form method="POST" action="{{ route('reservaciones.cancelar', $reservacion) }}" class="card card-body mb-4" data-confirmar="¿Cancelar esta reservación? Los otros bloqueos vigentes se conservan.">
            @csrf @method('PUT')
            <label for="motivo_cancelacion" class="form-label">Motivo de cancelación (opcional)</label>
            <textarea class="form-control mb-3" id="motivo_cancelacion" name="motivo_cancelacion" maxlength="5000" rows="2">{{ is_string(old('motivo_cancelacion')) ? old('motivo_cancelacion') : '' }}</textarea>
            <button class="btn btn-outline-danger align-self-start">Cancelar reservación</button>
        </form>
    @endif
@endcan
@can('reservaciones.editar')
    @canany(['reservaciones.autorizar_entrada_temprana', 'reservaciones.autorizar_salida_tardia'])
        @if (in_array($reservacion->estado, ['pendiente', 'confirmada', 'hospedada']))
        <form method="POST" action="{{ route('reservaciones.horarios', $reservacion) }}" class="card card-body mb-4">
            @csrf @method('PUT')
            <h2 class="h5">Autorizar horario excepcional</h2>
            <p>La entrada temprana y salida tardía requieren permisos administrativos y una nueva comprobación de disponibilidad. Hora de Honduras.</p>
            <div class="row">
                @can('reservaciones.autorizar_entrada_temprana')
                    @if ($reservacion->estado !== 'hospedada')
                    <div class="col-md-6"><x-campo nombre="hora_entrada" etiqueta="Hora de entrada" tipo="time" :valor="$reservacion->entrada_prevista->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->format('H:i')" required /></div>
                    @endif
                @endcan
                @can('reservaciones.autorizar_salida_tardia')
                    <div class="col-md-6"><x-campo nombre="hora_salida" etiqueta="Hora de salida" tipo="time" :valor="$reservacion->salida_prevista->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->format('H:i')" required /></div>
                @endcan
            </div>
            <button class="btn btn-outline-primary align-self-start">Autorizar horario</button>
        </form>
        @endif
    @endcanany
@endcan
<a class="btn btn-outline-secondary" href="{{ route('reservaciones.listar') }}">Volver a reservaciones</a>
@endsection
