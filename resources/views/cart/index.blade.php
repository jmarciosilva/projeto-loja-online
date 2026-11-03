@extends('layouts.app')

@section('title', 'Carrinho')

@section('content')
    <main class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-extrabold text-slate-900">Carrinho</h1>
        <livewire:cart.cart-page />
    </main>
@endsection
