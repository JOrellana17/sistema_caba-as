@props(['requerido' => true, 'seleccion' => null, 'altaEnFormulario' => false])
@php($identificador = 'buscador-'.\Illuminate\Support\Str::uuid())
@can('huespedes.buscar')
<div id="{{ $identificador }}" data-buscador-huesped data-url="{{ route('huespedes.buscar') }}" data-requerido="{{ $requerido ? '1' : '0' }}" class="mb-4">
    <label for="{{ $identificador }}-entrada" class="form-label">Buscar cliente por nombre, teléfono o número de identidad</label>
    <div class="input-group">
        <input id="{{ $identificador }}-entrada" data-entrada type="search" class="form-control" autocomplete="off" maxlength="120" value="{{ $seleccion?->nombre ?? '' }}"
            role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="{{ $identificador }}-resultados"
            aria-describedby="{{ $identificador }}-estado">
        <button data-buscar type="button" class="btn btn-outline-primary">Buscar</button>
    </div>
    <input type="hidden" data-identificador name="huesped_id" value="{{ $seleccion?->id ?? '' }}">
    <p id="{{ $identificador }}-estado" data-estado role="status" aria-live="polite" class="form-text">{{ $seleccion ? 'Cliente seleccionado.' : 'Escribe al menos tres letras o números para buscar.' }}</p>
    <div id="{{ $identificador }}-resultados" data-resultados role="listbox" aria-label="Clientes encontrados" class="list-group mb-3" hidden></div>
    <p data-seleccion class="mb-2" aria-live="polite">@if ($seleccion)
        Cliente seleccionado: {{ $seleccion->nombre }} · Teléfono: {{ $seleccion->telefono ?? 'Sin registrar' }} · Identidad: {{ $seleccion->numero_identidad ?? 'Sin registrar' }}
        @if (! $seleccion->activo) · Advertencia: inactivo @endif
        @if ($seleccion->lista_negra) · Advertencia: lista negra @endif
    @endif</p>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" data-cambiar class="btn btn-outline-secondary btn-sm" @if (! $seleccion) hidden @endif>Cambiar cliente</button>
        <button type="button" data-reintentar class="btn btn-outline-primary btn-sm" hidden>Reintentar consulta</button>
        @can('huespedes.crear')
            @if ($altaEnFormulario)
                <button type="button" data-abrir-alta class="btn btn-outline-primary btn-sm" aria-expanded="false" aria-controls="{{ $identificador }}-alta">Nuevo huésped</button>
            @else
                <a data-crear class="btn btn-outline-primary btn-sm" href="{{ route('huespedes.crear') }}" target="_blank" rel="noopener" hidden>Crear huésped (nueva pestaña)</a>
            @endif
        @endcan
    </div>
    @if ($altaEnFormulario)
        @can('huespedes.crear')
            <section id="{{ $identificador }}-alta" data-alta-huesped data-url="{{ route('huespedes.guardar_desde_formulario') }}" class="border rounded p-3 mt-3" aria-label="Alta de huésped" hidden>
                <h2 class="h5">Nuevo huésped</h2>
                <p class="form-text">Busca primero para reutilizar una ficha existente. Nombre y teléfono son obligatorios; identidad es opcional.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="{{ $identificador }}-nombre" class="form-label">Nombre completo</label>
                        <input id="{{ $identificador }}-nombre" data-alta-nombre class="form-control" maxlength="150" autocomplete="name" required disabled>
                    </div>
                    <div class="col-md-6">
                        <label for="{{ $identificador }}-telefono" class="form-label">Teléfono</label>
                        <input id="{{ $identificador }}-telefono" data-alta-telefono type="tel" class="form-control" maxlength="80" autocomplete="tel" required disabled>
                    </div>
                    <div class="col-md-6">
                        <label for="{{ $identificador }}-identidad" class="form-label">Número de identidad (opcional)</label>
                        <input id="{{ $identificador }}-identidad" data-alta-identidad class="form-control" maxlength="80" autocomplete="off" disabled>
                    </div>
                </div>
                <p data-alta-estado role="status" aria-live="polite" class="mt-3 mb-2"></p>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" data-guardar-alta class="btn btn-primary">Crear y seleccionar huésped</button>
                    <button type="button" data-cerrar-alta class="btn btn-outline-secondary">Cerrar alta</button>
                </div>
            </section>
        @endcan
    @endif
    @error('huesped_id')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
    <noscript><p>Activa JavaScript para buscar y seleccionar un cliente.</p></noscript>
</div>
@endcan
