@extends('layouts.administracion')
@section('titulo', 'Inicio')
@section('contenido')
<h1 class="h2 mb-2">Inicio</h1>
<p class="text-body-secondary mb-4">Bienvenido, {{ auth()->user()->nombre }}. Selecciona el módulo al que deseas acceder.</p>
<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
    <x-acceso-modulo titulo="Cabañas" descripcion="Consulta las cabañas, sus capacidades, tarifas y estados." ruta="cabanas.listar" permiso="cabanas.ver" />
    <x-acceso-modulo titulo="Reservaciones" descripcion="Consulta, reagenda y gestiona las estancias." ruta="reservaciones.listar" permiso="reservaciones.ver" />
    @can('huespedes.buscar')
        <x-acceso-modulo titulo="Nueva reservación" descripcion="Busca o registra al huésped y prepara su reservación." ruta="reservaciones.crear" permiso="reservaciones.crear" />
    @endcan
    @can('reservaciones.ver_disponibilidad')
        <x-acceso-modulo titulo="Disponibilidad" descripcion="Comprueba las cabañas disponibles para las fechas de la estancia." ruta="reservaciones.disponibilidad" permiso="reservaciones.ver_disponibilidad" />
    @elsecan('cabanas.ver_disponibilidad')
        <x-acceso-modulo titulo="Disponibilidad" descripcion="Comprueba las cabañas disponibles para las fechas de la estancia." ruta="cabanas.disponibilidad" permiso="cabanas.ver_disponibilidad" />
    @endcan
    <x-acceso-modulo titulo="Huéspedes" descripcion="Consulta y actualiza las fichas de tus huéspedes." ruta="huespedes.listar" permiso="huespedes.ver" />
    <x-acceso-modulo titulo="Buscar cliente" descripcion="Encuentra un huésped por nombre, teléfono o identidad." ruta="huespedes.buscador" permiso="huespedes.buscar" />
    <x-acceso-modulo titulo="Usuarios" descripcion="Administra las cuentas del personal." ruta="usuarios.listar" permiso="usuarios.ver" />
    <x-acceso-modulo titulo="Roles" descripcion="Gestiona los roles y sus permisos." ruta="roles.listar" permiso="roles.ver" />
    <x-acceso-modulo titulo="Permisos" descripcion="Consulta y administra los permisos del sistema." ruta="permisos.listar" permiso="permisos.ver" />
    <x-acceso-modulo titulo="Auditoría" descripcion="Revisa el historial de operaciones registradas." ruta="auditoria.listar" permiso="auditoria.ver" />
</div>
@endsection
