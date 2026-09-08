{{--
    Formulário do item de menu, compartilhado pela criação (na tela do menu) e
    pela edição (em tela própria).

    Recebe: $action, $method, $submitLabel, $menu, $types, $pages, $parents e,
    na edição, $item.

    Não há campo de ordem nem campo de menu. `sort_order` é atribuído pelo
    MenuService, que conhece os outros irmãos, e `menu_id` vem do contexto da
    rota — o item não muda de menu por edição.

    Os `id` são prefixados com `item-` porque este formulário divide a tela de
    edição com o formulário do menu, e dois elementos não podem compartilhar o
    mesmo identificador. Os `name` continuam sendo os do domínio.

    Sem JavaScript: os dois campos de destino aparecem juntos e o `type` decide
    qual deles vale. Alternar a visibilidade exigiria script só desta tela, e a
    validação condicional do Form Request já recusa a combinação errada.
--}}
@php($item = $item ?? null)
{{--
    Na tela do menu este formulário divide o `old()` com o formulário do menu.
    `label` só existe aqui, então a presença dele identifica de quem é o input
    velho — sem isso, uma recusa lá repovoaria a caixa de seleção daqui.
--}}
@php($resubmitted = old('label') !== null)
@php($selectedType = old('type', $item?->type->value ?? \App\Enums\MenuItemType::Page->value))

<form method="POST" action="{{ $action }}"
      class="rounded-xl border border-gray-200 bg-white p-6 sm:p-8">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="space-y-5">

        <div>
            <label for="item-label" class="block text-sm font-medium text-gray-900">Rótulo</label>
            <input
                id="item-label"
                name="label"
                type="text"
                value="{{ old('label', $item?->label) }}"
                required
                maxlength="120"
                @error('label') aria-invalid="true" aria-describedby="item-label-erro" @else aria-describedby="item-label-ajuda" @enderror
                class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('label') border-red-400 @enderror"
            >
            @error('label')
                <p id="item-label-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @else
                <p id="item-label-ajuda" class="mt-2 text-sm text-gray-500">
                    Texto que aparece na navegação da loja.
                </p>
            @enderror
        </div>

        <div>
            <label for="item-type" class="block text-sm font-medium text-gray-900">Destino</label>
            <select
                id="item-type"
                name="type"
                required
                @error('type') aria-invalid="true" aria-describedby="item-type-erro" @else aria-describedby="item-type-ajuda" @enderror
                class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('type') border-red-400 @enderror"
            >
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('type')
                <p id="item-type-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @else
                <p id="item-type-ajuda" class="mt-2 text-sm text-gray-500">
                    Preencha somente o campo correspondente ao destino escolhido: o item aponta
                    para uma página <strong class="font-medium text-gray-700">ou</strong> para uma URL,
                    nunca para as duas coisas.
                </p>
            @enderror
        </div>

        <div>
            <label for="item-page_id" class="block text-sm font-medium text-gray-900">Página do site</label>
            <select
                id="item-page_id"
                name="page_id"
                @error('page_id') aria-invalid="true" aria-describedby="item-page-erro" @else aria-describedby="item-page-ajuda" @enderror
                class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('page_id') border-red-400 @enderror"
            >
                <option value="">Selecione uma página</option>
                {{-- O valor é sempre Page.id: o slug é endereço público e pode
                     mudar, e usá-lo como valor faria uma renomeação quebrar o
                     vínculo. Ele aparece apenas para distinguir as opções. --}}
                @foreach ($pages as $page)
                    <option value="{{ $page->id }}"
                        @selected((int) old('page_id', $item?->page_id) === $page->id)>
                        {{ $page->title }} — /paginas/{{ $page->slug }}@if ($page->status === \App\Enums\PageStatus::Draft) (rascunho)@endif
                    </option>
                @endforeach
            </select>
            @error('page_id')
                <p id="item-page-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @else
                <p id="item-page-ajuda" class="mt-2 text-sm text-gray-500">
                    Usado apenas quando o destino é <em>Página do site</em>. Rascunhos podem ser
                    vinculados desde já; páginas na lixeira não aparecem aqui.
                </p>
            @enderror
        </div>

        <div>
            <label for="item-url" class="block text-sm font-medium text-gray-900">URL personalizada</label>
            <input
                id="item-url"
                name="url"
                type="text"
                value="{{ old('url', $item?->url) }}"
                maxlength="2048"
                @error('url') aria-invalid="true" aria-describedby="item-url-erro" @else aria-describedby="item-url-ajuda" @enderror
                class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('url') border-red-400 @enderror"
            >
            @error('url')
                <p id="item-url-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @else
                <p id="item-url-ajuda" class="mt-2 text-sm text-gray-500">
                    Usada apenas quando o destino é <em>URL personalizada</em>. Caminho interno
                    começando por / — por exemplo, /contato — ou uma URL completa http/https.
                </p>
            @enderror
        </div>

        <div>
            <label for="item-parent_id" class="block text-sm font-medium text-gray-900">Item pai</label>
            <select
                id="item-parent_id"
                name="parent_id"
                @error('parent_id') aria-invalid="true" aria-describedby="item-parent-erro" @else aria-describedby="item-parent-ajuda" @enderror
                class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('parent_id') border-red-400 @enderror"
            >
                {{-- Vazio é a raiz. O middleware padrão do Laravel converte a
                     string vazia em null, então esta opção chega ao domínio como
                     ausência de pai — e não como zero. --}}
                <option value="">— Nível raiz —</option>
                {{-- Somente itens deste menu, e nunca o próprio item ou um de
                     seus descendentes: o Controller já os removeu da lista. --}}
                @foreach ($parents as $row)
                    <option value="{{ $row['item']->id }}"
                        @selected((int) old('parent_id', $item?->parent_id) === $row['item']->id)>
                        {{ str_repeat('— ', $row['depth']) }}{{ $row['item']->label }}
                    </option>
                @endforeach
            </select>
            @error('parent_id')
                <p id="item-parent-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @else
                <p id="item-parent-ajuda" class="mt-2 text-sm text-gray-500">
                    @if ($item === null)
                        O item entra no fim da lista do grupo escolhido.
                    @else
                        Trocar o item pai move o item para o fim da lista do novo grupo.
                    @endif
                </p>
            @enderror
        </div>

        <div class="border-t border-gray-100 pt-5">
            {{-- O campo oculto garante que a ausência do check chegue como "0",
                 em vez de o campo simplesmente não existir no POST. --}}
            <input type="hidden" name="is_active" value="0">
            <label for="item-is_active" class="flex items-start gap-3">
                <input
                    id="item-is_active"
                    name="is_active"
                    type="checkbox"
                    value="1"
                    @checked($resubmitted ? (bool) old('is_active') : (bool) ($item?->is_active ?? false))
                    class="mt-0.5 size-4 rounded border-gray-300 text-gray-900 focus:ring-2 focus:ring-gray-900/20"
                >
                <span>
                    <span class="block text-sm font-medium text-gray-900">Ativo</span>
                    <span class="block text-sm text-gray-500">
                        Um item novo nasce inativo. Ative quando ele estiver pronto para aparecer.
                    </span>
                </span>
            </label>
        </div>

        @if ($item !== null)
            <p class="text-sm text-gray-500">
                Ordem atual no grupo: <strong class="font-medium text-gray-700">{{ $item->sort_order }}</strong>.
                A ordem é ajustada pelos controles da árvore, nunca digitada.
            </p>
        @endif

    </div>

    <div class="mt-8 flex items-center gap-3">
        <button type="submit"
                class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-gray-700 focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:outline-none">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('admin.menus.edit', $menu) }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">
            @if ($item === null) Cancelar @else Voltar para o menu @endif
        </a>
    </div>
</form>
