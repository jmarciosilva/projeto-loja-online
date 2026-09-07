{{--
    Formulário compartilhado por criar e editar.

    Recebe: $action, $method, $submitLabel, $positions, $availableMedia e, na
    edição, $banner.

    Não há campo de ordem. `sort_order` é atribuído pelo BannerService, que
    conhece as outras linhas da posição — expor um número aqui devolveria ao
    administrador a tarefa de administrar inteiros, e a ordenação existe
    justamente para evitar isso. Na edição a ordem atual aparece só como
    informação, sem input.

    A imagem é escolhida entre as mídias já enviadas: não há upload nesta tela,
    nem modal, nem seletor reutilizável — a biblioteca da F2.7 é a única origem.
--}}
@php($banner = $banner ?? null)

<form method="POST" action="{{ $action }}"
      class="rounded-xl border border-gray-200 bg-white p-6 sm:p-8">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="space-y-5">

        <div>
            <label for="name" class="block text-sm font-medium text-gray-900">Nome</label>
            <input
                id="name"
                name="name"
                type="text"
                value="{{ old('name', $banner?->name) }}"
                required
                maxlength="120"
                @error('name') aria-invalid="true" aria-describedby="name-erro" @else aria-describedby="name-ajuda" @enderror
                class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('name') border-red-400 @enderror"
            >
            @error('name')
                <p id="name-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @else
                <p id="name-ajuda" class="mt-2 text-sm text-gray-500">
                    Identificação interna do banner. Não aparece na loja.
                </p>
            @enderror
        </div>

        <div>
            <label for="media_id" class="block text-sm font-medium text-gray-900">Imagem</label>
            <select
                id="media_id"
                name="media_id"
                required
                @error('media_id') aria-invalid="true" aria-describedby="media-erro" @else aria-describedby="media-ajuda" @enderror
                class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('media_id') border-red-400 @enderror"
            >
                <option value="">Selecione uma imagem</option>
                @foreach ($availableMedia as $item)
                    <option value="{{ $item->id }}"
                        @selected((int) old('media_id', $banner?->media_id) === $item->id)>
                        #{{ $item->id }} — {{ $item->original_name }} ({{ $item->width }} × {{ $item->height }} px)
                    </option>
                @endforeach
            </select>
            @error('media_id')
                <p id="media-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @else
                <p id="media-ajuda" class="mt-2 text-sm text-gray-500">
                    As imagens vêm da <a href="{{ route('admin.media.index') }}" class="font-medium underline">biblioteca de
                    mídia</a>. Envie a imagem por lá antes de criar o banner.
                </p>
            @enderror
        </div>

        <div>
            <label for="position" class="block text-sm font-medium text-gray-900">Posição</label>
            <select
                id="position"
                name="position"
                required
                @error('position') aria-invalid="true" aria-describedby="position-erro" @else aria-describedby="position-ajuda" @enderror
                class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('position') border-red-400 @enderror"
            >
                @foreach ($positions as $value => $label)
                    <option value="{{ $value }}"
                        @selected(old('position', $banner?->position->value ?? array_key_first($positions)) === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            @error('position')
                <p id="position-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @else
                <p id="position-ajuda" class="mt-2 text-sm text-gray-500">
                    @if ($banner === null)
                        O banner entra no fim da lista da posição escolhida.
                    @else
                        Trocar a posição move o banner para o fim da lista de destino.
                    @endif
                </p>
            @enderror
        </div>

        <div>
            <label for="alt_text" class="block text-sm font-medium text-gray-900">Texto alternativo</label>
            <input
                id="alt_text"
                name="alt_text"
                type="text"
                value="{{ old('alt_text', $banner?->alt_text) }}"
                required
                maxlength="255"
                @error('alt_text') aria-invalid="true" aria-describedby="alt-erro" @else aria-describedby="alt-ajuda" @enderror
                class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('alt_text') border-red-400 @enderror"
            >
            @error('alt_text')
                <p id="alt-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @else
                <p id="alt-ajuda" class="mt-2 text-sm text-gray-500">
                    Descreva a imagem para quem usa leitor de tela. Não é o nome do arquivo.
                </p>
            @enderror
        </div>

        <div>
            <label for="link_url" class="block text-sm font-medium text-gray-900">Link</label>
            <input
                id="link_url"
                name="link_url"
                type="text"
                value="{{ old('link_url', $banner?->link_url) }}"
                maxlength="2048"
                @error('link_url') aria-invalid="true" aria-describedby="link-erro" @else aria-describedby="link-ajuda" @enderror
                class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('link_url') border-red-400 @enderror"
            >
            @error('link_url')
                <p id="link-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @else
                <p id="link-ajuda" class="mt-2 text-sm text-gray-500">
                    Opcional. Caminho interno começando por / — por exemplo, /paginas/quem-somos — ou
                    uma URL completa http/https.
                </p>
            @enderror
        </div>

        <div class="border-t border-gray-100 pt-5">
            {{-- O campo oculto garante que a ausência do check chegue como "0",
                 em vez de o campo simplesmente não existir no POST. --}}
            <input type="hidden" name="is_active" value="0">
            <label for="is_active" class="flex items-start gap-3">
                <input
                    id="is_active"
                    name="is_active"
                    type="checkbox"
                    value="1"
                    @checked((bool) old('is_active', $banner?->is_active ?? false))
                    class="mt-0.5 size-4 rounded border-gray-300 text-gray-900 focus:ring-2 focus:ring-gray-900/20"
                >
                <span>
                    <span class="block text-sm font-medium text-gray-900">Ativo</span>
                    <span class="block text-sm text-gray-500">
                        Um banner novo nasce inativo. Ative quando ele estiver pronto para aparecer na loja.
                    </span>
                </span>
            </label>
            @error('is_active')
                <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        @if ($banner !== null)
            <p class="text-sm text-gray-500">
                Ordem atual na posição: <strong class="font-medium text-gray-700">{{ $banner->sort_order }}</strong>.
                A ordem é ajustada pelos controles da listagem.
            </p>
        @endif

    </div>

    <div class="mt-8 flex items-center gap-3">
        <button type="submit"
                class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-gray-700 focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:outline-none">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('admin.banners.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">
            Cancelar
        </a>
    </div>
</form>
