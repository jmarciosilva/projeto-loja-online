@extends('layouts.app')

@section('title', config('app.name'))

@section('content')
    {{-- Banners e navegação vêm dos serviços públicos, nunca de consultas na Blade. --}}
    @include('partials.hero-banners')

    <section class="mt-12 space-y-5" aria-labelledby="produtos-em-destaque">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><p class="text-sm font-semibold uppercase tracking-wide text-gray-500">Nossa seleção</p><h2 id="produtos-em-destaque" class="mt-1 text-2xl font-semibold">Produtos em destaque</h2></div>
            <a href="{{ route('catalog.index') }}" class="text-sm font-semibold underline underline-offset-4">Ver catálogo</a>
        </div>
        @if ($featuredProducts->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-600">Novidades da loja aparecerão aqui em breve.</div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">@foreach ($featuredProducts as $product)@include('catalog.partials.product-card', ['product' => $product])@endforeach</div>
        @endif
    </section>

    <section class="mt-10 grid min-w-0 gap-6 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:mt-12 sm:gap-8 sm:p-8 lg:mt-0 lg:grid-cols-[1.3fr_0.7fr] lg:items-center lg:p-10">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-gray-500">
                Uma curadoria para o cotidiano
            </p>
            <h1 class="mt-3 max-w-2xl break-words text-2xl font-semibold tracking-tight text-gray-950 sm:text-4xl">
                Mais leveza nos pequenos momentos.
            </h1>
            <p class="mt-4 max-w-2xl text-base leading-7 text-gray-600">
                Descubra peças, cuidados e detalhes escolhidos para acompanhar a sua rotina — com simplicidade, aconchego e personalidade.
            </p>
            <a href="{{ route('pages.show', ['slug' => 'sobre-a-loja']) }}"
               class="mt-6 inline-flex items-center rounded-lg bg-gray-900 px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-gray-700">
                Conheça a loja <span aria-hidden="true" class="ml-2">→</span>
            </a>
        </div>

        <div class="grid grid-cols-1 gap-3 text-center sm:grid-cols-2 sm:gap-4">
            <div class="rounded-xl bg-[#f5f2ec] px-4 py-6">
                <p class="text-sm font-semibold text-gray-900">Escolhas cuidadosas</p>
                <p class="mt-2 text-sm leading-6 text-gray-600">Uma seleção feita com atenção aos detalhes.</p>
            </div>
            <div class="rounded-xl bg-[#edf0e9] px-4 py-6">
                <p class="text-sm font-semibold text-gray-900">Feito para durar</p>
                <p class="mt-2 text-sm leading-6 text-gray-600">Qualidade e praticidade para o dia a dia.</p>
            </div>
        </div>
    </section>

    <section class="mt-14 border-t border-gray-200 pt-8 text-center">
        <p class="text-sm text-gray-600">Conheça nossa proposta e acompanhe as novidades da loja.</p>
        <a href="{{ route('pages.show', ['slug' => 'contato']) }}"
           class="mt-3 inline-block text-sm font-semibold text-gray-900 underline underline-offset-4 hover:text-gray-600">
            Fale com a gente
        </a>
    </section>
@endsection
