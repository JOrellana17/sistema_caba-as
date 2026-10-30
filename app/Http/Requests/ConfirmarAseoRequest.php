<?php

namespace App\Http\Requests;

class ConfirmarAseoRequest extends SolicitudAdministrativa
{
    public function authorize(): bool
    {
        return $this->user()?->can('aseo.ver') && $this->user()->can('aseo.confirmar');
    }

    public function rules(): array
    {
        return array_fill_keys(['cabana_id', 'reservacion_id', 'iniciado_en', 'confirmado_en', 'confirmado_por'], ['prohibited']);
    }
}
