<?php

namespace Tests\Feature;

use App\Enums\ShippingProfileReadiness;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductShippingProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_administrator_can_create_profile_and_partial_product_edit_preserves_it(): void
    {
        $admin = $this->catalogManager();
        $category = Category::factory()->create(['is_active' => true]);
        $this->actingAs($admin)->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'name' => 'Livro usado',
            'price' => '35,00',
            'shipping_profile' => [
                'requires_shipping' => '1', 'weight_kg' => '0,350',
                'height_cm' => '2,50', 'width_cm' => '14,00', 'length_cm' => '21,00',
                'package_weight_kg' => '0,400', 'package_height_cm' => '3,00',
                'package_width_cm' => '16,00', 'package_length_cm' => '23,00',
            ],
        ])->assertRedirect(route('admin.products.index'));

        $product = Product::query()->sole();
        $profile = $product->shippingProfile()->sole();
        $this->assertSame('0.350', $profile->weight_kg);
        $this->assertSame(ShippingProfileReadiness::Ready, ShippingProfileReadiness::for($profile));

        $this->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => $product->name,
            'price' => '39,00',
        ])->assertRedirect(route('admin.products.index'));

        $this->assertSame('0.350', $profile->fresh()->weight_kg);
        $this->assertSame('39.00', $product->fresh()->price);

        $this->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => $product->name,
            'price' => '39,00',
            'shipping_profile' => [
                'requires_shipping' => '1', 'weight_kg' => '0,500',
                'height_cm' => '3,00', 'width_cm' => '15,00', 'length_cm' => '22,00',
                'package_weight_kg' => '0,550', 'package_height_cm' => '4,00',
                'package_width_cm' => '17,00', 'package_length_cm' => '24,00',
            ],
        ])->assertRedirect(route('admin.products.index'));
        $this->assertSame('0.500', $profile->fresh()->weight_kg);
        $this->assertSame('0.550', $profile->fresh()->package_weight_kg);
        $this->actingAs($admin)->get(route('admin.products.edit', $product))
            ->assertOk()->assertSee('Pronto para preparar cotação de uma unidade.')
            ->assertSee('value="0.500"', false);
    }

    public function test_missing_profile_is_pending_and_does_not_default_to_no_shipping(): void
    {
        $product = Product::factory()->create();
        $this->assertNull($product->shippingProfile);
        $this->assertSame(ShippingProfileReadiness::ProfilePending, ShippingProfileReadiness::for(null));
    }

    public function test_incomplete_shipping_profile_can_be_saved_and_explicit_no_shipping_needs_no_measures(): void
    {
        $admin = $this->catalogManager();
        $category = Category::factory()->create(['is_active' => true]);

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'category_id' => $category->id, 'name' => 'Livro sem medidas', 'price' => '20,00',
            'shipping_profile' => ['requires_shipping' => '1'],
        ])->assertRedirect(route('admin.products.index'));
        $physical = Product::query()->where('name', 'Livro sem medidas')->sole();
        $this->assertSame(ShippingProfileReadiness::ProductMeasurementsPending, ShippingProfileReadiness::for($physical->shippingProfile));

        $this->post(route('admin.products.store'), [
            'category_id' => $category->id, 'name' => 'Produto sem envio', 'price' => '12,00',
            'shipping_profile' => ['requires_shipping' => '0'],
        ])->assertRedirect(route('admin.products.index'));
        $digital = Product::query()->where('name', 'Produto sem envio')->sole();
        $this->assertFalse($digital->shippingProfile->requires_shipping);
        $this->assertSame(ShippingProfileReadiness::NotRequired, ShippingProfileReadiness::for($digital->shippingProfile));
    }

    public function test_invalid_measurements_are_rejected_without_mutating_product_or_profile(): void
    {
        $admin = $this->catalogManager();
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Livro intacto', 'price' => '25.00']);
        $product->shippingProfile()->create(['requires_shipping' => true, 'weight_kg' => '0.350']);

        foreach (['0', '-1', '1001', 'NaN'] as $weight) {
            $this->actingAs($admin)->put(route('admin.products.update', $product), [
                'category_id' => $category->id, 'name' => 'Nome não salvo', 'price' => '99,00',
                'shipping_profile' => ['requires_shipping' => '1', 'weight_kg' => $weight],
            ])->assertSessionHasErrors('shipping_profile.weight_kg');
        }

        foreach (['0', '-2', '301'] as $dimension) {
            $this->put(route('admin.products.update', $product), [
                'category_id' => $category->id, 'name' => 'Nome não salvo', 'price' => '99,00',
                'shipping_profile' => ['requires_shipping' => '1', 'height_cm' => $dimension],
            ])->assertSessionHasErrors('shipping_profile.height_cm');
        }

        $this->assertSame('Livro intacto', $product->fresh()->name);
        $this->assertSame('25.00', $product->fresh()->price);
        $this->assertSame('0.350', $product->shippingProfile->fresh()->weight_kg);
    }

    public function test_catalog_permission_is_required_for_shipping_profile_changes(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['can_manage_catalog' => false]);
        $this->actingAs($user)->post(route('admin.products.store'), [
            'category_id' => $category->id, 'name' => 'Não autorizado', 'price' => '20,00',
            'shipping_profile' => ['requires_shipping' => '0'],
        ])->assertForbidden();
        $this->assertDatabaseMissing('products', ['name' => 'Não autorizado']);
    }

    public function test_profile_relationship_is_one_to_one(): void
    {
        $product = Product::factory()->create();
        $product->shippingProfile()->create(['requires_shipping' => false]);
        $this->expectException(QueryException::class);
        $product->shippingProfile()->create(['requires_shipping' => true]);
    }

    public function test_soft_delete_preserves_profile_and_force_delete_cascades_it(): void
    {
        $product = Product::factory()->create();
        $product->shippingProfile()->create(['requires_shipping' => false]);
        $product->delete();
        $this->assertDatabaseHas('product_shipping_profiles', ['product_id' => $product->id]);

        $product->forceDelete();
        $this->assertDatabaseMissing('product_shipping_profiles', ['product_id' => $product->id]);
    }

    private function catalogManager(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['can_manage_catalog' => true])->save();

        return $user;
    }
}
