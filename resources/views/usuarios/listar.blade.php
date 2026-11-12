{{-- usuarios: Listado con filtros, acciones autorizadas y navegación entre resultados. --}}
@extends('layouts.administracion')
@section('titulo', 'Usuarios')
{{-- Contenido específico de la pantalla insertado en la plantilla compartida. --}}
@section('contenido')
<div class="d-flex flex-wrap justify-content-between gap-3 align-items-center mb-4">
    <h1 class="h2 mb-0">Usuarios</h1>
    {{-- Muestra esta acción solo si el usuario tiene autorización; el servidor vuelve a comprobarla al recibir la petición. --}}
    @can('usuarios.crear')<a href="{{ route('usuarios.crear') }}" class="btn btn-primary">Crear usuario</a>@endcan
</div>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        {{-- Organiza los datos en columnas para comparar registros y localizar sus acciones. --}}
        <table class="table align-middle mb-0">
            <caption class="px-3">Cuentas del personal</caption>
            <thead><tr><th scope="col">Nombre</th><th scope="col">Usuario</th><th scope="col">Estado</th><th scope="col">Roles</th><th scope="col">Acciones</th></tr></thead>
            <tbody>
                {{-- Renderiza los registros y ofrece un estado vacío cuando no existen coincidencias. --}}
                @forelse ($usuarios as $usuario)
                    <tr>
                        <td>{{ $usuario->nombre }}</td><td>{{ $usuario->usuario ?? 'Sin asignar' }}</td>
                        <td><span class="badge {{ $usuario->activo ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $usuario->activo ? 'Activo' : 'Inactivo' }}</span>@if ($usuario->estaBloqueado()) <span class="badge text-bg-warning">Bloqueado</span>@endif</td>
                        <td>{{ $usuario->roles->pluck('nombre')->join(', ') ?: 'Sin roles' }}</td>
                        <td><a class="btn btn-outline-primary btn-sm" href="{{ route('usuarios.ver', $usuario) }}">Ver cuenta</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-4">No hay usuarios registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $usuarios->links() }}</div>
@endsection
