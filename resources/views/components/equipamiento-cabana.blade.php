@props(['cabana'])
@php
    $cantidadesCamas = $cabana->cantidadesCamas() ?? [];
    $tiposPresentes = array_filter(\App\Models\Cabana::TIPOS_CAMA, fn ($tipo) => ($cantidadesCamas[$tipo] ?? 0) > 0, ARRAY_FILTER_USE_KEY);
@endphp
<div class="small">
    <p class="mb-1"><strong>Total de camas:</strong> {{ $cabana->totalCamas() ?? 'Sin registrar' }}</p>
    @if ($tiposPresentes !== [])
        <p class="mb-1">@foreach ($tiposPresentes as $tipo => $etiqueta){{ $etiqueta }}: {{ $cantidadesCamas[$tipo] }}{{ $loop->last ? '' : ' · ' }}@endforeach</p>
    @endif
    <p class="mb-1">Refrigerador: {{ $cabana->tiene_refrigerador === null ? 'Sin registrar' : ($cabana->tiene_refrigerador ? 'Sí' : 'No') }}</p>
    <p class="mb-0">Aire acondicionado: {{ $cabana->tiene_aire_acondicionado === null ? 'Sin registrar' : ($cabana->tiene_aire_acondicionado ? 'Sí' : 'No') }}</p>
</div>
