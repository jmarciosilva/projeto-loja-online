@extends('layouts.admin')

@section('title', 'Banners')

@section('content')
    <div class="space-y-6">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold tracking-tight">Banners</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Imagens posicionadas na loja. A ordem vale dentro de cada posição.
                </p>
            </div>
            <a href="{{ route('admin.banners.create') }}"
               class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-gray-700 focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:outline-none">
                Novo banner
            </a>
        </div>

        @if (session('status'))
            <div role="status" class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4">
                <p class="text-sm font-medium text-red-800">Não foi possível concluir a ação</p>
                <ul class="mt-1 space-y-1 text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{--
            Um bloco por posição, e não uma tabela única: a ordem é contextual —
            comparar a ordem de um destaque com a de um rodapé não significa
            nada. Sem paginação, porque paginar quebraria os controles de ordem.
        --}}
        @foreach ($positions as $value => $label)
            @php($banners = $grouped[$value])

            <section class="space-y-3">
                <div class="flex items-baseline gap-2">
                    <h3 class="text-sm font-semibold tracking-tight text-gray-900">{{ $label }}</h3>
                    <span class="text-xs text-gray-500">{{ $banners->count() }} banner(s)</span>
                </div>

                @if ($banners->isEmpty())
                    <div class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center">
                        <p class="text-sm text-gray-600">Nenhum banner nesta posição.</p>
                    </div>
                @else
                    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-gray-200 text-xs tracking-wide text-gray-500 uppercase">
                                <tr>
                                    <th scope="col" class="px-4 py-3 font-medium">Ordem</th>
                                    <th scope="col" class="px-4 py-3 font-medium">Imagem</th>
                                    <th scope="col" class="px-4 py-3 font-medium">Nome</th>
                                    <th scope="col" class="px-4 py-3 font-medium">Posição</th>
                                    <th scope="col" class="px-4 py-3 font-medium">Estado</th>
                                    <th scope="col" class="px-4 py-3 text-right font-medium">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($banners as $indice => $banner)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <span class="w-6 text-gray-600">{{ $banner->sort_order }}</span>
                                                {{-- Subir/descer em vez de arrastar: formulário simples,
                                                     sem JavaScript, e sem expor a ordem como número livre. --}}
                                                <form method="POST" action="{{ route('admin.banners.move', $banner) }}">
                                                    @csrf
                                                    <input type="hidden" name="direction" value="up">
                                                    <button type="submit"
                                                            @disabled($indice === 0)
                                                            aria-label="Subir {{ $banner->name }}"
                                                            class="rounded border border-gray-300 px-1.5 py-0.5 text-gray-700 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40">
                                                        &uarr;
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.banners.move', $banner) }}">
                                                    @csrf
                                                    <input type="hidden" name="direction" value="down">
                                                    <button type="submit"
                                                            @disabled($indice === $banners->count() - 1)
                                                            aria-label="Descer {{ $banner->name }}"
                                                            class="rounded border border-gray-300 px-1.5 py-0.5 text-gray-700 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40">
                                                        &darr;
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            {{-- URL derivada da mídia pela infraestrutura da F2.7; o banner
                                                 nunca guarda caminho nem URL. --}}
                                            @if ($banner->media !== null)
                                                <img src="{{ $media->url($banner->media) }}"
                                                     alt="{{ $banner->alt_text }}"
                                                     class="h-12 w-20 rounded border border-gray-200 bg-gray-50 object-contain">
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 font-medium text-gray-900">{{ $banner->name }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ $positions[$banner->position->value] }}</td>
                                        <td class="px-4 py-3">
                                            <span @class([
                                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                                'bg-green-100 text-green-800' => $banner->is_active,
                                                'bg-gray-100 text-gray-700' => ! $banner->is_active,
                                            ])>
                                                {{ $banner->is_active ? 'Ativo' : 'Inativo' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-3">
                                                <a href="{{ route('admin.banners.edit', $banner) }}"
                                                   class="font-medium text-gray-900 underline-offset-2 hover:underline">
                                                    Editar
                                                </a>
                                                <form method="POST" action="{{ route('admin.banners.destroy', $banner) }}">
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
        @endforeach

    </div>
@endsection
