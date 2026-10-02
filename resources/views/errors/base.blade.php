@extends('layouts.base')
@section('titulo', 'No se pudo completar la solicitud')
@section('pagina')
<main id="contenido" class="container acceso-contenedor py-5">
    <section class="card border-0 shadow-sm rounded-4 p-4 p-sm-5">
        <p class="text-body-secondary">Error @yield('codigo')</p>
        <h1 class="h3">@yield('mensaje')</h1>
        <p class="text-body-secondary">@yield('explicacion')</p>
        <a class="btn btn-primary" href="{{ route('portada') }}">Volver al inicio</a>
    </section>
</main>
@endsection
