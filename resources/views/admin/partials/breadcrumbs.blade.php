{{--
    Trilha derivada da rota atual, sem sistema genérico: com duas páginas
    administrativas, um resolvedor configurável seria mais código do que
    navegação. Cada nova seção acrescenta um @elseif aqui até que o volume
    justifique outra abordagem.
--}}
<nav aria-label="Trilha de navegação" class="text-sm text-gray-500">
    <ol class="flex items-center gap-2">
        @if (request()->routeIs('admin.settings.theme.*'))
            <li>
                <a href="{{ route('admin') }}" class="hover:text-gray-700">Dashboard</a>
            </li>
            <li aria-hidden="true" class="text-gray-300">/</li>
            <li>
                <a href="{{ route('admin.settings.edit') }}" class="hover:text-gray-700">Configurações</a>
            </li>
            <li aria-hidden="true" class="text-gray-300">/</li>
            <li class="font-medium text-gray-700" aria-current="page">Tema e cores</li>
        @elseif (request()->routeIs('admin.settings.*'))
            <li>
                <a href="{{ route('admin') }}" class="hover:text-gray-700">Dashboard</a>
            </li>
            <li aria-hidden="true" class="text-gray-300">/</li>
            <li class="font-medium text-gray-700" aria-current="page">Configurações</li>
        @else
            <li>Painel</li>
            <li aria-hidden="true" class="text-gray-300">/</li>
            <li class="font-medium text-gray-700" aria-current="page">Dashboard</li>
        @endif
    </ol>
</nav>
