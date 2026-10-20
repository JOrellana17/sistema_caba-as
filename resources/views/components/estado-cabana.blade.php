@props(['cabana'])
<div class="row">
    <div class="col-md-6 mb-3">
        <label for="estado" class="form-label">Estado operativo</label>
        <select id="estado" name="estado" class="form-select @error('estado') is-invalid @enderror" required
            @error('estado') aria-invalid="true" aria-describedby="error-estado" @enderror>
            <option value="">Selecciona un estado</option>
            @foreach (\App\Models\Cabana::ESTADOS as $valor => $etiqueta)
                <option value="{{ $valor }}" @selected(old('estado', $cabana->estado) === $valor)>{{ $etiqueta }}</option>
            @endforeach
        </select>
        @error('estado')<div id="error-estado" class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="activa" class="form-label">Activa</label>
        <select id="activa" name="activa" class="form-select @error('activa') is-invalid @enderror" required
            @error('activa') aria-invalid="true" aria-describedby="error-activa" @enderror>
            <option value="">Selecciona una opción</option>
            <option value="1" @selected(in_array(old('activa', $cabana->activa), [1, '1', true], true))>Sí</option>
            <option value="0" @selected(in_array(old('activa', $cabana->activa), [0, '0', false], true))>No</option>
        </select>
        @error('activa')<div id="error-activa" class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6"><x-campo nombre="bloqueo_inicio" etiqueta="Inicio del bloqueo (obligatorio si no está disponible)" tipo="datetime-local" /></div>
    <div class="col-md-6"><x-campo nombre="bloqueo_fin" etiqueta="Fin del bloqueo (exclusivo)" tipo="datetime-local" /></div>
</div>
<p class="form-text">Los estados ocupada, reservada, mantenimiento y bloqueada requieren un período para el calendario. Cambiar a disponible no elimina bloqueos externos ni los períodos manuales ya registrados; estos últimos se liberan desde Calendario.</p>