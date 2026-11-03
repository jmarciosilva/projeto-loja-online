<?php

namespace App\Services;

use App\DTO\Cart\CartLine;
use App\Enums\CartLineStatus;
use App\Exceptions\CartException;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Carrinho de visitante, guardado na sessão.
 *
 * A sessão guarda somente `product_id => quantidade`. Preço, nome e
 * disponibilidade são recalculados a cada leitura pelas regras oficiais
 * (publicação, categoria, controle de estoque e `effectivePrice()`). Nenhuma
 * operação deste serviço altera estoque.
 */
final class CartService
{
    public const MAX_QUANTITY_PER_PRODUCT = 10;

    private const SESSION_KEY = 'cart.items';

    public function __construct(private readonly Session $session) {}

    /**
     * Adiciona unidades respeitando o limite vigente. Retorna a quantidade final.
     */
    public function add(int $productId, int $quantity = 1): int
    {
        if ($quantity < 1) {
            throw new CartException('A quantidade deve ser pelo menos 1.');
        }

        $probe = $this->availability($productId);

        if (! $probe->isPurchasable()) {
            throw new CartException($probe->status->message() ?? 'Este produto não pode ser adicionado.');
        }

        $items = $this->items();
        $current = $items[$productId] ?? 0;
        $limit = $probe->maxQuantity;

        if ($current >= $limit) {
            throw new CartException("Você já tem a quantidade máxima disponível ({$limit}) deste produto no carrinho.");
        }

        $items[$productId] = min($current + $quantity, $limit);
        $this->save($items);

        return $items[$productId];
    }

    /**
     * Situação atual de um produto para uma unidade, com o limite vigente.
     * É a fonte única da regra de disponibilidade para a página de produto.
     */
    public function availability(int $productId): CartLine
    {
        return $this->evaluate([$productId => 1])[0];
    }

    /**
     * Define a quantidade exata. Valores acima do limite são recusados, não reduzidos.
     */
    public function setQuantity(int $productId, int $quantity): int
    {
        $items = $this->items();

        if (! array_key_exists($productId, $items)) {
            throw new CartException('Este produto não está no carrinho.');
        }

        if ($quantity < 1) {
            throw new CartException('A quantidade deve ser pelo menos 1. Para retirar o item, use "Remover".');
        }

        $line = $this->evaluate([$productId => $quantity])[0];

        if ($line->status === CartLineStatus::QuantityAboveLimit) {
            throw new CartException($line->status->message($line->maxQuantity));
        }

        if (! $line->isPurchasable()) {
            throw new CartException('Este item está indisponível. Remova-o do carrinho.');
        }

        $items[$productId] = $quantity;
        $this->save($items);

        return $quantity;
    }

    public function remove(int $productId): void
    {
        $items = $this->items();
        unset($items[$productId]);
        $this->save($items);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /**
     * Itens do carrinho revalidados agora, na ordem em que foram adicionados.
     *
     * @return Collection<int, CartLine>
     */
    public function lines(): Collection
    {
        $items = $this->items();

        if ($items === []) {
            return collect();
        }

        return collect($this->evaluate($items));
    }

    /**
     * Regra única para liberar o checkout: carrinho com itens e todos compráveis.
     */
    public function readyForCheckout(): bool
    {
        $lines = $this->lines();

        return $lines->isNotEmpty() && $lines->every(fn (CartLine $line): bool => $line->isPurchasable());
    }

    /** Unidades compráveis. Itens indisponíveis não entram na contagem. */
    public function count(): int
    {
        return $this->lines()
            ->filter(fn (CartLine $line): bool => $line->isPurchasable())
            ->sum(fn (CartLine $line): int => $line->quantity);
    }

    /** Subtotal em reais com duas casas, calculado com bcmath (sem ponto flutuante). */
    public function subtotal(): string
    {
        return $this->lines()
            ->filter(fn (CartLine $line): bool => $line->isPurchasable())
            ->reduce(fn (string $total, CartLine $line): string => bcadd($total, $line->lineTotal, 2), '0.00');
    }

    /**
     * Itens válidos da sessão. Chaves e valores que não são inteiros positivos
     * são descartados, pois não podem ser produzidos pela interface.
     *
     * @return array<int, int>
     */
    private function items(): array
    {
        $stored = $this->session->get(self::SESSION_KEY, []);

        if (! is_array($stored)) {
            return [];
        }

        $items = [];

        foreach ($stored as $productId => $quantity) {
            $id = filter_var($productId, FILTER_VALIDATE_INT);
            $qty = filter_var($quantity, FILTER_VALIDATE_INT);

            if ($id === false || $qty === false || $id < 1 || $qty < 1 || $qty > self::MAX_QUANTITY_PER_PRODUCT) {
                continue;
            }

            $items[$id] = $qty;
        }

        return $items;
    }

    /** @param array<int, int> $items */
    private function save(array $items): void
    {
        $this->session->put(self::SESSION_KEY, $items);
    }

    /**
     * Revalida um conjunto de itens em consultas fixas, sem consultas por linha.
     *
     * @param  array<int, int>  $quantities  product_id => quantidade
     * @return list<CartLine>
     */
    private function evaluate(array $quantities): array
    {
        $ids = array_keys($quantities);
        $now = now(config('app.timezone'));

        $products = Product::query()
            ->withTrashed()
            ->with(['stock', 'category.parent', 'mainMedia'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $visibleIds = Product::query()
            ->publiclyVisible()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->flip();

        $lines = [];

        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);

            if ($product === null || $product->trashed()) {
                $lines[] = $this->line($productId, null, $quantity, 0, CartLineStatus::NotFound, $now);

                continue;
            }

            if (! $visibleIds->has($productId)) {
                $status = $product->is_active ? CartLineStatus::CategoryHidden : CartLineStatus::Unpublished;
                $lines[] = $this->line($productId, $product, $quantity, 0, $status, $now);

                continue;
            }

            $stock = $product->stock;

            if ($stock === null || ! $stock->stock_control_enabled) {
                $lines[] = $this->line($productId, $product, $quantity, 0, CartLineStatus::NoStockControl, $now);

                continue;
            }

            $available = $stock->availableQuantity();

            if ($available < 1) {
                $lines[] = $this->line($productId, $product, $quantity, 0, CartLineStatus::SoldOut, $now);

                continue;
            }

            $maximum = min($available, self::MAX_QUANTITY_PER_PRODUCT);
            $status = $quantity > $maximum ? CartLineStatus::QuantityAboveLimit : CartLineStatus::Available;

            $lines[] = $this->line($productId, $product, $quantity, $maximum, $status, $now);
        }

        return $lines;
    }

    private function line(int $productId, ?Product $product, int $quantity, int $maximum, CartLineStatus $status, CarbonInterface $now): CartLine
    {
        $unitPrice = $product?->effectivePrice($now);
        $purchasable = $status->isPurchasable();

        return new CartLine(
            productId: $productId,
            product: $product,
            quantity: $quantity,
            maxQuantity: $maximum,
            unitPrice: $unitPrice,
            lineTotal: $purchasable ? bcmul((string) $unitPrice, (string) $quantity, 2) : '0.00',
            status: $status,
        );
    }
}
