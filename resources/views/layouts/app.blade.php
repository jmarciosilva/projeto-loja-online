<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    {{-- O Alpine vem embutido no Livewire 4 — não importe alpinejs separadamente,
         senão duas instâncias competem e o console acusa
         "Alpine has already been initialized". --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-gray-50 text-gray-900 antialiased">

    <header class="border-b border-gray-200 bg-white">
        <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
            <a href="{{ route('home') }}" class="text-lg font-semibold tracking-tight">
                {{ config('app.name') }}
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
