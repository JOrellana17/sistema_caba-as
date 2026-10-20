<?php

namespace App\Http\Requests;

use App\Models\Cabana;
use Illuminate\Validation\Rule;

class GuardarCabanaRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        $editando = $this->route('cabana') !== null;

        return [
            'bloqueo_inicio' => ['nullable', Rule::requiredIf(fn () => is_string($this->input('estado')) && $this->input('estado') !== 'disponible'), 'date_format:Y-m-d\TH:i', 'required_with:bloqueo_fin'],
            'bloqueo_fin' => ['nullable', Rule::requiredIf(fn () => is_string($this->input('estado')) && $this->input('estado') !== 'disponible'), 'date_format:Y-m-d\TH:i', 'required_with:bloqueo_inicio', 'after:bloqueo_inicio'],
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'capacidad_base' => ['required', 'integer', 'min:1', 'max:65535'],
            'capacidad_maxima' => ['required', 'integer', 'min:1', 'max:65535', 'gte:capacidad_base'],
            'precio_base' => ['required', 'regex:/^\\d{1,10}(\\.\\d{1,2})?$/'],
            'precio_persona_adicional' => ['required', 'regex:/^\\d{1,10}(\\.\\d{1,2})?$/'],
            'precio_base_usd' => ['nullable', 'required_with:precio_persona_adicional_usd', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'precio_persona_adicional_usd' => ['nullable', 'required_with:precio_base_usd', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'notas' => ['nullable', 'string', 'max:5000'],
            'imagen' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=3000,max_height=3000'],
            'estado' => $editando ? ['prohibited'] : ['required', Rule::in(array_keys(Cabana::ESTADOS))],
            'activa' => $editando ? ['prohibited'] : ['required', 'boolean'],
            'slug' => ['prohibited'],
            'creado_por' => ['prohibited'],
            'imagen_principal' => ['prohibited'],
            'deleted_at' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'integer' => 'La capacidad debe ser un número entero.',
            'min' => 'La capacidad debe ser al menos :min.',
            'gte' => 'La capacidad máxima debe ser igual o mayor que la capacidad base.',
            'precio_base.regex' => 'Introduce un precio base no negativo, con hasta diez enteros y dos decimales separados por punto.',
            'precio_persona_adicional.regex' => 'Introduce un precio por persona adicional no negativo, con hasta diez enteros y dos decimales separados por punto.',
            'in' => 'Selecciona un estado operativo permitido.',
            'imagen.image' => 'El archivo debe ser una imagen válida.',
            'imagen.file' => 'No se pudo recibir la imagen.',
            'imagen.mimes' => 'La imagen debe ser JPEG, PNG o WebP.',
            'imagen.extensions' => 'La extensión debe ser jpg, jpeg, png o webp.',
            'imagen.max' => 'La imagen no puede superar 2 MiB.',
            'imagen.dimensions' => 'La imagen no puede superar 3000 × 3000 píxeles.',
            'imagen.uploaded' => 'No se pudo cargar la imagen. Comprueba su tamaño e inténtalo de nuevo.',
        ]);
    }

    public function attributes(): array
    {
        return [
            'bloqueo_inicio' => 'inicio del bloqueo',
            'bloqueo_fin' => 'fin del bloqueo',
            'capacidad_base' => 'capacidad base',
            'capacidad_maxima' => 'capacidad máxima',
            'precio_base' => 'precio base',
            'precio_persona_adicional' => 'precio por persona adicional',
            'imagen' => 'imagen principal',
        ];
    }
}
