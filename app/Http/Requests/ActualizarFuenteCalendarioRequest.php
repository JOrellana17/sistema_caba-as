<?php

// Validación de entrada HTTP. Valida la edición de cobertura, URL e interpretación de una fuente;
// utiliza las restricciones declaradas sin aceptar cambios arbitrarios de estado.

namespace App\Http\Requests;

class ActualizarFuenteCalendarioRequest extends GuardarFuenteCalendarioRequest
{
    // Valida la edición de cobertura, URL e interpretación de una fuente; utiliza las restricciones
    // declaradas sin aceptar cambios arbitrarios de estado.
    public function rules(): array
    {
        return array_replace(parent::rules(), ['cabana_id' => ['prohibited'], 'proveedor' => ['prohibited']]);
    }
}
