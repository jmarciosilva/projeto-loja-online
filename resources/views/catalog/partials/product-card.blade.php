@php
    $pricingAt = now(config('app.timezone'));
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
@endphp
<article class="group min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white transition duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md motion-reduce:transform-none motion-reduce:transition-none">
    <a href="{{ route('catalog.show', $product->slug) }}" class="theme-focus block h-full min-w-0 rounded-2xl" aria-label="Ver {{ $product->name }}">
        <div class="relative aspect-[4/3] overflow-hidden bg-slate-50 p-2 sm:p-3">
            @if ($product->mainMedia)
                <img src="{{ app(\App\Services\MediaService::class)->url($product->mainMedia) }}" alt="{{ $product->name }}" width="{{ $product->mainMedia->width }}" height="{{ $product->mainMedia->height }}" loading="lazy" decoding="async" class="h-full w-full object-contain">
            @else
                <div class="flex h-full items-center justify-center rounded-xl bg-slate-100 px-2 text-center text-xs font-medium text-slate-500">Imagem em breve</div>
            @endif
            @if ($product->promotionIsActive($pricingAt))
                <span class="absolute left-2 top-2 rounded-full bg-orange-100 px-2.5 py-1 text-[11px] font-bold text-slate-900">Oferta</span>
            @endif
        </div>
        <div class="flex min-h-[10.25rem] flex-col p-3 sm:min-h-[10.75rem] sm:p-4">
            <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-slate-500 sm:text-[11px]">{{ $product->category->name }}</p>
            <h3 class="mt-1 line-clamp-2 min-h-9 break-words text-[13px] font-semibold leading-[1.15rem] text-slate-900 sm:min-h-10 sm:text-sm sm:leading-5">{{ $product->name }}</h3>
            <div class="mt-1.5 min-h-6">
                @if ($product->condition === 'used')
                    <span class="inline-flex w-fit rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700 sm:text-[11px]">Usado</span>
                @elseif ($product->condition === 'new')
                    <span class="inline-flex w-fit rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-900 sm:text-[11px]">Novo</span>
                @endif
            </div>
            <div class="mt-auto pt-3">
                @if ($product->promotionIsActive($pricingAt))
                <p class="text-[11px] text-slate-500 line-through">{{ \App\Services\ProductService::formatPrice($product->price) }}</p>
                @endif
                <p class="theme-text-primary whitespace-nowrap text-base font-extrabold leading-tight sm:text-lg">{{ \App\Services\ProductService::formatPrice($product->effectivePrice($pricingAt)) }}</p>
                <span class="mt-1.5 inline-flex max-w-full items-center rounded-full px-2 py-0.5 text-[10px] font-semibold leading-4 sm:text-[11px] {{ $availabilityStyle }}">{{ $availabilityLabel }}</span>
            </div>
        </div>
    </a>
</article>
