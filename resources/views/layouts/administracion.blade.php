@extends('layouts.base')
@section('pagina')
<header class="navbar navbar-dark cabecera px-3 px-lg-4 py-3">
    <div class="container-fluid gap-3 p-0">
        <a class="navbar-brand d-flex align-items-center gap-2 m-0" href="{{ route('inicio') }}">
            <span class="marca marca-pequena" aria-hidden="true">CE</span>
            <span>Cabañas El Capitán</span>
        </a>
        <div class="d-flex align-items-center flex-wrap gap-3 text-white">
            <span>{{ auth()->user()->nombre }}</span>
            <form method="POST" action="{{ route('sesion.cerrar') }}">
                @csrf
                <button type="submit" class="btn btn-outline-light btn-sm">Cerrar sesión</button>
            </form>
        </div>
    </div>
</header>
<nav class="bg-white border-bottom" aria-label="Administración">
    <div class="container d-flex flex-wrap gap-2 py-2">
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('inicio') }}">Inicio</a>
        @can('cabanas.ver')<a class="btn btn-sm btn-outline-primary" href="{{ route('cabanas.listar') }}">Cabañas</a>@endcan
        @can('reservaciones.ver')<a class="btn btn-sm btn-outline-primary" href="{{ route('reservaciones.listar') }}">Reservaciones</a>@endcan
        @can('reservaciones.ver_disponibilidad')
            <a class="btn btn-sm btn-outline-primary" href="{{ route('reservaciones.disponibilidad') }}">Disponibilidad</a>
        @elsecan('cabanas.ver_disponibilidad')
            <a class="btn btn-sm btn-outline-primary" href="{{ route('cabanas.disponibilidad') }}">Disponibilidad</a>
        @endcan
        @can('huespedes.ver')<a class="btn btn-sm btn-outline-primary" href="{{ route('huespedes.listar') }}">Huéspedes</a>@endcan
        @can('huespedes.buscar')<a class="btn btn-sm btn-outline-primary" href="{{ route('huespedes.buscador') }}">Buscar cliente</a>@endcan
        @can('usuarios.ver')<a class="btn btn-sm btn-outline-primary" href="{{ route('usuarios.listar') }}">Usuarios</a>@endcan
        @can('roles.ver')<a class="btn btn-sm btn-outline-primary" href="{{ route('roles.listar') }}">Roles</a>@endcan
        @can('permisos.ver')<a class="btn btn-sm btn-outline-primary" href="{{ route('permisos.listar') }}">Permisos</a>@endcan
        @can('auditoria.ver')<a class="btn btn-sm btn-outline-primary" href="{{ route('auditoria.listar') }}">Auditoría</a>@endcan
    </div>
</nav>
<main id="contenido" class="container py-4 py-lg-5">
    <x-mensajes />
    @yield('contenido')
</main>
@endsection
