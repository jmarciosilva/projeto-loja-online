<?php

namespace Tests\Feature;

use App\Livewire\Checkout\BuyerForm;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\Checkout\CheckoutSession;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutBuyerTest extends TestCase
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

    public function test_empty_cart_cannot_reach_the_checkout(): void
    {
        $this->get(route('checkout.show'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('cart_error');
    }

    public function test_cart_with_an_unavailable_item_cannot_reach_the_checkout(): void
    {
        $product = $this->publishedProduct(stock: 3);
        app(CartService::class)->add($product->id);
        $product->forceFill(['is_active' => false])->save();

        $this->get(route('checkout.show'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('cart_error');
    }

    public function test_ready_cart_renders_the_identification_step(): void
    {
        $product = $this->publishedProduct(stock: 3);
        app(CartService::class)->add($product->id);

        $this->get(route('checkout.show'))
            ->assertOk()
            ->assertSee('Identificação e endereço')
            ->assertSee('Salvar e continuar');
    }

    public function test_all_required_fields_are_reported_on_submit(): void
    {
        $this->readyCart();

        Livewire::test(BuyerForm::class)
            ->call('save')
            ->assertHasErrors(['name', 'email', 'phone', 'postalCode', 'street', 'number', 'district', 'city', 'state']);
    }

    public function test_invalid_email_is_rejected(): void
    {
        $this->readyCart();

        Livewire::test(BuyerForm::class)
            ->set('email', 'nao-e-um-email')
            ->call('save')
            ->assertHasErrors(['email']);
    }

    public function test_landline_and_short_phone_numbers_are_rejected(): void
    {
        $this->readyCart();

        Livewire::test(BuyerForm::class)
            ->set('phone', '1133334444')
            ->call('save')
            ->assertHasErrors(['phone']);

        Livewire::test(BuyerForm::class)
            ->set('phone', '119123')
            ->call('save')
            ->assertHasErrors(['phone']);
    }

    public function test_mobile_with_ddd_is_accepted_and_stored_as_digits(): void
    {
        $this->readyCart();

        Livewire::test(BuyerForm::class)
            ->fill($this->validBuyer(['phone' => '(11) 91234-5678']))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('11912345678', session('checkout.buyer.phone'));
    }

    public function test_invalid_cep_is_rejected(): void
    {
        $this->readyCart();

        Livewire::test(BuyerForm::class)
            ->set('postalCode', '1234')
            ->call('save')
            ->assertHasErrors(['postalCode']);

        Livewire::test(BuyerForm::class)
            ->set('postalCode', '00000000')
            ->call('save')
            ->assertHasErrors(['postalCode']);
    }

    public function test_valid_cep_is_stored_as_digits(): void
    {
        $this->readyCart();

        Livewire::test(BuyerForm::class)
            ->fill($this->validBuyer(['postalCode' => '01310-100']))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('01310100', session('checkout.buyer.postalCode'));
    }

    public function test_invalid_uf_is_rejected_and_valid_uf_is_normalized(): void
    {
        $this->readyCart();

        Livewire::test(BuyerForm::class)
            ->set('state', 'XX')
            ->call('save')
            ->assertHasErrors(['state']);

        Livewire::test(BuyerForm::class)
            ->fill($this->validBuyer(['state' => 'sp']))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('SP', session('checkout.buyer.state'));
    }

    public function test_name_requires_first_and_last_name(): void
    {
        $this->readyCart();

        Livewire::test(BuyerForm::class)
            ->set('name', 'Maria')
            ->call('save')
            ->assertHasErrors(['name']);
    }

    public function test_house_number_without_digits_such_as_sn_is_accepted(): void
    {
        $this->readyCart();

        Livewire::test(BuyerForm::class)
            ->fill($this->validBuyer(['number' => 'S/N']))
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_email_is_normalized_to_lowercase_and_trimmed(): void
    {
        $this->readyCart();

        Livewire::test(BuyerForm::class)
            ->fill($this->validBuyer(['email' => '  Maria.Silva@Exemplo.COM ']))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('maria.silva@exemplo.com', session('checkout.buyer.email'));
    }

    public function test_valid_submission_is_kept_only_in_the_session(): void
    {
        $product = $this->publishedProduct(stock: 1);
        app(CartService::class)->add($product->id);
        $stockBefore = $this->stockSnapshot($product);

        Livewire::test(BuyerForm::class)
            ->fill($this->validBuyer())
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', true);

        $this->assertSame($stockBefore, $this->stockSnapshot($product), 'O checkout não pode reservar nem baixar estoque.');
        $this->assertSame(
            ['name', 'email', 'phone', 'postalCode', 'street', 'number', 'complement', 'district', 'city', 'state', 'reference'],
            array_keys(session('checkout.buyer')),
        );
    }

    public function test_data_is_preserved_when_the_visitor_navigates_away_and_back(): void
    {
        $product = $this->publishedProduct(stock: 3);
        app(CartService::class)->add($product->id);

        Livewire::test(BuyerForm::class)
            ->fill($this->validBuyer())
            ->call('save');

        $this->get(route('catalog.show', $product->slug))->assertOk();

        Livewire::test(BuyerForm::class)
            ->assertSet('name', 'Maria Silva')
            ->assertSet('postalCode', '01310100')
            ->assertSet('saved', true);
    }

    public function test_failed_submission_does_not_overwrite_previously_saved_data(): void
    {
        $this->readyCart();

        Livewire::test(BuyerForm::class)
            ->fill($this->validBuyer())
            ->call('save');

        Livewire::test(BuyerForm::class)
            ->set('email', 'invalido')
            ->call('save')
            ->assertHasErrors(['email']);

        $this->assertSame('maria.silva@exemplo.com', session('checkout.buyer.email'));
    }

    public function test_save_is_refused_when_the_cart_becomes_unavailable_before_submit(): void
    {
        $product = $this->publishedProduct(stock: 3);
        app(CartService::class)->add($product->id);

        $form = Livewire::test(BuyerForm::class)->fill($this->validBuyer());
        $product->forceFill(['is_active' => false])->save();

        $form->call('save');

        $this->assertNull(app(CheckoutSession::class)->buyer());
        $this->assertSame('Seu carrinho mudou. Revise os itens antes de continuar.', session('cart_error'));
    }

    private function readyCart(): void
    {
        $product = $this->publishedProduct(stock: 3);
        app(CartService::class)->add($product->id);
    }

    /** @param array<string, string> $overrides */
    private function validBuyer(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maria Silva',
            'email' => 'maria.silva@exemplo.com',
            'phone' => '(11) 91234-5678',
            'postalCode' => '01310-100',
            'street' => 'Avenida Paulista',
            'number' => '1000',
            'complement' => 'Apto 12',
            'district' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
            'reference' => 'Próximo ao metrô',
        ], $overrides);
    }

    /** @return array{on_hand:int, reserved:int, movements:int} */
    private function stockSnapshot(Product $product): array
    {
        $stock = $product->stock()->first();

        return [
            'on_hand' => $stock->quantity_on_hand,
            'reserved' => $stock->quantity_reserved,
            'movements' => $product->stockMovements()->count(),
        ];
    }

    private function publishedProduct(int $stock = 5): Product
    {
        $category = Category::factory()->create(['is_active' => true, 'parent_category_id' => null]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'price' => '20.00',
        ]);

        app(StockService::class)->configureInitial($product, true, $stock, 0, $this->actor, 'Saldo de teste');

        return $product;
    }
}
