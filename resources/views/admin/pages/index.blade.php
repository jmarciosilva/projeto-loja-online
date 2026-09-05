@extends('layouts.admin')

@section('title', 'Páginas')

@section('content')
    <div class="space-y-6">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold tracking-tight">Páginas</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Páginas de conteúdo institucional da loja.
                </p>
            </div>
            <a href="{{ route('admin.pages.create') }}"
               class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-gray-700 focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:outline-none">
                Nova página
            </a>
        </div>

        @if (session('status'))
            <div role="status" class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($pages->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
                <p class="text-sm font-medium text-gray-900">Nenhuma página cadastrada.</p>
                <p class="mt-1 text-sm text-gray-600">
                    Crie a primeira página institucional da loja.
                </p>
                <a href="{{ route('admin.pages.create') }}"
                   class="mt-4 inline-block rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-gray-700 focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:outline-none">
                    Criar primeira página
                </a>
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs tracking-wide text-gray-500 uppercase">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">Título</th>
                            <th scope="col" class="px-4 py-3 font-medium">Endereço</th>
                            <th scope="col" class="px-4 py-3 font-medium">Status</th>
                            <th scope="col" class="px-4 py-3 font-medium">Última atualização</th>
                            <th scope="col" class="px-4 py-3 text-right font-medium">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($pages as $page)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $page->title }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $page->slug }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-green-100 text-green-800' => $page->status === \App\Enums\PageStatus::Published,
                                        'bg-gray-100 text-gray-700' => $page->status !== \App\Enums\PageStatus::Published,
                                    ])>
                                        {{ $statuses[$page->status->value] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ $page->updated_at?->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('admin.pages.edit', $page) }}"
                                           class="font-medium text-gray-900 underline-offset-2 hover:underline">
                                            Editar
                                        </a>
                                        <form method="POST" action="{{ route('admin.pages.destroy', $page) }}">
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

            @if ($pages->hasPages())
                <div>
                    {{ $pages->links() }}
                </div>
            @endif
        @endif

    </div>
@endsection
