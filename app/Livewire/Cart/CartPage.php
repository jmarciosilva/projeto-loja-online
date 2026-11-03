<?php

namespace App\Livewire\Cart;

use App\Exceptions\CartException;
use App\Services\CartService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Página do carrinho: itens revalidados, ajuste de quantidade e resumo.
 */
class CartPage extends Component
{
    public ?string $feedback = null;

    public bool $feedbackIsError = false;

    public function setQuantity(int $productId, int $quantity, CartService $cart): void
    {
        try {
            $cart->setQuantity($productId, $quantity);

            $this->feedback = 'Quantidade atualizada.';
            $this->feedbackIsError = false;

            $this->dispatch('cart-updated');
        } catch (CartException $exception) {
            $this->feedback = $exception->getMessage();
            $this->feedbackIsError = true;
        }
    }

    public function remove(int $productId, CartService $cart): void
    {
        $cart->remove($productId);

        $this->feedback = 'Item removido do carrinho.';
        $this->feedbackIsError = false;

        $this->dispatch('cart-updated');
    }

    public function render(CartService $cart): View
    {
        return view('livewire.cart.cart-page', [
            'lines' => $cart->lines(),
            'count' => $cart->count(),
            'subtotal' => $cart->subtotal(),
        ]);
    }
}
