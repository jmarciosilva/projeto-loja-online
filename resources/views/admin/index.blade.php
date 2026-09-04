@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    {{--
        Dashboard estrutural: descreve o que a fundação do painel oferece.
        Nenhum dado de negócio é consultado aqui — vendas, pedidos, produtos,
        estoque e clientes pertencem às fases que criarem esses módulos.
    --}}
    <div class="space-y-6">

        <section class="rounded-xl border border-gray-200 bg-white p-6 sm:p-8">
            <h2 class="text-lg font-semibold tracking-tight">
                Bem-vindo, {{ auth()->user()->name }}
            </h2>

            <p class="mt-2 max-w-2xl text-sm text-gray-600">
                A fundação do painel administrativo está pronta. Os módulos de
                gestão da loja serão adicionados nas próximas etapas e aparecerão
                no menu lateral conforme forem implementados.
            </p>
        </section>

        <section aria-labelledby="estrutura-disponivel">
            <h3 id="estrutura-disponivel" class="text-sm font-semibold tracking-tight text-gray-900">
                Estrutura disponível
            </h3>

            <dl class="mt-3 grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white p-5">
                    <dt class="text-sm font-medium text-gray-900">Autenticação</dt>
                    <dd class="mt-1 text-sm text-gray-600">
                        Ativa. O acesso ao painel exige sessão autenticada.
                    </dd>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5">
                    <dt class="text-sm font-medium text-gray-900">Layout do painel</dt>
                    <dd class="mt-1 text-sm text-gray-600">
                        Barra lateral, cabeçalho e trilha de navegação prontos para
                        receber novas páginas.
                    </dd>
                </div>
            </dl>
        </section>

        <section aria-labelledby="proximas-areas">
            <h3 id="proximas-areas" class="text-sm font-semibold tracking-tight text-gray-900">
                Próximas áreas administrativas
            </h3>

            {{--
                Lista propositalmente textual: enquanto as páginas não existirem,
                transformá-las em links produziria caminhos quebrados.
            --}}
            <div class="mt-3 rounded-xl border border-gray-200 bg-white p-5">
                <p class="text-sm text-gray-600">
                    Configurações globais, páginas estáticas, biblioteca de mídia,
                    banners e menus entram no menu lateral à medida que forem
                    implementados.
                </p>
            </div>
        </section>

    </div>
@endsection
