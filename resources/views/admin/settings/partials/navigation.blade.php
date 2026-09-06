{{--
    Navegação local das subpáginas de Configurações. Fica aqui, e não na sidebar
    principal, para que o menu lateral não cresça com um item por tela de
    configuração. Cada item entra somente quando sua rota passa a existir.
--}}
<nav aria-label="Seções de configurações" class="flex flex-wrap gap-2">
    @php
        $secoes = [
            ['rota' => 'admin.settings.edit', 'padrao' => 'admin.settings.edit', 'rotulo' => 'Gerais'],
            ['rota' => 'admin.settings.theme.edit', 'padrao' => 'admin.settings.theme.*', 'rotulo' => 'Tema e cores'],
            ['rota' => 'admin.settings.identity.edit', 'padrao' => 'admin.settings.identity.*', 'rotulo' => 'Identidade visual'],
        ];
    @endphp

    @foreach ($secoes as $secao)
        @php($ativa = request()->routeIs($secao['padrao']))
        <a href="{{ route($secao['rota']) }}"
           @if ($ativa) aria-current="page" @endif
           @class([
               'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:outline-none',
               'bg-gray-900 text-white' => $ativa,
               'border border-gray-300 text-gray-700 hover:bg-gray-50' => ! $ativa,
           ])>
            {{ $secao['rotulo'] }}
        </a>
    @endforeach
</nav>
