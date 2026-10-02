@if (session('estado'))
    <div class="alert alert-success" role="status">{{ session('estado') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger" role="alert" tabindex="-1" data-resumen-errores>
        <p class="fw-semibold mb-2">Revisa los siguientes datos:</p>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
