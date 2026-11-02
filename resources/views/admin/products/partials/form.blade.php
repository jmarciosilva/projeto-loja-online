@php
    $selectedMediaId = old('main_media_id', $product->main_media_id);
    $selectedMedia = $media->firstWhere('id', (int) $selectedMediaId);
    $selectedMediaUrl = $selectedMedia ? app(\App\Services\MediaService::class)->url($selectedMedia) : null;
    $promotionEnabled = (bool) old('promotion_enabled', $product->promotion_enabled ?? false);
    $oldGalleryIds = old('gallery_media_ids');
    $gallery = is_array($oldGalleryIds)
        ? collect($oldGalleryIds)->map(fn ($id) => $media->firstWhere('id', (int) $id))->filter()->values()
        : ($product->exists ? $product->images : collect());
    if ($gallery->isEmpty() && $selectedMedia) $gallery = collect([$selectedMedia]);
    $descriptionHtml = app(\App\Services\ProductDescription::class)->render(old('description', $product->description));
@endphp

<form method="POST" action="{{ $action }}" class="min-w-0 space-y-4 sm:space-y-6">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <section class="grid gap-5 rounded-xl border border-gray-200 bg-white p-6 sm:p-8 lg:grid-cols-2" aria-labelledby="identificacao-produto">
        <div class="lg:col-span-2"><h3 id="identificacao-produto" class="text-lg font-semibold">Identificação</h3></div>
        <div>
            <label for="name" class="block text-sm font-medium">Nome</label>
            <input id="name" name="name" required maxlength="255" value="{{ old('name', $product->name) }}" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2.5">
            @error('name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="category_id" class="block text-sm font-medium">Categoria</label>
            <select id="category_id" name="category_id" required class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2.5">
                <option value="">Selecione</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id) === (string) $category->id)>{{ $category->name }}{{ $category->is_active ? '' : ' (inativa)' }}</option>
                @endforeach
            </select>
            @error('category_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            <a class="mt-1 inline-block text-sm underline" href="{{ route('admin.categories.create') }}">Cadastrar categoria</a>
        </div>
        <div>
            @if ($product->exists)
                <label class="block text-sm font-medium">SKU</label>
                <p class="mt-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 font-mono">{{ $product->sku }}</p>
                <p class="mt-1 text-sm text-gray-500">O SKU permanece estável após a criação.</p>
            @else
                <label for="sku" class="block text-sm font-medium">SKU manual (opcional)</label>
                <details class="mt-2">
                    <summary class="cursor-pointer text-sm underline">Definir SKU manualmente</summary>
                    <input id="sku" name="sku" maxlength="255" value="{{ old('sku') }}" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2.5">
                    @error('sku')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </details>
                <p class="mt-1 text-sm text-gray-500">SKU gerado automaticamente ao salvar.</p>
            @endif
        </div>
        <div>
            <label for="slug" class="block text-sm font-medium">Endereço (slug)</label>
            <input id="slug" name="slug" maxlength="255" value="{{ old('slug', $product->slug) }}" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2.5">
            @error('slug')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@else<p class="mt-1 text-sm text-gray-500">Em branco na criação, gerado a partir do nome.</p>@enderror
        </div>
        <div class="lg:col-span-2">
            <label for="description-editor" class="block text-sm font-medium">Descrição formatada</label>
            <div class="mt-2 overflow-hidden rounded-lg border border-gray-300">
                <div class="flex flex-wrap gap-1 border-b border-gray-200 bg-gray-50 p-2" role="toolbar" aria-label="Formatação da descrição">
                    @foreach ([['bold','Negrito'],['italic','Itálico'],['underline','Sublinhado'],['h2','Título H2'],['h3','Título H3'],['p','Parágrafo'],['insertUnorderedList','Lista com marcadores'],['insertOrderedList','Lista numerada'],['createLink','Inserir link'],['undo','Desfazer'],['redo','Refazer'],['removeFormat','Remover formatação']] as [$command,$label])
                        <button type="button" data-editor-command="{{ $command }}" class="rounded border border-gray-300 px-2.5 py-1.5 text-sm hover:bg-white" aria-label="{{ $label }}">{{ $label }}</button>
                    @endforeach
                </div>
                <div id="description-editor" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Descrição do produto" class="product-description min-h-40 p-3 outline-none">{!! $descriptionHtml !!}</div>
            </div>
            <textarea id="description" name="description" class="hidden">{{ old('description', $product->description) }}</textarea>
            <p class="mt-1 text-xs text-gray-500">Formatação básica, títulos, listas e links seguros.</p>
            @error('description')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
    </section>

    <section class="grid gap-5 rounded-xl border border-gray-200 bg-white p-6 sm:p-8 sm:grid-cols-[12rem_1fr]" aria-labelledby="imagens-produto">
        <div>
            <h3 id="imagens-produto" class="text-lg font-semibold">Imagens</h3>
            <p class="mt-1 text-sm text-gray-500">Escolha até seis imagens. Os botões de ordem movem as imagens; marque a principal.</p>
        </div>
        <div class="space-y-4">
            <input id="main_media_id" type="hidden" name="main_media_id" value="{{ $selectedMediaId }}">
            <div class="flex flex-wrap items-center gap-3"><button id="open-media-picker" type="button" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold">Adicionar imagem da biblioteca</button><p id="gallery-count" class="text-sm text-gray-600" aria-live="polite"></p></div>
            <p id="gallery-limit" class="hidden text-sm font-medium text-amber-800">Limite de seis imagens atingido.</p>
            <div id="product-gallery" class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ($gallery as $image)
                    @php($imageUrl = app(\App\Services\MediaService::class)->url($image))
                    <div class="gallery-item rounded-lg border border-gray-200 p-2" data-media-id="{{ $image->id }}" data-media-url="{{ $imageUrl }}" data-media-name="{{ $image->original_name }}">
                        <img src="{{ $imageUrl }}" alt="{{ $image->original_name }}" class="h-28 w-full rounded bg-gray-50 object-contain">
                        <input type="hidden" name="gallery_media_ids[]" value="{{ $image->id }}">
                        <label class="mt-2 flex items-center gap-2 text-xs"><input type="radio" name="gallery_primary_choice" value="{{ $image->id }}" @checked((string)$image->id === (string)$selectedMediaId)> Imagem principal</label>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs"><span class="min-w-0 flex-1 truncate">{{ $image->original_name }}</span><button type="button" data-move="up" aria-label="Mover imagem para cima">↑</button><button type="button" data-move="down" aria-label="Mover imagem para baixo">↓</button><button type="button" data-remove-image class="underline">Remover</button></div>
                    </div>
                @endforeach
            </div>
            @error('main_media_id')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
            @error('gallery_media_ids')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
            @error('gallery_media_ids.*')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
    </section>

    <section class="grid gap-5 rounded-xl border border-gray-200 bg-white p-6 sm:p-8 lg:grid-cols-2" aria-labelledby="precificacao-produto">
        <div class="lg:col-span-2"><h3 id="precificacao-produto" class="text-lg font-semibold">Precificação</h3></div>
        <div>
            <label for="cost_price" class="block text-sm font-medium">Preço de custo</label>
            <input id="cost_price" name="cost_price" inputmode="decimal" placeholder="30,00" value="{{ old('cost_price', $product->cost_price) }}" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2.5">
            @error('cost_price')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="target_margin" class="block text-sm font-medium">Margem de lucro desejada (%)</label>
            <input id="target_margin" name="target_margin" inputmode="decimal" placeholder="40" value="{{ old('target_margin', $product->target_margin) }}" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2.5">
            <p class="mt-1 text-sm text-gray-500">Margem é calculada sobre o preço de venda; markup é calculado sobre o custo.</p>
            @error('target_margin')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <div class="rounded-lg bg-gray-50 p-4">
            <p class="text-sm font-medium">Preço de venda sugerido</p>
            <output id="suggested-price" class="mt-1 block text-lg font-semibold">Informe custo e margem.</output>
            <button id="apply-suggested-price" type="button" class="mt-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold" disabled>Aplicar preço sugerido</button>
        </div>
        <div>
            <label for="price" class="block text-sm font-medium">Preço de venda</label>
            <input id="price" name="price" inputmode="decimal" placeholder="50,00" required value="{{ old('price', $product->price) }}" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2.5">
            @error('price')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            <p id="effective-margin" class="mt-2 text-sm text-gray-600" aria-live="polite"></p>
        </div>
    </section>

    <section class="space-y-5 rounded-xl border border-gray-200 bg-white p-6 sm:p-8" aria-labelledby="promocao-produto">
        <h3 id="promocao-produto" class="text-lg font-semibold">Promoção</h3>
        <input type="hidden" name="promotion_enabled" value="0">
        <label class="flex items-center gap-2 text-sm font-medium">
            <input id="promotion_enabled" type="checkbox" name="promotion_enabled" value="1" @checked($promotionEnabled)>
            Habilitar promoção
        </label>
        <div id="promotion-fields" @class(['grid gap-5 lg:grid-cols-2', 'hidden' => ! $promotionEnabled])>
            <div>
                <label for="discount_percent" class="block text-sm font-medium">Percentual de desconto sugerido (%)</label>
                <input id="discount_percent" inputmode="decimal" placeholder="10" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2.5">
                <output id="suggested-promotion-price" class="mt-2 block text-sm text-gray-600">Informe o preço normal e o desconto.</output>
                <button id="apply-suggested-promotion" type="button" class="mt-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold" disabled>Aplicar preço promocional sugerido</button>
            </div>
            <div>
                <label for="promotional_price" class="block text-sm font-medium">Preço promocional</label>
                <input id="promotional_price" name="promotional_price" inputmode="decimal" placeholder="45,00" value="{{ old('promotional_price', $product->promotional_price) }}" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2.5">
                @error('promotional_price')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="promotion_starts_at" class="block text-sm font-medium">Início ({{ config('app.timezone') }})</label>
                <input id="promotion_starts_at" name="promotion_starts_at" type="datetime-local" value="{{ old('promotion_starts_at', $product->promotion_starts_at?->format('Y-m-d\\TH:i')) }}" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2.5">
                @error('promotion_starts_at')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="promotion_ends_at" class="block text-sm font-medium">Término ({{ config('app.timezone') }})</label>
                <input id="promotion_ends_at" name="promotion_ends_at" type="datetime-local" value="{{ old('promotion_ends_at', $product->promotion_ends_at?->format('Y-m-d\\TH:i')) }}" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2.5">
                @error('promotion_ends_at')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
        </div>
        <p class="text-sm text-gray-500">O início é inclusivo e o término é exclusivo. Promoções desabilitadas, futuras ou expiradas exibem o preço normal.</p>
    </section>

    <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-6 sm:p-8" aria-labelledby="publicacao-produto">
        <h3 id="publicacao-produto" class="text-lg font-semibold">Publicação</h3>
        <label class="block text-sm font-medium">Ordem de exibição
            <input name="sort_order" type="number" min="1" value="{{ old('sort_order', $product->sort_order ?: 1) }}" class="mt-2 block w-32 rounded-lg border border-gray-300 px-3 py-2.5">
        </label>
        <input type="hidden" name="is_active" value="0">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->exists ? $product->is_active : false))> Produto ativo</label>
        <input type="hidden" name="is_featured" value="0">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))> Produto em destaque na home</label>
    </section>

    <button class="rounded-lg bg-gray-900 px-5 py-3 text-sm font-semibold text-white">{{ $submitLabel }}</button>
</form>

<dialog id="media-picker" class="m-auto max-h-[90dvh] w-[min(56rem,calc(100%-2rem))] overflow-y-auto rounded-xl p-0 shadow-xl backdrop:bg-black/50">
    <div class="space-y-5 p-5 sm:p-7">
        <div class="flex items-start justify-between gap-4">
            <div><h2 class="text-xl font-semibold">Biblioteca de mídia</h2><p class="mt-1 text-sm text-gray-600">Escolha uma imagem existente ou envie outra.</p></div>
            <button id="close-media-picker" type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold">Fechar</button>
        </div>
        <div id="media-upload-form" data-action="{{ route('admin.media.store') }}" data-token="{{ csrf_token() }}" class="flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 p-4">
            <label class="min-w-0 flex-1 text-sm font-medium">Enviar nova imagem
                <input id="media-file" name="file" type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-sm">
            </label>
            <button id="upload-media-button" type="button" class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white">Enviar imagem</button>
            <p id="media-upload-error" class="hidden basis-full text-sm text-red-700" role="alert"></p>
        </div>
        <div id="media-picker-list" class="grid max-h-[45vh] grid-cols-2 gap-3 overflow-y-auto sm:grid-cols-3 lg:grid-cols-4">
            @forelse ($media as $image)
                <button type="button" data-media-id="{{ $image->id }}" data-media-url="{{ app(\App\Services\MediaService::class)->url($image) }}" data-media-name="{{ $image->original_name }}" class="rounded-lg border border-gray-200 p-2 text-left hover:border-gray-500 focus:outline-2">
                    <img src="{{ app(\App\Services\MediaService::class)->url($image) }}" alt="" class="h-28 w-full rounded bg-gray-50 object-contain">
                    <span class="mt-2 block truncate text-xs">{{ $image->original_name }}</span>
                </button>
            @empty
                <p id="media-picker-empty" class="col-span-full rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">Nenhuma imagem cadastrada ainda.</p>
            @endforelse
        </div>
    </div>
</dialog>

<script>
(() => {
    const editor = document.getElementById('description-editor');
    const description = document.getElementById('description');
    if (editor && description) {
        const sync = () => { description.value = editor.innerHTML; };
        editor.addEventListener('input', sync);
        editor.closest('form').addEventListener('submit', sync);
        editor.closest('form').querySelector('[role="toolbar"]').addEventListener('mousedown', event => {
            if (event.target.closest('button')) event.preventDefault();
        });
        editor.closest('form').querySelectorAll('[data-editor-command]').forEach(button => button.addEventListener('click', () => {
            editor.focus();
            const command = button.dataset.editorCommand;
            if (command === 'h2' || command === 'h3' || command === 'p') document.execCommand('formatBlock', false, command);
            else if (command === 'createLink') {
                const href = window.prompt('Endereço do link (https://, http:// ou mailto:)');
                if (href) document.execCommand('createLink', false, href);
            } else document.execCommand(command, false, null);
            sync();
        }));
    }
})();

(() => {
    const picker = document.getElementById('media-picker');
    const list = document.getElementById('media-picker-list');
    const mediaInput = document.getElementById('main_media_id');
    const gallery = document.getElementById('product-gallery');
    const count = document.getElementById('gallery-count');
    const limit = document.getElementById('gallery-limit');
    const uploadForm = document.getElementById('media-upload-form');
    const file = document.getElementById('media-file');
    const uploadError = document.getElementById('media-upload-error');
    const uploadButton = document.getElementById('upload-media-button');
    if (!picker || !list || !gallery || !mediaInput) return;

    const updateGallery = () => {
        const items = [...gallery.querySelectorAll('.gallery-item')];
        count.textContent = `${items.length} de 6 imagens selecionadas`;
        limit.classList.toggle('hidden', items.length < 6);
        mediaInput.value = gallery.querySelector('input[name="gallery_primary_choice"]:checked')?.value ?? '';
        document.getElementById('open-media-picker').disabled = items.length >= 6;
    };
    const makeItem = (id, url, name, primary = false) => {
        const card = document.createElement('div');
        card.className = 'gallery-item rounded-lg border border-gray-200 p-2';
        card.dataset.mediaId = id; card.dataset.mediaUrl = url; card.dataset.mediaName = name;
        const img = document.createElement('img'); img.src = url; img.alt = name; img.loading = 'lazy'; img.className = 'h-28 w-full rounded bg-gray-50 object-contain';
        const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = 'gallery_media_ids[]'; hidden.value = id;
        const label = document.createElement('label'); label.className = 'mt-2 flex items-center gap-2 text-xs';
        const radio = document.createElement('input'); radio.type = 'radio'; radio.name = 'gallery_primary_choice'; radio.value = id; radio.checked = primary;
        label.append(radio, document.createTextNode(' Imagem principal'));
        const actions = document.createElement('div'); actions.className = 'mt-2 flex flex-wrap gap-2 text-xs';
        const title = document.createElement('span'); title.className = 'min-w-0 flex-1 truncate'; title.textContent = name;
        actions.append(title);
        for (const [move, text] of [['up','↑'], ['down','↓']]) { const button = document.createElement('button'); button.type = 'button'; button.dataset.move = move; button.setAttribute('aria-label', move === 'up' ? 'Mover imagem para cima' : 'Mover imagem para baixo'); button.textContent = text; actions.append(button); }
        const remove = document.createElement('button'); remove.type = 'button'; remove.dataset.removeImage = ''; remove.className = 'underline'; remove.textContent = 'Remover'; actions.append(remove);
        card.append(img, hidden, label, actions); return card;
    };
    const addImage = (id, url, name) => {
        if (gallery.querySelector(`[data-media-id="${CSS.escape(String(id))}"]`)) throw new Error('Esta imagem já está selecionada.');
        if (gallery.children.length >= 6) throw new Error('A galeria já atingiu o limite de seis imagens.');
        gallery.append(makeItem(id, url, name, gallery.children.length === 0));
        updateGallery();
    };

    document.getElementById('open-media-picker').addEventListener('click', () => picker.showModal());
    document.getElementById('close-media-picker').addEventListener('click', () => picker.close());
    gallery.addEventListener('change', updateGallery);
    gallery.closest('form').addEventListener('submit', updateGallery);
    gallery.addEventListener('click', event => {
        const card = event.target.closest('.gallery-item');
        if (!card) return;
        if (event.target.closest('[data-remove-image]')) {
            card.remove();
            if (!gallery.querySelector('input[name="gallery_primary_choice"]:checked') && gallery.firstElementChild) gallery.firstElementChild.querySelector('input[name="gallery_primary_choice"]').checked = true;
            updateGallery();
        } else {
            const move = event.target.closest('[data-move]')?.dataset.move;
            if (move === 'up' && card.previousElementSibling) gallery.insertBefore(card, card.previousElementSibling);
            if (move === 'down' && card.nextElementSibling) gallery.insertBefore(card.nextElementSibling, card);
            updateGallery();
        }
    });
    list.addEventListener('click', event => {
        const button = event.target.closest('[data-media-id]');
        if (button) {
            try { addImage(button.dataset.mediaId, button.dataset.mediaUrl, button.dataset.mediaName); picker.close(); }
            catch (error) { uploadError.textContent = error.message; uploadError.classList.remove('hidden'); }
        }
    });
    uploadButton.addEventListener('click', async () => {
        uploadError.textContent = '';
        uploadError.classList.add('hidden');
        if (!file.files.length) { uploadError.textContent = 'Escolha uma imagem para enviar.'; uploadError.classList.remove('hidden'); return; }
        uploadButton.disabled = true;
        try {
            const body = new FormData(); body.append('file', file.files[0]); body.append('_token', uploadForm.dataset.token);
            const response = await fetch(uploadForm.dataset.action, {
                method: 'POST',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body,
            });
            const result = await response.json();
            if (!response.ok) {
                const message = result.errors?.file?.[0] ?? result.message ?? 'Não foi possível enviar a imagem.';
                throw new Error(message);
            }
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.mediaId = result.id;
            button.dataset.mediaUrl = result.url;
            button.dataset.mediaName = result.name;
            button.className = 'rounded-lg border border-gray-200 p-2 text-left hover:border-gray-500 focus:outline-2';
            const image = document.createElement('img');
            image.src = result.url;
            image.alt = '';
            image.className = 'h-28 w-full rounded bg-gray-50 object-contain';
            const label = document.createElement('span');
            label.textContent = result.name;
            label.className = 'mt-2 block truncate text-xs';
            button.append(image, label);
            document.getElementById('media-picker-empty')?.remove();
            list.prepend(button);
            file.value = '';
            addImage(result.id, result.url, result.name);
            picker.close();
        } catch (error) {
            uploadError.textContent = error.message || 'Não foi possível enviar a imagem. Os demais campos foram preservados.';
            uploadError.classList.remove('hidden');
        } finally {
            uploadButton.disabled = false;
        }
    });
    updateGallery();
})();

(() => {
    const cost = document.getElementById('cost_price');
    const margin = document.getElementById('target_margin');
    const price = document.getElementById('price');
    const suggestion = document.getElementById('suggested-price');
    const applyPrice = document.getElementById('apply-suggested-price');
    const effectiveMargin = document.getElementById('effective-margin');
    const discount = document.getElementById('discount_percent');
    const promotionalPrice = document.getElementById('promotional_price');
    const promotionSuggestion = document.getElementById('suggested-promotion-price');
    const applyPromotion = document.getElementById('apply-suggested-promotion');
    const promotionToggle = document.getElementById('promotion_enabled');
    const promotionFields = document.getElementById('promotion-fields');

    const parseCents = value => {
        const normalized = value.trim().replace(',', '.');
        if (!/^\d{1,10}(?:\.\d{1,2})?$/.test(normalized)) return null;
        const [whole, fraction = ''] = normalized.split('.');
        return BigInt(whole) * 100n + BigInt((fraction + '00').slice(0, 2));
    };
    const parseBasisPoints = value => {
        const normalized = value.trim().replace(',', '.');
        if (!/^\d{1,2}(?:\.\d{1,2})?$/.test(normalized)) return null;
        const [whole, fraction = ''] = normalized.split('.');
        return BigInt(whole) * 100n + BigInt((fraction + '00').slice(0, 2));
    };
    const roundDivide = (numerator, denominator) => (numerator * 2n + denominator) / (2n * denominator);
    const moneyText = cents => (cents / 100n) + ',' + String(cents % 100n).padStart(2, '0');
    const percentText = basis => (basis / 100n) + ',' + String(basis % 100n).padStart(2, '0') + '%';

    let suggestedCents = null;
    const updatePriceSuggestion = () => {
        const costCents = parseCents(cost.value);
        const marginBasis = parseBasisPoints(margin.value);
        if (costCents === null || marginBasis === null || marginBasis >= 10000n) {
            suggestedCents = null;
            suggestion.textContent = marginBasis !== null && marginBasis >= 10000n ? 'A margem precisa ser menor que 100%.' : 'Informe custo e margem.';
            applyPrice.disabled = true;
            return;
        }
        suggestedCents = roundDivide(costCents * 10000n, 10000n - marginBasis);
        if (suggestedCents > 999999999999n) {
            suggestedCents = null;
            suggestion.textContent = 'O preço sugerido excede o limite permitido.';
            applyPrice.disabled = true;
            return;
        }
        suggestion.textContent = 'R$ ' + moneyText(suggestedCents);
        applyPrice.disabled = false;
        updatePromotionSuggestion();
    };
    const updateEffectiveMargin = () => {
        const costCents = parseCents(cost.value);
        const saleCents = parseCents(price.value);
        if (costCents === null || saleCents === null || saleCents === 0n) {
            effectiveMargin.textContent = '';
            return;
        }
        const profit = saleCents - costCents;
        const sign = profit < 0n ? '-' : '';
        const absoluteProfit = profit < 0n ? -profit : profit;
        const basis = roundDivide(absoluteProfit * 10000n, saleCents);
        effectiveMargin.textContent = 'Margem efetiva: ' + sign + percentText(basis);
    };
    const updatePromotionSuggestion = () => {
        const saleCents = parseCents(price.value);
        const discountBasis = parseBasisPoints(discount.value);
        if (saleCents === null || discountBasis === null || discountBasis > 10000n) {
            promotionSuggestion.textContent = 'Informe o preço normal e um desconto de até 100%.';
            applyPromotion.disabled = true;
            return;
        }
        const cents = roundDivide(saleCents * (10000n - discountBasis), 10000n);
        promotionSuggestion.textContent = 'Preço sugerido: R$ ' + moneyText(cents);
        applyPromotion.disabled = false;
    };

    cost.addEventListener('input', () => { updatePriceSuggestion(); updateEffectiveMargin(); });
    margin.addEventListener('input', updatePriceSuggestion);
    price.addEventListener('input', () => { updateEffectiveMargin(); updatePromotionSuggestion(); });
    discount.addEventListener('input', updatePromotionSuggestion);
    applyPrice.addEventListener('click', () => {
        if (suggestedCents !== null) {
            price.value = moneyText(suggestedCents);
            price.dispatchEvent(new Event('input', {bubbles: true}));
        }
    });
    applyPromotion.addEventListener('click', () => {
        const saleCents = parseCents(price.value);
        const discountBasis = parseBasisPoints(discount.value);
        if (saleCents !== null && discountBasis !== null) {
            promotionalPrice.value = moneyText(roundDivide(saleCents * (10000n - discountBasis), 10000n));
        }
    });
    promotionToggle.addEventListener('change', () => promotionFields.classList.toggle('hidden', !promotionToggle.checked));
    updatePriceSuggestion();
    updateEffectiveMargin();
    updatePromotionSuggestion();
})();
</script>
