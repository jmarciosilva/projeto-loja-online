<?php

namespace Tests\Feature;

use App\Livewire\Cart\AddToCartButton;
use App\Livewire\Cart\CartCounter;
use App\Livewire\Cart\CartPage;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CartComponentsTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create();
        $this->actor->forceFill([
            'can_manage_catalog' => true,
            'can_manage_stock' => true,
            'can_view_stock_movements' => true,
        ])->save();
    }

    public function test_add_button_adds_the_product_and_notifies_the_counter(): void
    {
        $product = $this->publishedProduct(stock: 3);

        Livewire::test(AddToCartButton::class, ['productId' => $product->id])
            ->call('addToCart')
            ->assertSee('Produto adicionado ao carrinho. Quantidade no carrinho: 1.')
            ->assertDispatched('cart-updated');

        $this->assertSame(1, app(CartService::class)->count());
    }

    public function test_add_button_shows_the_limit_message_instead_of_failing_silently(): void
    {
        $product = $this->publishedProduct(stock: 1);

        Livewire::test(AddToCartButton::class, ['productId' => $product->id])
            ->call('addToCart')
            ->call('addToCart')
            ->assertSee('quantidade máxima disponível (1)');
    }

    public function test_add_button_is_disabled_for_sold_out_products(): void
    {
        $product = $this->publishedProduct(stock: 1);
        app(StockService::class)->manualExit($product, 1, 'Venda de teste', $this->actor);

        Livewire::test(AddToCartButton::class, ['productId' => $product->id])
            ->assertSee('Indisponível para compra')
            ->assertSee('Este produto está esgotado.');
    }

    public function test_add_button_is_disabled_for_products_without_stock_control(): void
    {
        $product = $this->publishedProduct(stock: 0, stockControl: false);

        Livewire::test(AddToCartButton::class, ['productId' => $product->id])
            ->assertSee('controle de estoque ativo');
    }

    public function test_counter_reflects_the_cart_contents(): void
    {
        $product = $this->publishedProduct(stock: 5);
        app(CartService::class)->add($product->id, 3);

        Livewire::test(CartCounter::class)
            ->assertSee('Carrinho, 3 itens');
    }

    public function test_counter_refreshes_when_the_cart_changes(): void
    {
        $product = $this->publishedProduct(stock: 5);
        $counter = Livewire::test(CartCounter::class)->assertSee('Carrinho, 0 itens');

        app(CartService::class)->add($product->id, 2);

        $counter->dispatch('cart-updated')->assertSee('Carrinho, 2 itens');
    }

    public function test_cart_page_updates_quantity_and_subtotal(): void
    {
        $product = $this->publishedProduct(price: '15.00', stock: 5);
        app(CartService::class)->add($product->id);

        Livewire::test(CartPage::class)
            ->call('setQuantity', $product->id, 3)
            ->assertSee('Quantidade atualizada.')
            ->assertSee('R$ 45,00');
    }

    public function test_cart_page_refuses_quantities_above_stock_with_a_message(): void
    {
        $product = $this->publishedProduct(stock: 2);
        app(CartService::class)->add($product->id);

        Livewire::test(CartPage::class)
            ->call('setQuantity', $product->id, 9)
            ->assertSee('A quantidade máxima disponível é 2.');

        $this->assertSame(1, app(CartService::class)->count());
    }

    public function test_cart_page_refuses_changes_to_products_not_in_the_cart(): void
    {
        $product = $this->publishedProduct(stock: 5);

        Livewire::test(CartPage::class)
            ->call('setQuantity', $product->id, 1)
            ->assertSee('Este produto não está no carrinho.');
    }

    public function test_cart_page_removes_items(): void
    {
        $product = $this->publishedProduct(stock: 5);
        app(CartService::class)->add($product->id);

        Livewire::test(CartPage::class)
            ->call('remove', $product->id)
            ->assertSee('Item removido do carrinho.')
            ->assertSee('Seu carrinho está vazio');
    }

    public function test_cart_page_shows_unavailable_items_without_adding_them_to_the_total(): void
    {
        $available = $this->publishedProduct(price: '10.00', stock: 5);
        $unpublished = $this->publishedProduct(price: '90.00', stock: 5);
        app(CartService::class)->add($available->id, 2);
        app(CartService::class)->add($unpublished->id);
        $unpublished->forceFill(['is_active' => false])->save();

        Livewire::test(CartPage::class)
            ->assertSee($unpublished->name)
            ->assertSee('Este produto foi removido da loja.')
            ->assertSee('R$ 20,00')
            ->assertDontSee('R$ 110,00');
    }

    public function test_cart_page_empty_state_offers_a_way_back_to_the_catalog(): void
    {
        Livewire::test(CartPage::class)
            ->assertSee('Seu carrinho está vazio')
            ->assertSee(route('catalog.index'));
    }

    public function test_checkout_link_appears_only_when_every_line_is_purchasable(): void
    {
        $product = $this->publishedProduct(stock: 5);
        app(CartService::class)->add($product->id);

        Livewire::test(CartPage::class)
            ->assertSee(route('checkout.show'));
    }

    public function test_checkout_is_blocked_on_the_cart_when_an_item_is_unavailable(): void
    {
        $product = $this->publishedProduct(stock: 5);
        app(CartService::class)->add($product->id);
        $product->forceFill(['is_active' => false])->save();

        Livewire::test(CartPage::class)
            ->assertDontSee(route('checkout.show'))
            ->assertSee('Remova ou ajuste os itens indisponíveis para continuar.');
    }

    public function test_cart_persists_between_requests_and_appears_on_the_cart_page(): void
    {
        $product = $this->publishedProduct(price: '30.00', stock: 5);

        Livewire::test(AddToCartButton::class, ['productId' => $product->id])
            ->call('addToCart');

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('Itens para compra');
    }

    public function test_product_page_renders_the_add_button(): void
    {
        $product = $this->publishedProduct(stock: 5);

        $this->get(route('catalog.show', $product->slug))
            ->assertOk()
            ->assertSee('Adicionar ao carrinho');
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
