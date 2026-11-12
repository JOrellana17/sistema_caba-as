{{-- aseo: Listado con filtros, acciones autorizadas y navegación entre resultados. --}}
@extends('layouts.administracion')
@section('titulo', 'Aseo de cabañas')
{{-- Contenido específico de la pantalla insertado en la plantilla compartida. --}}
@section('contenido')
<h1 class="h3 mb-2">Aseo de cabañas</h1>
<p class="text-body-secondary mb-4">Confirma el aseo al terminar de preparar la cabaña. La confirmación retira solo el bloqueo por aseo.</p>
<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
    {{-- Renderiza los registros y ofrece un estado vacío cuando no existen coincidencias. --}}
    @forelse ($cabanas as $cabana)
        <div class="col">
            <article class="card h-100 {{ $cabana->en_aseo ? 'border-warning' : '' }}" aria-labelledby="cabana-{{ $cabana->id }}">
                <div class="card-body d-flex flex-column gap-3">
                    <h2 id="cabana-{{ $cabana->id }}" class="h5 mb-0 text-break">{{ $cabana->nombre }}</h2>
                    <div><span class="badge {{ $cabana->en_aseo ? 'text-bg-warning' : 'text-bg-secondary' }}">{{ $cabana->en_aseo ? 'En aseo' : 'Sin aseo pendiente' }}</span></div>
                    @if (! $cabana->activa || $cabana->estado !== 'disponible')
                        <p class="small text-body-secondary mb-0">{{ $cabana->activa ? \App\Models\Cabana::ESTADOS[$cabana->estado] : 'Inactiva' }}. Confirmar aseo conserva esta condición.</p>
                    @endif
                    {{-- Renderiza los registros y ofrece un estado vacío cuando no existen coincidencias. --}}
                    @forelse ($cabana->aseosPendientes as $aseo)
                        <p class="small mb-0">Estancia #{{ $aseo->reservacion_id }} · Pendiente desde {{ $aseo->iniciado_en->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->format('d/m/Y H:i') }}</p>
                        <div class="mt-auto"><x-confirmar-aseo :aseo="$aseo" /></div>
                    @empty
                        <p class="small text-body-secondary mb-0">{{ $cabana->ultimoAseo?->confirmado_en ? 'Último aseo confirmado: '.$cabana->ultimoAseo->confirmado_en->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->format('d/m/Y H:i') : 'Todavía no hay confirmaciones registradas.' }}</p>
                    @endforelse
                </div>
            </article>
        </div>
    @empty
        <div class="col-12"><p class="alert alert-info">No hay cabañas registradas.</p></div>
    @endforelse
</div>
<div class="mt-4">{{ $cabanas->links() }}</div>
@endsection
