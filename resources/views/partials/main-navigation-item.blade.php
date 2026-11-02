@php($submenuId = 'menu-submenu-'.$node['item']->id)
<li class="relative min-w-0" x-data="{ submenuOpen: false }">
    <div @class(['flex min-w-0 items-center gap-1', 'w-full' => $mobile])>
        <a href="{{ $node['url'] }}"
           @if (request()->url() === $node['url']) aria-current="page" @endif
           @class([
               'block min-h-11 min-w-0 break-words rounded-md px-3 py-2.5 text-base font-medium text-gray-800 [overflow-wrap:anywhere] hover:bg-gray-50' => $mobile,
               'whitespace-nowrap py-2 text-sm font-medium text-gray-700 hover:text-gray-950' => ! $mobile,
               'underline underline-offset-4' => request()->url() === $node['url'],
           ])>{{ $node['item']->label }}</a>

        @if ($node['children'] !== [])
            <button type="button" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-md text-gray-700 hover:bg-gray-100"
                    aria-label="Abrir submenu: {{ $node['item']->label }}"
                    aria-controls="{{ $submenuId }}"
                    :aria-expanded="submenuOpen.toString()"
                    @click="submenuOpen = !submenuOpen">
                <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 transition-transform" :class="submenuOpen ? 'rotate-180' : ''"><path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
            </button>
        @endif
    </div>

    @if ($node['children'] !== [])
        <ul id="{{ $submenuId }}" x-show="submenuOpen" x-cloak
            @class([
                'ml-3 space-y-1 border-l border-gray-200 pl-2' => $mobile,
                'absolute left-0 top-full z-30 mt-2 min-w-56 space-y-1 rounded-lg border border-gray-200 bg-white p-2 shadow-lg' => ! $mobile && $depth === 0,
                'absolute left-full top-0 z-30 ml-1 min-w-56 space-y-1 rounded-lg border border-gray-200 bg-white p-2 shadow-lg' => ! $mobile && $depth > 0,
            ])>
            @foreach ($node['children'] as $child)
                @include('partials.main-navigation-item', ['node' => $child, 'mobile' => $mobile, 'depth' => $depth + 1])
            @endforeach
        </ul>
    @endif
</li>
