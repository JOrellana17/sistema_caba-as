{{-- components: Presentación de campo. --}}
{{-- Entradas del componente y valores por defecto; los atributos adicionales proceden de la vista que lo utiliza. --}}
@props(['nombre', 'etiqueta', 'valor' => '', 'tipo' => 'text'])
<div class="mb-3">
    <label for="{{ $nombre }}" class="form-label">{{ $etiqueta }}</label>
    <input id="{{ $nombre }}" name="{{ $nombre }}" type="{{ $tipo }}"
        value="{{ $tipo !== 'password' && is_scalar(old($nombre, $valor)) ? old($nombre, $valor) : '' }}"
        {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($nombre)]) }}
        @if ($errors->has($nombre)) aria-invalid="true" aria-describedby="error-{{ $nombre }}" @endif>
    {{-- Presenta el mensaje de validación asociado a este campo. --}}
    @error($nombre)<div id="error-{{ $nombre }}" class="invalid-feedback">{{ $message }}</div>@enderror
</div>
