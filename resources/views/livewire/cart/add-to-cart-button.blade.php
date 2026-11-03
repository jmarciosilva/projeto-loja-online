<div class="mt-4 w-full">
    @if ($line->isPurchasable())
        <button
            type="button"
            wire:click="addToCart"
            wire:loading.attr="disabled"
            wire:target="addToCart"
            class="theme-focus theme-bg-primary inline-flex min-h-12 w-full items-center justify-center rounded-xl px-6 text-base font-bold text-white shadow-sm transition hover:brightness-110 disabled:opacity-60 sm:w-auto"
        >
            <span wire:loading.remove wire:target="addToCart">Adicionar ao carrinho</span>
            <span wire:loading wire:target="addToCart">Adicionando…</span>
        </button>
    @else
        <button type="button" disabled aria-disabled="true" class="inline-flex min-h-12 w-full cursor-not-allowed items-center justify-center rounded-xl bg-slate-200 px-6 text-base font-bold text-slate-600 sm:w-auto">
            Indisponível para compra
        </button>
        <p class="mt-2 text-sm font-semibold text-amber-900">{{ $line->status->message($line->maxQuantity) }}</p>
    @endif

    <div role="status" aria-live="polite" class="mt-2 min-h-6 text-sm font-semibold {{ $feedbackIsError ? 'text-red-700' : 'text-emerald-800' }}">
        @if ($feedback)
            {{ $feedback }}
            @if (! $feedbackIsError)
                <a href="{{ route('cart.index') }}" class="theme-focus ml-1 rounded underline underline-offset-4">Ver carrinho</a>
            @endif
        @endif
    </div>
</div>
