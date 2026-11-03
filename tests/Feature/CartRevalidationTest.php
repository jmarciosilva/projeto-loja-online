<?php

namespace Tests\Feature;

use App\Enums\CartLineStatus;
use App\Exceptions\CartException;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\StockService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartRevalidationTest extends TestCase
{
    use RefreshDatabase;

    private CartService $cart;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cart = app(CartService::class);
        $this->actor = User::factory()->create();
        $this->actor->forceFill([
            'can_manage_catalog' => true,
            'can_manage_stock' => true,
            'can_view_stock_movements' => true,
        ])->save();
    }

    public function test_unpublished_product_is_kept_visible_with_a_warning_and_excluded_from_subtotal(): void
    {
        $product = $this->publishedProduct(price: '30.00', stock: 3);
        $this->cart->add($product->id);

        $product->forceFill(['is_active' => false])->save();

        $line = $this->cart->lines()->first();
        $this->assertSame(CartLineStatus::Unpublished, $line->status);
        $this->assertFalse($line->isPurchasable());
        $this->assertSame(CartLineStatus::Unpublished->message(), $line->status->message());
        $this->assertSame(0, $this->cart->count());
        $this->assertSame('0.00', $this->cart->subtotal());
    }

    public function test_product_in_a_hidden_category_is_reported_as_category_hidden(): void
    {
        $product = $this->publishedProduct(stock: 3);
        $this->cart->add($product->id);

        $product->category->forceFill(['is_active' => false])->save();

        $this->assertSame(CartLineStatus::CategoryHidden, $this->cart->lines()->first()->status);
        $this->assertSame('0.00', $this->cart->subtotal());
    }

    public function test_deleted_product_is_reported_as_not_found(): void
    {
        $product = $this->publishedProduct(stock: 3);
        $this->cart->add($product->id);

        $product->delete();

        $this->assertSame(CartLineStatus::NotFound, $this->cart->lines()->first()->status);
        $this->assertSame(0, $this->cart->count());
    }

    public function test_product_that_sells_out_after_being_added_is_reported_as_sold_out(): void
    {
        $product = $this->publishedProduct(price: '45.00', stock: 2);
        $this->cart->add($product->id, 2);

        app(StockService::class)->manualExit($product, 2, 'Venda de teste', $this->actor);

        $this->assertSame(CartLineStatus::SoldOut, $this->cart->lines()->first()->status);
        $this->assertSame('0.00', $this->cart->subtotal());
    }

    public function test_stock_reduced_below_the_quantity_is_flagged_and_not_silently_reduced(): void
    {
        $product = $this->publishedProduct(price: '20.00', stock: 5);
        $this->cart->add($product->id, 4);

        app(StockService::class)->manualExit($product, 3, 'Ajuste de teste', $this->actor);

        $line = $this->cart->lines()->first();
        $this->assertSame(CartLineStatus::QuantityAboveLimit, $line->status);
        $this->assertSame(2, $line->maxQuantity);
        $this->assertSame(4, $line->quantity, 'A quantidade solicitada deve ser mantida.');
        $this->assertSame('0.00', $this->cart->subtotal());
    }

    public function test_user_can_adjust_a_flagged_line_to_the_new_maximum(): void
    {
        $product = $this->publishedProduct(price: '20.00', stock: 5);
        $this->cart->add($product->id, 4);
        app(StockService::class)->manualExit($product, 3, 'Ajuste de teste', $this->actor);

        $this->cart->setQuantity($product->id, 2);

        $this->assertSame(CartLineStatus::Available, $this->cart->lines()->first()->status);
        $this->assertSame('40.00', $this->cart->subtotal());
    }

    public function test_product_without_active_stock_control_cannot_be_added(): void
    {
        $product = $this->publishedProduct(stock: 0, stockControl: false);

        $this->expectException(CartException::class);
        $this->expectExceptionMessage('controle de estoque ativo');

        $this->cart->add($product->id);
    }

    public function test_price_change_is_reflected_on_the_next_read(): void
    {
        $product = $this->publishedProduct(price: '10.00', stock: 5);
        $this->cart->add($product->id, 2);

        $product->forceFill(['price' => '12.50'])->save();

        $this->assertSame('12.50', $this->cart->lines()->first()->unitPrice);
        $this->assertSame('25.00', $this->cart->subtotal());
    }

    public function test_promotion_that_is_active_uses_the_promotional_price(): void
    {
        $product = $this->publishedProduct(price: '100.00', stock: 5);
        $product->forceFill([
            'promotion_enabled' => true,
            'promotional_price' => '80.00',
            'promotion_starts_at' => now()->subDay(),
            'promotion_ends_at' => now()->addDay(),
        ])->save();

        $this->cart->add($product->id);

        $this->assertSame('80.00', $this->cart->lines()->first()->unitPrice);
        $this->assertSame('80.00', $this->cart->subtotal());
    }

    public function test_promotion_that_has_ended_falls_back_to_the_regular_price(): void
    {
        $product = $this->publishedProduct(price: '100.00', stock: 5);
        $this->cart->add($product->id);

        $product->forceFill([
            'promotion_enabled' => true,
            'promotional_price' => '80.00',
            'promotion_starts_at' => CarbonImmutable::now()->subDays(3),
            'promotion_ends_at' => CarbonImmutable::now()->subDay(),
        ])->save();

        $this->assertSame('100.00', $this->cart->lines()->first()->unitPrice);
        $this->assertSame('100.00', $this->cart->subtotal());
    }

    public function test_subtotal_counts_only_purchasable_lines_in_a_mixed_cart(): void
    {
        $available = $this->publishedProduct(price: '15.00', stock: 5);
        $hidden = $this->publishedProduct(price: '99.00', stock: 5);

        $this->cart->add($available->id, 2);
        $this->cart->add($hidden->id);
        $hidden->forceFill(['is_active' => false])->save();

        $this->assertSame(2, $this->cart->count());
        $this->assertSame('30.00', $this->cart->subtotal());
        $this->assertCount(2, $this->cart->lines());
    }

    private function publishedProduct(string $price = '59.90', int $stock = 5, bool $stockControl = true): Product
    {
        $category = Category::factory()->create(['is_active' => true, 'parent_category_id' => null]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'price' => $price,
        ]);

        app(StockService::class)->configureInitial($product, $stockControl, $stock, 0, $this->actor, 'Saldo de teste');

        return $product;
    }
}
