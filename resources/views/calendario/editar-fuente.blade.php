@extends('layouts.administracion')
@section('titulo', 'Editar fuente de calendario')
@section('contenido')
<h1 class="h3">Editar fuente · {{ $fuente->nombre }}</h1>
<p class="alert alert-info">La fuente conservará su cabaña y proveedor. Al guardar se suspenden las consultas hasta una nueva previsualización; los bloqueos existentes se conservan.</p>
<form class="card card-body" method="POST" action="{{ route('calendario.fuentes.actualizar', $fuente) }}">
@csrf @method('PUT')
<label class="form-label" for="nombre">Nombre</label><input class="form-control mb-3" name="nombre" id="nombre" value="{{ old('nombre', $fuente->nombre) }}" maxlength="100" required>
<label class="form-label" for="url">Nueva URL HTTPS privada (dejar vacía para conservar la actual)</label><input class="form-control mb-3" type="url" name="url" id="url" maxlength="4000" autocomplete="off">
<label class="form-label" for="interpretacion_fechas">Eventos sin hora</label><select class="form-select mb-3" name="interpretacion_fechas" id="interpretacion_fechas" required><option value="noches_estancia" @selected(old('interpretacion_fechas', $fuente->interpretacion_fechas) === 'noches_estancia')>Estancias por noche: 14:00 / 11:00</option><option value="dias_completos" @selected(old('interpretacion_fechas', $fuente->interpretacion_fechas) === 'dias_completos')>Cierres de días completos</option></select>
<label class="form-label" for="zona_horaria">Zona para horas sin zona explícita</label><input class="form-control mb-3" name="zona_horaria" id="zona_horaria" value="{{ old('zona_horaria', $fuente->zona_horaria) }}" required>
<div class="row g-3 mb-3"><div class="col-md-6"><label class="form-label" for="cobertura_desde">Cobertura verificada desde</label><input class="form-control" type="date" name="cobertura_desde" id="cobertura_desde" value="{{ old('cobertura_desde', $fuente->cobertura_desde?->format('Y-m-d')) }}"></div><div class="col-md-6"><label class="form-label" for="cobertura_hasta">Cobertura verificada hasta (incluida)</label><input class="form-control" type="date" name="cobertura_hasta" id="cobertura_hasta" value="{{ old('cobertura_hasta', $fuente->cobertura_hasta?->format('Y-m-d')) }}"></div></div>
<p class="form-text">No amplíes cobertura sin comprobar el horizonte real del proveedor. Una cobertura desconocida mantiene las ausencias pendientes.</p>
<div><button class="btn btn-primary" type="submit">Guardar y suspender consultas</button> <a class="btn btn-outline-secondary" href="{{ route('calendario.fuentes') }}">Volver</a></div>
</form>
@endsection