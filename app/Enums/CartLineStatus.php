<?php

namespace App\Enums;

/**
 * Situação de um item do carrinho, recalculada a cada leitura.
 *
 * Somente `Available` entra no subtotal e na contagem de itens compráveis.
 */
enum CartLineStatus: string
{
    case Available = 'available';
    case NotFound = 'not_found';
    case Unpublished = 'unpublished';
    case CategoryHidden = 'category_hidden';
    case NoStockControl = 'no_stock_control';
    case SoldOut = 'sold_out';
    case QuantityAboveLimit = 'quantity_above_limit';

    public function isPurchasable(): bool
    {
        return $this === self::Available;
    }

    /**
     * Mensagem exibida ao cliente. `$maximum` é o limite vigente do item.
     */
    public function message(int $maximum = 0): ?string
    {
        return match ($this) {
            self::Available => null,
            self::NotFound => 'Este produto não está mais disponível.',
            self::Unpublished => 'Este produto foi removido da loja.',
            self::CategoryHidden => 'A categoria deste produto não está disponível.',
            self::NoStockControl => 'Este produto não tem controle de estoque ativo e não pode ser comprado agora.',
            self::SoldOut => 'Este produto está esgotado.',
            self::QuantityAboveLimit => "A quantidade máxima disponível é {$maximum}. Ajuste a quantidade.",
        };
    }
}
