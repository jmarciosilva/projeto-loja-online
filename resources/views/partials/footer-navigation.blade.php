{{-- Recebe somente os destinos já filtrados pelo MenuService. --}}
@if ($nodes !== [])
    <nav aria-label="Links institucionais" class="mt-3">
        <ul class="space-y-1">
            @foreach ($nodes as $node)
                <li>
                    <a href="{{ $node['url'] }}" class="theme-focus inline-flex min-h-11 max-w-full items-center break-words rounded text-sm text-gray-700 underline-offset-4 hover:text-gray-950 hover:underline [overflow-wrap:anywhere]">{{ $node['item']->label }}</a>
                    @if ($node['children'] !== [])
                        @include('partials.footer-navigation', ['nodes' => $node['children']])
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>
@endif
