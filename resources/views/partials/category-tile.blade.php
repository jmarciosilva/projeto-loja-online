@php
    $name = mb_strtolower($category->name);
    $icon = match (true) {
        str_contains($name, 'inform') => 'computer',
        str_contains($name, 'fantas') => 'sparkle',
        str_contains($name, 'literatura') => 'open-book',
        str_contains($name, 'livro') => 'books',
        default => 'collection',
    };
    $tone = match ($toneIndex % 4) {
        0 => 'bg-orange-50',
        1 => 'bg-sky-50',
        2 => 'bg-violet-50',
        default => 'bg-emerald-50',
    };
@endphp

<a href="{{ route('categories.show', $category->slug) }}" class="theme-focus group flex min-h-[6.75rem] min-w-0 flex-col items-center justify-center gap-1.5 rounded-2xl border border-transparent {{ $tone }} px-2 py-3 text-center transition hover:border-slate-200 hover:bg-white hover:shadow-sm motion-reduce:transition-none sm:min-h-[7.5rem] sm:flex-row sm:justify-start sm:gap-3 sm:px-3 sm:text-left lg:px-4">
    <span class="theme-text-primary flex h-10 w-10 shrink-0 items-center justify-center sm:h-12 sm:w-12">
        @if ($icon === 'computer')
            <svg aria-hidden="true" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.2" class="h-10 w-10 sm:h-12 sm:w-12"><rect x="5" y="7" width="38" height="27" rx="3"/><path stroke-linecap="round" stroke-linejoin="round" d="M18 41h12m-6-7v7M10 29h28"/></svg>
        @elseif ($icon === 'sparkle')
            <svg aria-hidden="true" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.2" class="h-10 w-10 sm:h-12 sm:w-12"><path stroke-linecap="round" stroke-linejoin="round" d="m24 5 4.1 12.9L41 22l-12.9 4.1L24 39l-4.1-12.9L7 22l12.9-4.1L24 5Zm14 25 1.8 5.2L45 37l-5.2 1.8L38 44l-1.8-5.2L31 37l5.2-1.8L38 30Z"/></svg>
        @elseif ($icon === 'open-book')
            <svg aria-hidden="true" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.2" class="h-10 w-10 sm:h-12 sm:w-12"><path stroke-linecap="round" stroke-linejoin="round" d="M24 14c-5-5-11-5-18-4v25c7-1 13-1 18 4 5-5 11-5 18-4V10c-7-1-13-1-18 4Zm0 0v25M11 17c4-.3 7 .2 10 2m-10 3c4-.3 7 .2 10 2m16-7c-4-.3-7 .2-10 2m10 3c-4-.3-7 .2-10 2"/></svg>
        @elseif ($icon === 'books')
            <svg aria-hidden="true" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.2" class="h-10 w-10 sm:h-12 sm:w-12"><path stroke-linecap="round" stroke-linejoin="round" d="m8 13 25-5 3 8-25 5-3-8Zm2 12 26-3 2 8-26 4-2-9Zm3 13 26-1v-8H13v9Z"/><path stroke-linecap="round" d="M14 15v2m3 9v2m2 7v2"/></svg>
        @else
            <svg aria-hidden="true" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.2" class="h-10 w-10 sm:h-12 sm:w-12"><rect x="7" y="7" width="14" height="14" rx="3"/><rect x="27" y="7" width="14" height="14" rx="3"/><rect x="7" y="27" width="14" height="14" rx="3"/><rect x="27" y="27" width="14" height="14" rx="3"/></svg>
        @endif
    </span>
    <span class="min-w-0">
        <span class="block line-clamp-2 text-[11px] font-bold leading-tight text-slate-900 sm:text-sm">{{ $category->name }}</span>
        <span class="mt-0.5 block text-[10px] leading-tight text-slate-600 sm:text-xs">{{ $category->public_products_count ?? 0 }} {{ ($category->public_products_count ?? 0) === 1 ? 'produto' : 'produtos' }}</span>
    </span>
</a>
