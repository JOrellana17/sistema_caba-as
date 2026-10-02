@extends('layouts.administracion')
@section('titulo', 'Buscar cliente')
@section('contenido')
<h1 class="h3 mb-3">Buscar cliente</h1>
<p class="text-body-secondary">Encuentra una ficha existente. Seleccionar un cliente no crea una reservación, factura ni pago.</p>
<div class="card card-body"><x-buscador-huesped :requerido="false" /></div>
@endsection
