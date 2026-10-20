@extends('layouts.administracion')
@section('titulo', 'Previsualización iCalendar')
@section('contenido')
<h1 class="h3">Previsualización · {{ $fuente->nombre }}</h1>
<p>Destino: {{ $fuente->cabana->nombre }}. {{ count($eventos) }} eventos. No se han cambiado los bloqueos.</p>
<p class="alert alert-warning">Los eventos válidos bloquearán automáticamente al confirmar. Los cruces con otras reservaciones se conservarán como conflictos para revisar. No se crean huéspedes ni importes. Esta importación no comprueba bajas por ausencia.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Entrada / inicio local</th><th>Salida / fin local exclusivo</th><th>Resultado</th></tr></thead><tbody>
@foreach (array_slice($eventos, 0, 50) as $evento)<tr><td>{{ isset($evento['inicio']) ? $evento['inicio']->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->format('d/m/Y H:i') : 'Sin fechas' }}</td><td>{{ isset($evento['fin']) ? $evento['fin']->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->format('d/m/Y H:i') : 'Sin fechas' }}</td><td>{{ $evento['accion'] }} @if ($evento['conflicto'])<span class="badge text-bg-danger">Conflicto: revisar</span>@endif</td></tr>@endforeach
</tbody></table></div>
@if (count($eventos) > 50)<p>Vista limitada a los primeros 50 eventos. Se procesarán los {{ count($eventos) }} al confirmar.</p>@endif
<form method="POST" action="{{ route('calendario.importar', $fuente) }}">@csrf<input type="hidden" name="confirmacion" value="{{ $confirmacion }}"><button class="btn btn-primary" type="submit">{{ $fuente->activa ? 'Confirmar importación' : 'Activar fuente e importar' }}</button> <a class="btn btn-outline-secondary" href="{{ route('calendario.fuentes') }}">Volver sin importar</a></form>
@endsection
