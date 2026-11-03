<a
    href="{{ route('cart.index') }}"
    class="theme-focus relative inline-flex min-h-11 items-center gap-2 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold hover:bg-slate-50"
    aria-label="Carrinho, {{ $count }} {{ $count === 1 ? 'item' : 'itens' }}"
>
    <span>Carrinho</span>
    <span aria-hidden="true" class="theme-bg-accent inline-flex min-w-6 items-center justify-center rounded-full px-1.5 text-xs font-bold text-white">{{ $count }}</span>
</a>
