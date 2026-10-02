@extends('layouts.administracion')
@section('titulo', 'Registro de auditoría')
@section('contenido')
<h1 class="h2 mb-4">Registro de auditoría #{{ $registro->id }}</h1>
<div class="card border-0 shadow-sm"><div class="card-body">
<dl class="row">
    <dt class="col-md-3">Usuario</dt><dd class="col-md-9">{{ $registro->usuario?->nombre ?? 'Sin usuario autenticado / consola' }}</dd>
    <dt class="col-md-3">Fecha ({{ config('app.timezone') }})</dt><dd class="col-md-9">{{ $registro->created_at->format('d/m/Y H:i:s') }}</dd>
    <dt class="col-md-3">Operación</dt><dd class="col-md-9">{{ $registro->modulo }} · {{ $registro->accion }}</dd>
    <dt class="col-md-3">Registro afectado</dt><dd class="col-md-9">{{ $registro->registro_id ?? 'No aplica' }}</dd>
    <dt class="col-md-3">Dirección IP</dt><dd class="col-md-9">{{ $registro->direccion_ip ?? 'Sin registrar' }}</dd>
    <dt class="col-md-3">Agente de usuario</dt><dd class="col-md-9 text-break">{{ $registro->agente_usuario ?? 'Sin registrar' }}</dd>
</dl>
<div class="row g-3">
    <section class="col-md-6"><h2 class="h5">Valores anteriores</h2><pre class="text-wrap bg-body-tertiary p-3">{{ json_encode($registro->valores_anteriores ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></section>
    <section class="col-md-6"><h2 class="h5">Valores nuevos</h2><pre class="text-wrap bg-body-tertiary p-3">{{ json_encode($registro->valores_nuevos ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></section>
</div>
</div></div>
@endsection
