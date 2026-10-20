<?php

return [
    'panel' => ['ver'],
    'cabanas' => ['ver', 'crear', 'editar', 'eliminar', 'restaurar', 'cambiar_estado', 'ver_disponibilidad'],
    'huespedes' => ['ver', 'buscar', 'crear', 'editar', 'eliminar', 'restaurar', 'cambiar_estado', 'cambiar_lista_negra', 'marcar_frecuente'],
    'reservaciones' => ['ver', 'crear', 'editar', 'eliminar', 'confirmar', 'cancelar', 'registrar_entrada', 'registrar_salida', 'marcar_no_presentado', 'autorizar_entrada_temprana', 'autorizar_salida_tardia', 'ver_disponibilidad', 'ver_calendario', 'gestionar_servicios'],
    'facturas' => ['ver', 'crear', 'editar', 'eliminar', 'cancelar', 'marcar_pagada', 'exportar'],
    'pagos' => ['ver', 'crear', 'reembolsar', 'ver_resumen'],
    'servicios' => ['ver', 'crear', 'editar', 'eliminar', 'cambiar_estado'],
    'reportes' => ['ver', 'exportar'],
    'usuarios' => ['ver', 'crear', 'editar', 'eliminar', 'cambiar_estado', 'restablecer_contrasena', 'gestionar_roles', 'gestionar_permisos'],
    'roles' => ['ver', 'crear', 'editar', 'eliminar', 'gestionar_permisos'],
    'permisos' => ['ver', 'crear', 'editar', 'eliminar'],
    'configuracion' => ['ver', 'editar', 'exportar', 'importar', 'limpiar_cache'],
    'auditoria' => ['ver'],
    'calendario' => ['administrar_fuentes', 'sincronizar', 'importar', 'exportar', 'gestionar_enlaces', 'gestionar_bloqueos', 'vincular'],
];
