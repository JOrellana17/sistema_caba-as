<?php

namespace App\Http\Requests;

class ActualizarFuenteCalendarioRequest extends GuardarFuenteCalendarioRequest
{
    public function rules(): array
    {
        return array_replace(parent::rules(), ['cabana_id' => ['prohibited'], 'proveedor' => ['prohibited']]);
    }
}
