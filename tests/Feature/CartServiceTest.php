<?php

namespace Tests\Feature;

use App\DTO\Cart\CartLine;
use App\Enums\CartLineStatus;
use App\Exceptions\CartException;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CartService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    private CartService $cart;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cart = app(CartService::class);
    }

    public function test_empty_cart_has_no_lines_count_or_subtotal(): void
    {
        $this->assertCount(0, $this->cart->lines());
        $this->assertSame(0, $this->cart->count());
        $this->assertSame('0.00', $this->cart->subtotal());
    }

    public function test_adding_a_product_updates_count_and_subtotal(): void
    {
        $product = $this->publishedProduct(price: '49.90', stock: 5);

        $this->assertSame(1, $this->cart->add($product->id));

        $this->assertSame(1, $this->cart->count());
        $this->assertSame('49.90', $this->cart->subtotal());
        $this->assertSame(CartLineStatus::Available, $this->cart->lines()->first()->status);
    }

    public function test_adding_the_same_product_increments_without_duplicating_the_line(): void
    {
        $product = $this->publishedProduct(price: '10.00', stock: 5);

        $this->cart->add($product->id);
        $this->cart->add($product->id);

        $this->assertCount(1, $this->cart->lines());
        $this->assertSame(2, $this->cart->count());
        $this->assertSame('20.00', $this->cart->subtotal());
    }

    public function test_single_unit_product_cannot_be_added_twice(): void
    {
        $product = $this->publishedProduct(stock: 1);
        $this->cart->add($product->id);

        $this->expectException(CartException::class);
        $this->expectExceptionMessage('quantidade máxima disponível (1)');

        $this->cart->add($product->id);
    }

    public function test_quantity_is_capped_at_stock_and_the_global_maximum(): void
    {
        $limited = $this->publishedProduct(stock: 3);
        $this->assertSame(3, $this->cart->add($limited->id, 10));

        $large = $this->publishedProduct(stock: 50);
        $this->assertSame(CartService::MAX_QUANTITY_PER_PRODUCT, $this->cart->add($large->id, 25));
    }

    public function test_set_quantity_above_the_limit_is_refused_not_reduced_silently(): void
    {
        $product = $this->publishedProduct(stock: 4);
        $this->cart->add($product->id);

        try {
            $this->cart->setQuantity($product->id, 9);
            $this->fail('A quantidade acima do estoque deveria ser recusada.');
        } catch (CartException $exception) {
            $this->assertStringContainsString('máxima disponível é 4', $exception->getMessage());
        }

        $this->assertSame(1, $this->cart->count());
    }

    public function test_set_quantity_updates_subtotal(): void
    {
        $product = $this->publishedProduct(price: '12.50', stock: 5);
        $this->cart->add($product->id);

        $this->assertSame(3, $this->cart->setQuantity($product->id, 3));
        $this->assertSame('37.50', $this->cart->subtotal());
    }

    public function test_set_quantity_rejects_zero_and_products_not_in_cart(): void
    {
        $product = $this->publishedProduct(stock: 5);
        $this->cart->add($product->id);

        $this->expectException(CartException::class);
        $this->cart->setQuantity($product->id, 0);
    }

    public function test_setting_quantity_for_a_product_not_in_cart_is_refused(): void
    {
        $product = $this->publishedProduct(stock: 5);

        $this->expectException(CartException::class);
        $this->expectExceptionMessage('não está no carrinho');

        $this->cart->setQuantity($product->id, 1);
    }

    public function test_remove_and_clear(): void
    {
        $first = $this->publishedProduct(stock: 5);
        $second = $this->publishedProduct(stock: 5);
        $this->cart->add($first->id);
        $this->cart->add($second->id);

        $this->cart->remove($first->id);
        $this->assertCount(1, $this->cart->lines());

        $this->cart->clear();
        $this->assertCount(0, $this->cart->lines());
    }

    public function test_subtotal_uses_exact_decimal_arithmetic(): void
    {
        $tenCents = $this->publishedProduct(price: '0.10', stock: 10);
        $twentyCents = $this->publishedProduct(price: '0.20', stock: 10);

        $this->cart->add($tenCents->id, 1);
        $this->cart->add($twentyCents->id, 1);

        $this->assertSame('0.30', $this->cart->subtotal());
    }

    public function test_session_stores_only_product_ids_and_quantities(): void
    {
        $product = $this->publishedProduct(price: '99.00', stock: 5);
        $this->cart->add($product->id, 2);

        $stored = session()->get('cart.items');

        $this->assertSame([$product->id => 2], $stored);
    }

    public function test_tampered_session_values_are_ignored(): void
    {
        $product = $this->publishedProduct(price: '10.00', stock: 5);

        session()->put('cart.items', [
            $product->id => 1,
            'abc' => 2,
            -5 => 1,
            999999 => 0,
            7 => 'x',
        ]);

        $this->assertCount(1, $this->cart->lines());
        $this->assertSame(1, $this->cart->count());
    }

    public function test_adding_and_changing_the_cart_creates_no_stock_movements(): void
    {
        $product = $this->publishedProduct(stock: 5);
        $before = StockMovement::query()->count();

        $this->cart->add($product->id, 3);
        $this->cart->setQuantity($product->id, 2);
        $this->cart->remove($product->id);

        $this->assertSame($before, StockMovement::query()->count());
    }

    public function test_adding_an_unknown_product_is_refused(): void
    {
        $this->expectException(CartException::class);

        $this->cart->add(987654);
    }

    public function test_line_is_a_cart_line_dto(): void
    {
        $product = $this->publishedProduct(stock: 5);
        $this->cart->add($product->id);

        $this->assertInstanceOf(CartLine::class, $this->cart->lines()->first());
    }

    private function publishedProduct(string $price = '59.90', int $stock = 5): Product
    {
        $category = Category::factory()->create(['is_active' => true, 'parent_category_id' => null]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'price' => $price,
        ]);

        $actor = User::factory()->create();
        $actor->forceFill([
            'can_manage_catalog' => true,
            'can_manage_stock' => true,
            'can_view_stock_movements' => true,
        ])->save();

        app(StockService::class)->configureInitial($product, true, $stock, 0, $actor, 'Saldo de teste');

        return $product;
    }
}
