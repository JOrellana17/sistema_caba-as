@props(['nombre', 'etiqueta', 'valor' => '', 'tipo' => 'text'])
<div class="mb-3">
    <label for="{{ $nombre }}" class="form-label">{{ $etiqueta }}</label>
    <input id="{{ $nombre }}" name="{{ $nombre }}" type="{{ $tipo }}"
        value="{{ $tipo !== 'password' && is_scalar(old($nombre, $valor)) ? old($nombre, $valor) : '' }}"
        {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($nombre)]) }}
        @if ($errors->has($nombre)) aria-invalid="true" aria-describedby="error-{{ $nombre }}" @endif>
    @error($nombre)<div id="error-{{ $nombre }}" class="invalid-feedback">{{ $message }}</div>@enderror
</div>
