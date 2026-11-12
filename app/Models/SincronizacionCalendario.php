<?php

// Resultado de cada aplicación o fallo de lectura de una fuente externa.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SincronizacionCalendario extends Model
{
    // Declara la tabla en español para no depender de la pluralización automática de Eloquent.
    protected $table = 'sincronizaciones_calendario';

    protected $guarded = ['id'];
}
