{{-- cabanas: Formulario reutilizado para alta y edición; conserva entradas tras errores de validación. --}}
@extends('layouts.administracion')
@section('titulo', $cabana->exists ? 'Editar cabaña' : 'Nueva cabaña')
{{-- Contenido específico de la pantalla insertado en la plantilla compartida. --}}
@section('contenido')
<h1 class="h3 mb-4">{{ $cabana->exists ? 'Editar cabaña' : 'Nueva cabaña' }}</h1>
{{-- Envía los datos a la ruta indicada; los campos y reglas se revalidan en el servidor. --}}
<form method="POST" enctype="multipart/form-data" action="{{ $cabana->exists ? route('cabanas.actualizar', $cabana) : route('cabanas.guardar') }}" class="card card-body">
    {{-- Incluye el token de sesión que protege la petición contra envíos desde otros sitios. --}}
    @csrf
    @if ($cabana->exists) @method('PUT') @endif
    <x-campo nombre="nombre" etiqueta="Nombre" :valor="$cabana->nombre" maxlength="120" required />
    <div class="mb-3">
        <label class="form-label" for="descripcion">Descripción</label>
        <textarea class="form-control @error('descripcion') is-invalid @enderror" id="descripcion" name="descripcion" rows="3" maxlength="5000"
            {{-- Presenta el mensaje de validación asociado a este campo. --}}
            @error('descripcion') aria-invalid="true" aria-describedby="error-descripcion" @enderror>{{ is_string(old('descripcion', $cabana->descripcion)) ? old('descripcion', $cabana->descripcion) : '' }}</textarea>
        {{-- Presenta el mensaje de validación asociado a este campo. --}}
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
    {{-- Agrupa campos relacionados bajo una misma sección del formulario. --}}
    <fieldset class="border rounded p-3 mb-4" data-equipamiento>
        <legend class="float-none w-auto px-2 h5">Equipamiento</legend>
        <x-selector-camas :cabana="$cabana" />
        {{-- Presenta el mensaje de validación asociado a este campo. --}}
        @error('camas')<p class="text-danger">{{ $message }}</p>@enderror
        <div class="row">
            @foreach (['tiene_refrigerador' => 'Refrigerador', 'tiene_aire_acondicionado' => 'Aire acondicionado'] as $campo => $etiqueta)
                @php($valorEquipo = old($campo, $cabana->$campo))
                <div class="col-md-6 mb-2">
                    <label for="{{ $campo }}" class="form-label">{{ $etiqueta }}</label>
                    <select id="{{ $campo }}" name="{{ $campo }}" class="form-select {{ $errors->has($campo) ? 'is-invalid' : '' }}"
                        @if ($errors->has($campo)) aria-invalid="true" aria-describedby="error-{{ $campo }}" @endif>
                        <option value="" @selected($valorEquipo === null || $valorEquipo === '')>Sin registrar</option>
                        <option value="1" @selected(in_array($valorEquipo, [true, 1, '1'], true))>Sí</option>
                        <option value="0" @selected(in_array($valorEquipo, [false, 0, '0'], true))>No</option>
                    </select>
                    @if ($errors->has($campo))<div id="error-{{ $campo }}" class="invalid-feedback">{{ $errors->first($campo) }}</div>@endif
                </div>
            @endforeach
        </div>
    </fieldset>
    @if (! $cabana->exists)
        <x-estado-cabana :cabana="$cabana" />
    @else
        <p class="text-body-secondary">Estado actual: {{ \App\Models\Cabana::ESTADOS[$cabana->estado] }} · {{ $cabana->activa ? 'Activa' : 'Inactiva' }}.
            {{-- Muestra esta acción solo si el usuario tiene autorización; el servidor vuelve a comprobarla al recibir la petición. --}}
            @can('cabanas.cambiar_estado')
                {{-- Muestra esta acción solo si el usuario tiene autorización; el servidor vuelve a comprobarla al recibir la petición. --}}
                @can('cabanas.ver')<a href="{{ route('cabanas.ver', $cabana) }}#estado-operativo">Administrar estado</a>@endcan
            @endcan
        </p>
    @endif
    <div class="mb-3">
        <label for="imagen" class="form-label">Imagen principal{{ $cabana->imagen_principal ? ' (reemplazar)' : '' }}</label>
        <input class="form-control @error('imagen') is-invalid @enderror" type="file" name="imagen" id="imagen" accept="image/jpeg,image/png,image/webp"
            aria-describedby="ayuda-imagen @error('imagen') error-imagen @enderror" @error('imagen') aria-invalid="true" @enderror>
        <div class="form-text" id="ayuda-imagen">Opcional. JPEG, PNG o WebP; máximo 2 MiB y 3000 × 3000 píxeles. Si no eliges otra imagen, se conserva la actual. Tras un error, selecciona el archivo nuevamente.</div>
        {{-- Presenta el mensaje de validación asociado a este campo. --}}
        @error('imagen')<div id="error-imagen" class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-4">
        <label class="form-label" for="notas">Notas internas</label>
        <textarea class="form-control @error('notas') is-invalid @enderror" id="notas" name="notas" rows="3" maxlength="5000"
            {{-- Presenta el mensaje de validación asociado a este campo. --}}
            @error('notas') aria-invalid="true" aria-describedby="error-notas" @enderror>{{ is_string(old('notas', $cabana->notas)) ? old('notas', $cabana->notas) : '' }}</textarea>
        {{-- Presenta el mensaje de validación asociado a este campo. --}}
        @error('notas')<div id="error-notas" class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">Guardar cabaña</button>
        <a class="btn btn-outline-secondary" href="{{ auth()->user()->can('cabanas.ver') ? route('cabanas.listar') : route('inicio') }}">Cancelar</a>
    </div>
</form>
@endsection
