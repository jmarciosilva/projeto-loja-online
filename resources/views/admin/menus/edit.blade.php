@extends('layouts.admin')

@section('title', $menu->name)

@section('content')
    <div class="max-w-4xl space-y-8">

        <div>
            <h2 class="text-lg font-semibold tracking-tight">{{ $menu->name }}</h2>
            <p class="mt-1 text-sm text-gray-600">
                Código <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700">{{ $menu->code }}</code>
                — os itens abaixo formam a árvore de navegação deste menu.
            </p>
        </div>

        @include('admin.menus.partials.feedback')

        <section class="space-y-3">
            <h3 class="text-sm font-semibold tracking-tight text-gray-900">Dados do menu</h3>

            @include('admin.menus.partials.menu-form', [
                'action' => route('admin.menus.update', $menu),
                'method' => 'PUT',
                'submitLabel' => 'Salvar alterações',
            ])
        </section>

        {{--
            Árvore administrativa: hierarquia e ordem, montadas em memória a
            partir dos itens já carregados. Nenhuma consulta por nó — nem aqui,
            nem no Controller.

            A ordem é relativa **dentro de cada grupo de irmãos**, então subir e
            descer nunca atravessam grupos: o primeiro filho de um item não pode
            subir para virar irmão do pai. Trocar de grupo é editar o item pai.

            Sem arrastar e soltar: dois botões por linha, sem JavaScript. Nas
            bordas do grupo o botão impossível vem desabilitado — e o backend
            também trata a operação como no-op, caso a requisição chegue assim
            mesmo.
        --}}
        <section class="space-y-3">
            <div class="flex items-baseline gap-2">
                <h3 class="text-sm font-semibold tracking-tight text-gray-900">Itens do menu</h3>
                <span class="text-xs text-gray-500">{{ count($rows) }} item(ns)</span>
            </div>

            @if ($rows === [])
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center">
                    <p class="text-sm text-gray-600">Este menu ainda não tem itens.</p>
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-200 text-xs tracking-wide text-gray-500 uppercase">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">Ordem</th>
                                <th scope="col" class="px-4 py-3 font-medium">Item</th>
                                <th scope="col" class="px-4 py-3 font-medium">Destino</th>
                                <th scope="col" class="px-4 py-3 font-medium">Estado</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($rows as $row)
                                @php($node = $row['item'])
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="w-6 text-gray-600">{{ $node->sort_order }}</span>
                                            <form method="POST" action="{{ route('admin.menus.items.move-up', [$menu, $node]) }}">
                                                @csrf
                                                <button type="submit"
                                                        @disabled($row['first'])
                                                        aria-label="Subir {{ $node->label }}"
                                                        class="rounded border border-gray-300 px-1.5 py-0.5 text-gray-700 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40">
                                                    &uarr;
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.menus.items.move-down', [$menu, $node]) }}">
                                                @csrf
                                                <button type="submit"
                                                        @disabled($row['last'])
                                                        aria-label="Descer {{ $node->label }}"
                                                        class="rounded border border-gray-300 px-1.5 py-0.5 text-gray-700 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40">
                                                    &darr;
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{-- A profundidade vira recuo. O valor é calculado no
                                             Controller: a Blade não percorre a árvore. --}}
                                        <span class="block font-medium text-gray-900"
                                              style="padding-left: {{ $row['depth'] * 1.25 }}rem">
                                            @if ($row['depth'] > 0)
                                                <span aria-hidden="true" class="text-gray-400">&#8627;</span>
                                            @endif
                                            {{ $node->label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        @if ($node->type === \App\Enums\MenuItemType::Page)
                                            @if ($node->page === null)
                                                <span class="text-amber-700">Página indisponível (#{{ $node->page_id }})</span>
                                            @else
                                                {{ $node->page->title }}
                                                <span class="text-gray-400">— /paginas/{{ $node->page->slug }}</span>
                                            @endif
                                        @else
                                            <span class="font-mono text-xs">{{ $node->url }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span @class([
                                            'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                            'bg-green-100 text-green-800' => $node->is_active,
                                            'bg-gray-100 text-gray-700' => ! $node->is_active,
                                        ])>
                                            {{ $node->is_active ? 'Ativo' : 'Inativo' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-3">
                                            <form method="POST" action="{{ route('admin.menus.items.toggle', [$menu, $node]) }}">
                                                @csrf
                                                <button type="submit"
                                                        class="font-medium text-gray-600 underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:outline-none">
                                                    {{ $node->is_active ? 'Desativar' : 'Ativar' }}
                                                </button>
                                            </form>
                                            <a href="{{ route('admin.menus.items.edit', [$menu, $node]) }}"
                                               class="font-medium text-gray-900 underline-offset-2 hover:underline">
                                                Editar
                                            </a>
                                            {{-- Um item com filhos é recusado pelo MenuService: a
                                                 subárvore não é cascateada nem promovida. --}}
                                            <form method="POST" action="{{ route('admin.menus.items.destroy', [$menu, $node]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="font-medium text-red-700 underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 focus-visible:outline-none">
                                                    Excluir
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="space-y-3">
            <h3 class="text-sm font-semibold tracking-tight text-gray-900">Novo item</h3>

            @include('admin.menus.partials.item-form', [
                'action' => route('admin.menus.items.store', $menu),
                'method' => 'POST',
                'submitLabel' => 'Adicionar item',
                'parents' => $parents,
            ])
        </section>

        <div>
            <form method="POST" action="{{ route('admin.menus.destroy', $menu) }}">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="text-sm font-medium text-red-700 underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 focus-visible:outline-none">
                    Excluir este menu e todos os seus itens
                </button>
            </form>
        </div>

    </div>
@endsection
