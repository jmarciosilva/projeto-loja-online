{{--
    Formulário compartilhado por criar e editar.

    Recebe: $action, $method, $submitLabel, $statuses e, na edição, $page.
    O conteúdo é Markdown em textarea — a renderização segura para HTML é da
    F2.4-C, então nada aqui usa {!! !!}.
--}}
@php($page = $page ?? null)

<form method="POST" action="{{ $action }}"
      class="rounded-xl border border-gray-200 bg-white p-6 sm:p-8">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="space-y-5">

        <div>
            <label for="title" class="block text-sm font-medium text-gray-900">Título</label>
            <input
                id="title"
                name="title"
                type="text"
                value="{{ old('title', $page?->title) }}"
                required
                maxlength="255"
                @error('title') aria-invalid="true" aria-describedby="title-erro" @enderror
                class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('title') border-red-400 @enderror"
            >
            @error('title')
                <p id="title-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="slug" class="block text-sm font-medium text-gray-900">Endereço</label>
            <input
                id="slug"
                name="slug"
                type="text"
                value="{{ old('slug', $page?->slug) }}"
                maxlength="255"
                @error('slug') aria-invalid="true" aria-describedby="slug-erro" @else aria-describedby="slug-ajuda" @enderror
                class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('slug') border-red-400 @enderror"
            >
            @error('slug')
                <p id="slug-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @else
                <p id="slug-ajuda" class="mt-2 text-sm text-gray-500">
                    @if ($page === null)
                        Em branco, o endereço é gerado a partir do título — por exemplo, quem-somos.
                    @else
                        Em branco, o endereço atual é mantido. Alterá-lo muda a URL pública da página.
                    @endif
                </p>
            @enderror
        </div>

        <div>
            <label for="status" class="block text-sm font-medium text-gray-900">Status</label>
            <select
                id="status"
                name="status"
                required
                @error('status') aria-invalid="true" aria-describedby="status-erro" @enderror
                class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('status') border-red-400 @enderror"
            >
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}"
                        @selected(old('status', $page?->status->value ?? array_key_first($statuses)) === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            @error('status')
                <p id="status-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="content" class="block text-sm font-medium text-gray-900">Conteúdo</label>
            <textarea
                id="content"
                name="content"
                rows="14"
                @error('content') aria-invalid="true" aria-describedby="content-erro" @else aria-describedby="content-ajuda" @enderror
                class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 font-mono text-sm text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('content') border-red-400 @enderror"
            >{{ old('content', $page?->content) }}</textarea>
            @error('content')
                <p id="content-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @else
                <p id="content-ajuda" class="mt-2 text-sm text-gray-500">
                    O conteúdo é escrito em Markdown.
                </p>
            @enderror
        </div>

        <fieldset class="border-t border-gray-100 pt-5">
            <legend class="text-sm font-medium text-gray-900">SEO</legend>
            <p class="mt-1 text-sm text-gray-500">
                Opcional. Em branco, a página usa o próprio título.
            </p>

            <div class="mt-4 space-y-5">
                <div>
                    <label for="meta_title" class="block text-sm font-medium text-gray-900">Meta title</label>
                    <input
                        id="meta_title"
                        name="meta_title"
                        type="text"
                        value="{{ old('meta_title', $page?->meta_title) }}"
                        maxlength="255"
                        @error('meta_title') aria-invalid="true" aria-describedby="meta-title-erro" @enderror
                        class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('meta_title') border-red-400 @enderror"
                    >
                    @error('meta_title')
                        <p id="meta-title-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="meta_description" class="block text-sm font-medium text-gray-900">Meta description</label>
                    <textarea
                        id="meta_description"
                        name="meta_description"
                        rows="3"
                        maxlength="320"
                        @error('meta_description') aria-invalid="true" aria-describedby="meta-description-erro" @enderror
                        class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('meta_description') border-red-400 @enderror"
                    >{{ old('meta_description', $page?->meta_description) }}</textarea>
                    @error('meta_description')
                        <p id="meta-description-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </fieldset>

    </div>

    <div class="mt-8 flex items-center gap-3">
        <button type="submit"
                class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-gray-700 focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:outline-none">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('admin.pages.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">
            Cancelar
        </a>
    </div>
</form>
