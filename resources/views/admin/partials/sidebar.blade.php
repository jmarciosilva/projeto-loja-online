<aside class="border-b border-gray-200 bg-white lg:w-64 lg:shrink-0 lg:border-r lg:border-b-0">

    <div class="flex items-center gap-3 px-4 py-4 sm:px-6 lg:px-4">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-gray-900">
            {{-- Marca neutra: a identidade visual da loja ainda não foi definida. --}}
            <svg class="size-4 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 1a4 4 0 0 0-4 4v3H5.5A1.5 1.5 0 0 0 4 9.5v7A1.5 1.5 0 0 0 5.5 18h9a1.5 1.5 0 0 0 1.5-1.5v-7A1.5 1.5 0 0 0 14.5 8H14V5a4 4 0 0 0-4-4Zm2.5 7V5a2.5 2.5 0 0 0-5 0v3h5Z" clip-rule="evenodd" />
            </svg>
        </span>
        <span class="text-sm font-semibold tracking-tight">Área administrativa</span>
    </div>

    <nav aria-label="Navegação administrativa" class="px-4 pb-4 sm:px-6 lg:px-4">
        <ul class="space-y-1">
            <li>
                {{--
                    O estado ativo sai direto de routeIs(), sem serviço ou registro
                    de navegação: com um item só, qualquer abstração custaria mais
                    do que resolve. Novos módulos repetem este mesmo padrão.
                --}}
                <a href="{{ route('admin') }}"
                   @if (request()->routeIs('admin')) aria-current="page" @endif
                   @class([
                       'block rounded-lg px-3 py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:outline-none',
                       'bg-gray-900 text-white' => request()->routeIs('admin'),
                       'text-gray-700 hover:bg-gray-100' => ! request()->routeIs('admin'),
                   ])>
                    Dashboard
                </a>
            </li>
        </ul>

        {{--
            Espaço reservado para as próximas seções do painel. Cada uma entra
            aqui somente quando a funcionalidade correspondente existir — link
            para página inexistente é caminho quebrado, não prévia.
        --}}
        <p class="mt-6 hidden border-t border-gray-100 pt-4 text-xs text-gray-400 lg:block">
            Novas seções aparecem aqui conforme forem implementadas.
        </p>
    </nav>

</aside>
