<?php

namespace App\Livewire\Cart;

use App\Services\CartService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Contador de itens compráveis no cabeçalho.
 */
class CartCounter extends Component
{
    #[On('cart-updated')]
    public function refresh(): void
    {
        // Nova renderização recalcula a contagem.
    }

    public function render(CartService $cart): View
    {
        return view('livewire.cart.cart-counter', [
            'count' => $cart->count(),
        ]);
    }
}
