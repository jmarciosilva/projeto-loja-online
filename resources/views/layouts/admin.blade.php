<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Área administrativa') &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-50 text-gray-900 antialiased">

    {{--
        Layout único das páginas administrativas: nenhuma view sob admin/ deve
        declarar <html>, <head> ou <body> por conta própria.

        Em telas menores que lg a sidebar vira uma faixa no topo, empilhada
        acima do conteúdo. É uma degradação simples e sem JavaScript — a
        sidebar recolhível pertence a etapas posteriores.
    --}}
    <div class="flex min-h-full flex-col lg:flex-row">

        @include('admin.partials.sidebar')

        <div class="flex min-w-0 flex-1 flex-col">

            @include('admin.partials.topbar')

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                @include('admin.partials.breadcrumbs')

                <div class="mt-4">
                    @yield('content')
                </div>
            </main>

        </div>
    </div>

</body>
</html>
