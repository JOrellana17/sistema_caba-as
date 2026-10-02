@props(['permisos', 'seleccionados' => []])
<fieldset>
    <legend class="h6">Permisos disponibles</legend>
    <div class="row g-2 mb-3">
        @foreach ($permisos as $permiso)
            <div class="col-12 col-md-6 col-xl-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="permiso-{{ $permiso->id }}" name="permisos[]" value="{{ $permiso->id }}"
                        @checked(in_array($permiso->id, is_array(old('permisos', $seleccionados)) ? old('permisos', $seleccionados) : []))>
                    <label class="form-check-label" for="permiso-{{ $permiso->id }}">{{ str_replace(['.', '_'], [' · ', ' '], $permiso->codigo) }}</label>
                </div>
            </div>
        @endforeach
    </div>
    <p class="text-body-secondary small">Guardar sin selecciones retira las asignaciones de este formulario. Solo puedes conceder permisos que posees.</p>
</fieldset>
