@extends('layouts.administracion')
@section('titulo', $huesped->exists ? 'Editar huésped' : 'Nuevo huésped')
@section('contenido')
<h1 class="h3 mb-4">{{ $huesped->exists ? 'Editar huésped' : 'Nuevo huésped' }}</h1>
<form method="POST" action="{{ $huesped->exists ? route('huespedes.actualizar', $huesped) : route('huespedes.guardar') }}" class="card card-body">
    @csrf @if ($huesped->exists) @method('PUT') @endif
    <x-campo nombre="nombre" etiqueta="Nombre completo" :valor="$huesped->nombre" maxlength="150" autocomplete="name" required />
    <div class="row">
        <div class="col-md-6"><x-campo nombre="telefono" etiqueta="Teléfono" tipo="tel" :valor="$huesped->telefono" maxlength="80" autocomplete="tel" required /></div>
        <div class="col-md-6"><x-campo nombre="numero_identidad" etiqueta="Número de identidad (opcional)" :valor="$huesped->numero_identidad" maxlength="80" autocomplete="off" /></div>
    </div>
    <p class="form-text">Conserva el código de país del teléfono cuando corresponda. La identidad admite letras, números, espacios y guiones; se conservará el formato registrado.</p>
    <x-campo nombre="nacionalidad" etiqueta="Nacionalidad (opcional)" :valor="$huesped->nacionalidad" maxlength="120" />
    <div class="form-check mb-4">
        <input type="hidden" name="vip" value="0">
        <input id="vip" name="vip" value="1" type="checkbox" class="form-check-input" @checked(in_array(old('vip', $huesped->vip), [1, '1', true], true))>
        <label for="vip" class="form-check-label">VIP</label>
        @error('vip')<p class="text-danger">{{ $message }}</p>@enderror
    </div>
    @foreach (['notas' => 'Notas internas', 'notas_incidentes' => 'Notas de incidentes'] as $campo => $etiqueta)
        <div class="mb-3">
            <label for="{{ $campo }}" class="form-label">{{ $etiqueta }}</label>
            <textarea id="{{ $campo }}" name="{{ $campo }}" rows="3" maxlength="5000" class="form-control @error($campo) is-invalid @enderror"
                @error($campo) aria-invalid="true" aria-describedby="error-{{ $campo }}" @enderror>{{ is_string(old($campo, $huesped->{$campo})) ? old($campo, $huesped->{$campo}) : '' }}</textarea>
            @error($campo)<div class="invalid-feedback" id="error-{{ $campo }}">{{ $message }}</div>@enderror
        </div>
    @endforeach
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Guardar huésped</button>
        <a class="btn btn-outline-secondary" href="{{ auth()->user()->can('huespedes.ver') ? route('huespedes.listar') : route('inicio') }}">Cancelar</a>
    </div>
</form>
@endsection
