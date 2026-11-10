@extends('layouts.base')
@section('pagina')
@php
    $usuario = auth()->user();
    $usaDisponibilidadReservaciones = $usuario->can('reservaciones.ver_disponibilidad');
    $gruposMenu = [
        'Operación diaria' => [
            'reservaciones' => ['Reservaciones', 'reservaciones.listar', 'reservaciones.ver'],
            'calendario' => ['Calendario', 'calendario.ver', 'reservaciones.ver_calendario'],
            'disponibilidad' => ['Disponibilidad',
                $usaDisponibilidadReservaciones ? 'reservaciones.disponibilidad' : 'cabanas.disponibilidad',
                $usaDisponibilidadReservaciones ? 'reservaciones.ver_disponibilidad' : 'cabanas.ver_disponibilidad'],
            'huespedes' => ['Huéspedes', 'huespedes.listar', 'huespedes.ver'],
            'buscador' => ['Buscar cliente', 'huespedes.buscador', 'huespedes.buscar'],
            'cabanas' => ['Cabañas', 'cabanas.listar', 'cabanas.ver'],
            'aseo' => ['Aseo', 'aseo.listar', 'aseo.ver'],
        ],
        'Administración' => [
            'usuarios' => ['Usuarios', 'usuarios.listar', 'usuarios.ver'],
            'roles' => ['Roles', 'roles.listar', 'roles.ver'],
            'permisos' => ['Permisos', 'permisos.listar', 'permisos.ver'],
            'auditoria' => ['Auditoría', 'auditoria.listar', 'auditoria.ver'],
        ],
    ];
    $moduloActual = match (true) {
        request()->routeIs('reservaciones.disponibilidad', 'cabanas.disponibilidad') => 'disponibilidad',
        request()->routeIs('huespedes.buscador') => 'buscador',
        default => \Illuminate\Support\Str::before(request()->route()?->getName() ?? '', '.'),
    };
@endphp
<div class="aplicacion-marco">
    <header class="cabecera-movil d-lg-none">
        <a class="marca-enlace" href="{{ route('inicio') }}">
            <x-marca-cabana />
            <span>Cabañas El Capitán</span>
        </a>
        <button class="boton-menu" type="button" data-bs-toggle="offcanvas" data-bs-target="#menu-administracion"
            aria-controls="menu-administracion" aria-label="Abrir menú">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
                <path d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </header>

    <aside class="offcanvas-lg offcanvas-start barra-lateral" tabindex="-1" id="menu-administracion" aria-labelledby="titulo-menu">
        <div class="offcanvas-header">
            <a class="marca-enlace" href="{{ route('inicio') }}">
                <x-marca-cabana />
                <span id="titulo-menu">Cabañas El Capitán</span>
            </a>
            <button class="btn-close btn-close-white d-lg-none" type="button" data-bs-dismiss="offcanvas"
                data-bs-target="#menu-administracion" aria-label="Cerrar menú"></button>
        </div>
        <div class="offcanvas-body">
            <nav class="menu-modulos" aria-label="Administración">
                @unless ($usuario->esAseador())
                    <a class="menu-enlace" href="{{ route('inicio') }}" @if (request()->routeIs('inicio')) aria-current="page" @endif>Inicio</a>
                @endunless
                @foreach ($gruposMenu as $titulo => $enlaces)
                    @canany(array_column($enlaces, 2))
                        <div class="menu-grupo">
                            <p class="menu-titulo">{{ $titulo }}</p>
                            @foreach ($enlaces as $modulo => [$etiqueta, $ruta, $permiso])
                                @can($permiso)
                                    <a class="menu-enlace" href="{{ route($ruta) }}"
                                        @if ($moduloActual === $modulo) aria-current="{{ request()->routeIs($ruta) ? 'page' : 'true' }}" @endif>{{ $etiqueta }}</a>
                                @endcan
                            @endforeach
                        </div>
                    @endcanany
                @endforeach
            </nav>
            <div class="menu-cuenta">
                <p class="mb-1 fw-semibold">{{ $usuario->nombre }}</p>
                <a class="menu-enlace" href="{{ route('perfil.ver') }}">Mi perfil</a>
                <a class="menu-enlace" href="{{ route('sesiones.propias') }}">Mis sesiones</a>
                @can('sesiones.ver_todas')
                    <a class="menu-enlace" href="{{ route('sesiones.todas') }}">Historial de sesiones</a>
                @endcan
                <form method="POST" action="{{ route('sesion.cerrar') }}">
                    @csrf
                    <button type="submit" class="menu-salir">Cerrar sesión</button>
                </form>
            </div>
        </div>
    </aside>

    <main id="contenido" class="aplicacion-contenido">
        <x-mensajes />
        @yield('contenido')
    </main>
</div>
@endsection
