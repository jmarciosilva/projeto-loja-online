@extends('layouts.admin')

@section('title', 'Menus')

@section('content')
    <div class="space-y-6">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold tracking-tight">Menus</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Conjuntos de navegação da loja. Cada menu tem um código técnico próprio.
                </p>
            </div>
            <a href="{{ route('admin.menus.create') }}"
               class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-gray-700 focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:outline-none">
                Novo menu
            </a>
        </div>

        @include('admin.menus.partials.feedback')

        @if ($menus->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
                <p class="text-sm font-medium text-gray-900">Nenhum menu cadastrado.</p>
                <p class="mt-1 text-sm text-gray-600">
                    Crie o primeiro menu de navegação da loja.
                </p>
                <a href="{{ route('admin.menus.create') }}"
                   class="mt-4 inline-block rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-gray-700 focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:outline-none">
                    Criar primeiro menu
                </a>
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs tracking-wide text-gray-500 uppercase">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">Nome</th>
                            <th scope="col" class="px-4 py-3 font-medium">Código</th>
                            <th scope="col" class="px-4 py-3 font-medium">Itens</th>
                            <th scope="col" class="px-4 py-3 font-medium">Estado</th>
                            <th scope="col" class="px-4 py-3 text-right font-medium">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($menus as $menu)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $menu->name }}</td>
                                <td class="px-4 py-3">
                                    <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700">{{ $menu->code }}</code>
                                </td>
                                {{-- A contagem vem de withCount(), numa consulta agregada:
                                     contar aqui dentro faria uma query por linha. --}}
                                <td class="px-4 py-3 text-gray-600">{{ $menu->items_count }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-green-100 text-green-800' => $menu->is_active,
                                        'bg-gray-100 text-gray-700' => ! $menu->is_active,
                                    ])>
                                        {{ $menu->is_active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <form method="POST" action="{{ route('admin.menus.toggle', $menu) }}">
                                            @csrf
                                            <button type="submit"
                                                    class="font-medium text-gray-600 underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:outline-none">
                                                {{ $menu->is_active ? 'Desativar' : 'Ativar' }}
                                            </button>
                                        </form>
                                        <a href="{{ route('admin.menus.edit', $menu) }}"
                                           class="font-medium text-gray-900 underline-offset-2 hover:underline">
                                            Editar
                                        </a>
                                        {{-- Excluir remove o menu e toda a sua árvore, numa
                                             única transação do MenuService. --}}
                                        <form method="POST" action="{{ route('admin.menus.destroy', $menu) }}">
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

    </div>
@endsection
