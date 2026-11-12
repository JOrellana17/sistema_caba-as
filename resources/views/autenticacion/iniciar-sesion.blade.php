{{-- autenticacion: Presentación de iniciar sesion. --}}
@extends('layouts.base')
@section('titulo', 'Iniciar sesión')
@section('pagina')
<div class="acceso-diseno">
    <div class="acceso-marca">
        <x-marca-cabana />
        <p class="acceso-nombre">Cabañas El Capitán</p>
        <p class="acceso-descripcion mb-0 d-none d-md-block">Reservaciones, huéspedes y calendario de las cabañas en un solo lugar.</p>
    </div>
    <main id="contenido" class="acceso-formulario">
        <section aria-labelledby="titulo-acceso">
            <h1 id="titulo-acceso" class="h2 mb-2">Iniciar sesión</h1>
            <p class="text-body-secondary mb-4">Ingresa con tu cuenta de personal.</p>
            <x-mensajes />
            {{-- Envía los datos a la ruta indicada; los campos y reglas se revalidan en el servidor. --}}
            <form method="POST" action="{{ route('sesion.iniciar') }}">
                {{-- Incluye el token de sesión que protege la petición contra envíos desde otros sitios. --}}
                @csrf
                <div class="mb-3">
                    <label for="usuario" class="form-label">Usuario o correo</label>
                    <input id="usuario" name="usuario" type="text" class="form-control @error('usuario') is-invalid @enderror"
                        value="{{ is_string(old('usuario')) ? old('usuario') : '' }}" autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="255" required
                        {{-- Presenta el mensaje de validación asociado a este campo. --}}
                        @error('usuario') aria-invalid="true" aria-describedby="error-usuario" @enderror>
                    {{-- Presenta el mensaje de validación asociado a este campo. --}}
                    @error('usuario') <div id="error-usuario" class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label for="metodo" class="form-label">Acceder con</label>
                    <select id="metodo" name="metodo" class="form-select" data-metodo-acceso>
                        <option value="contrasena" @selected(old('metodo') !== 'pin')>Contraseña</option>
                        <option value="pin" @selected(old('metodo') === 'pin')>PIN</option>
                    </select>
                </div>
                <div class="mb-3" data-acceso-contrasena>
                    <label for="contrasena" class="form-label">Contraseña</label>
                    <input id="contrasena" name="contrasena" type="password" class="form-control @error('contrasena') is-invalid @enderror"
                        autocomplete="current-password" maxlength="1024" required
                        {{-- Presenta el mensaje de validación asociado a este campo. --}}
                        @error('contrasena') aria-invalid="true" aria-describedby="error-contrasena" @enderror>
                    {{-- Presenta el mensaje de validación asociado a este campo. --}}
                    @error('contrasena') <div id="error-contrasena" class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3" data-acceso-pin @if (old('metodo') !== 'pin') hidden @endif>
                    <label for="pin" class="form-label">PIN de seis dígitos</label>
                    <input id="pin" name="pin" type="password" inputmode="numeric" maxlength="6" autocomplete="off"
                        class="form-control @error('pin') is-invalid @enderror" disabled
                        {{-- Presenta el mensaje de validación asociado a este campo. --}}
                        @error('pin') aria-invalid="true" aria-describedby="error-pin" @enderror>
                    {{-- Presenta el mensaje de validación asociado a este campo. --}}
                    @error('pin') <div id="error-pin" class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="form-check mb-4">
                    <input id="recordar" name="recordar" type="checkbox" class="form-check-input" value="1" @checked(old('recordar'))>
                    <label class="form-check-label" for="recordar">Recordarme en este dispositivo</label>
                </div>
                <button class="btn btn-primary w-100 py-2" type="submit">Iniciar sesión</button>
            </form>
        </section>
    </main>
</div>
@endsection
