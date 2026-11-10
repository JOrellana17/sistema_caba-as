@extends('layouts.administracion')
@section('titulo', $todas ? 'Historial de sesiones' : 'Mis sesiones')
@section('contenido')
<h1 class="h2 mb-3">{{ $todas ? 'Historial de sesiones' : 'Mis sesiones' }}</h1>
<p class="text-body-secondary">El vencimiento se calcula por inactividad. Cerrar el navegador no equivale a cerrar sesión. Los registros «observada» comienzan en la primera actividad registrada, no necesariamente en el inicio original.</p>
<form method="GET" class="row g-3 align-items-end mb-4">
@if ($todas)<div class="col-md-3"><label for="f-usuario" class="form-label">Usuario exacto</label><input id="f-usuario" class="form-control" name="usuario" value="{{ request('usuario') }}" maxlength="100"></div>@endif
<div class="col-md-3"><label for="f-estado" class="form-label">Estado</label><select id="f-estado" name="estado" class="form-select"><option value="">Todos</option>@foreach (['activa', 'cerrada', 'vencida', 'revocada'] as $estado)<option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ ucfirst($estado) }}</option>@endforeach</select></div>
<div class="col-md-2"><label for="desde" class="form-label">Desde</label><input id="desde" name="desde" type="date" class="form-control" value="{{ request('desde') }}"></div>
<div class="col-md-2"><label for="hasta" class="form-label">Hasta</label><input id="hasta" name="hasta" type="date" class="form-control" value="{{ request('hasta') }}"></div>
<div class="col-auto"><button class="btn btn-primary" type="submit">Filtrar</button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr>
@if ($todas)<th>Usuario</th>@endif<th>Inicio / origen</th><th>Última actividad</th><th>Finalización / vencimiento</th><th>Estado</th><th>Dispositivo</th>
</tr></thead><tbody>
@forelse ($sesiones as $sesion)<tr>
@if ($todas)<td>{{ $sesion->usuario?->usuario }}</td>@endif
<td>{{ $sesion->iniciada_en->format('d/m/Y H:i:s') }}<br><small>{{ $sesion->origen }}</small></td>
<td>{{ $sesion->actividad_en->format('d/m/Y H:i:s') }}</td>
<td>{{ ($sesion->finalizada_en ?? $sesion->vence_en)->format('d/m/Y H:i:s') }}<br><small>{{ $sesion->motivo ?: 'Vencimiento previsto' }}</small></td>
<td>{{ ucfirst($sesion->estado) }}</td><td class="text-break"><small>{{ $sesion->direccion_ip }}<br>{{ $sesion->agente_usuario }}</small></td>
</tr>@empty<tr><td colspan="{{ $todas ? 6 : 5 }}">No hay sesiones que coincidan.</td></tr>@endforelse
</tbody></table></div></div><div class="mt-3">{{ $sesiones->links() }}</div>
@endsection
