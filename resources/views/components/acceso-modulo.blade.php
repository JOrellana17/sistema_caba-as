@props(['titulo', 'descripcion', 'ruta', 'permiso'])
@can($permiso)
<div class="col">
    <a href="{{ route($ruta) }}" data-acceso-modulo class="card h-100 text-decoration-none text-body border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                <h2 class="h4 mb-0">{{ $titulo }}</h2>
                <span class="text-primary fs-3" aria-hidden="true">→</span>
            </div>
            <p class="text-body-secondary mb-0">{{ $descripcion }}</p>
        </div>
    </a>
</div>
@endcan
