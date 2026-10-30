@props(['indice', 'tipo' => '', 'abierta' => false])
@php
    $numero = is_int($indice) ? $indice + 1 : '__NUMERO__';
    $campo = 'tipos_camas.'.$indice;
@endphp
<details class="border rounded mb-2" id="cama-{{ $indice }}" name="camas-cabana" data-cama @if ($abierta) open @endif>
    <summary class="p-3 fw-semibold"><span data-resumen-cama>Cama {{ $numero }} · {{ \App\Models\Cabana::TIPOS_CAMA[$tipo] ?? 'Selecciona el tipo' }}</span></summary>
    <div class="px-3 pb-3">
        <label class="form-label" for="tipo-cama-{{ $indice }}">Tipo de la cama {{ $numero }}</label>
        <select id="tipo-cama-{{ $indice }}" name="tipos_camas[{{ $indice }}]" data-tipo-cama required
            class="form-select {{ $errors->has($campo) ? 'is-invalid' : '' }}"
            @if ($errors->has($campo)) aria-invalid="true" aria-describedby="error-tipo-cama-{{ $indice }}" @endif>
            <option value="">Selecciona el tipo</option>
            @foreach (\App\Models\Cabana::TIPOS_CAMA as $valor => $etiqueta)
                <option value="{{ $valor }}" @selected($tipo === $valor)>{{ $etiqueta }}</option>
            @endforeach
        </select>
        @if ($errors->has($campo))<div class="invalid-feedback" id="error-tipo-cama-{{ $indice }}">{{ $errors->first($campo) }}</div>@endif
    </div>
</details>
