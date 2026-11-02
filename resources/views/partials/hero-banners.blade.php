{{--
    Banners de destaque da vitrine.

    Recebe `$heroBanners` já filtrado e ordenado pelo `BannerService`: a view
    não consulta `Banner`, não conhece `is_active` e não reordena nada.

    Sem banners ativos, **nada** é emitido — nem a seção vazia, nem uma moldura
    sem conteúdo.

    A mídia é conferida antes de renderizar pelo mesmo motivo da listagem
    administrativa: a FK protege o banco, mas a vitrine prefere omitir um item
    a estourar caso o relacionamento não resolva.
--}}
@php
    $slides = $heroBanners->filter(fn ($banner) => $banner->media !== null)->values();
@endphp

@if ($slides->isNotEmpty())
    @if ($slides->count() === 1)
        @php($banner = $slides->first())
        <section aria-label="Destaques" class="mb-8 overflow-hidden rounded-xl border border-slate-200 bg-white">
            @if ($banner->link_url !== null)
                <a href="{{ $banner->link_url }}" class="block">
                    @include('partials.banner-image', ['banner' => $banner, 'media' => $media, 'loading' => 'eager'])
                </a>
            @else
                @include('partials.banner-image', ['banner' => $banner, 'media' => $media, 'loading' => 'eager'])
            @endif
        </section>
    @else
        <section
            class="relative mb-8 overflow-hidden rounded-xl border border-slate-200 bg-white"
            aria-label="Destaques"
            aria-roledescription="carrossel"
            x-data="heroCarousel({{ $slides->count() }})"
            @mouseenter="setHovered(true)"
            @mouseleave="setHovered(false)"
            @focusin="setFocused(true)"
            @focusout="$nextTick(() => setFocused($el.contains(document.activeElement)))"
        >
            <div id="hero-banner-slides" class="relative aspect-[16/6] overflow-hidden bg-white">
                @foreach ($slides as $index => $banner)
                    <div
                        x-show="current === {{ $index }}"
                        @if ($index > 0) x-cloak @endif
                        x-transition:enter="transition-opacity duration-500 motion-reduce:transition-none"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition-opacity duration-500 motion-reduce:transition-none"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="absolute inset-0"
                        role="group"
                        aria-roledescription="slide"
                        aria-label="{{ $index + 1 }} de {{ $slides->count() }}"
                        :aria-hidden="current !== {{ $index }}"
                        :inert="current !== {{ $index }}"
                    >
                        @if ($banner->link_url !== null)
                            <a href="{{ $banner->link_url }}" class="block h-full w-full">
                                @include('partials.banner-image', ['banner' => $banner, 'media' => $media, 'loading' => $index === 0 ? 'eager' : 'lazy', 'imageClass' => 'h-full w-full object-contain'])
                            </a>
                        @else
                            @include('partials.banner-image', ['banner' => $banner, 'media' => $media, 'loading' => $index === 0 ? 'eager' : 'lazy', 'imageClass' => 'h-full w-full object-contain'])
                        @endif
                    </div>
                @endforeach

                <button type="button" @click="advance(-1)" aria-label="Banner anterior" class="theme-focus theme-carousel-arrow absolute left-3 top-1/2 z-10 flex min-h-11 min-w-11 -translate-y-1/2 items-center justify-center rounded-full shadow-md transition-colors duration-200 motion-reduce:transition-none sm:left-5" aria-controls="hero-banner-slides">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/></svg>
                </button>
                <button type="button" @click="advance(1)" aria-label="Próximo banner" class="theme-focus theme-carousel-arrow absolute right-3 top-1/2 z-10 flex min-h-11 min-w-11 -translate-y-1/2 items-center justify-center rounded-full shadow-md transition-colors duration-200 motion-reduce:transition-none sm:right-5" aria-controls="hero-banner-slides">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                </button>
            </div>

            <div id="hero-banner-controls" class="flex min-h-14 items-center justify-center gap-3 border-t border-slate-100 px-3 py-2">
                <button type="button" x-show="!reducedMotion" x-cloak @click="togglePause" :aria-pressed="pausedByUser.toString()" :aria-label="pausedByUser ? 'Retomar rotação automática' : 'Pausar rotação automática'" class="theme-focus theme-carousel-toggle flex min-h-11 min-w-11 items-center justify-center rounded-full border transition-colors duration-200 motion-reduce:transition-none" aria-controls="hero-banner-slides">
                    <svg x-show="!pausedByUser" aria-hidden="true" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M7 5a2 2 0 0 0-2 2v10a2 2 0 1 0 4 0V7a2 2 0 0 0-2-2Zm10 0a2 2 0 0 0-2 2v10a2 2 0 1 0 4 0V7a2 2 0 0 0-2-2Z"/></svg>
                    <svg x-show="pausedByUser" x-cloak aria-hidden="true" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M8 5a1 1 0 0 0-1 1v12a1 1 0 0 0 1.5.866l10-6a1 1 0 0 0 0-1.732l-10-6A1 1 0 0 0 8 5Z"/></svg>
                </button>
                <div class="flex items-center justify-center gap-2" role="group" aria-label="Escolher banner">
                    @foreach ($slides as $index => $banner)
                        <button type="button" @click="goTo({{ $index }})" :aria-current="current === {{ $index }} ? 'true' : 'false'" aria-label="Mostrar banner {{ $index + 1 }} de {{ $slides->count() }}" class="theme-focus flex min-h-11 min-w-11 items-center justify-center rounded-full">
                            <span aria-hidden="true" :class="current === {{ $index }} ? 'theme-carousel-dot-active h-2.5 w-7 rounded-full' : 'theme-carousel-dot-inactive h-2.5 w-2.5 rounded-full'" class="block transition-[width,background-color] duration-200 motion-reduce:transition-none"></span>
                        </button>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endif
