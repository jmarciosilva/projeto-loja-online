{{-- Recebe somente banners elegíveis, ordenados pelo BannerService. --}}
@php($slides = $heroBanners->filter(fn ($banner) => $banner->media !== null)->values())

@if ($slides->isEmpty())
    <section aria-labelledby="storefront-hero-title" class="relative overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-br from-white via-orange-50 to-sky-50 px-5 py-8 shadow-sm sm:px-8 sm:py-10 lg:px-12 lg:py-12">
        <div class="relative z-10 max-w-2xl">
            <p class="theme-text-accent text-xs font-extrabold uppercase tracking-[.18em]">{{ $storefrontBrand['name'] }}</p>
            <h1 id="storefront-hero-title" class="theme-text-primary mt-2 text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl lg:text-5xl">{{ $storefrontBrand['slogan'] }}</h1>
            <p class="mt-3 max-w-xl text-sm leading-6 text-slate-700 sm:text-base">Explore o catálogo da loja e veja as informações de cada produto.</p>
            <a href="{{ route('catalog.index') }}" class="theme-bg-accent theme-focus mt-5 inline-flex min-h-12 items-center gap-2 rounded-xl px-5 text-sm font-bold shadow-sm transition hover:brightness-95">Explorar produtos <span aria-hidden="true">→</span></a>
        </div>
    </section>
@else
    @php($multipleSlides = $slides->count() > 1)
    <section
        class="relative isolate overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"
        aria-label="Destaques"
        @if ($multipleSlides)
            aria-roledescription="carrossel"
            x-data="heroCarousel({{ $slides->count() }})"
            @mouseenter="setHovered(true)"
            @mouseleave="setHovered(false)"
            @focusin="setFocused(true)"
            @focusout="$nextTick(() => setFocused($el.contains(document.activeElement)))"
        @endif
    >
        <div id="hero-banner-slides" class="relative lg:aspect-[16/5]">
            @foreach ($slides as $index => $banner)
                <div
                    @if ($multipleSlides)
                        x-show="current === {{ $index }}"
                        @if ($index > 0) x-cloak @endif
                        x-transition:enter="transition-opacity duration-500 motion-reduce:transition-none"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition-opacity duration-500 motion-reduce:transition-none"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                    @endif
                    @class(['relative lg:absolute lg:inset-0 lg:h-full'])
                    @if ($multipleSlides)
                        role="group"
                        aria-roledescription="slide"
                        aria-label="{{ $index + 1 }} de {{ $slides->count() }}"
                        :aria-hidden="current !== {{ $index }}"
                        :inert="current !== {{ $index }}"
                    @endif
                >
                    <div class="relative aspect-[16/9] overflow-hidden bg-slate-100 sm:aspect-[16/7] lg:absolute lg:inset-0 lg:aspect-auto lg:h-full">
                        @include('partials.banner-image', ['banner' => $banner, 'media' => $media, 'loading' => $index === 0 ? 'eager' : 'lazy', 'imageClass' => 'absolute inset-0 h-full w-full object-cover object-center'])
                        <div aria-hidden="true" class="absolute inset-0 hidden bg-gradient-to-r from-white/95 via-white/80 to-white/0 lg:block"></div>
                    </div>

                    <div class="relative z-10 bg-white px-4 py-5 sm:px-7 sm:py-6 lg:absolute lg:inset-0 lg:flex lg:items-center lg:bg-transparent lg:px-12 lg:py-8">
                        <div class="max-w-2xl lg:max-w-[48%]">
                            @if ($index === 0)
                                <h1 id="storefront-hero-title" class="theme-text-primary text-2xl font-extrabold leading-tight tracking-tight sm:text-3xl lg:text-5xl">{{ $storefrontBrand['slogan'] }}</h1>
                            @else
                                <h2 class="theme-text-primary text-2xl font-extrabold leading-tight tracking-tight sm:text-3xl lg:text-5xl">{{ $storefrontBrand['slogan'] }}</h2>
                            @endif
                            <p class="mt-2 text-sm leading-6 text-slate-700 sm:mt-3 sm:text-base">Explore o catálogo da loja e veja as informações de cada produto.</p>
                            <a href="{{ $banner->link_url ?? route('catalog.index') }}" class="theme-bg-accent theme-focus mt-4 inline-flex min-h-11 items-center gap-2 rounded-xl px-5 text-sm font-bold shadow-sm transition hover:brightness-95 sm:mt-5">Explorar produtos <span aria-hidden="true">→</span></a>
                        </div>
                    </div>
                </div>
            @endforeach

            @if ($multipleSlides)
                <button type="button" @click="advance(-1)" aria-label="Banner anterior" class="theme-focus theme-carousel-arrow absolute left-2 top-[24vw] z-20 flex min-h-11 min-w-11 -translate-y-1/2 items-center justify-center rounded-full shadow-md transition-colors duration-200 motion-reduce:transition-none sm:left-4 sm:top-[30%] lg:top-1/2" aria-controls="hero-banner-slides">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/></svg>
                </button>
                <button type="button" @click="advance(1)" aria-label="Próximo banner" class="theme-focus theme-carousel-arrow absolute right-2 top-[24vw] z-20 flex min-h-11 min-w-11 -translate-y-1/2 items-center justify-center rounded-full shadow-md transition-colors duration-200 motion-reduce:transition-none sm:right-4 sm:top-[30%] lg:top-1/2" aria-controls="hero-banner-slides">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                </button>
            @endif
        </div>

        @if ($multipleSlides)
            <div id="hero-banner-controls" class="flex min-h-12 items-center justify-center gap-2 border-t border-slate-100 bg-white px-3 py-1 sm:gap-3">
                <button type="button" x-show="!reducedMotion" x-cloak @click="togglePause" :aria-pressed="pausedByUser.toString()" :aria-label="pausedByUser ? 'Retomar rotação automática' : 'Pausar rotação automática'" class="theme-focus theme-carousel-toggle flex min-h-11 min-w-11 items-center justify-center rounded-full border bg-white/90 transition-colors duration-200 motion-reduce:transition-none" aria-controls="hero-banner-slides">
                    <svg x-show="!pausedByUser" aria-hidden="true" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M7 5a2 2 0 0 0-2 2v10a2 2 0 1 0 4 0V7a2 2 0 0 0-2-2Zm10 0a2 2 0 0 0-2 2v10a2 2 0 1 0 4 0V7a2 2 0 0 0-2-2Z"/></svg>
                    <svg x-show="pausedByUser" x-cloak aria-hidden="true" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M8 5a1 1 0 0 0-1 1v12a1 1 0 0 0 1.5.866l10-6a1 1 0 0 0 0-1.732l-10-6A1 1 0 0 0 8 5Z"/></svg>
                </button>
                <div class="flex items-center justify-center gap-1 rounded-full bg-white/85 px-1" role="group" aria-label="Escolher banner">
                    @foreach ($slides as $index => $banner)
                        <button type="button" @click="goTo({{ $index }})" :aria-current="current === {{ $index }} ? 'true' : 'false'" aria-label="Mostrar banner {{ $index + 1 }} de {{ $slides->count() }}" class="theme-focus flex min-h-11 min-w-11 items-center justify-center rounded-full">
                            <span aria-hidden="true" :class="current === {{ $index }} ? 'theme-carousel-dot-active h-2.5 w-7 rounded-full' : 'theme-carousel-dot-inactive h-2.5 w-2.5 rounded-full'" class="block transition-[width,background-color] duration-200 motion-reduce:transition-none"></span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
@endif
