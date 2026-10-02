@props(['huesped'])
@if (! $huesped->activo)<span class="badge text-bg-warning">Inactivo</span>@endif
@if ($huesped->lista_negra)<span class="badge text-bg-danger">Lista negra</span>@endif
@if ($huesped->vip)<span class="badge text-bg-primary">VIP</span>@endif
@if ($huesped->frecuente)<span class="badge text-bg-info">Frecuente</span>@endif
@if ($huesped->activo && ! $huesped->lista_negra && ! $huesped->vip && ! $huesped->frecuente)<span class="badge text-bg-secondary">Activo</span>@endif
