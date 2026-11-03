@extends('layouts.app')

@section('title', 'Carrinho')

@section('content')
    <main class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-extrabold text-slate-900">Carrinho</h1>

        @if (session('cart_error'))
            <p role="alert" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-950">{{ session('cart_error') }}</p>
        @endif

        <livewire:cart.cart-page />
    </main>
@endsection
