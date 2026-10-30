@props(['aseo'])
@can('aseo.ver')
    @can('aseo.confirmar')
        <form method="POST" action="{{ route('aseo.confirmar', $aseo) }}">
            @csrf @method('PUT')
            <button type="submit" class="btn btn-success">Confirmar aseo<span class="visually-hidden"> de la estancia {{ $aseo->reservacion_id }}</span></button>
        </form>
    @endcan
@endcan
