<?php

// Límites de tamaño y cantidad de eventos, intervalo de consulta y dominios autorizados para la
// importación segura de calendarios.

return [
    'max_bytes' => 2 * 1024 * 1024,
    'max_eventos' => 2000,
    'intervalo_minutos' => 15,
    'hosts_permitidos' => ['airbnb.com', 'booking.com'],
];
