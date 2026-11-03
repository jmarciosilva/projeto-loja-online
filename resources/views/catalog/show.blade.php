@extends('layouts.app')

@php
    $pricingAt = now(config('app.timezone'));
@endphp

@section('title', $product->name.' · '.$storefrontBrand['name'])

@section('content')
    @php
        $gallery = $product->images;
        if ($gallery->isEmpty() && $product->mainMedia) {
            $gallery = collect([$product->mainMedia]);
        }
        $gallery = $gallery->values();
        $galleryIndex = $gallery->search(fn ($image) => $image->id === $product->main_media_id);
        $galleryIndex = $galleryIndex === false ? 0 : $galleryIndex;
        $initialImage = $gallery->get($galleryIndex);
        $galleryData = $gallery->map(fn ($image, $index) => [
            'url' => app(\App\Services\MediaService::class)->url($image),
            'alt' => $product->name.' — imagem '.($index + 1),
        ])->values();
        $availability = $product->stockAvailability();
        $availabilityLabel = match ($availability) {
            'available' => $product->stock->availableQuantity() === 1 ? 'Última unidade' : 'Disponível',
            'sold_out' => 'Esgotado',
            default => 'Disponibilidade não informada',
        };
        $availabilityStyle = match ($availability) {
            'available' => 'bg-emerald-50 text-emerald-800',
            'sold_out' => 'bg-rose-50 text-rose-800',
            default => 'bg-slate-100 text-slate-700',
        };
        $contactPhone = preg_replace('/[^0-9+]/', '', (string) ($storefrontContact['phone'] ?? ''));
        $contactPhoneHref = preg_match('/^\+?[0-9]{8,15}$/', $contactPhone) === 1 ? 'tel:'.$contactPhone : null;
        $hasProductContact = filled($storefrontContact['email'] ?? '') || $contactPhoneHref !== null;
    @endphp

    <div class="min-w-0 space-y-4 sm:space-y-6">
        <nav aria-label="Breadcrumb" class="break-words text-xs text-slate-500">
            <a href="{{ route('home') }}" class="theme-focus rounded underline underline-offset-2">Início</a>
            <span class="px-1" aria-hidden="true">/</span>
            <a href="{{ route('categories.show', $product->category->slug) }}" class="theme-focus rounded underline underline-offset-2">{{ $product->category->name }}</a>
            <span class="px-1" aria-hidden="true">/</span>
            <span aria-current="page" class="font-medium text-slate-700">{{ $product->name }}</span>
        </nav>

        <a href="{{ route('catalog.index') }}" class="theme-focus inline-flex min-h-10 items-center text-sm font-semibold underline underline-offset-4">← Voltar ao catálogo</a>

        <article class="grid min-w-0 gap-5 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:gap-8 sm:p-6 md:grid-cols-2 lg:p-8">
            <section class="min-w-0" aria-label="Galeria de imagens do produto">
                @if ($initialImage)
                    <div class="product-zoom-frame relative aspect-square w-full overflow-hidden rounded-xl bg-slate-50" data-gallery='@json($galleryData)' data-index="{{ $galleryIndex }}">
                        <img id="product-main-image" src="{{ app(\App\Services\MediaService::class)->url($initialImage) }}" alt="{{ $product->name }} — imagem {{ $galleryIndex + 1 }}" width="{{ $initialImage->width }}" height="{{ $initialImage->height }}" fetchpriority="high" decoding="async" class="h-full w-full object-contain">
                        @if ($gallery->count() > 1)
                            <button type="button" data-gallery-step="-1" aria-label="Imagem anterior" class="theme-focus theme-carousel-arrow absolute left-2 top-1/2 z-10 flex min-h-11 min-w-11 -translate-y-1/2 items-center justify-center rounded-full text-xl shadow">‹</button>
                            <button type="button" data-gallery-step="1" aria-label="Próxima imagem" class="theme-focus theme-carousel-arrow absolute right-2 top-1/2 z-10 flex min-h-11 min-w-11 -translate-y-1/2 items-center justify-center rounded-full text-xl shadow">›</button>
                        @endif
                        <button id="open-image-zoom" type="button" aria-label="Ampliar imagem" class="absolute inset-0 z-0 cursor-zoom-in" data-zoom-open></button>
                    </div>

                    @if ($gallery->count() > 1)
                        <div class="mt-3 flex gap-2 overflow-x-auto overscroll-x-contain pb-1" role="group" aria-label="Imagens do produto">
                            @foreach ($gallery as $index => $image)
                                <button type="button" data-gallery-thumb="{{ $index }}" aria-label="Exibir imagem {{ $index + 1 }}" aria-pressed="{{ $index === $galleryIndex ? 'true' : 'false' }}" @class(['theme-focus flex min-h-[4.5rem] min-w-[4.5rem] shrink-0 items-center justify-center rounded-lg border-2 p-1 sm:min-h-20 sm:min-w-20', 'border-gray-900' => $index === $galleryIndex, 'border-transparent' => $index !== $galleryIndex])>
                                    <img src="{{ app(\App\Services\MediaService::class)->url($image) }}" alt="" width="{{ $image->width }}" height="{{ $image->height }}" loading="lazy" decoding="async" class="h-16 w-16 object-contain">
                                </button>
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="flex aspect-square items-center justify-center rounded-xl bg-slate-50 text-sm text-slate-500">Imagem em breve</div>
                @endif
            </section>

            <div class="flex min-w-0 flex-col items-start md:py-2">
                <p class="theme-text-secondary text-xs font-bold uppercase tracking-[.12em]">{{ $product->category->name }}</p>
                <h1 class="theme-text-primary mt-2 w-full break-words text-2xl font-bold tracking-tight [overflow-wrap:anywhere] sm:text-3xl">{{ $product->name }}</h1>

                <div class="mt-4 grid w-full gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Referência</p>
                        <p class="mt-1 break-all font-mono text-sm text-slate-800">{{ $product->sku }}</p>
                    </div>
                    @if ($product->condition === 'used')
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Condição</p>
                            <p class="mt-1 text-sm font-semibold text-amber-950">Usado</p>
                        </div>
                    @elseif ($product->condition === 'new')
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Condição</p>
                            <p class="mt-1 text-sm font-semibold text-blue-950">Novo</p>
                        </div>
                    @endif
                    <div @class(['sm:col-span-2' => $product->condition === null])>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Disponibilidade</p>
                        <p class="mt-1 inline-flex min-h-8 items-center rounded-full px-3 text-sm font-semibold {{ $availabilityStyle }}">{{ $availabilityLabel }}</p>
                    </div>
                </div>

                @if ($product->condition === 'used' && filled($product->condition_notes))
                    <section class="mt-4 w-full rounded-xl border border-amber-200 bg-amber-50 p-4" aria-labelledby="conservacao-produto">
                        <h2 id="conservacao-produto" class="text-sm font-bold text-amber-950">Estado de conservação</h2>
                        <p class="mt-1 break-words text-sm leading-6 text-amber-950 [overflow-wrap:anywhere]">{{ $product->condition_notes }}</p>
                    </section>
                @endif

                <div class="mt-4 w-full rounded-2xl bg-slate-50 p-4 sm:p-5" aria-label="Preço do produto">
                    @if ($product->promotionIsActive($pricingAt))
                        <p class="text-sm text-slate-500">Preço anterior <span class="line-through">{{ \App\Services\ProductService::formatPrice($product->price) }}</span></p>
                        <p class="mt-2 text-xs font-bold uppercase tracking-wide text-orange-800">Preço promocional</p>
                    @endif
                    <p class="theme-text-primary {{ $product->promotionIsActive($pricingAt) ? 'mt-1' : '' }} whitespace-nowrap text-3xl font-extrabold sm:text-4xl">{{ \App\Services\ProductService::formatPrice($product->effectivePrice($pricingAt)) }}</p>
                </div>

                @if ($product->description)
                    <section class="product-description mt-5 w-full min-w-0 break-words text-slate-700 [overflow-wrap:anywhere]" aria-labelledby="descricao-produto">
                        <h2 id="descricao-produto" class="mb-2 text-lg font-bold">Sobre este produto</h2>
                        {!! app(\App\Services\ProductDescription::class)->render($product->description) !!}
                    </section>
                @endif

                @if ($hasProductContact)
                    <div class="mt-5 w-full rounded-xl border border-slate-200 p-4 text-sm text-slate-700">
                        <p class="font-semibold text-slate-900">Dúvidas sobre este produto?</p>
                        <p class="mt-1">Entre em contato com a loja pelos canais disponíveis:</p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
                            @if (filled($storefrontContact['email'] ?? ''))<a class="theme-focus break-all rounded underline underline-offset-4" href="mailto:{{ $storefrontContact['email'] }}">{{ $storefrontContact['email'] }}</a>@endif
                            @if ($contactPhoneHref)<a class="theme-focus rounded underline underline-offset-4" href="{{ $contactPhoneHref }}">{{ $storefrontContact['phone'] }}</a>@endif
                        </div>
                    </div>
                @endif
            </div>
        </article>
    </div>

    @if ($initialImage)
        <dialog id="product-zoom-dialog" class="m-auto max-h-[calc(100dvh-1rem)] max-w-[calc(100vw-1rem)] overscroll-contain rounded-xl bg-black p-2 text-white backdrop:bg-black/80" aria-label="Visualização ampliada da galeria">
            <div class="relative flex max-h-[calc(100dvh-2rem)] items-center justify-center">
                <img id="zoom-dialog-image" src="{{ app(\App\Services\MediaService::class)->url($initialImage) }}" alt="{{ $product->name }} — imagem {{ $galleryIndex + 1 }}" width="{{ $initialImage->width }}" height="{{ $initialImage->height }}" class="max-h-[calc(100dvh-3rem)] max-w-[calc(100vw-1.5rem)] touch-pan-y object-contain">
                @if ($gallery->count() > 1)
                    <button type="button" data-zoom-step="-1" aria-label="Imagem anterior" class="theme-focus absolute left-1 top-1/2 flex min-h-11 min-w-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/70 text-xl">‹</button>
                    <button type="button" data-zoom-step="1" aria-label="Próxima imagem" class="theme-focus absolute right-1 top-1/2 flex min-h-11 min-w-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/70 text-xl">›</button>
                @endif
                <button type="button" data-zoom-close aria-label="Fechar visualização" class="theme-focus absolute right-1 top-1 flex min-h-11 min-w-11 items-center justify-center rounded-full bg-black/80 text-sm">×</button>
            </div>
        </dialog>
        <script>
        (() => {
            const frame = document.querySelector('.product-zoom-frame');
            if (!frame) return;
            const images = JSON.parse(frame.dataset.gallery);
            let index = Number(frame.dataset.index) || 0;
            const main = document.getElementById('product-main-image');
            const dialog = document.getElementById('product-zoom-dialog');
            const zoomImage = document.getElementById('zoom-dialog-image');
            const thumbs = [...document.querySelectorAll('[data-gallery-thumb]')];
            const show = next => {
                index = (next + images.length) % images.length;
                main.src = images[index].url;
                main.alt = images[index].alt;
                zoomImage.src = images[index].url;
                zoomImage.alt = images[index].alt;
                thumbs.forEach((thumb, position) => {
                    thumb.classList.toggle('border-gray-900', position === index);
                    thumb.classList.toggle('border-transparent', position !== index);
                    thumb.setAttribute('aria-pressed', (position === index).toString());
                });
                frame.dataset.index = index;
            };
            thumbs.forEach(thumb => thumb.addEventListener('click', () => show(Number(thumb.dataset.galleryThumb))));
            frame.querySelectorAll('[data-gallery-step]').forEach(button => button.addEventListener('click', () => show(index + Number(button.dataset.galleryStep))));
            const oldBodyOverflow = document.body.style.overflow;
            document.querySelector('[data-zoom-open]').addEventListener('click', () => { dialog.showModal(); document.body.style.overflow = 'hidden'; });
            dialog.querySelector('[data-zoom-close]').addEventListener('click', () => dialog.close());
            dialog.addEventListener('close', () => { document.body.style.overflow = oldBodyOverflow; });
            dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
            dialog.querySelectorAll('[data-zoom-step]').forEach(button => button.addEventListener('click', () => show(index + Number(button.dataset.zoomStep))));
            document.addEventListener('keydown', event => { if (dialog.open && images.length > 1 && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) show(index + (event.key === 'ArrowRight' ? 1 : -1)); });
            let touchX = null;
            zoomImage.addEventListener('touchstart', event => { touchX = event.changedTouches[0].clientX; }, {passive: true});
            zoomImage.addEventListener('touchend', event => {
                if (touchX === null) return;
                const delta = event.changedTouches[0].clientX - touchX;
                if (Math.abs(delta) > 45) show(index + (delta < 0 ? 1 : -1));
                touchX = null;
            }, {passive: true});
            frame.addEventListener('pointermove', event => {
                if (event.pointerType !== 'mouse' || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                const rect = frame.getBoundingClientRect();
                main.style.transformOrigin = `${((event.clientX - rect.left) / rect.width) * 100}% ${((event.clientY - rect.top) / rect.height) * 100}%`;
                main.style.transform = 'scale(1.8)';
            });
            frame.addEventListener('pointerleave', () => { main.style.transform = ''; main.style.transformOrigin = ''; });
        })();
        </script>
    @endif
@endsection
