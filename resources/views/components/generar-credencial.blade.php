{{-- components: Presentación de generar credencial. --}}
{{-- Entradas del componente y valores por defecto; los atributos adicionales proceden de la vista que lo utiliza. --}}
@props(['tipo', 'contexto'])
<div class="d-flex flex-wrap gap-2 mb-3" data-credencial="{{ $tipo }}" data-contexto="{{ $contexto }}" data-url="{{ route('credenciales.generar') }}">
    <button type="button" class="btn btn-sm btn-outline-primary" data-generar>Generar {{ $tipo === 'pin' ? 'PIN' : ($tipo === 'usuario' ? 'usuario' : 'contraseña') }}</button>
    @if ($tipo !== 'usuario')<button type="button" class="btn btn-sm btn-outline-secondary" data-mostrar>Mostrar</button>@endif
    <button type="button" class="btn btn-sm btn-outline-secondary" data-copiar>Copiar</button>
    <span class="small align-self-center" role="status" aria-live="polite" data-resultado></span>
</div>
