<?php

namespace App\DTO\Cart;

use App\Enums\CartLineStatus;
use App\Models\Product;

/**
 * Item do carrinho já revalidado no servidor.
 *
 * Preço, subtotal e disponibilidade são sempre calculados na leitura. Nada
 * disso vem da sessão ou do navegador.
 */
final readonly class CartLine
{
    public function __construct(
        public int $productId,
        public ?Product $product,
        public int $quantity,
        public int $maxQuantity,
        public ?string $unitPrice,
        public string $lineTotal,
        public CartLineStatus $status,
    ) {}

    public function isPurchasable(): bool
    {
        return $this->status->isPurchasable();
    }
}
