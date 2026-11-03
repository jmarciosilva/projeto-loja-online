<div class="w-full">
    <div role="status" aria-live="polite" class="min-h-6 text-sm font-semibold {{ $feedbackIsError ? 'text-red-700' : 'text-emerald-800' }}">
        {{ $feedback }}
    </div>

    @if ($lines->isEmpty())
        <section class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center" aria-labelledby="carrinho-vazio">
            <h2 id="carrinho-vazio" class="text-xl font-bold text-slate-900">Seu carrinho está vazio</h2>
            <p class="mt-2 text-slate-600">Explore o catálogo e escolha livros e materiais para o seu artesanato.</p>
            <a href="{{ route('catalog.index') }}" class="theme-focus theme-bg-primary mt-6 inline-flex min-h-12 items-center justify-center rounded-xl px-6 font-bold text-white">Ver produtos</a>
        </section>
    @else
        <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem] lg:items-start">
            <ul class="space-y-4" aria-label="Itens do carrinho">
                @foreach ($lines as $line)
                    @php
                        $product = $line->product;
                        $quantityOptions = range(1, max($line->maxQuantity, $line->quantity, 1));
                    @endphp
                    <li class="flex gap-4 rounded-2xl border border-slate-200 bg-white p-4" wire:key="cart-line-{{ $line->productId }}">
                        <div class="h-24 w-20 shrink-0 overflow-hidden rounded-lg bg-slate-100 sm:h-28 sm:w-24">
                            @if ($product?->mainMedia)
                                <img src="{{ app(\App\Services\MediaService::class)->url($product->mainMedia) }}" alt="" width="{{ $product->mainMedia->width }}" height="{{ $product->mainMedia->height }}" loading="lazy" decoding="async" class="h-full w-full object-contain">
                            @endif
                        </div>

                        <div class="flex min-w-0 flex-1 flex-col">
                            @if ($product)
                                <a href="{{ route('catalog.show', $product->slug) }}" class="theme-focus break-words font-bold text-slate-900 underline-offset-4 hover:underline">{{ $product->name }}</a>
                                <p class="mt-1 text-sm text-slate-600">
                                    {{ \App\Services\ProductService::formatPrice($line->unitPrice) }} cada
                                    @if ($product->condition === 'used') · Usado @endif
                                </p>
                            @else
                                <p class="font-bold text-slate-900">Produto indisponível</p>
                            @endif

                            @unless ($line->isPurchasable())
                                <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-950" role="alert">{{ $line->status->message($line->maxQuantity) }}</p>
                            @endunless

                            <div class="mt-auto flex flex-wrap items-end justify-between gap-3 pt-3">
                                @if ($line->maxQuantity > 0 || $line->status === \App\Enums\CartLineStatus::QuantityAboveLimit)
                                    <label class="text-sm font-semibold text-slate-700">
                                        Quantidade
                                        <select
                                            class="theme-focus ml-2 min-h-11 rounded-lg border-slate-300 bg-white px-3 text-base"
                                            wire:change="setQuantity({{ $line->productId }}, $event.target.value)"
                                        >
                                            @foreach ($quantityOptions as $option)
                                                <option value="{{ $option }}" @selected($option === $line->quantity)>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endif

                                <button type="button" wire:click="remove({{ $line->productId }})" class="theme-focus inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-slate-700 underline underline-offset-4 hover:text-slate-950">
                                    Remover<span class="sr-only"> {{ $product?->name ?? 'item indisponível' }}</span>
                                </button>

                                <p class="text-base font-extrabold text-slate-900">
                                    @if ($line->isPurchasable())
                                        {{ \App\Services\ProductService::formatPrice($line->lineTotal) }}
                                    @else
                                        <span class="text-sm font-semibold text-slate-500">Não incluído</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <aside class="rounded-2xl border border-slate-200 bg-slate-50 p-5 lg:sticky lg:top-6" aria-labelledby="resumo-carrinho">
                <h2 id="resumo-carrinho" class="text-lg font-bold text-slate-900">Resumo</h2>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-600">Itens para compra</dt>
                        <dd class="font-semibold text-slate-900">{{ $count }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-t border-slate-200 pt-3 text-base">
                        <dt class="font-bold text-slate-900">Subtotal</dt>
                        <dd class="theme-text-primary font-extrabold">{{ \App\Services\ProductService::formatPrice($subtotal) }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-slate-500">O frete e o total final serão calculados na finalização da compra.</p>

                @if ($ready)
                    <a href="{{ route('checkout.show') }}" class="theme-focus theme-bg-primary mt-5 inline-flex min-h-12 w-full items-center justify-center rounded-xl px-6 font-bold text-white shadow-sm">
                        Finalizar compra
                    </a>
                @else
                    <button type="button" disabled aria-disabled="true" class="mt-5 inline-flex min-h-12 w-full cursor-not-allowed items-center justify-center rounded-xl bg-slate-200 px-6 font-bold text-slate-600">
                        Finalizar compra
                    </button>
                    <p class="mt-2 text-xs text-slate-600">Remova ou ajuste os itens indisponíveis para continuar.</p>
                @endif

                <a href="{{ route('catalog.index') }}" class="theme-focus mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-6 font-semibold text-slate-800 hover:bg-slate-50">
                    Continuar comprando
                </a>
            </aside>
        </div>
    @endif
</div>
