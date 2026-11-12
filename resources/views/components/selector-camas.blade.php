{{-- components: Presentación de selector camas. --}}
{{-- Entradas del componente y valores por defecto; los atributos adicionales proceden de la vista que lo utiliza. --}}
@props(['cabana'])
{{-- Prepara valores de presentación antes de renderizar este bloque. --}}
@php
    $tipos = old('tipos_camas', $cabana->tiposCamas() ?? []);
    $tipos = is_array($tipos) && array_is_list($tipos) ? array_slice($tipos, 0, \App\Models\Cabana::MAXIMO_CAMAS_FORMULARIO) : [];
    $cantidad = old('cantidad_camas', $cabana->totalCamas());
    $cantidadValida = filter_var($cantidad, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => \App\Models\Cabana::MAXIMO_CAMAS_FORMULARIO]]);
    $filas = $cantidadValida === false ? count($tipos) : $cantidadValida;
@endphp
<div data-selector-camas class="mb-4">
    <div class="mb-3">
        <label for="cantidad_camas" class="form-label">Número de camas</label>
        <input type="number" class="form-control @error('cantidad_camas') is-invalid @enderror" id="cantidad_camas" name="cantidad_camas"
            min="0" max="{{ \App\Models\Cabana::MAXIMO_CAMAS_FORMULARIO }}" step="1" value="{{ is_scalar($cantidad) ? $cantidad : '' }}" data-numero-camas
            aria-describedby="ayuda-camas @error('cantidad_camas') error-cantidad-camas @enderror" @error('cantidad_camas') aria-invalid="true" @enderror>
        <p class="form-text mb-0" id="ayuda-camas">Indica cuántas camas tiene la cabaña y selecciona el tipo de cada una. Deja el número vacío para «Sin registrar» o utiliza cero si no tiene camas. Al guardar una cantidad menor, se conservarán solo las camas indicadas.</p>
        {{-- Presenta el mensaje de validación asociado a este campo. --}}
        @error('cantidad_camas')<div class="invalid-feedback" id="error-cantidad-camas">{{ $message }}</div>@enderror
    </div>
    {{-- Presenta el mensaje de validación asociado a este campo. --}}
    @error('tipos_camas')<p class="text-danger">{{ $message }}</p>@enderror
    <p class="mb-2">Total de camas: <output data-total-camas>{{ $cantidadValida === false ? 'Sin registrar' : $cantidadValida }}</output></p>
    <p class="small text-body-secondary" data-estado-camas aria-live="polite">Selecciona el tipo de cada cama.</p>
    <div data-lista-camas>
        @for ($indice = 0; $indice < $filas; $indice++)
            <x-cama-formulario :indice="$indice" :tipo="is_string($tipos[$indice] ?? null) ? $tipos[$indice] : ''" :abierta="$indice === 0" />
        @endfor
    </div>
    <template data-plantilla-cama><x-cama-formulario indice="__INDICE__" /></template>
</div>
