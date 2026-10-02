@extends('layouts.base')
@section('titulo', 'Administración')
@section('pagina')
<main id="contenido" class="container acceso-contenedor py-5">
    <section class="card border-0 shadow-sm rounded-4 p-4 p-sm-5 text-center">
        <span class="marca mx-auto mb-4" aria-hidden="true">CE</span>
        <h1 class="h2">Cabañas El Capitán</h1>
        <p class="text-body-secondary mb-4">Sistema administrativo</p>
        @auth
            <a class="btn btn-primary" href="{{ route('inicio') }}">Ir al inicio</a>
        @else
            <a class="btn btn-primary" href="{{ route('sesion.mostrar') }}">Iniciar sesión</a>
        @endauth
    </section>
</main>
@endsection
