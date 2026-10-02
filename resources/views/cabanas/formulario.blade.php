@extends('layouts.administracion')
@section('titulo', $cabana->exists ? 'Editar cabaña' : 'Nueva cabaña')
@section('contenido')
<h1 class="h3 mb-4">{{ $cabana->exists ? 'Editar cabaña' : 'Nueva cabaña' }}</h1>
<form method="POST" enctype="multipart/form-data" action="{{ $cabana->exists ? route('cabanas.actualizar', $cabana) : route('cabanas.guardar') }}" class="card card-body">
    @csrf
    @if ($cabana->exists) @method('PUT') @endif
    <x-campo nombre="nombre" etiqueta="Nombre" :valor="$cabana->nombre" maxlength="120" required />
    <div class="mb-3">
        <label class="form-label" for="descripcion">Descripción</label>
        <textarea class="form-control @error('descripcion') is-invalid @enderror" id="descripcion" name="descripcion" rows="3" maxlength="5000"
            @error('descripcion') aria-invalid="true" aria-describedby="error-descripcion" @enderror>{{ is_string(old('descripcion', $cabana->descripcion)) ? old('descripcion', $cabana->descripcion) : '' }}</textarea>
        @error('descripcion')<div id="error-descripcion" class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="row">
        <div class="col-md-6"><x-campo nombre="capacidad_base" etiqueta="Capacidad base" tipo="number" :valor="$cabana->capacidad_base" min="1" max="65535" step="1" required /></div>
        <div class="col-md-6"><x-campo nombre="capacidad_maxima" etiqueta="Capacidad máxima" tipo="number" :valor="$cabana->capacidad_maxima" min="1" max="65535" step="1" required /></div>
        <div class="col-md-6"><x-campo nombre="precio_base" etiqueta="Precio base (HNL)" tipo="number" :valor="$cabana->precio_base" min="0" max="9999999999.99" step="0.01" required /></div>
        <div class="col-md-6"><x-campo nombre="precio_persona_adicional" etiqueta="Precio por persona adicional (HNL)" tipo="number" :valor="$cabana->precio_persona_adicional" min="0" max="9999999999.99" step="0.01" required /></div>
    </div>
    <div class="row">
        <div class="col-md-6"><x-campo nombre="precio_base_usd" etiqueta="Precio base (USD, opcional)" tipo="number" :valor="$cabana->precio_base_usd" min="0" max="9999999999.99" step="0.01" /></div>
        <div class="col-md-6"><x-campo nombre="precio_persona_adicional_usd" etiqueta="Precio por persona adicional (USD, opcional)" tipo="number" :valor="$cabana->precio_persona_adicional_usd" min="0" max="9999999999.99" step="0.01" /></div>
    </div>
    <p class="form-text">Configura ambas tarifas en dólares para habilitar USD. No hay conversión entre monedas.</p>
    @if (! $cabana->exists)
        <x-estado-cabana :cabana="$cabana" />
    @else
        <p class="text-body-secondary">Estado actual: {{ \App\Models\Cabana::ESTADOS[$cabana->estado] }} · {{ $cabana->activa ? 'Activa' : 'Inactiva' }}.
            @can('cabanas.cambiar_estado')
                @can('cabanas.ver')<a href="{{ route('cabanas.ver', $cabana) }}#estado-operativo">Administrar estado</a>@endcan
            @endcan
        </p>
    @endif
    <div class="mb-3">
        <label for="imagen" class="form-label">Imagen principal{{ $cabana->imagen_principal ? ' (reemplazar)' : '' }}</label>
        <input class="form-control @error('imagen') is-invalid @enderror" type="file" name="imagen" id="imagen" accept="image/jpeg,image/png,image/webp"
            aria-describedby="ayuda-imagen @error('imagen') error-imagen @enderror" @error('imagen') aria-invalid="true" @enderror>
        <div class="form-text" id="ayuda-imagen">Opcional. JPEG, PNG o WebP; máximo 2 MiB y 3000 × 3000 píxeles. Si no eliges otra imagen, se conserva la actual. Tras un error, selecciona el archivo nuevamente.</div>
        @error('imagen')<div id="error-imagen" class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-4">
        <label class="form-label" for="notas">Notas internas</label>
        <textarea class="form-control @error('notas') is-invalid @enderror" id="notas" name="notas" rows="3" maxlength="5000"
            @error('notas') aria-invalid="true" aria-describedby="error-notas" @enderror>{{ is_string(old('notas', $cabana->notas)) ? old('notas', $cabana->notas) : '' }}</textarea>
        @error('notas')<div id="error-notas" class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">Guardar cabaña</button>
        <a class="btn btn-outline-secondary" href="{{ auth()->user()->can('cabanas.ver') ? route('cabanas.listar') : route('inicio') }}">Cancelar</a>
    </div>
</form>
@endsection
