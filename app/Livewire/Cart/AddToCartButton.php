<?php

namespace App\Livewire\Cart;

use App\Exceptions\CartException;
use App\Services\CartService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Botão "Adicionar ao carrinho" da página de produto.
 */
class AddToCartButton extends Component
{
    public int $productId;

    public ?string $feedback = null;

    public bool $feedbackIsError = false;

    public function mount(int $productId): void
    {
        $this->productId = $productId;
    }

    public function addToCart(CartService $cart): void
    {
        try {
            $quantity = $cart->add($this->productId);

            $this->feedback = "Produto adicionado ao carrinho. Quantidade no carrinho: {$quantity}.";
            $this->feedbackIsError = false;

            $this->dispatch('cart-updated');
        } catch (CartException $exception) {
            $this->feedback = $exception->getMessage();
            $this->feedbackIsError = true;
        }
    }

    public function render(CartService $cart): View
    {
        return view('livewire.cart.add-to-cart-button', [
            'line' => $cart->availability($this->productId),
        ]);
    }
}
