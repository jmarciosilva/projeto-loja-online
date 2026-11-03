<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Entrada do checkout. Só avança com carrinho compráveis.
 */
class CheckoutController extends Controller
{
    public function show(CartService $cart): View|RedirectResponse
    {
        if ($cart->lines()->isEmpty()) {
            return redirect()->route('cart.index')
                ->with('cart_error', 'Seu carrinho está vazio. Adicione produtos antes de finalizar a compra.');
        }

        if (! $cart->readyForCheckout()) {
            return redirect()->route('cart.index')
                ->with('cart_error', 'Há itens indisponíveis ou com quantidade acima do limite. Ajuste o carrinho para continuar.');
        }

        return view('checkout.index', [
            'count' => $cart->count(),
            'subtotal' => $cart->subtotal(),
        ]);
    }
}
