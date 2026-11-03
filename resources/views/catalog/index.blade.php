@extends('layouts.app')

@section('title', ($selectedCategory?->name ?? 'Produtos').' · '.$storefrontBrand['name'])

@section('content')
    @php
        $subcategories = $selectedCategory?->parent?->children ?? $selectedCategory?->children ?? collect();
        $subcategories = $subcategories->where('is_active', true)->sortBy('sort_order')->values();
        $activeFilterCount = collect($filters ?? [])->only(['category', 'q', 'condition', 'price_min', 'price_max', 'availability'])->filter(fn ($value) => filled($value))->count();
    @endphp
    <div class="space-y-6 sm:space-y-8">
        <header class="space-y-3">
            <nav aria-label="Breadcrumb" class="text-xs text-slate-500"><a class="theme-focus rounded underline underline-offset-2" href="{{ route('home') }}">Início</a><span class="px-1" aria-hidden="true">/</span>@if ($selectedCategory?->parent)<a class="theme-focus rounded underline underline-offset-2" href="{{ route('categories.show', $selectedCategory->parent->slug) }}">{{ $selectedCategory->parent->name }}</a><span class="px-1" aria-hidden="true">/</span>@endif<span aria-current="page" class="font-medium text-slate-700">{{ $selectedCategory?->name ?? 'Produtos' }}</span></nav>
            <div class="flex flex-wrap items-end justify-between gap-3"><div><p class="theme-text-secondary text-xs font-bold uppercase tracking-[.12em]">Catálogo UAU</p><h1 class="theme-text-primary mt-1 break-words text-2xl font-bold tracking-tight sm:text-3xl">{{ $selectedCategory?->name ?? 'Todos os produtos' }}</h1>@if ($selectedCategory?->description)<p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">{{ $selectedCategory->description }}</p>@endif</div></div>
            @if ($subcategories->isNotEmpty())
                <nav class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0" aria-label="Subcategorias de {{ $selectedCategory->name }}">
                    @foreach ($subcategories as $child)<a @if($selectedCategory?->id === $child->id) aria-current="page" @endif class="theme-focus inline-flex min-h-10 shrink-0 items-center rounded-full border px-4 text-sm font-semibold transition {{ $selectedCategory?->id === $child->id ? 'border-blue-900 bg-blue-50 text-blue-900' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-400' }}" href="{{ route('categories.show', $child->slug) }}">{{ $child->name }}</a>@endforeach
                </nav>
            @endif
        </header>

        <details class="group rounded-2xl border border-slate-200 bg-white shadow-sm" @if (request()->hasAny(['q', 'condition', 'price_min', 'price_max', 'availability'])) open @endif>
            <summary class="theme-focus flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 rounded-2xl px-4 py-3 font-semibold text-slate-800 [&::-webkit-details-marker]:hidden"><span class="flex items-center gap-2"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M7 12h10m-7 6h4"/><circle cx="8" cy="6" r="1.5" fill="currentColor"/><circle cx="15" cy="12" r="1.5" fill="currentColor"/></svg>Filtrar produtos @if($activeFilterCount > 0)<span class="theme-bg-primary inline-flex min-h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-xs" aria-label="{{ $activeFilterCount }} filtros ativos">{{ $activeFilterCount }}</span>@endif</span><span class="flex items-center gap-2 text-sm text-slate-500"><span class="group-open:hidden">Mostrar</span><span class="hidden group-open:inline">Ocultar</span><span aria-hidden="true" class="transition-transform group-open:rotate-180">⌄</span></span></summary>
            <form method="GET" action="{{ $selectedCategory ? route('categories.show', $selectedCategory->slug) : route('catalog.index') }}" class="grid gap-3 border-t border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-4">
                @if (filled($filters['sort'] ?? null))<input type="hidden" name="sort" value="{{ $filters['sort'] }}">@endif
                @if (! $selectedCategory)<div><label for="catalog-category" class="block text-xs font-semibold text-slate-600">Categoria</label><select id="catalog-category" name="categoria" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm"><option value="">Todas</option>@foreach ($categories as $category)<option value="{{ $category->slug }}" @selected(($filters['category'] ?? null) === $category->slug)>{{ $category->parent ? $category->parent->name.' › ' : '' }}{{ $category->name }}</option>@endforeach</select></div>@endif
                <div class="{{ $selectedCategory ? '' : 'lg:col-span-2' }}"><label for="catalog-query" class="block text-xs font-semibold text-slate-600">Buscar produto</label><input id="catalog-query" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Nome, descrição ou SKU" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm"></div>
                <div><label for="catalog-condition" class="block text-xs font-semibold text-slate-600">Condição</label><select id="catalog-condition" name="condition" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm"><option value="">Todas</option><option value="new" @selected(($filters['condition'] ?? '') === 'new')>Novo</option><option value="used" @selected(($filters['condition'] ?? '') === 'used')>Usado</option></select></div>
                <div><label for="catalog-availability" class="block text-xs font-semibold text-slate-600">Disponibilidade</label><select id="catalog-availability" name="availability" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm"><option value="">Todas</option><option value="available" @selected(($filters['availability'] ?? '') === 'available')>Disponível</option><option value="sold_out" @selected(($filters['availability'] ?? '') === 'sold_out')>Esgotado</option><option value="unreported" @selected(($filters['availability'] ?? '') === 'unreported')>Não informada</option></select></div>
                <div><label for="catalog-min-price" class="block text-xs font-semibold text-slate-600">Preço mínimo (R$)</label><input id="catalog-min-price" name="price_min" type="number" min="0" step="0.01" value="{{ $filters['price_min'] ?? '' }}" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm"></div>
                <div><label for="catalog-max-price" class="block text-xs font-semibold text-slate-600">Preço máximo (R$)</label><input id="catalog-max-price" name="price_max" type="number" min="0" step="0.01" value="{{ $filters['price_max'] ?? '' }}" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm"></div>
                <div class="flex items-end gap-2"><button type="submit" class="theme-bg-accent theme-focus min-h-11 flex-1 rounded-full px-5 text-sm font-bold">Aplicar</button><a href="{{ $selectedCategory ? route('categories.show', $selectedCategory->slug) : route('catalog.index') }}" class="theme-focus inline-flex min-h-11 items-center rounded-full border border-slate-300 px-4 text-sm font-semibold">Limpar</a></div>
            </form>
        </details>

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white/70 px-3 py-2 sm:px-4">
            <p class="text-sm text-slate-600"><span class="font-semibold text-slate-800">{{ $products->total() }}</span> {{ $products->total() === 1 ? 'produto' : 'produtos' }}</p>
            <form method="GET" action="{{ $selectedCategory ? route('categories.show', $selectedCategory->slug) : route('catalog.index') }}" class="flex min-w-0 items-center gap-2">
                @foreach (['q', 'condition', 'price_min', 'price_max', 'availability'] as $preservedFilter)
                    @if (filled($filters[$preservedFilter] ?? null))<input type="hidden" name="{{ $preservedFilter }}" value="{{ $filters[$preservedFilter] }}">@endif
                @endforeach
                <label for="catalog-sort" class="shrink-0 text-xs font-semibold text-slate-600 sm:text-sm">Ordenar:</label>
                <select id="catalog-sort" name="sort" class="theme-focus min-h-10 min-w-0 rounded-xl border border-slate-300 bg-white px-2 text-sm sm:px-3"><option value="relevance" @selected(($filters['sort'] ?? 'relevance') === 'relevance')>Relevância</option><option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Menor preço</option><option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Maior preço</option><option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Mais recentes</option></select>
                <button type="submit" class="theme-focus min-h-10 rounded-xl border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:text-sm">Aplicar</button>
            </form>
        </div>

        @if ($products->isEmpty())
            <section class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center"><p class="text-lg font-semibold text-slate-800">Nenhum produto encontrado</p><p class="mt-2 text-sm text-slate-600">Tente mudar a busca ou remover alguns filtros.</p><a href="{{ $selectedCategory ? route('categories.show', $selectedCategory->slug) : route('catalog.index') }}" class="theme-bg-accent theme-focus mt-5 inline-flex min-h-11 items-center rounded-full px-5 text-sm font-bold">Limpar filtros</a></section>
        @else
            <div class="grid min-w-0 grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">@foreach ($products as $product)@include('catalog.partials.product-card', ['product' => $product])@endforeach</div>
            @if ($products->hasPages())<div class="overflow-x-auto rounded-xl bg-white p-3">{{ $products->withQueryString()->links() }}</div>@endif
        @endif
    </div>
@endsection
