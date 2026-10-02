@extends('layouts.administracion')
@section('titulo', $huesped->nombre)
@section('contenido')
<div class="d-flex flex-wrap justify-content-between gap-3 align-items-center mb-4">
    <div><h1 class="h3">{{ $huesped->nombre }}</h1><x-condicion-huesped :huesped="$huesped" /></div>
    <div class="d-flex gap-2">
        @can('huespedes.editar')<a href="{{ route('huespedes.editar', $huesped) }}" class="btn btn-primary">Editar ficha</a>@endcan
        <a href="{{ route('huespedes.listar') }}" class="btn btn-outline-secondary">Volver al listado</a>
    </div>
</div>
@if (! $huesped->activo || $huesped->lista_negra)
    <p class="alert alert-warning">Advertencia: este huésped está {{ ! $huesped->activo ? 'inactivo' : '' }}{{ ! $huesped->activo && $huesped->lista_negra ? ' y ' : '' }}{{ $huesped->lista_negra ? 'en lista negra' : '' }}. Puede seleccionarse conservando estas condiciones.</p>
@endif
<div class="row g-4">
    <div class="col-lg-7">
        <section class="card card-body mb-4">
            <h2 class="h5">Identificación y contacto</h2>
            <dl class="row mb-0">
                <dt class="col-sm-4">Teléfono</dt><dd class="col-sm-8">{{ $huesped->telefono ?? 'Sin registrar' }}</dd>
                <dt class="col-sm-4">Identidad</dt><dd class="col-sm-8">{{ $huesped->numero_identidad ?? 'Sin registrar' }}</dd>
                <dt class="col-sm-4">Nacionalidad</dt><dd class="col-sm-8">{{ $huesped->nacionalidad ?: 'Sin registrar' }}</dd>
            </dl>
            <h2 class="h5 mt-3">Notas internas</h2><p class="text-break">{{ $huesped->notas ?: 'Sin notas.' }}</p>
            <h2 class="h5">Notas de incidentes</h2><p class="text-break mb-0">{{ $huesped->notas_incidentes ?: 'Sin registrar.' }}</p>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="card card-body mb-4">
            <h2 class="h5">Condiciones de la ficha</h2>
            @foreach ([
                ['activo', 'huespedes.cambiar_estado', 'huespedes.estado', 'Activación', 'Activar', 'Desactivar'],
                ['lista_negra', 'huespedes.cambiar_lista_negra', 'huespedes.lista_negra', 'Lista negra', 'Incluir en lista negra', 'Retirar de lista negra'],
                ['frecuente', 'huespedes.marcar_frecuente', 'huespedes.frecuente', 'Frecuente', 'Marcar frecuente', 'Retirar marca frecuente'],
            ] as [$campo, $permiso, $ruta, $etiqueta, $alta, $baja])
                @can($permiso)
                    <form method="POST" action="{{ route($ruta, $huesped) }}" class="mb-3" data-confirmar="¿Actualizar {{ mb_strtolower($etiqueta) }} de este huésped?">
                        @csrf @method('PUT')
                        <input type="hidden" name="valor" value="{{ $huesped->{$campo} ? '0' : '1' }}">
                        <button class="btn btn-outline-primary" type="submit">{{ $huesped->{$campo} ? $baja : $alta }}</button>
                    </form>
                @endcan
            @endforeach
            @can('huespedes.eliminar')
                <form method="POST" action="{{ route('huespedes.eliminar', $huesped) }}" data-confirmar="¿Eliminar esta ficha? Se conservarán sus datos para una posible restauración.">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger" type="submit">Eliminar huésped</button>
                </form>
            @endcan
        </section>
    </div>
</div>
@if ($estancias !== null)
<section class="card card-body mt-4 mb-4">
    <h2 class="h5">Reservaciones y estancias</h2>
    <div class="table-responsive"><table class="table">
        <thead><tr><th scope="col">Cabaña</th><th scope="col">Entrada / salida</th><th scope="col">Estado</th><th scope="col">Detalle</th></tr></thead>
        <tbody>
        @forelse ($estancias as $estancia)
            <tr><td>{{ $estancia->cabana->nombre }}</td><td>{{ $estancia->fecha_entrada->format('d/m/Y') }} / {{ $estancia->fecha_salida->format('d/m/Y') }}</td>
                <td>{{ \App\Models\Reservacion::ESTADOS[$estancia->estado] }}</td><td><a href="{{ route('reservaciones.ver', $estancia) }}">Ver reservación {{ $estancia->codigo }}</a></td></tr>
        @empty
            <tr><td colspan="4">No hay reservaciones asociadas a este huésped.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $estancias->links() }}
</section>
@endif
@if ($historial !== null)
@php($acciones = ['creado' => 'Ficha creada', 'actualizado' => 'Ficha actualizada', 'activo_actualizado' => 'Activación actualizada', 'lista_negra_actualizado' => 'Lista negra actualizada', 'frecuente_actualizado' => 'Marca frecuente actualizada', 'eliminado' => 'Ficha eliminada', 'restaurado' => 'Ficha restaurada'])
<section class="card card-body">
    <h2 class="h5">Historial de cambios de la ficha</h2>
    <div class="table-responsive"><table class="table">
        <thead><tr><th scope="col">Fecha ({{ config('app.timezone') }})</th><th scope="col">Usuario</th><th scope="col">Acción</th><th scope="col">Detalle</th></tr></thead>
        <tbody>
        @forelse ($historial as $registro)
            <tr><td>{{ $registro->created_at->format('d/m/Y H:i:s') }}</td><td>{{ $registro->usuario?->nombre ?? 'Sin usuario' }}</td>
                <td>{{ $acciones[$registro->accion] ?? $registro->accion }}</td><td><a href="{{ route('auditoria.ver', $registro) }}">Ver registro</a></td></tr>
        @empty
            <tr><td colspan="4">No hay cambios registrados.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $historial->links() }}
</section>
@endif
@endsection
