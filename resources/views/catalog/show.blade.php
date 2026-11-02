@extends('layouts.app')
@php
    $pricingAt = now(config('app.timezone'));
@endphp
@section('title', $product->name.' · '.$storefrontBrand['name'])
@section('content')
@php
    $gallery = $product->images;
    if ($gallery->isEmpty() && $product->mainMedia) $gallery = collect([$product->mainMedia]);
    $galleryData = $gallery->map(fn ($image) => ['url' => app(\App\Services\MediaService::class)->url($image), 'alt' => $image->original_name])->values();
    $initialImage = $gallery->firstWhere('id', $product->main_media_id) ?? $gallery->first();
@endphp
<div class="min-w-0 space-y-5 sm:space-y-6"><a href="{{ route('catalog.index') }}" class="theme-focus inline-flex min-h-11 items-center text-sm font-medium underline underline-offset-4">← Voltar ao catálogo</a><article class="grid min-w-0 gap-5 rounded-2xl border border-gray-200 bg-white p-3 sm:gap-8 sm:p-6 md:grid-cols-2 lg:p-8">
    <div class="min-w-0">
        @if ($initialImage)
            <div class="product-zoom-frame relative aspect-square w-full overflow-hidden rounded-xl bg-gray-50" data-gallery='@json($galleryData)' data-index="{{ $gallery->search(fn ($image) => $image->id === $initialImage->id) ?: 0 }}">
                <img id="product-main-image" src="{{ app(\App\Services\MediaService::class)->url($initialImage) }}" alt="{{ $product->name }} — {{ $initialImage->original_name }}" class="h-full w-full object-contain" fetchpriority="high">
                @if ($gallery->count() > 1)
                    <button type="button" data-gallery-step="-1" aria-label="Imagem anterior" class="theme-focus theme-carousel-arrow absolute left-2 top-1/2 z-10 flex min-h-11 min-w-11 -translate-y-1/2 items-center justify-center rounded-full text-xl shadow">‹</button>
                    <button type="button" data-gallery-step="1" aria-label="Próxima imagem" class="theme-focus theme-carousel-arrow absolute right-2 top-1/2 z-10 flex min-h-11 min-w-11 -translate-y-1/2 items-center justify-center rounded-full text-xl shadow">›</button>
                @endif
                <button id="open-image-zoom" type="button" aria-label="Ampliar imagem" class="absolute inset-0 z-0 cursor-zoom-in" data-zoom-open></button>
            </div>
            @if ($gallery->count() > 1)
                <div class="mt-3 flex gap-2 overflow-x-auto overscroll-x-contain pb-1" role="group" aria-label="Imagens do produto">
                    @foreach ($gallery as $index => $image)
                        <button type="button" data-gallery-thumb="{{ $index }}" aria-label="Exibir imagem {{ $index + 1 }}" aria-pressed="{{ $image->id === $initialImage->id ? 'true' : 'false' }}" @class(['flex min-h-[4.5rem] min-w-[4.5rem] shrink-0 items-center justify-center rounded-lg border-2 p-1 sm:min-h-20 sm:min-w-20', 'border-gray-900' => $image->id === $initialImage->id, 'border-transparent' => $image->id !== $initialImage->id])>
                            <img src="{{ app(\App\Services\MediaService::class)->url($image) }}" alt="" loading="lazy" class="h-16 w-16 object-contain">
                        </button>
                    @endforeach
                </div>
            @endif
        @else
            <div class="flex aspect-square items-center justify-center rounded-xl bg-gray-100 text-gray-500">Imagem em breve</div>
        @endif
    </div>
    <div class="flex min-w-0 flex-col items-start"><a href="{{ route('categories.show', $product->category->slug) }}" class="theme-focus inline-flex min-h-11 items-center break-words text-sm font-medium text-gray-600 underline">{{ $product->category->name }}</a><h1 class="theme-text-primary mt-2 w-full break-words text-2xl font-semibold tracking-tight [overflow-wrap:anywhere] sm:text-3xl">{{ $product->name }}</h1><p class="mt-3 break-words text-sm text-gray-500">Referência: {{ $product->sku }}</p>@if ($product->promotionIsActive($pricingAt))<p class="mt-5 text-base text-gray-500 line-through">{{ \App\Services\ProductService::formatPrice($product->price) }}</p>@endif<p class="theme-text-primary mt-1 whitespace-nowrap text-2xl font-semibold">{{ \App\Services\ProductService::formatPrice($product->effectivePrice($pricingAt)) }}</p>@if ($product->description)<div class="product-description mt-5 w-full min-w-0 break-words text-gray-700 [overflow-wrap:anywhere]">{!! app(\App\Services\ProductDescription::class)->render($product->description) !!}</div>@endif<p class="mt-8 text-sm text-gray-500 sm:mt-auto sm:pt-8">Consulte a loja pelos canais de contato para informações sobre este produto.</p></div>
</article></div>
@if ($initialImage)
<dialog id="product-zoom-dialog" class="m-auto max-h-[calc(100dvh-1rem)] max-w-[calc(100vw-1rem)] overscroll-contain rounded-xl bg-black p-2 text-white backdrop:bg-black/80" aria-label="Visualização ampliada da galeria">
    <div class="relative flex max-h-[calc(100dvh-2rem)] items-center justify-center"><img id="zoom-dialog-image" src="{{ app(\App\Services\MediaService::class)->url($initialImage) }}" alt="{{ $product->name }}" class="max-h-[calc(100dvh-3rem)] max-w-[calc(100vw-1.5rem)] touch-pan-y object-contain">@if($gallery->count() > 1)<button type="button" data-zoom-step="-1" aria-label="Imagem anterior" class="absolute left-1 top-1/2 flex min-h-11 min-w-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/70 text-xl">‹</button><button type="button" data-zoom-step="1" aria-label="Próxima imagem" class="absolute right-1 top-1/2 flex min-h-11 min-w-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/70 text-xl">›</button>@endif<button type="button" data-zoom-close aria-label="Fechar visualização" class="absolute right-1 top-1 flex min-h-11 min-w-11 items-center justify-center rounded-full bg-black/80 text-sm">×</button></div>
</dialog>
<script>
(() => {
    const frame = document.querySelector('.product-zoom-frame'); if (!frame) return;
    const images = JSON.parse(frame.dataset.gallery); let index = Number(frame.dataset.index) || 0;
    const main = document.getElementById('product-main-image'), dialog = document.getElementById('product-zoom-dialog'), zoomImage = document.getElementById('zoom-dialog-image');
    const thumbs = [...document.querySelectorAll('[data-gallery-thumb]')];
    const show = next => { index = (next + images.length) % images.length; main.src = images[index].url; main.alt = @json($product->name) + ' — ' + images[index].alt; zoomImage.src = images[index].url; zoomImage.alt = main.alt; thumbs.forEach((thumb, i) => { thumb.classList.toggle('border-gray-900', i === index); thumb.classList.toggle('border-transparent', i !== index); thumb.setAttribute('aria-pressed', (i === index).toString()); }); frame.dataset.index = index; };
    thumbs.forEach(thumb => thumb.addEventListener('click', () => show(Number(thumb.dataset.galleryThumb))));
    frame.querySelectorAll('[data-gallery-step]').forEach(button => button.addEventListener('click', () => show(index + Number(button.dataset.galleryStep))));
    const oldBodyOverflow = document.body.style.overflow;
    document.querySelector('[data-zoom-open]').addEventListener('click', () => { dialog.showModal(); document.body.style.overflow = 'hidden'; });
    dialog.querySelector('[data-zoom-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { document.body.style.overflow = oldBodyOverflow; });
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    dialog.querySelectorAll('[data-zoom-step]').forEach(button => button.addEventListener('click', () => show(index + Number(button.dataset.zoomStep))));
    document.addEventListener('keydown', event => { if (dialog.open && images.length > 1 && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) show(index + (event.key === 'ArrowRight' ? 1 : -1)); });
    let touchX = null; zoomImage.addEventListener('touchstart', event => { touchX = event.changedTouches[0].clientX; }, {passive:true}); zoomImage.addEventListener('touchend', event => { if (touchX === null) return; const delta = event.changedTouches[0].clientX - touchX; if (Math.abs(delta) > 45) show(index + (delta < 0 ? 1 : -1)); touchX = null; }, {passive:true});
    frame.addEventListener('pointermove', event => { if (event.pointerType !== 'mouse' || matchMedia('(prefers-reduced-motion: reduce)').matches) return; const rect = frame.getBoundingClientRect(); main.style.transformOrigin = `${((event.clientX - rect.left) / rect.width) * 100}% ${((event.clientY - rect.top) / rect.height) * 100}%`; main.style.transform = 'scale(1.8)'; });
    frame.addEventListener('pointerleave', () => { main.style.transform = ''; main.style.transformOrigin = ''; });
})();
</script>
@endif
@endsection
