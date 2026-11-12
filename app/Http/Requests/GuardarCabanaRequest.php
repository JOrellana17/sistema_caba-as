<?php

// Validación de entrada HTTP. Valida capacidades, tarifas, equipamiento, camas e imagen. Distingue
// alta de edición y rechaza campos controlados exclusivamente por el servidor.

namespace App\Http\Requests;

use App\Models\Cabana;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GuardarCabanaRequest extends SolicitudAdministrativa
{
    // Convierte el arreglo heredado de camas a null cuando todos sus tipos están vacíos;
    // conserva los ceros explícitos y deja que las reglas rechacen estructuras incompletas.
    protected function prepareForValidation(): void
    {
        $camas = $this->input('camas');
        if (is_array($camas) && count($camas) === count(Cabana::TIPOS_CAMA)
            && array_diff(array_keys($camas), array_keys(Cabana::TIPOS_CAMA)) === []
            && count(array_filter($camas, fn ($cantidad) => $cantidad !== null && $cantidad !== '')) === 0) {
            $this->merge(['camas' => null]);
        }
    }

    // Valida capacidades, tarifas, equipamiento, camas e imagen. Distingue alta de edición y rechaza
    // campos controlados exclusivamente por el servidor.
    public function rules(): array
    {
        $editando = $this->route('cabana') !== null;

        return [
            'cantidad_camas' => [Rule::requiredIf(fn () => $this->has('tipos_camas')), 'nullable', 'integer', 'min:0', 'max:'.Cabana::MAXIMO_CAMAS_FORMULARIO],
            'tipos_camas' => ['nullable', 'array', 'list', 'max:'.Cabana::MAXIMO_CAMAS_FORMULARIO],
            'tipos_camas.*' => ['required', 'string', Rule::in(array_keys(Cabana::TIPOS_CAMA))],
            'camas' => $this->hasAny(['cantidad_camas', 'tipos_camas']) ? ['prohibited'] : ['sometimes', 'nullable', 'array:'.implode(',', array_keys(Cabana::TIPOS_CAMA)), 'required_array_keys:'.implode(',', array_keys(Cabana::TIPOS_CAMA))],
            'camas.*' => ['required', 'integer', 'min:0', 'max:65535'],
            'tiene_refrigerador' => ['sometimes', 'nullable', 'boolean'],
            'tiene_aire_acondicionado' => ['sometimes', 'nullable', 'boolean'],
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
            // Comprueba contenido y extensión, limita el tamaño y las dimensiones; una extensión por sí sola no prueba que sea una imagen.
            'imagen' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=3000,max_height=3000'],
            'estado' => $editando ? ['prohibited'] : ['required', Rule::in(array_keys(Cabana::ESTADOS))],
            'activa' => $editando ? ['prohibited'] : ['required', 'boolean'],
            'slug' => ['prohibited'],
            'creado_por' => ['prohibited'],
            'imagen_principal' => ['prohibited'],
            'deleted_at' => ['prohibited'],
        ];
    }

    // Define mensajes legibles en español para los errores de validación; las claves corresponden a
    // campos o reglas de Laravel.
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'cantidad_camas.required' => 'Indica el número de camas para los tipos seleccionados.',
            'cantidad_camas.integer' => 'El número de camas debe ser un entero.',
            'cantidad_camas.min' => 'El número de camas no puede ser negativo.',
            'cantidad_camas.max' => 'El formulario admite hasta '.Cabana::MAXIMO_CAMAS_FORMULARIO.' camas.',
            'tipos_camas.list' => 'La lista de camas debe estar numerada consecutivamente.',
            'tipos_camas.*.required' => 'Selecciona el tipo de esta cama.',
            'tipos_camas.*.in' => 'Selecciona un tipo de cama permitido.',
            'camas.required_array_keys' => 'Registra la cantidad de cada tipo de cama.',
            'camas.*.required' => 'Registra la cantidad de este tipo de cama; utiliza cero si no tiene.',
            'camas.*.integer' => 'La cantidad de camas debe ser un número entero.',
            'camas.*.min' => 'La cantidad de camas no puede ser negativa.',
            'camas.*.max' => 'La cantidad de camas no puede superar 65535.',
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

    // Asocia nombres técnicos de campos con etiquetas en español para que los mensajes del validador
    // sean comprensibles.
    public function attributes(): array
    {
        return [
            'cantidad_camas' => 'número de camas',
            'tipos_camas' => 'tipos de camas',
            'camas' => 'camas',
            'tiene_refrigerador' => 'refrigerador',
            'tiene_aire_acondicionado' => 'aire acondicionado',
            'bloqueo_inicio' => 'inicio del bloqueo',
            'bloqueo_fin' => 'fin del bloqueo',
            'capacidad_base' => 'capacidad base',
            'capacidad_maxima' => 'capacidad máxima',
            'precio_base' => 'precio base',
            'precio_persona_adicional' => 'precio por persona adicional',
            'imagen' => 'imagen principal',
        ];
    }

    // Comprueba la coherencia entre número de camas y tipos seleccionados después de las reglas
    // básicas; exige un tipo por cama.
    public function after(): array
    {
        return [function (Validator $validador) {
            if (! $this->hasAny(['cantidad_camas', 'tipos_camas']) || $validador->errors()->hasAny(['cantidad_camas', 'tipos_camas'])) {
                return;
            }
            $cantidad = $this->input('cantidad_camas');
            $tipos = $this->input('tipos_camas') ?? [];
            if (($cantidad === null && $tipos !== []) || ($cantidad !== null && (int) $cantidad !== count($tipos))) {
                $validador->errors()->add('tipos_camas', 'Selecciona exactamente un tipo por cada cama indicada.');
            }
        }];
    }
}
