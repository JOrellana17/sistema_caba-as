<?php

namespace App\Http\Requests;

class AsignarRolesRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        return ['roles' => ['sometimes', 'array', 'max:100'], 'roles.*' => ['integer', 'distinct', 'exists:roles,id']];
    }
}
