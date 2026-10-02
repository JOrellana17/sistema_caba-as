<?php

namespace App\Http\Requests;

class CondicionHuespedRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        return ['valor' => ['required', 'boolean']];
    }

    public function attributes(): array
    {
        return ['valor' => 'condición'];
    }
}
