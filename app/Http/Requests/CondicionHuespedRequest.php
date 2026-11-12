<?php

// Validación de entrada HTTP. Valida el valor booleano de la condición que la ruta autorizada cambiará
// en la ficha.

namespace App\Http\Requests;

class CondicionHuespedRequest extends SolicitudAdministrativa
{
    // Valida el valor booleano de la condición que la ruta autorizada cambiará en la ficha.
    public function rules(): array
    {
        return ['valor' => ['required', 'boolean']];
    }

    // Asocia nombres técnicos de campos con etiquetas en español para que los mensajes del validador
    // sean comprensibles.
    public function attributes(): array
    {
        return ['valor' => 'condición'];
    }
}
