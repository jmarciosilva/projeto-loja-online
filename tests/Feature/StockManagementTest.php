<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ProductService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class StockManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_stock_is_audited_and_existing_product_remains_unreported(): void
    {
        $product = Product::factory()->create();
        $this->assertNull($product->stock()->first());
        $this->assertSame('unreported', $product->stockAvailability());

        $actor = $this->stockManager();
        $stock = app(StockService::class)->configureInitial($product, true, 1, 0, $actor);
        $this->assertSame(1, $stock->availableQuantity());
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'type' => 'initial_balance', 'quantity_delta' => 1,
            'quantity_before' => 0, 'quantity_after' => 1, 'user_id' => $actor->id,
        ]);
        $this->assertSame('available', $product->fresh()->stockAvailability());
    }

    public function test_entries_exits_adjustments_and_history_are_atomic(): void
    {
        $product = Product::factory()->create();
        $actor = $this->stockManager();
        $service = app(StockService::class);
        $service->configureInitial($product, true, 4, 1, $actor);
        $service->entry($product, 3, 'Reposição recebida', $actor);
        $service->manualExit($product, 2, 'Avaria', $actor);
        $service->positiveAdjustment($product, 1, 'Contagem física', $actor);
        $service->negativeAdjustment($product, 1, 'Correção de contagem', $actor);

        $this->assertSame(5, $product->fresh()->stock->quantity_on_hand);
        $this->assertCount(5, $service->history($product));
        $this->assertSame(5, StockMovement::query()->where('product_id', $product->id)->count());
    }

    public function test_negative_saldo_and_saida_acima_do_disponivel_are_rejected_without_partial_writes(): void
    {
        $product = Product::factory()->create();
        $actor = $this->stockManager();
        $service = app(StockService::class);
        $stock = $service->configureInitial($product, true, 2, 0, $actor);
        $stock->forceFill(['quantity_reserved' => 1])->save();

        try {
            $service->manualExit($product, 2, 'Saída inválida', $actor);
            $this->fail('A saída acima do disponível deveria falhar.');
        } catch (InvalidArgumentException) {
            $this->assertSame(2, $stock->fresh()->quantity_on_hand);
            $this->assertSame(1, $stock->fresh()->quantity_reserved);
            $this->assertSame(1, StockMovement::query()->where('product_id', $product->id)->count());
        }

        try {
            $service->negativeAdjustment($product, 2, 'Ajuste inválido', $actor);
            $this->fail('O ajuste abaixo do reservado deveria falhar.');
        } catch (InvalidArgumentException) {
            $this->assertSame(2, $stock->fresh()->quantity_on_hand);
        }
    }

    public function test_product_edits_do_not_overwrite_stock_and_stock_routes_require_specific_permission(): void
    {
        $actor = $this->stockManager();
        $category = Category::factory()->create();
        $this->actingAs($actor);
        $product = app(ProductService::class)->create([
            'category_id' => $category->id, 'name' => 'Livro usado', 'price' => '20.00',
            'condition' => 'used', 'condition_notes' => 'Pequenas marcas na capa',
            'stock_control_enabled' => true, 'initial_stock_quantity' => 1,
        ]);

        $this->put(route('admin.products.update', $product), [
            'category_id' => $category->id, 'name' => $product->name, 'price' => '22.00', 'condition' => 'used',
            'condition_notes' => 'Pequenas marcas na capa',
        ])->assertRedirect(route('admin.products.index'));
        $this->assertSame(1, $product->fresh()->stock->quantity_on_hand);
        $this->get(route('admin.products.stock', $product))->assertOk()->assertSee('Última unidade');

        $reader = User::factory()->create();
        $reader->forceFill(['can_manage_catalog' => true, 'can_view_stock_movements' => true])->save();
        $this->actingAs($reader)->get(route('admin.products.stock', $product))->assertOk();
        $this->actingAs($reader)->post(route('admin.products.stock.store', $product), [
            'mode' => 'movement', 'type' => 'entry', 'quantity' => 1, 'reason' => 'Sem permissão',
        ])->assertForbidden();

        $ordinary = User::factory()->create();
        $this->actingAs($ordinary)->get(route('admin.products.stock', $product))->assertForbidden();
    }

    public function test_public_catalog_never_implies_availability_and_shows_sold_out_and_condition(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $pending = Product::factory()->create([
            'category_id' => $category->id, 'is_active' => true, 'condition' => 'used',
            'condition_notes' => 'Páginas preservadas',
        ]);
        $soldOut = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);
        $stock = app(StockService::class)->configureInitial($soldOut, true, 0, 0, $this->stockManager());

        $this->get(route('catalog.show', $pending->slug))->assertOk()
            ->assertSee('Disponibilidade não informada')
            ->assertSee('Usado')
            ->assertSee('Páginas preservadas');
        $this->get(route('catalog.show', $soldOut->slug))->assertOk()->assertSee('Esgotado');
        $this->assertSame(0, $stock->fresh()->availableQuantity());
    }

    public function test_creating_a_product_with_stock_disabled_records_explicit_unreported_state(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['can_manage_catalog' => true])->save();
        $category = Category::factory()->create();
        $this->actingAs($admin)->post(route('admin.products.store'), [
            'category_id' => $category->id, 'name' => 'Sem contagem confirmada', 'price' => '12.00',
            'stock_control_enabled' => '0',
        ])->assertRedirect(route('admin.products.index'));
        $product = Product::query()->where('name', 'Sem contagem confirmada')->sole();
        $this->assertDatabaseHas('product_stocks', ['product_id' => $product->id, 'stock_control_enabled' => false]);
        $this->assertSame('unreported', $product->stockAvailability());
    }

    public function test_repeated_initial_configuration_is_rejected(): void
    {
        $product = Product::factory()->create();
        $service = app(StockService::class);
        $actor = $this->stockManager();
        $service->configureInitial($product, true, 1, 0, $actor);
        $this->expectException(InvalidArgumentException::class);
        $service->configureInitial($product, true, 9, 0, $actor);
    }

    public function test_wrong_sign_is_rejected_instead_of_turning_an_entry_into_an_exit(): void
    {
        $product = Product::factory()->create();
        $actor = $this->stockManager();
        $service = app(StockService::class);
        $service->configureInitial($product, true, 2, 0, $actor);

        $this->expectException(InvalidArgumentException::class);
        $service->entry($product, -1, 'Entrada inválida', $actor);
    }

    public function test_explicitly_untracked_product_can_be_initialized_after_physical_count(): void
    {
        $product = Product::factory()->create();
        $service = app(StockService::class);
        $actor = $this->stockManager();
        $service->configureInitial($product, false, 0, 0, $actor);
        $stock = $service->configureInitial($product, true, 3, 1, $actor);

        $this->assertSame(3, $stock->availableQuantity());
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'initial_balance', 'quantity_after' => 3]);
    }

    public function test_legacy_product_can_receive_its_first_counted_balance_through_admin_flow(): void
    {
        $admin = $this->stockManager();
        $product = Product::factory()->create(['name' => 'Produto legado sem contagem']);

        $this->actingAs($admin)->get(route('admin.products.stock', $product))
            ->assertOk()->assertSee('Disponibilidade não informada')->assertSee('Configurar estoque inicial');
        $this->assertDatabaseMissing('product_stocks', ['product_id' => $product->id]);

        $this->post(route('admin.products.stock.store', $product), [
            'mode' => 'initial', 'stock_control_enabled' => '1', 'quantity' => '2', 'minimum_quantity' => '1',
        ])->assertRedirect(route('admin.products.stock', $product));

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'type' => 'initial_balance', 'quantity_before' => 0,
            'quantity_after' => 2, 'user_id' => $admin->id,
        ]);
        $beforeEdit = $product->fresh()->stock->quantity_on_hand;
        app(ProductService::class)->update($product->fresh(), [
            'category_id' => $product->category_id, 'name' => $product->name,
            'price' => $product->price,
        ]);
        $this->assertSame($beforeEdit, $product->fresh()->stock->quantity_on_hand);
    }

    private function stockManager(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['can_manage_catalog' => true, 'can_manage_stock' => true])->save();

        return $user;
    }
}
