<?php

return [
    // Permisos iniciales aprobados; las ediciones posteriores se administran desde Roles.
    'empleado' => [
        'panel.ver',
        'cabanas.ver', 'cabanas.ver_disponibilidad',
        'huespedes.ver', 'huespedes.buscar', 'huespedes.crear', 'huespedes.editar',
        'reservaciones.ver', 'reservaciones.crear', 'reservaciones.editar',
        'reservaciones.confirmar', 'reservaciones.cancelar',
        'reservaciones.registrar_entrada', 'reservaciones.registrar_salida', 'reservaciones.marcar_no_presentado',
        'reservaciones.ver_disponibilidad', 'reservaciones.ver_calendario',
    ],
];
