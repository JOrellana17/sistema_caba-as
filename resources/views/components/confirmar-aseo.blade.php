{{-- components: Presentación de confirmar aseo. --}}
{{-- Entradas del componente y valores por defecto; los atributos adicionales proceden de la vista que lo utiliza. --}}
@props(['aseo'])
{{-- Muestra esta acción solo si el usuario tiene autorización; el servidor vuelve a comprobarla al recibir la petición. --}}
@can('aseo.ver')
    {{-- Muestra esta acción solo si el usuario tiene autorización; el servidor vuelve a comprobarla al recibir la petición. --}}
    @can('aseo.confirmar')
        {{-- Envía los datos a la ruta indicada; los campos y reglas se revalidan en el servidor. --}}
        <form method="POST" action="{{ route('aseo.confirmar', $aseo) }}">
            @csrf @method('PUT')
            <button type="submit" class="btn btn-success">Confirmar aseo<span class="visually-hidden"> de la estancia {{ $aseo->reservacion_id }}</span></button>
        </form>
    @endcan
@endcan
