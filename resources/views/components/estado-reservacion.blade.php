@props(['estado'])
@php($color = match ($estado) { 'pendiente' => 'warning', 'confirmada' => 'primary', 'hospedada' => 'success', 'cancelada' => 'danger', 'no_presentado' => 'dark', default => 'secondary' })
<span {{ $attributes->class(['badge', 'text-bg-'.$color]) }}>{{ \App\Models\Reservacion::ESTADOS[$estado] ?? 'Estado por revisar' }}</span>
