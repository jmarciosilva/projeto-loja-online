@extends('layouts.admin')
@section('title', 'Editar produto')
@section('content')
<div class="space-y-5"><a class="text-sm underline" href="{{ route('admin.products.index') }}">← Produtos</a><h2 class="text-lg font-semibold">Editar produto</h2>@include('admin.products.partials.form', ['action' => route('admin.products.update', $product), 'method' => 'PUT', 'submitLabel' => 'Salvar alterações'])</div>
@endsection
