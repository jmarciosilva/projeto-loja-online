@php
    $brandLogo = $visualIdentity['logo'] ?? null;
    $brandLogoUrl = $brandLogo['url'] ?? asset('images/uau-netshopping-logo-header.webp');
    $brandLogoWidth = $brandLogo['width'] ?? 1200;
    $brandLogoHeight = $brandLogo['height'] ?? 642;
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
    <header class="theme-border-accent border-t-4 border-b border-b-gray-200 bg-white" x-data="{ mobileMenuOpen: false }" @keydown.escape.window="mobileMenuOpen = false">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="flex min-h-20 items-center justify-between gap-3 py-2 md:gap-6">
                <div class="flex min-w-0 shrink-0 items-center gap-4 xl:gap-5">
                <a href="{{ route('home') }}" class="theme-focus flex min-w-0 shrink-0 items-center rounded-md" aria-label="{{ $storefrontBrand['name'] }} — início">
                    <img
                        src="{{ $brandLogoUrl }}"
                        alt="{{ $brandLogo['alt'] ?? $storefrontBrand['name'] }}"
                        width="{{ $brandLogoWidth }}"
                        height="{{ $brandLogoHeight }}"
                        fetchpriority="high"
                        class="h-auto max-h-16 w-auto max-w-[10rem] object-contain sm:max-h-[4.75rem] sm:max-w-[12rem]"
                    >
                </a>
                    @if (filled($storefrontBrand['slogan'] ?? ''))
                        <span class="theme-text-primary hidden whitespace-nowrap border-l border-gray-200 pl-4 text-lg font-semibold leading-snug xl:block 2xl:text-xl">{{ $storefrontBrand['slogan'] }}</span>
                    @endif
                </div>

                <div class="hidden min-w-0 flex-1 items-center justify-end gap-4 md:flex lg:gap-6">
                    <div class="min-w-0 max-w-[min(58vw,52rem)] overflow-x-auto">
                        @include('partials.main-navigation', ['nodes' => $mainNavigation ?? [], 'mobile' => false])
                    </div>
                    @if ($catalogNavigationVisible ?? true)
                        <a href="{{ route('catalog.index') }}" @if(request()->url() === route('catalog.index')) aria-current="page" @endif class="theme-focus shrink-0 whitespace-nowrap rounded-md px-2 py-2 text-sm font-semibold text-gray-800 underline-offset-4 hover:underline">Produtos</a>
                    @endif
                    @if (($catalogCategories ?? collect())->isNotEmpty())
                        <details class="group relative shrink-0">
                            <summary class="theme-focus flex min-h-11 cursor-pointer list-none items-center gap-1 rounded-md px-2 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50 [&::-webkit-details-marker]:hidden">
                                Categorias <span aria-hidden="true" class="text-xs">▾</span>
                            </summary>
                            <ul class="absolute right-0 top-full z-40 mt-1 max-h-[70vh] min-w-56 overflow-y-auto rounded-xl border border-gray-200 bg-white p-2 shadow-lg">
                                @foreach ($catalogCategories as $category)
                                    <li><a href="{{ route('categories.show', $category->slug) }}" class="theme-focus block min-h-11 rounded-lg px-3 py-2.5 text-sm text-gray-800 hover:bg-gray-50">{{ $category->name }}</a></li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                    <a href="{{ auth()->check() ? route('admin') : route('login') }}" class="theme-focus shrink-0 whitespace-nowrap rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-800 transition-colors hover:bg-gray-50">Administração</a>
                </div>

                <button type="button" class="theme-focus inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-gray-300 text-gray-800 md:hidden" @click="mobileMenuOpen = !mobileMenuOpen" :aria-expanded="mobileMenuOpen.toString()" aria-controls="mobile-navigation" :aria-label="mobileMenuOpen ? 'Fechar menu' : 'Abrir menu'">
                    <svg x-show="!mobileMenuOpen" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg x-show="mobileMenuOpen" x-cloak aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <div id="mobile-navigation" x-show="mobileMenuOpen" x-cloak @click="if ($event.target.closest('a')) mobileMenuOpen = false" class="border-t border-gray-200 py-3 md:!hidden" @click.outside="mobileMenuOpen = false">
                <div class="mb-2 flex items-center justify-between px-1">
                    <span class="text-sm font-semibold text-gray-600">Navegação</span>
                    <button type="button" class="theme-focus min-h-11 rounded-lg px-3 text-sm font-semibold underline underline-offset-4" @click="mobileMenuOpen = false">Fechar menu</button>
                </div>
                <nav aria-label="Navegação móvel">
                    @include('partials.main-navigation', ['nodes' => $mainNavigation ?? [], 'mobile' => true])
                    @if ($catalogNavigationVisible ?? true)
                        <a href="{{ route('catalog.index') }}" @if(request()->url() === route('catalog.index')) aria-current="page" @endif class="theme-focus block min-h-11 rounded-md px-3 py-2.5 text-base font-semibold text-gray-800 hover:bg-gray-50">Produtos</a>
                    @endif
                    @if (($catalogCategories ?? collect())->isNotEmpty())
                        <details class="mx-1 rounded-lg">
                            <summary class="theme-focus flex min-h-11 cursor-pointer list-none items-center justify-between rounded-md px-3 py-2.5 text-base font-semibold text-gray-800 hover:bg-gray-50 [&::-webkit-details-marker]:hidden">Categorias <span aria-hidden="true">▾</span></summary>
                            <ul class="ml-4 border-l border-gray-200 pl-2">
                                @foreach ($catalogCategories as $category)
                                    <li><a href="{{ route('categories.show', $category->slug) }}" class="theme-focus block min-h-11 break-words rounded-md px-3 py-2.5 text-base text-gray-800 [overflow-wrap:anywhere] hover:bg-gray-50">{{ $category->name }}</a></li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                    <a href="{{ auth()->check() ? route('admin') : route('login') }}" class="theme-focus mt-1 block min-h-11 rounded-md px-3 py-2.5 text-base font-semibold text-gray-800 hover:bg-gray-50">Administração</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 sm:py-10">
        @yield('content')
    </main>

    <footer class="mt-10 border-t border-gray-200 bg-white sm:mt-16">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 pb-24 pt-8 sm:px-6 sm:pb-24 md:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)] md:pt-10">
            <div class="min-w-0">
                <a href="{{ route('home') }}" class="theme-focus inline-flex rounded-md" aria-label="{{ $storefrontBrand['name'] }} — início">
                    <img src="{{ $brandLogoUrl }}" alt="{{ $brandLogo['alt'] ?? $storefrontBrand['name'] }}" width="{{ $brandLogoWidth }}" height="{{ $brandLogoHeight }}" loading="lazy" class="h-auto max-h-14 w-auto max-w-40 object-contain">
                </a>
                <p class="mt-2 text-sm text-gray-600">{{ $storefrontBrand['slogan'] }}</p>
                <p class="mt-4 text-xs text-gray-500">&copy; {{ date('Y') }} {{ $storefrontBrand['name'] }}</p>
            </div>

            @if (($footerNavigation ?? []) !== [] || filled($storefrontContact['email'] ?? '') || filled($storefrontContact['phone'] ?? '') || filled($storefrontContact['address'] ?? ''))
                <div class="grid min-w-0 gap-6 sm:grid-cols-2">
                    @if (($footerNavigation ?? []) !== [])
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900">Informações</h2>
                            @include('partials.footer-navigation', ['nodes' => $footerNavigation])
                        </div>
                    @endif
                    @if (filled($storefrontContact['email'] ?? '') || filled($storefrontContact['phone'] ?? '') || filled($storefrontContact['address'] ?? ''))
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900">Contato</h2>
                            <address class="mt-3 space-y-2 text-sm not-italic text-gray-700">
                                @if (filled($storefrontContact['email'] ?? ''))<p><a class="theme-focus break-all rounded text-gray-800 underline underline-offset-4" href="mailto:{{ $storefrontContact['email'] }}">{{ $storefrontContact['email'] }}</a></p>@endif
                                @if (filled($storefrontContact['phone'] ?? ''))<p>{{ $storefrontContact['phone'] }}</p>@endif
                                @if (filled($storefrontContact['address'] ?? ''))<p class="whitespace-pre-line">{{ $storefrontContact['address'] }}</p>@endif
                            </address>
                        </div>
                    @endif
                </div>
            @endif
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
