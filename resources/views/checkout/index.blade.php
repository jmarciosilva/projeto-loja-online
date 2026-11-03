@extends('layouts.app')

@section('title', 'Finalizar compra')

@section('content')
    <main class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <nav aria-label="Etapas da compra" class="mb-6">
            <ol class="flex flex-wrap gap-2 text-sm font-semibold">
                <li class="text-slate-500">Carrinho</li>
                <li aria-hidden="true" class="text-slate-400">/</li>
                <li aria-current="step" class="theme-text-primary">Identificação e endereço</li>
                <li aria-hidden="true" class="text-slate-400">/</li>
                <li class="text-slate-400">Entrega</li>
                <li aria-hidden="true" class="text-slate-400">/</li>
                <li class="text-slate-400">Revisão</li>
                <li aria-hidden="true" class="text-slate-400">/</li>
                <li class="text-slate-400">Pagamento</li>
            </ol>
        </nav>

        <h1 class="text-3xl font-extrabold text-slate-900">Finalizar compra</h1>

        <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem] lg:items-start">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="titulo-identificacao">
                <h2 id="titulo-identificacao" class="sr-only">Identificação e endereço</h2>
                <livewire:checkout.buyer-form />
            </section>

            <aside class="rounded-2xl border border-slate-200 bg-slate-50 p-5 lg:sticky lg:top-6" aria-labelledby="resumo-pedido">
                <h2 id="resumo-pedido" class="text-lg font-bold text-slate-900">Resumo</h2>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-600">Itens</dt>
                        <dd class="font-semibold text-slate-900">{{ $count }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-t border-slate-200 pt-3 text-base">
                        <dt class="font-bold text-slate-900">Subtotal</dt>
                        <dd class="theme-text-primary font-extrabold">{{ \App\Services\ProductService::formatPrice($subtotal) }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-slate-500">O frete será calculado na etapa de entrega.</p>
            </aside>
        </div>
    </main>
@endsection
