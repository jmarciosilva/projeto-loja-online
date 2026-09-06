<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    {{-- Slot de metadados da página. Quem preenche é responsável pelo escape:
         @yield imprime sem escapar. --}}
    @yield('meta')

    {{-- Favicon da biblioteca de mídia. O type vem do MIME da própria mídia;
         sem favicon configurado, nenhuma tag é emitida. --}}
    @if ($visualIdentity['favicon'] ?? null)
        <link rel="icon" type="{{ $visualIdentity['favicon']['mimeType'] }}"
              href="{{ $visualIdentity['favicon']['url'] }}">
    @endif

    {{-- O Alpine vem embutido no Livewire 4 — não importe alpinejs separadamente,
         senão duas instâncias competem e o console acusa
         "Alpine has already been initialized". --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Depois do @vite para que as variáveis de runtime vençam o CSS compilado. --}}
    @include('partials.theme-styles')

    @livewireStyles
</head>
<body class="h-full bg-gray-50 text-gray-900 antialiased">

    <header class="theme-border-accent border-t-4 border-b border-b-gray-200 bg-white">
        <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
            {{-- Com logo configurada, ela substitui o texto; sem ela, o nome da
                 loja continua sendo o fallback. `h-10 w-auto` preserva a
                 proporção qualquer que seja a imagem escolhida. --}}
            <a href="{{ route('home') }}" class="theme-text-primary text-lg font-semibold tracking-tight">
                @if ($visualIdentity['logo'] ?? null)
                    <img src="{{ $visualIdentity['logo']['url'] }}"
                         alt="{{ $visualIdentity['logo']['alt'] }}"
                         width="{{ $visualIdentity['logo']['width'] }}"
                         height="{{ $visualIdentity['logo']['height'] }}"
                         class="h-10 w-auto">
                @else
                    {{ config('app.name') }}
                @endif
            </a>
            {{-- Menu, carrinho e área do cliente entram nas Fases 2 e 5. --}}
        </nav>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-10">
        @yield('content')
    </main>

    <footer class="mt-16 border-t border-gray-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-gray-500">
            &copy; {{ date('Y') }} {{ config('app.name') }}
        </div>
    </footer>

    @livewireScripts
</body>
</html>
