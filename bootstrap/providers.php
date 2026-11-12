<?php

// Lista de proveedores que Laravel carga al iniciar la aplicación; no ejecuta procesos ni migraciones
// por sí misma.

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
];
