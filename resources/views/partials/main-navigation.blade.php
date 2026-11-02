{{-- Recebe somente a árvore pública já filtrada pelo MenuService. --}}
@if ($nodes !== [])
    <nav aria-label="Navegação principal">
        <ul @class([
            'space-y-1' => $mobile,
            'flex flex-nowrap items-center gap-4 lg:gap-6' => ! $mobile,
        ])>
            @foreach ($nodes as $node)
                @include('partials.main-navigation-item', ['node' => $node, 'mobile' => $mobile, 'depth' => 0])
            @endforeach
        </ul>
    </nav>
@endif
