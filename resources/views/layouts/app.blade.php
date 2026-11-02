<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @yield('meta')
    @if ($visualIdentity['favicon'] ?? null)
        <link rel="icon" type="{{ $visualIdentity['favicon']['mimeType'] }}" href="{{ $visualIdentity['favicon']['url'] }}">
    @endif
    {{-- Alpine is bundled with Livewire 4; importing a second copy breaks component state. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme-styles')
    @livewireStyles
</head>
<body class="min-h-full overflow-x-clip bg-gray-50 text-gray-900 antialiased">
    <header class="theme-border-accent border-t-4 border-b border-b-gray-200 bg-white" x-data="{ mobileMenuOpen: false }" @keydown.escape.window="mobileMenuOpen = false">
        <div class="mx-auto max-w-6xl px-4">
            <div class="flex min-h-16 items-center justify-between gap-3 py-2 md:gap-5">
                <a href="{{ route('home') }}" class="theme-text-primary min-w-0 max-w-[72vw] truncate text-lg font-semibold tracking-tight sm:max-w-xs md:max-w-none">
                    @if ($visualIdentity['logo'] ?? null)
                        <img src="{{ $visualIdentity['logo']['url'] }}" alt="{{ $visualIdentity['logo']['alt'] }}" width="{{ $visualIdentity['logo']['width'] }}" height="{{ $visualIdentity['logo']['height'] }}" class="h-9 w-auto sm:h-10">
                    @else
                        {{ config('app.name') }}
                    @endif
                </a>

                <div class="hidden min-w-0 flex-1 items-center justify-end gap-4 md:flex">
                    <div class="min-w-0 max-w-[min(56vw,48rem)] overflow-x-auto">
                        @include('partials.main-navigation', ['nodes' => $mainNavigation ?? [], 'mobile' => false])
                    </div>
                    @if ($catalogNavigationVisible ?? true)
                        <a href="{{ route('catalog.index') }}" @if(request()->url() === route('catalog.index')) aria-current="page" @endif class="shrink-0 whitespace-nowrap text-sm font-medium text-gray-700 underline-offset-4 hover:underline">Produtos</a>
                    @endif
                    <a href="{{ auth()->check() ? route('admin') : route('login') }}" class="shrink-0 whitespace-nowrap rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">Administração</a>
                </div>

                <button type="button" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-gray-300 text-gray-800 md:hidden" @click="mobileMenuOpen = !mobileMenuOpen" :aria-expanded="mobileMenuOpen.toString()" aria-controls="mobile-navigation" :aria-label="mobileMenuOpen ? 'Fechar menu' : 'Abrir menu'">
                    <svg x-show="!mobileMenuOpen" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg x-show="mobileMenuOpen" x-cloak aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <div id="mobile-navigation" x-show="mobileMenuOpen" x-cloak @click="if ($event.target.closest('a')) mobileMenuOpen = false" class="border-t border-gray-200 py-3 md:!hidden" @click.outside="mobileMenuOpen = false">
                <div class="mb-2 flex items-center justify-between px-1">
                    <span class="text-sm font-semibold text-gray-600">Navegação</span>
                    <button type="button" class="min-h-11 rounded-lg px-3 text-sm font-medium underline underline-offset-4" @click="mobileMenuOpen = false">Fechar menu</button>
                </div>
                <nav aria-label="Navegação móvel">
                    @include('partials.main-navigation', ['nodes' => $mainNavigation ?? [], 'mobile' => true])
                    @if ($catalogNavigationVisible ?? true)
                        <a href="{{ route('catalog.index') }}" @if(request()->url() === route('catalog.index')) aria-current="page" @endif class="block min-h-11 rounded-md px-3 py-2.5 text-base font-medium text-gray-800 hover:bg-gray-50">Produtos</a>
                    @endif
                    <a href="{{ auth()->check() ? route('admin') : route('login') }}" class="mt-1 block min-h-11 rounded-md px-3 py-2.5 text-base font-medium text-gray-800 hover:bg-gray-50">Administração</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-6xl px-4 py-6 sm:py-10">
        @yield('content')
    </main>

    <footer class="mt-10 border-t border-gray-200 bg-white sm:mt-16">
        <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-gray-500">&copy; {{ date('Y') }} {{ config('app.name') }}</div>
    </footer>
    @livewireScripts
</body>
</html>
