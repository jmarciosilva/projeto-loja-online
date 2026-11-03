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
    <div class="space-y-6 sm:space-y-8">
        @include('partials.hero-banners')

        <section aria-label="Como explorar a loja" class="hidden grid-cols-3 gap-3 rounded-2xl border border-slate-100 bg-white px-5 py-4 shadow-sm md:grid lg:px-8">
            <div class="flex items-center gap-3 border-r border-slate-100 pr-3"><span class="theme-text-primary flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-50"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4zM8 9h8M8 13h5"/></svg></span><p class="text-xs leading-5 text-slate-700 sm:text-sm"><strong class="block text-slate-900">Informações por produto</strong>Preço, condição e detalhes</p></div>
            <div class="flex items-center gap-3 border-r border-slate-100 px-2 sm:px-4"><span class="theme-text-primary flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-orange-50"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg></span><p class="text-xs leading-5 text-slate-700 sm:text-sm"><strong class="block text-slate-900">Encontre com facilidade</strong>Busca e navegação por categoria</p></div>
            <div class="flex items-center gap-3 pl-2 sm:pl-4"><span class="theme-text-primary flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5"/></svg></span><p class="text-xs leading-5 text-slate-700 sm:text-sm"><strong class="block text-slate-900">Descubra o catálogo</strong>Produtos publicados pela loja</p></div>
        </section>

        <section class="space-y-3" aria-labelledby="store-categories">
            <div class="flex items-end justify-between gap-3"><div><p class="theme-text-secondary text-xs font-semibold uppercase tracking-[.12em]">Descubra</p><h2 id="store-categories" class="theme-text-primary mt-1 text-lg font-bold sm:text-xl">Explore por categoria</h2></div><a href="{{ route('catalog.index') }}" class="theme-focus shrink-0 rounded text-sm font-semibold underline underline-offset-4">Ver todas <span aria-hidden="true">→</span></a></div>
            @if ($homepageCategories->isEmpty())
                <p class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-sm text-slate-600">As categorias da loja aparecerão aqui em breve.</p>
            @else
                <ul class="grid grid-cols-2 gap-2 sm:gap-3 min-[390px]:grid-cols-4">
                    @foreach ($homepageCategories as $category)
                        <li class="min-w-0">@include('partials.category-tile', ['category' => $category, 'toneIndex' => $loop->index])</li>
                        @foreach ($category->children as $child)
                            <li class="min-w-0">@include('partials.category-tile', ['category' => $child, 'toneIndex' => $loop->parent->index + $loop->index + 1])</li>
                        @endforeach
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="space-y-3" aria-labelledby="produtos-em-destaque">
            <div class="flex items-end justify-between gap-3"><div><p class="theme-text-secondary text-xs font-semibold uppercase tracking-[.12em]">Para descobrir</p><h2 id="produtos-em-destaque" class="theme-text-primary mt-1 text-xl font-bold sm:text-2xl">{{ $showFeaturedLabel ? 'Produtos em destaque' : 'Novidades no catálogo' }}</h2></div><a href="{{ route('catalog.index') }}" class="theme-focus shrink-0 rounded text-sm font-semibold underline underline-offset-4">Ver todos</a></div>
            @if ($featuredProducts->isEmpty())
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-600">Produtos publicados aparecerão aqui quando estiverem disponíveis no catálogo.</div>
            @else
                <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">@foreach ($featuredProducts as $product)@include('catalog.partials.product-card', ['product' => $product])@endforeach</div>
            @endif
        </section>

        @foreach ($discoveryCategories as $section)
            <section class="space-y-3" aria-labelledby="category-section-{{ $section['category']->id }}">
                <div class="flex items-end justify-between gap-3"><div><p class="theme-text-secondary text-xs font-semibold uppercase tracking-[.12em]">{{ $section['category']->parent?->name ?? 'Seleção da loja' }}</p><h2 id="category-section-{{ $section['category']->id }}" class="theme-text-primary mt-1 text-xl font-bold sm:text-2xl">{{ $section['category']->name }}</h2></div><a href="{{ route('categories.show', $section['category']->slug) }}" class="theme-focus shrink-0 rounded text-sm font-semibold underline underline-offset-4">Ver categoria</a></div>
                <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">@foreach ($section['products'] as $product)@include('catalog.partials.product-card', ['product' => $product])@endforeach</div>
            </section>
        @endforeach
    </div>
@endsection
