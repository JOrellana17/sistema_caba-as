@extends('layouts.administracion')
@section('titulo', $cabana->nombre)
@section('contenido')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <h1 class="h3 mb-0">{{ $cabana->nombre }}</h1>
    <div class="d-flex gap-2">
        @can('cabanas.editar')<a class="btn btn-primary" href="{{ route('cabanas.editar', $cabana) }}">Editar datos</a>@endcan
        <a class="btn btn-outline-secondary" href="{{ route('cabanas.listar') }}">Volver al listado</a>
    </div>
</div>
<div class="row g-4">
    <div class="col-lg-7">
        <section class="card card-body mb-4" aria-label="Datos de la cabaña">
            @if ($cabana->imagen_principal)
                <img class="img-fluid rounded mb-4" src="{{ route('cabanas.imagen', $cabana) }}" alt="Imagen de {{ $cabana->nombre }}">
            @endif
            <h2 class="h5">Descripción</h2>
            <p class="text-break">{{ $cabana->descripcion ?: 'Sin descripción.' }}</p>
            <dl class="row mb-0">
                <dt class="col-sm-6">Capacidad base</dt><dd class="col-sm-6">{{ $cabana->capacidad_base }}</dd>
                <dt class="col-sm-6">Capacidad máxima</dt><dd class="col-sm-6">{{ $cabana->capacidad_maxima }}</dd>
                <dt class="col-sm-6">Precio base (HNL)</dt><dd class="col-sm-6">{{ $cabana->precio_base }}</dd>
                <dt class="col-sm-6">Precio por persona adicional (HNL)</dt><dd class="col-sm-6">{{ $cabana->precio_persona_adicional }}</dd>
                <dt class="col-sm-6">Precio base (USD)</dt><dd class="col-sm-6">{{ $cabana->precio_base_usd ?? 'Sin configurar' }}</dd>
                <dt class="col-sm-6">Persona adicional (USD)</dt><dd class="col-sm-6">{{ $cabana->precio_persona_adicional_usd ?? 'Sin configurar' }}</dd>
                <dt class="col-sm-6">Creada por</dt><dd class="col-sm-6">{{ $cabana->creador?->nombre ?? 'Usuario no disponible' }}</dd>
            </dl>
            <h2 class="h5 mt-3">Notas internas</h2>
            <p class="text-break mb-0">{{ $cabana->notas ?: 'Sin notas.' }}</p>
        </section>
    </div>
    <div class="col-lg-5">
        <section id="estado-operativo" class="card card-body mb-4">
            <h2 class="h5">Estado operativo</h2>
            <p>{{ \App\Models\Cabana::ESTADOS[$cabana->estado] }} · {{ $cabana->activa ? 'Activa' : 'Inactiva' }}</p>
            <p class="text-body-secondary">El estado operativo no confirma disponibilidad para unas fechas.</p>
            @can('cabanas.cambiar_estado')
                <form method="POST" action="{{ route('cabanas.estado', $cabana) }}">
                    @csrf @method('PUT')
                    <x-estado-cabana :cabana="$cabana" />
                    <button class="btn btn-outline-primary" type="submit">Guardar estado y activación</button>
                </form>
            @endcan
        </section>
        @can('cabanas.eliminar')
            <section class="card card-body">
                <h2 class="h5">Eliminar cabaña</h2>
                <p>Se ocultará del catálogo. Sus datos se conservarán para una posible restauración.</p>
                <form method="POST" action="{{ route('cabanas.eliminar', $cabana) }}" data-confirmar="¿Eliminar esta cabaña del catálogo? Sus datos se conservarán.">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger" type="submit">Eliminar cabaña</button>
                </form>
            </section>
        @endcan
    </div>
</div>
@endsection
