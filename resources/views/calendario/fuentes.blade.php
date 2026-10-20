@extends('layouts.administracion')
@section('titulo', 'Fuentes de calendario')
@section('contenido')
<h1 class="h3 mb-3">Fuentes, importar y exportar</h1>
<p class="alert alert-info">Cada anuncio corresponde a una cabaña. Los enlaces externos son privados. Solo el origen puede liberar sus bloqueos. Para conectar CEC con plataformas necesitarás un dominio HTTPS; los archivos ICS se pueden probar localmente.</p>
@if (session('enlace_ical'))<div class="alert alert-success"><label for="enlace-generado" class="form-label">Copia este enlace; solo se muestra ahora</label><input id="enlace-generado" class="form-control" readonly value="{{ session('enlace_ical') }}"><p class="small mb-0 mt-2">No compartas el enlace públicamente. Usa uno distinto por destino.</p></div>@endif

@can('calendario.administrar_fuentes')
<section class="card card-body mb-4">
<h2 class="h5">Nueva fuente externa</h2>
<form method="POST" action="{{ route('calendario.fuentes.guardar') }}">
@csrf
<div class="row g-3">
<div class="col-md-4"><label for="fuente-cabana" class="form-label">Cabaña</label><select id="fuente-cabana" class="form-select" name="cabana_id" required><option value="">Selecciona</option>@foreach ($cabanas as $cabana)<option value="{{ $cabana->id }}" @selected(old('cabana_id') == $cabana->id)>{{ $cabana->nombre }}</option>@endforeach</select></div>
<div class="col-md-4"><label class="form-label" for="nombre">Nombre de la fuente</label><input class="form-control" id="nombre" name="nombre" maxlength="100" value="{{ old('nombre') }}" required></div>
<div class="col-md-4"><label class="form-label" for="proveedor">Proveedor</label><select class="form-select" name="proveedor" id="proveedor" required><option value="">Selecciona</option><option value="airbnb" @selected(old('proveedor') === 'airbnb')>Airbnb</option><option value="booking" @selected(old('proveedor') === 'booking')>Booking</option></select></div>
<div class="col-12"><label class="form-label" for="url">URL HTTPS privada (opcional para importar archivos)</label><input class="form-control" id="url" name="url" type="url" maxlength="4000" autocomplete="off"></div>
<div class="col-md-6"><label for="interpretacion_fechas" class="form-label">Eventos sin hora</label><select class="form-select" id="interpretacion_fechas" name="interpretacion_fechas" required><option value="">Comprueba una muestra y selecciona</option><option value="noches_estancia" @selected(old('interpretacion_fechas') === 'noches_estancia')>Estancias por noche: entrada 14:00 / salida 11:00</option><option value="dias_completos" @selected(old('interpretacion_fechas') === 'dias_completos')>Cierres de días completos: final exclusivo</option></select></div>
<div class="col-md-6"><label for="zona_horaria" class="form-label">Zona para horas sin zona explícita</label><input class="form-control" id="zona_horaria" name="zona_horaria" value="{{ old('zona_horaria', 'America/Tegucigalpa') }}" required></div>
<div class="col-md-6"><label for="cobertura_desde" class="form-label">Cobertura verificada desde (opcional)</label><input class="form-control" type="date" id="cobertura_desde" name="cobertura_desde" value="{{ old('cobertura_desde') }}"></div>
<div class="col-md-6"><label for="cobertura_hasta" class="form-label">Cobertura verificada hasta (opcional, incluida)</label><input class="form-control" type="date" id="cobertura_hasta" name="cobertura_hasta" value="{{ old('cobertura_hasta') }}"></div>
</div>
<p class="form-text">Completa cobertura solo si comprobaste que el proveedor entrega todas esas fechas. Sin cobertura, una ausencia no se considera cancelación; un CANCELLED explícito sí puede comprobarse. No se admiten recurrencias o adjuntos: se mostrará error sin liberar bloqueos.</p>
<button class="btn btn-primary" type="submit">Guardar fuente inactiva</button>
</form>
</section>
@endcan

<section class="mb-4">
<h2 class="h4">Fuentes configuradas</h2>
@forelse ($fuentes as $fuente)
<div class="card card-body mb-3">
<h3 class="h5">{{ $fuente->nombre }} · {{ $fuente->cabana?->nombre }} · {{ strtoupper($fuente->proveedor) }}</h3>
<p class="mb-2">{{ $fuente->activa ? 'Activa' : 'Suspendida / sin activar' }} · {{ $fuente->estado }}<br>Última lectura correcta: {{ $fuente->ultima_correcta?->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->format('d/m/Y H:i') ?? 'Sin lecturas' }}<br>Próxima consulta: {{ $fuente->proxima_consulta?->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->format('d/m/Y H:i') ?? 'Sin programar' }}</p>
@if ($fuente->error)<p class="text-danger">{{ $fuente->error }}</p>@endif
@can('calendario.importar')
<form class="d-flex flex-wrap gap-2 align-items-center mb-2" method="POST" action="{{ route('calendario.previsualizar', $fuente) }}" enctype="multipart/form-data">
@csrf<label for="archivo-{{ $fuente->id }}" class="form-label mb-0">Archivo ICS, hasta 2 MiB</label><input class="form-control w-auto" type="file" id="archivo-{{ $fuente->id }}" name="archivo" accept=".ics,text/calendar"><button class="btn btn-outline-primary" type="submit">Previsualizar archivo o URL</button>
</form>
@endcan
<div class="d-flex flex-wrap gap-2">
@can('calendario.sincronizar')@if ($fuente->activa && $fuente->url)<form method="POST" action="{{ route('calendario.sincronizar', $fuente) }}">@csrf<button class="btn btn-outline-primary" type="submit">Sincronizar ahora</button></form>@endif @endcan
@can('calendario.administrar_fuentes')<a class="btn btn-outline-secondary" href="{{ route('calendario.fuentes.editar', $fuente) }}">Editar fuente</a>@if ($fuente->activa)<form method="POST" action="{{ route('calendario.fuentes.suspender', $fuente) }}">@csrf<button class="btn btn-outline-secondary" type="submit">Suspender consultas (conserva bloqueos)</button></form>@endif @endcan
</div>
</div>
@empty<p>No hay fuentes configuradas.</p>@endforelse
{{ $fuentes->links() }}
</section>

@can('calendario.gestionar_bloqueos')
<section class="card card-body mb-4"><h2 class="h5">Bloqueo manual CEC con período</h2>
<form method="POST" action="{{ route('calendario.bloquear') }}">@csrf
<div class="row g-3 mb-3">
<div class="col-md-4"><label for="bloqueo-cabana" class="form-label">Cabaña</label><select class="form-select" id="bloqueo-cabana" name="cabana_id" required>@foreach ($cabanas as $cabana)<option value="{{ $cabana->id }}">{{ $cabana->nombre }}</option>@endforeach</select></div>
<div class="col-md-4"><label for="inicio" class="form-label">Inicio local</label><input class="form-control" id="inicio" name="inicio" type="datetime-local" required></div>
<div class="col-md-4"><label for="fin" class="form-label">Fin local (exclusivo)</label><input class="form-control" id="fin" name="fin" type="datetime-local" required></div>
</div><button class="btn btn-primary" type="submit">Bloquear período</button>
<p class="form-text">No cambia el estado global de la cabaña. Si se mantiene ocupada, reservada, en mantenimiento o bloqueada, ese estado también impide nuevas operaciones hasta que el personal lo cambie.</p>
</form></section>
@endcan

@can('calendario.exportar')
<section class="card card-body mb-4"><h2 class="h5">Descargar calendario ICS</h2><form method="GET" action="{{ route('calendario.descargar') }}">
<div class="row g-3 mb-3"><div class="col-md-4"><label for="exportar-cabana" class="form-label">Alcance</label><select class="form-select" id="exportar-cabana" name="cabana_id"><option value="">Todas las cabañas (uso administrativo)</option>@foreach ($cabanas as $cabana)<option value="{{ $cabana->id }}">{{ $cabana->nombre }}</option>@endforeach</select></div><div class="col-md-4"><label for="exportar-desde" class="form-label">Desde</label><input class="form-control" id="exportar-desde" type="date" name="desde" required></div><div class="col-md-4"><label for="exportar-hasta" class="form-label">Hasta (incluida)</label><input class="form-control" id="exportar-hasta" type="date" name="hasta" required></div></div><button class="btn btn-outline-primary" type="submit">Descargar ICS</button>
</form></section>
@endcan

@can('calendario.gestionar_enlaces')
<section class="card card-body mb-4"><h2 class="h5">Enlace de exportación por destino</h2><form method="POST" action="{{ route('calendario.enlaces.guardar') }}">@csrf
<div class="row g-3 mb-3"><div class="col-md-3"><label for="enlace-cabana" class="form-label">Cabaña</label><select class="form-select" id="enlace-cabana" name="cabana_id"><option value="">General (solo destino general)</option>@foreach ($cabanas as $cabana)<option value="{{ $cabana->id }}">{{ $cabana->nombre }}</option>@endforeach</select></div><div class="col-md-3"><label for="destino" class="form-label">Destino</label><select class="form-select" id="destino" name="destino" required><option value="airbnb">Airbnb</option><option value="booking">Booking</option><option value="general">General administrativo</option></select></div><div class="col-md-3"><label for="enlace-desde" class="form-label">Desde</label><input class="form-control" id="enlace-desde" name="desde" type="date" required></div><div class="col-md-3"><label for="enlace-hasta" class="form-label">Hasta (incluida)</label><input class="form-control" id="enlace-hasta" name="hasta" type="date" required></div></div>
<button class="btn btn-primary" type="submit">Generar enlace privado</button><p class="form-text">Revisa y renueva el rango antes de que venza. La exportación inicial incluye reservas CEC y bloqueos CEC; reenviar eventos de un canal a otro requiere comprobar los ecos en un piloto real.</p>
</form>
<ul class="list-group mt-3">@foreach ($enlaces as $enlace)<li class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2"><span>{{ $enlace->cabana?->nombre ?? 'General' }} · {{ $enlace->destino }} · {{ $enlace->desde->format('d/m/Y') }}–{{ $enlace->hasta->format('d/m/Y') }} · {{ $enlace->activo ? 'Activo' : 'Revocado' }}</span>@if ($enlace->activo)<form method="POST" action="{{ route('calendario.enlaces.revocar', $enlace) }}">@csrf<button class="btn btn-sm btn-outline-danger" type="submit">Revocar</button></form>@endif</li>@endforeach</ul>
</section>
@endcan
<section><h2 class="h5">Últimas sincronizaciones</h2><ul class="list-group">@forelse ($historial as $lectura)<li class="list-group-item">Fuente {{ $lectura->fuente_id }} · {{ $lectura->estado }} · {{ $lectura->procesados }} procesados · {{ $lectura->retirados }} retirados · {{ $lectura->created_at->format('d/m/Y H:i') }} UTC @if ($lectura->error)<br>{{ $lectura->error }}@endif</li>@empty<li class="list-group-item">Sin ejecuciones.</li>@endforelse</ul></section>
@endsection
