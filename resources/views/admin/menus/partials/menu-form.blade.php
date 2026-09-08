{{--
    Formulário do menu, compartilhado por criar e editar.

    Recebe: $action, $method, $submitLabel e, na edição, $menu.

    O `code` só é um campo na **criação**. Depois ele vira texto informativo:
    é a identidade técnica pela qual o consumidor público encontra o menu, e um
    input desabilitado — ou um campo que o backend descarta — daria a entender
    que existe algo a decidir. O `UpdateMenuRequest` sequer o valida, e o
    `MenuService` recusaria a alteração vinda de qualquer outro consumidor.
--}}
@php($menu = $menu ?? null)
{{--
    A tela de edição hospeda dois formulários que compartilham o mesmo `old()`.
    Sem esta marca, uma recusa no formulário de item repovoaria a caixa de
    seleção daqui com o estado que o administrador escolheu lá. `name` só existe
    neste formulário, então a presença dele identifica de quem é o input velho.
--}}
@php($resubmitted = old('name') !== null)

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
                value="{{ old('name', $menu?->name) }}"
                required
                maxlength="120"
                @error('name') aria-invalid="true" aria-describedby="name-erro" @else aria-describedby="name-ajuda" @enderror
                class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('name') border-red-400 @enderror"
            >
            @error('name')
                <p id="name-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @else
                <p id="name-ajuda" class="mt-2 text-sm text-gray-500">
                    Identificação administrativa do menu. Pode mudar quando quiser.
                </p>
            @enderror
        </div>

        @if ($menu === null)
            <div>
                <label for="code" class="block text-sm font-medium text-gray-900">Código</label>
                <input
                    id="code"
                    name="code"
                    type="text"
                    value="{{ old('code') }}"
                    required
                    maxlength="64"
                    @error('code') aria-invalid="true" aria-describedby="code-erro" @else aria-describedby="code-ajuda" @enderror
                    class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 font-mono text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('code') border-red-400 @enderror"
                >
                @error('code')
                    <p id="code-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                @else
                    <p id="code-ajuda" class="mt-2 text-sm text-gray-500">
                        Identidade técnica do menu — por exemplo, <code>main</code> ou <code>rodape</code>.
                        Letras minúsculas, números e hífen. <strong class="font-medium text-gray-700">Não poderá ser
                        alterado depois</strong>, porque é por ele que a loja encontra o menu.
                    </p>
                @enderror
            </div>
        @else
            <div>
                <span class="block text-sm font-medium text-gray-900">Código</span>
                <p class="mt-2 font-mono text-sm text-gray-700">{{ $menu->code }}</p>
                <p class="mt-2 text-sm text-gray-500">
                    O código é definido na criação e não muda: é por ele que a loja encontra este menu.
                </p>
            </div>
        @endif

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
                    @checked($resubmitted ? (bool) old('is_active') : (bool) ($menu?->is_active ?? false))
                    class="mt-0.5 size-4 rounded border-gray-300 text-gray-900 focus:ring-2 focus:ring-gray-900/20"
                >
                <span>
                    <span class="block text-sm font-medium text-gray-900">Ativo</span>
                    <span class="block text-sm text-gray-500">
                        Um menu novo nasce inativo. Ative quando a estrutura estiver pronta.
                    </span>
                </span>
            </label>
        </div>

    </div>

    <div class="mt-8 flex items-center gap-3">
        <button type="submit"
                class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-gray-700 focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:outline-none">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('admin.menus.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">
            Voltar para os menus
        </a>
    </div>
</form>
