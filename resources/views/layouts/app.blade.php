@php
    $brandLogo = $visualIdentity['logo'] ?? null;
    $brandLogoUrl = $brandLogo['url'] ?? asset('images/uau-netshopping-logo-header.webp');
    $brandLogoWidth = $brandLogo['width'] ?? 1200;
    $brandLogoHeight = $brandLogo['height'] ?? 642;
    $showAdminLink = ! auth()->check() || auth()->user()->canManageCatalog();
    $footerHasContact = filled($storefrontContact['email'] ?? '') || filled($storefrontContact['phone'] ?? '') || filled($storefrontContact['address'] ?? '');
    $footerPhone = preg_replace('/[^0-9+]/', '', (string) ($storefrontContact['phone'] ?? ''));
    $footerPhoneHref = preg_match('/^\+?[0-9]{8,15}$/', $footerPhone) === 1 ? 'tel:'.$footerPhone : null;
    $footerColumnCount = 2 + (($footerNavigation ?? []) !== [] ? 1 : 0) + ($footerHasContact ? 1 : 0);
    $footerColumnsClass = match ($footerColumnCount) {
        2 => 'lg:grid-cols-2',
        3 => 'lg:grid-cols-3',
        default => 'lg:grid-cols-4',
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $themeColors['primary'] }}">
    <title>{{ trim($__env->yieldContent('title', $storefrontBrand['name'] ?? 'UAU NetShopping')) }}</title>
    <link rel="canonical" href="{{ url()->current() }}">
    @yield('meta')
    @if ($visualIdentity['favicon'] ?? null)
        <link rel="icon" type="{{ $visualIdentity['favicon']['mimeType'] }}" href="{{ $visualIdentity['favicon']['url'] }}">
    @endif
    {{-- Alpine is bundled with Livewire 4; importing a second copy breaks component state. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme-styles')
    @livewireStyles
</head>
<body class="storefront-shell min-h-full overflow-x-clip storefront-background text-gray-900 antialiased">
    <header class="theme-border-accent border-t-4 border-b border-b-gray-200 bg-white" x-data="{ mobileMenuOpen: false }" @keydown.escape.window="if (mobileMenuOpen) { mobileMenuOpen = false; $nextTick(() => $refs.mobileMenuButton.focus()) }">
        <div class="hidden theme-bg-primary lg:block">
            <div class="mx-auto flex max-w-[1360px] items-center justify-between gap-6 px-6 py-2 text-xs font-medium text-white">
                <p class="flex items-center gap-2"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>Explore categorias e produtos publicados</p>
                <p class="flex items-center gap-2"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg>Consulte preço e disponibilidade em cada produto</p>
                <p class="flex items-center gap-2"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4zM8 9h8M8 13h5"/></svg>Veja a condição e os detalhes quando informados</p>
            </div>
        </div>

        <div class="mx-auto max-w-[1360px] px-4 sm:px-6">
            <div class="hidden min-h-[6rem] grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-8 py-3 lg:grid">
                <div class="flex min-w-0 items-center gap-4">
                    <a href="{{ route('home') }}" class="theme-focus flex shrink-0 items-center rounded-md" aria-label="{{ $storefrontBrand['name'] }} — início">
                        <img src="{{ $brandLogoUrl }}" alt="{{ $brandLogo['alt'] ?? $storefrontBrand['name'] }}" width="{{ $brandLogoWidth }}" height="{{ $brandLogoHeight }}" fetchpriority="high" class="h-auto max-h-16 w-auto max-w-[10rem] object-contain">
                    </a>
                    @if (filled($storefrontBrand['slogan'] ?? ''))<span class="theme-text-primary max-w-52 border-l border-slate-200 pl-4 text-base font-bold leading-snug">{{ $storefrontBrand['slogan'] }}</span>@endif
                </div>

                <form method="GET" action="{{ route('catalog.index') }}" role="search" class="mx-auto flex min-w-0 w-full max-w-3xl">
                    <label for="store-search" class="sr-only">Buscar produtos</label>
                    <input id="store-search" name="q" type="search" value="{{ request('q') }}" placeholder="Buscar produtos, livros e categorias..." class="theme-focus min-h-12 min-w-0 flex-1 rounded-l-xl border border-r-0 border-slate-300 bg-slate-50 px-5 text-sm focus:bg-white" maxlength="100">
                    <button type="submit" aria-label="Buscar" class="theme-bg-accent theme-focus flex min-h-12 min-w-14 items-center justify-center rounded-r-xl px-4 font-semibold transition hover:brightness-95"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg></button>
                </form>

                @if ($showAdminLink)<a href="{{ auth()->check() ? route('admin') : route('login') }}" class="theme-focus whitespace-nowrap rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">{{ auth()->check() ? 'Administração' : 'Acesso administrativo' }}</a>@endif
            </div>

            <div class="grid min-h-[4.25rem] grid-cols-[3rem_minmax(0,1fr)_3rem] items-center gap-2 py-2 lg:hidden">
                <button x-ref="mobileMenuButton" type="button" class="theme-focus inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-slate-200 text-slate-800" @click="mobileMenuOpen = !mobileMenuOpen; if (mobileMenuOpen) $nextTick(() => document.querySelector('#mobile-navigation a, #mobile-navigation button, #mobile-navigation summary')?.focus())" :aria-expanded="mobileMenuOpen.toString()" aria-controls="mobile-navigation" :aria-label="mobileMenuOpen ? 'Fechar menu' : 'Abrir menu'">
                    <svg x-show="!mobileMenuOpen" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg x-show="mobileMenuOpen" x-cloak aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
                <a href="{{ route('home') }}" class="theme-focus mx-auto flex max-w-[10rem] items-center justify-center rounded-md" aria-label="{{ $storefrontBrand['name'] }} — início">
                    <img src="{{ $brandLogoUrl }}" alt="{{ $brandLogo['alt'] ?? $storefrontBrand['name'] }}" width="{{ $brandLogoWidth }}" height="{{ $brandLogoHeight }}" fetchpriority="high" class="h-auto max-h-14 w-auto max-w-full object-contain">
                </a>
                <span aria-hidden="true"></span>
            </div>

            <form method="GET" action="{{ route('catalog.index') }}" role="search" class="flex pb-3 lg:hidden">
                <label for="store-search-mobile" class="sr-only">Buscar produtos</label>
                <input id="store-search-mobile" name="q" type="search" value="{{ request('q') }}" placeholder="Buscar produtos, livros e categorias..." class="theme-focus min-h-11 min-w-0 flex-1 rounded-l-xl border border-r-0 border-slate-300 bg-slate-50 px-4 text-sm" maxlength="100">
                <button type="submit" aria-label="Buscar" class="theme-bg-accent theme-focus flex min-h-11 min-w-12 items-center justify-center rounded-r-xl px-3"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg></button>
            </form>

            <div class="hidden min-w-0 items-center gap-3 border-t border-slate-100 py-2 lg:flex">
                @if (($catalogCategories ?? collect())->isNotEmpty())
                    <details class="group relative shrink-0">
                        <summary class="theme-bg-accent theme-focus flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm transition hover:brightness-95 [&::-webkit-details-marker]:hidden"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>Categorias <span aria-hidden="true" class="text-xs">▾</span></summary>
                        <ul class="absolute left-0 top-full z-40 mt-2 max-h-[70vh] min-w-64 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                            @foreach ($catalogCategories as $category)
                                <li class="py-1">
                                    @if ($category->catalog_link_visible)<a href="{{ route('categories.show', $category->slug) }}" class="theme-focus block min-h-10 rounded-lg px-3 py-2 text-sm font-semibold hover:bg-slate-50">{{ $category->name }}</a>@else<span class="block px-3 py-2 text-xs font-bold uppercase tracking-wide text-slate-500">{{ $category->name }}</span>@endif
                                    @foreach ($category->children as $child)<a href="{{ route('categories.show', $child->slug) }}" class="theme-focus block min-h-10 rounded-lg py-2 pl-6 pr-3 text-sm text-slate-700 hover:bg-slate-50">{{ $child->name }}</a>@endforeach
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
                <div class="min-w-0 flex-1 overflow-x-auto">
                    @include('partials.main-navigation', ['nodes' => $mainNavigation ?? [], 'mobile' => false])
                </div>
                <nav aria-label="Atalhos da loja" class="flex shrink-0 items-center gap-1">
                    @if ($homeNavigationVisible ?? true)<a href="{{ route('home') }}" class="theme-focus whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold hover:bg-slate-50">Início</a>@endif
                    @if ($catalogNavigationVisible ?? true)<a href="{{ route('catalog.index') }}" class="theme-focus whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold hover:bg-slate-50">Todos os produtos</a>@endif
                </nav>
            </div>

            <div id="mobile-navigation" x-show="mobileMenuOpen" x-cloak @click="if ($event.target.closest('a')) mobileMenuOpen = false" class="border-t border-gray-200 py-3 lg:!hidden" @click.outside="mobileMenuOpen = false">
                <div class="mb-2 flex items-center justify-between px-1">
                    <span class="text-sm font-semibold text-gray-600">Navegação</span>
                    <button type="button" class="theme-focus min-h-11 rounded-lg px-3 text-sm font-semibold underline underline-offset-4" @click="mobileMenuOpen = false; $nextTick(() => $refs.mobileMenuButton.focus())">Fechar menu</button>
                </div>
                <nav aria-label="Navegação móvel">
                    @if ($homeNavigationVisible ?? true)<a href="{{ route('home') }}" class="theme-focus block min-h-11 rounded-md px-3 py-2.5 text-base font-semibold text-gray-800 hover:bg-gray-50">Início</a>@endif
                    @include('partials.main-navigation', ['nodes' => $mainNavigation ?? [], 'mobile' => true])
                    @if ($catalogNavigationVisible ?? true)
                        <a href="{{ route('catalog.index') }}" @if(request()->url() === route('catalog.index')) aria-current="page" @endif class="theme-focus block min-h-11 rounded-md px-3 py-2.5 text-base font-semibold text-gray-800 hover:bg-gray-50">Produtos</a>
                    @endif
                    @if (($catalogCategories ?? collect())->isNotEmpty())
                        <details class="mx-1 rounded-lg">
                            <summary class="theme-focus flex min-h-11 cursor-pointer list-none items-center justify-between rounded-md px-3 py-2.5 text-base font-semibold text-gray-800 hover:bg-gray-50 [&::-webkit-details-marker]:hidden">Categorias <span aria-hidden="true">▾</span></summary>
                            <ul class="ml-4 border-l border-gray-200 pl-2">
                                @foreach ($catalogCategories as $category)
                                    <li>@if ($category->catalog_link_visible)<a href="{{ route('categories.show', $category->slug) }}" class="theme-focus block min-h-11 break-words rounded-md px-3 py-2.5 text-base font-semibold text-gray-800 [overflow-wrap:anywhere] hover:bg-gray-50">{{ $category->name }}</a>@else<span class="block px-3 py-2 text-xs font-bold uppercase tracking-wide text-slate-500">{{ $category->name }}</span>@endif @foreach ($category->children as $child)<a href="{{ route('categories.show', $child->slug) }}" class="theme-focus block min-h-11 break-words rounded-md py-2.5 pl-7 pr-3 text-sm text-gray-700 [overflow-wrap:anywhere] hover:bg-gray-50">{{ $child->name }}</a>@endforeach</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                    @if ($showAdminLink)<a href="{{ auth()->check() ? route('admin') : route('login') }}" class="theme-focus mt-1 block min-h-11 rounded-md px-3 py-2.5 text-base font-semibold text-gray-800 hover:bg-gray-50">{{ auth()->check() ? 'Administração' : 'Acesso administrativo' }}</a>@endif
                </nav>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-[1360px] px-4 py-5 sm:px-6 sm:py-8">
        @yield('content')
    </main>

    <footer class="storefront-footer theme-bg-primary mt-10 sm:mt-16">
        <div @class(['mx-auto grid max-w-[1360px] grid-cols-1 gap-8 px-4 pb-10 pt-9 sm:px-6 sm:pt-12 md:grid-cols-2', $footerColumnsClass])>
            <section class="min-w-0" aria-labelledby="footer-brand-title">
                <h2 id="footer-brand-title" class="sr-only">{{ $storefrontBrand['name'] }}</h2>
                <a href="{{ route('home') }}" class="theme-focus inline-flex rounded-lg bg-white p-2" aria-label="{{ $storefrontBrand['name'] }} — início">
                    <img src="{{ $brandLogoUrl }}" alt="{{ $brandLogo['alt'] ?? $storefrontBrand['name'] }}" width="{{ $brandLogoWidth }}" height="{{ $brandLogoHeight }}" loading="lazy" class="h-auto max-h-14 w-auto max-w-40 object-contain">
                </a>
                <p class="mt-3 text-base font-semibold text-white">{{ $storefrontBrand['slogan'] }}</p>
                <p class="mt-2 max-w-sm text-sm leading-6 text-white/80">Encontre produtos no catálogo e consulte as informações de cada item.</p>
            </section>

            @if (($footerNavigation ?? []) !== [])
                <section class="min-w-0" aria-labelledby="footer-institutional-title">
                    <h2 id="footer-institutional-title" class="text-base font-bold text-white">Institucional</h2>
                    @include('partials.footer-navigation', ['nodes' => $footerNavigation])
                </section>
            @endif

            <section class="min-w-0" aria-labelledby="footer-explore-title">
                <h2 id="footer-explore-title" class="text-base font-bold text-white">Explore a loja</h2>
                <nav aria-label="Explorar produtos e categorias" class="mt-3">
                    <ul class="space-y-1">
                        <li><a href="{{ route('catalog.index') }}" class="theme-focus inline-flex min-h-10 max-w-full items-center break-words rounded text-sm underline-offset-4 hover:underline">Todos os produtos</a></li>
                        @foreach (($publicCategories ?? collect()) as $category)
                            <li>
                                <a href="{{ route('categories.show', $category->slug) }}" class="theme-focus inline-flex min-h-10 max-w-full items-center break-words rounded text-sm font-semibold underline-offset-4 hover:underline">{{ $category->name }}</a>
                                @if ($category->children->isNotEmpty())
                                    <ul class="ml-3 border-l border-white/20 pl-3">
                                        @foreach ($category->children as $child)
                                            <li><a href="{{ route('categories.show', $child->slug) }}" class="theme-focus inline-flex min-h-9 max-w-full items-center break-words rounded py-1 text-sm underline-offset-4 hover:underline">{{ $child->name }}</a></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </section>

            @if ($footerHasContact)
                <section class="min-w-0" aria-labelledby="footer-contact-title">
                    <h2 id="footer-contact-title" class="text-base font-bold text-white">Atendimento</h2>
                    <address class="mt-3 space-y-2 text-sm not-italic leading-6 text-white/85">
                        @if (filled($storefrontContact['email'] ?? ''))<p><a class="theme-focus break-all rounded underline underline-offset-4" href="mailto:{{ $storefrontContact['email'] }}">{{ $storefrontContact['email'] }}</a></p>@endif
                        @if (filled($storefrontContact['phone'] ?? ''))<p>@if ($footerPhoneHref)<a class="theme-focus rounded underline underline-offset-4" href="{{ $footerPhoneHref }}">{{ $storefrontContact['phone'] }}</a>@else{{ $storefrontContact['phone'] }}@endif</p>@endif
                        @if (filled($storefrontContact['address'] ?? ''))<p class="whitespace-pre-line">{{ $storefrontContact['address'] }}</p>@endif
                    </address>
                </section>
            @endif
        </div>
        <div class="border-t border-white/15">
            <div class="mx-auto flex max-w-[1360px] flex-col gap-1 px-4 py-4 text-xs text-white/75 sm:px-6 sm:flex-row sm:items-center sm:justify-between">
                <span>&copy; {{ date('Y') }} {{ $storefrontBrand['name'] }}</span>
                <span class="text-white/60">{{ $storefrontBrand['slogan'] }}</span>
            </div>
        </div>
    </footer>
    <div x-data="backToTop" x-cloak x-show="visible" x-transition.opacity class="fixed z-50" style="right: max(1rem, env(safe-area-inset-right)); bottom: max(1rem, env(safe-area-inset-bottom));" :aria-hidden="(!visible).toString()">
        <button type="button" @click="goToTop" aria-label="Voltar ao topo" title="Voltar ao topo" :tabindex="visible ? 0 : -1" class="theme-focus theme-bg-accent flex min-h-12 min-w-12 items-center justify-center rounded-full shadow-lg transition hover:brightness-95 motion-reduce:transition-none sm:min-h-14 sm:min-w-14">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m-7 7 7-7 7 7"/></svg>
        </button>
    </div>
    @livewireScripts
</body>
</html>
