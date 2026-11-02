@extends('layouts.admin')

@section('title', 'Painel da loja')

@section('content')
    <div class="space-y-8">
        <section class="admin-dashboard-hero rounded-2xl px-6 py-8 sm:px-8">
            <p class="text-sm font-medium text-gray-300">Administração da loja</p>
            <h2 class="mt-2 text-2xl font-semibold tracking-tight">
                Olá, {{ auth()->user()->name }}
            </h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-300">
                Gerencie o conteúdo e a apresentação da sua loja em um só lugar.
                Escolha uma área para começar.
            </p>
        </section>

        <section aria-labelledby="gestao-conteudo">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h3 id="gestao-conteudo" class="text-lg font-semibold tracking-tight text-gray-900">
                        Gestão da loja
                    </h3>
                    <p class="mt-1 text-sm text-gray-600">Áreas disponíveis no painel administrativo.</p>
                </div>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ([
                    ['title' => 'Páginas', 'description' => 'Crie e publique páginas institucionais.', 'route' => 'admin.pages.index', 'action' => 'Gerenciar páginas'],
                    ['title' => 'Banners', 'description' => 'Organize os destaques exibidos na loja.', 'route' => 'admin.banners.index', 'action' => 'Gerenciar banners'],
                    ['title' => 'Menus', 'description' => 'Configure os links da navegação principal.', 'route' => 'admin.menus.index', 'action' => 'Gerenciar menus'],
                    ['title' => 'Categorias', 'description' => 'Organize os produtos em categorias.', 'route' => 'admin.categories.index', 'action' => 'Gerenciar categorias'],
                    ['title' => 'Produtos', 'description' => 'Cadastre itens para o catálogo público.', 'route' => 'admin.products.index', 'action' => 'Gerenciar produtos'],
                    ['title' => 'Biblioteca de mídia', 'description' => 'Envie e organize imagens da loja.', 'route' => 'admin.media.index', 'action' => 'Abrir biblioteca'],
                    ['title' => 'Configurações', 'description' => 'Edite informações, cores e identidade visual.', 'route' => 'admin.settings.edit', 'action' => 'Configurar loja'],
                ] as $area)
                    <article class="flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                        <h4 class="font-semibold text-gray-900">{{ $area['title'] }}</h4>
                        <p class="mt-2 flex-1 text-sm leading-6 text-gray-600">{{ $area['description'] }}</p>
                        <a href="{{ route($area['route']) }}"
                           class="mt-5 inline-flex items-center text-sm font-semibold text-gray-900 underline-offset-4 hover:underline">
                            {{ $area['action'] }} <span aria-hidden="true" class="ml-1">→</span>
                        </a>
                    </article>
                @endforeach
            </div>
        </section>

        <p class="text-xs text-gray-500">
            A gestão de pedidos e clientes será adicionada nas próximas etapas do projeto.
        </p>
    </div>
@endsection
