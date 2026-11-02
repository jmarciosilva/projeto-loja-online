@php($pricingAt = now(config('app.timezone')))
<article class="min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <a href="{{ route('catalog.show', $product->slug) }}" class="block min-w-0 focus-visible:outline-2 focus-visible:outline-offset-2">
        @if ($product->mainMedia)
            <img src="{{ app(\App\Services\MediaService::class)->url($product->mainMedia) }}" alt="{{ $product->name }}" width="{{ $product->mainMedia->width }}" height="{{ $product->mainMedia->height }}" loading="lazy" class="aspect-square w-full bg-gray-50 object-contain">
        @else
            <div class="flex aspect-square w-full items-center justify-center bg-gray-100 text-sm text-gray-500">Imagem em breve</div>
        @endif
        <div class="space-y-2 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $product->category->name }}</p>
            <h3 class="break-words font-semibold text-gray-900 [overflow-wrap:anywhere]">{{ $product->name }}</h3>
            @if ($product->promotionIsActive($pricingAt))
                <p class="text-sm text-gray-500 line-through">{{ \App\Services\ProductService::formatPrice($product->price) }}</p>
            @endif
            <p class="theme-text-primary whitespace-nowrap text-base font-semibold sm:text-lg">{{ \App\Services\ProductService::formatPrice($product->effectivePrice($pricingAt)) }}</p>
            <span class="text-sm font-medium underline underline-offset-4">Ver detalhes</span>
        </div>
    </a>
</article>
