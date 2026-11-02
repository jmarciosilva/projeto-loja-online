@extends('layouts.admin')
@section('title', 'Novo produto')
@section('content')
<div class="space-y-5">
    <a class="text-sm underline" href="{{ route('admin.products.index') }}">← Produtos</a>
    <h2 class="text-lg font-semibold">Novo produto</h2>

    @if ($categories->isEmpty())
        <div role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-5">
            <p class="font-medium">Cadastre uma categoria antes de criar um produto.</p>
            <a class="mt-2 inline-block font-semibold underline" href="{{ route('admin.categories.create') }}">Cadastrar categoria</a>
        </div>
    @else
        @include('admin.products.partials.form', [
            'action' => route('admin.products.store'),
            'method' => 'POST',
            'submitLabel' => 'Criar produto',
            'product' => new \App\Models\Product,
        ])
    @endif
</div>
@endsection
