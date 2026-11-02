@extends('layouts.app')

@section('title', $storefrontBrand['name'])

@section('meta')
    <meta name="description" content="{{ $storefrontBrand['name'] }} — {{ $storefrontBrand['slogan'] }}">
    <meta property="og:title" content="{{ $storefrontBrand['name'] }}">
    <meta property="og:description" content="{{ $storefrontBrand['slogan'] }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ route('home') }}">
    <meta property="og:image" content="{{ $visualIdentity['logo']['url'] ?? asset('images/uau-netshopping-logo.webp') }}">
@endsection

@section('content')
    <section class="mb-10 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="storefront-welcome">
        @include('partials.hero-banners')
        <div class="px-5 py-8 sm:px-8 sm:py-10 lg:px-12">
            <p class="theme-text-secondary text-sm font-semibold uppercase tracking-wide">Bem-vindo à loja</p>
            <h1 id="storefront-welcome" class="theme-text-primary mt-2 break-words text-3xl font-bold tracking-tight sm:text-4xl">{{ $storefrontBrand['name'] }}</h1>
            <p class="mt-3 max-w-2xl text-lg leading-7 text-slate-700">{{ $storefrontBrand['slogan'] }}</p>
            <a href="{{ route('catalog.index') }}" class="theme-focus theme-bg-accent mt-6 inline-flex min-h-12 items-center rounded-lg px-5 py-3 text-sm font-bold transition hover:brightness-95">
                Explorar produtos <span aria-hidden="true" class="ml-2">→</span>
            </a>
        </div>
    </section>

    <section class="mt-10 space-y-5" aria-labelledby="store-categories">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><p class="theme-text-secondary text-sm font-semibold uppercase tracking-wide">Explore</p><h2 id="store-categories" class="theme-text-primary mt-1 text-2xl font-semibold">Categorias</h2></div>
            <a href="{{ route('catalog.index') }}" class="theme-focus theme-text-primary rounded text-sm font-semibold underline underline-offset-4">Ver catálogo</a>
        </div>
        @if ($publicCategories->isEmpty())
            <p class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-700">As categorias da loja aparecerão aqui em breve.</p>
        @else
            <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($publicCategories as $category)
                    <li><a href="{{ route('categories.show', $category->slug) }}" class="theme-focus theme-category-card theme-text-primary flex min-h-14 items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 font-semibold shadow-sm"><span class="break-words [overflow-wrap:anywhere]">{{ $category->name }}</span><span aria-hidden="true">→</span></a></li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="mt-12 space-y-5" aria-labelledby="produtos-em-destaque">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><p class="theme-text-secondary text-sm font-semibold uppercase tracking-wide">Nossa seleção</p><h2 id="produtos-em-destaque" class="theme-text-primary mt-1 text-2xl font-semibold">Produtos em destaque</h2></div>
            <a href="{{ route('catalog.index') }}" class="theme-focus theme-text-primary rounded text-sm font-semibold underline underline-offset-4">Ver catálogo</a>
        </div>
        @if ($featuredProducts->isEmpty())
            <div class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-700">Produtos em destaque aparecerão aqui quando forem publicados.</div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">@foreach ($featuredProducts as $product)@include('catalog.partials.product-card', ['product' => $product])@endforeach</div>
        @endif
    </section>
@endsection
