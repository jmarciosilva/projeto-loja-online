<?php

namespace Tests\Feature;

use App\Exceptions\MediaInUseException;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\User;
use App\Services\MediaService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(MediaService::DISK);
    }

    public function test_catalog_management_requires_an_explicit_capability(): void
    {
        $this->get('/admin/categorias')->assertRedirect('/login');

        $ordinary = User::factory()->create();
        $this->actingAs($ordinary)->get('/admin/categorias')->assertForbidden();
        $this->actingAs($ordinary)->post('/admin/categorias', ['name' => 'Tentativa'])->assertForbidden();
        $this->assertFalse($ordinary->fresh()->canManageCatalog());
    }

    public function test_authorized_user_can_manage_category_and_soft_delete_keeps_products(): void
    {
        $admin = $this->catalogManager();
        $this->actingAs($admin)->post('/admin/categorias', [
            'name' => 'Camisetas', 'description' => 'Peças para o dia a dia', 'is_active' => '1',
        ])->assertRedirect(route('admin.categories.index'));

        $category = Category::query()->sole();
        $this->assertSame('camisetas', $category->slug);
        $this->assertTrue($category->is_active);

        $product = Product::factory()->create(['category_id' => $category->id]);
        $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'name' => 'Camisetas básicas', 'slug' => 'camisetas-basicas',
            'description' => 'Descrição atualizada', 'is_active' => '1',
        ])->assertRedirect(route('admin.categories.index'));
        $this->assertSame('camisetas-basicas', $category->fresh()->slug);

        $this->actingAs($admin)->post(route('admin.categories.toggle', $category))->assertRedirect();
        $this->assertFalse($category->fresh()->is_active);

        $this->actingAs($admin)->delete(route('admin.categories.destroy', $category))->assertRedirect();
        $this->assertSoftDeleted('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $category->id]);
    }

    public function test_category_slug_is_unique_even_when_the_previous_category_was_deleted(): void
    {
        $admin = $this->catalogManager();
        $category = Category::factory()->create(['slug' => 'camisetas']);
        $category->delete();

        $this->actingAs($admin)->post('/admin/categorias', ['name' => 'Camisetas', 'slug' => 'camisetas'])
            ->assertSessionHasErrors('slug');
    }

    public function test_product_form_requires_a_category_and_offers_a_category_creation_path(): void
    {
        $admin = $this->catalogManager();
        $this->actingAs($admin)->get('/admin/produtos/criar')
            ->assertOk()
            ->assertSee(route('admin.categories.create'), false)
            ->assertSee('Cadastre uma categoria antes de criar um produto.');
    }

    public function test_category_create_and_edit_pages_render_their_forms(): void
    {
        $admin = $this->catalogManager();
        $category = Category::factory()->create();

        foreach ([
            route('admin.categories.create') => 'Criar categoria',
            route('admin.categories.edit', $category) => 'Salvar alterações',
        ] as $url => $submitLabel) {
            $this->actingAs($admin)->get($url)
                ->assertOk()
                ->assertSee('<form', false)
                ->assertSee('name="name"', false)
                ->assertSee('name="slug"', false)
                ->assertSee('name="description"', false)
                ->assertSee('name="sort_order"', false)
                ->assertSee('name="is_active"', false)
                ->assertSee($submitLabel)
                ->assertDontSee('@include(');
        }
    }

    public function test_product_create_and_edit_pages_render_all_catalog_fields(): void
    {
        $admin = $this->catalogManager();
        $category = Category::factory()->create(['is_active' => true]);
        $media = $this->media('catalog/form-edit.png');
        $product = Product::factory()->create(['category_id' => $category->id, 'main_media_id' => $media->id]);

        foreach ([
            route('admin.products.create') => 'Criar produto',
            route('admin.products.edit', $product) => 'Salvar alterações',
        ] as $url => $submitLabel) {
            $this->actingAs($admin)->get($url)
                ->assertOk()
                ->assertSee('<form', false)
                ->assertSee('name="name"', false)
                ->assertSee('name="category_id"', false)
                ->assertSee('name="price"', false)
                ->assertSee('name="cost_price"', false)
                ->assertSee('name="target_margin"', false)
                ->assertSee('name="promotional_price"', false)
                ->assertSee('name="promotion_starts_at"', false)
                ->assertSee('name="promotion_ends_at"', false)
                ->assertSee('id="main_media_id"', false)
                ->assertSee('Adicionar imagem da biblioteca')
                ->assertSee('Produto em destaque na home')
                ->assertSee($submitLabel)
                ->assertDontSee('@include(');
        }

        $editHtml = (string) $this->actingAs($admin)->get(route('admin.products.edit', $product))->assertOk()->getContent();
        $this->assertStringContainsString('src="'.e(app(MediaService::class)->url($media)).'"', $editHtml);
    }

    public function test_product_form_integrates_media_picker_without_navigating_away_or_clearing_fields(): void
    {
        $admin = $this->catalogManager();
        Category::factory()->create(['is_active' => true]);

        $html = (string) $this->actingAs($admin)->withSession(['_old_input' => [
            'name' => 'Camiseta de Algodão Básica',
            'price' => '50,00',
            'description' => 'Algodão penteado',
        ]])->get(route('admin.products.create'))->assertOk()->getContent();

        $this->assertStringContainsString('value="Camiseta de Algodão Básica"', $html);
        $this->assertStringContainsString('value="50,00"', $html);
        $this->assertStringContainsString('Algodão penteado', $html);
        $this->assertStringContainsString('<dialog id="media-picker"', $html);
        $this->assertStringContainsString('Adicionar imagem da biblioteca', $html);
        $this->assertStringContainsString("document.getElementById('close-media-picker').addEventListener", $html);
        $this->assertStringContainsString('fetch(uploadForm.dataset.action', $html);
        $this->assertStringContainsString('description-editor', $html);
        $this->assertStringContainsString('gallery_media_ids[]', $html);
        $this->assertStringContainsString('Os demais campos foram preservados.', $html);
        $this->assertStringNotContainsString('window.location', $html);
    }

    public function test_media_upload_failure_returns_validation_errors_without_creating_media(): void
    {
        $admin = $this->catalogManager();

        $this->actingAs($admin)->postJson(route('admin.media.store'), [
            'file' => UploadedFile::fake()->create('arquivo.txt', 1, 'text/plain'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertSame(0, Media::query()->count());
        $this->assertSame(0, Product::query()->count());
    }

    public function test_json_media_upload_uses_the_existing_media_service_and_returns_selectable_image(): void
    {
        $admin = $this->catalogManager();

        $response = $this->actingAs($admin)->postJson(route('admin.media.store'), [
            'file' => UploadedFile::fake()->image('imagem.png', 80, 60),
        ])->assertCreated()->assertJsonStructure(['id', 'name', 'mime_type', 'url']);

        $this->assertSame(1, Media::query()->count());
        $this->assertStringContainsString('media/', $response->json('url'));
    }

    public function test_product_sku_is_generated_stably_unique_and_not_reused_after_soft_delete(): void
    {
        $admin = $this->catalogManager();
        $category = Category::factory()->create(['is_active' => true]);
        $this->actingAs($admin);

        $this->post(route('admin.products.store'), [
            'category_id' => $category->id, 'name' => 'Camiseta de Algodão Básica',
            'price' => '50,00', 'cost_price' => '30,00', 'target_margin' => '40',
            'is_active' => '1',
        ])->assertRedirect(route('admin.products.index'));

        $first = Product::query()->sole();
        $this->assertSame('PRD-'.str_pad((string) $first->id, 6, '0', STR_PAD_LEFT), $first->sku);

        $this->put(route('admin.products.update', $first), [
            'category_id' => $category->id, 'name' => $first->name,
            'price' => '50,00', 'is_active' => '1',
        ])->assertRedirect(route('admin.products.index'));
        $this->assertSame($first->sku, $first->fresh()->sku);

        app(ProductService::class)->delete($first->fresh());
        $this->post(route('admin.products.store'), [
            'category_id' => $category->id, 'name' => 'Outra camiseta',
            'price' => '40,00', 'is_active' => '1',
        ])->assertRedirect(route('admin.products.index'));

        $second = Product::query()->sole();
        $this->assertSame('PRD-'.str_pad((string) $second->id, 6, '0', STR_PAD_LEFT), $second->sku);
        $this->assertNotSame($first->sku, $second->sku);
        $this->assertSame(2, Product::withTrashed()->count());
    }

    public function test_sku_manual_is_optional_unique_and_immutable_after_creation(): void
    {
        $admin = $this->catalogManager();
        $category = Category::factory()->create(['is_active' => true]);
        $this->actingAs($admin);
        $this->post(route('admin.products.store'), [
            'category_id' => $category->id, 'name' => 'Produto manual',
            'sku' => 'loja-abc-001', 'price' => '20,00',
        ])->assertRedirect(route('admin.products.index'));

        $product = Product::query()->sole();
        $this->assertSame('LOJA-ABC-001', $product->sku);

        $this->put(route('admin.products.update', $product), [
            'category_id' => $category->id, 'name' => $product->name,
            'sku' => 'OUTRO-001', 'price' => '20,00',
        ])->assertSessionHasErrors('sku');
        $this->assertSame('LOJA-ABC-001', $product->fresh()->sku);
    }

    public function test_manual_sku_from_a_soft_deleted_product_cannot_be_reused(): void
    {
        $admin = $this->catalogManager();
        $category = Category::factory()->create(['is_active' => true]);
        $deleted = Product::factory()->create([
            'category_id' => $category->id, 'sku' => 'CAM-ARCHIVE-1',
        ]);
        $deleted->delete();

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'category_id' => $category->id, 'name' => 'Novo produto',
            'sku' => 'CAM-ARCHIVE-1', 'price' => '20,00',
        ])->assertSessionHasErrors('sku');

        $this->assertSame(1, Product::withTrashed()->count());
    }

    public function test_cost_margin_and_manual_sale_price_are_persisted_without_overwriting_the_sale_price(): void
    {
        $admin = $this->catalogManager();
        $category = Category::factory()->create(['is_active' => true]);

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'name' => 'Preço ajustado',
            'cost_price' => '30,00',
            'target_margin' => '40',
            'price' => '51,11',
        ])->assertRedirect(route('admin.products.index'));

        $product = Product::query()->sole();
        $this->assertSame('30.00', $product->cost_price);
        $this->assertSame('40.00', $product->target_margin);
        $this->assertSame('51.11', $product->price);
    }

    public function test_promotion_requires_a_lower_price_and_end_after_start_and_margin_below_one_hundred(): void
    {
        $admin = $this->catalogManager();
        $category = Category::factory()->create(['is_active' => true]);
        $base = [
            'category_id' => $category->id,
            'name' => 'Produto programado',
            'price' => '50,00',
            'cost_price' => '30,00',
            'target_margin' => '100',
            'promotion_enabled' => '1',
            'promotional_price' => '45,00',
            'promotion_starts_at' => '2026-11-08T10:00',
            'promotion_ends_at' => '2026-11-08T10:00',
        ];

        $this->actingAs($admin)->post(route('admin.products.store'), $base)
            ->assertSessionHasErrors(['target_margin', 'promotion_ends_at']);

        $this->post(route('admin.products.store'), [...$base, 'target_margin' => '40', 'promotional_price' => '50,00'])
            ->assertSessionHasErrors('promotional_price');
        $this->post(route('admin.products.store'), [...$base, 'target_margin' => '40', 'promotional_price' => ''])
            ->assertSessionHasErrors('promotional_price');
        $this->assertSame(0, Product::query()->count());
    }

    public function test_scheduled_promotion_and_cost_values_are_persisted_in_the_configured_timezone(): void
    {
        $admin = $this->catalogManager();
        $category = Category::factory()->create(['is_active' => true]);
        $this->actingAs($admin)->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'name' => 'Promoção agendada',
            'cost_price' => '30,00',
            'target_margin' => '40',
            'price' => '50,00',
            'promotional_price' => '45,00',
            'promotion_enabled' => '1',
            'promotion_starts_at' => '2026-11-08T10:00',
            'promotion_ends_at' => '2026-11-10T10:00',
        ])->assertRedirect(route('admin.products.index'));

        $product = Product::query()->sole();
        $this->assertTrue($product->promotion_enabled);
        $this->assertSame('2026-11-08T10:00', $product->promotion_starts_at->format('Y-m-d\\TH:i'));
        $this->assertSame('2026-11-10T10:00', $product->promotion_ends_at->format('Y-m-d\\TH:i'));
        $this->assertSame('45.00', $product->effectivePrice($product->promotion_starts_at));
        $this->assertSame('50.00', $product->effectivePrice($product->promotion_ends_at));
    }

    public function test_many_automatic_skus_are_unique_and_database_index_remains_authoritative(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $service = app(ProductService::class);
        $skus = [];

        for ($index = 1; $index <= 20; $index++) {
            $product = $service->create([
                'category_id' => $category->id,
                'name' => 'Produto '.$index,
                'price' => '10,00',
            ]);
            $skus[] = $product->sku;
        }

        $this->assertCount(20, array_unique($skus));
        $this->assertSame(20, Product::query()->count());
        $this->assertSame(20, DB::table('products')->distinct()->count('sku'));
    }

    public function test_generated_sku_uses_a_unique_suffix_when_a_manual_sku_reserves_the_sequence_value(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        Product::factory()->create([
            'category_id' => $category->id,
            'sku' => 'PRD-000002',
        ]);

        $generated = app(ProductService::class)->create([
            'category_id' => $category->id,
            'name' => 'Produto com colisão',
            'price' => '10,00',
        ]);

        $this->assertSame('PRD-000002-2', $generated->sku);
        $this->assertSame(2, Product::query()->distinct('sku')->count('sku'));
    }

    public function test_authorized_user_can_create_and_update_a_product_using_brazilian_decimal_input(): void
    {
        $admin = $this->catalogManager();
        $category = Category::factory()->create(['is_active' => true]);
        $media = $this->media('catalog/camiseta.png');

        $this->actingAs($admin)->post('/admin/produtos', [
            'category_id' => $category->id,
            'main_media_id' => $media->id,
            'name' => 'Camiseta Básica', 'sku' => 'cam-001',
            'price' => '59,90', 'promotional_price' => '49,90',
            'is_active' => '1', 'is_featured' => '1',
        ])->assertRedirect(route('admin.products.index'));

        $product = Product::query()->sole();
        $this->assertSame('CAM-001', $product->sku);
        $this->assertSame('59.90', $product->price);
        $this->assertSame('49.90', $product->promotional_price);
        $this->assertSame($media->id, $product->main_media_id);
        $this->assertTrue($product->is_active);
        $this->assertTrue($product->is_featured);

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'category_id' => $category->id, 'main_media_id' => '',
            'name' => 'Camiseta Essencial', 'slug' => '', 'sku' => 'CAM-001',
            'price' => '60.00', 'promotional_price' => '',
            'is_active' => '1', 'is_featured' => '0',
        ])->assertRedirect(route('admin.products.index'));

        $product->refresh();
        $this->assertNull($product->main_media_id);
        $this->assertSame('60.00', $product->price);
        $this->assertNull($product->promotional_price);
        $this->assertFalse($product->is_featured);
        $this->assertTrue(Storage::disk(MediaService::DISK)->exists($media->path));
    }

    public function test_invalid_prices_categories_skus_and_slugs_are_rejected(): void
    {
        $admin = $this->catalogManager();
        $category = Category::factory()->create(['is_active' => true]);
        $this->actingAs($admin);
        $base = [
            'category_id' => $category->id, 'name' => 'Camiseta', 'sku' => 'CAM-001',
            'slug' => 'camiseta', 'price' => '59,90', 'is_active' => '1',
        ];

        $this->post('/admin/produtos', [...$base, 'price' => '-1'])->assertSessionHasErrors('price');
        $this->post('/admin/produtos', [...$base, 'promotional_price' => '59,90'])->assertSessionHasErrors('promotional_price');
        $this->post('/admin/produtos', [...$base, 'category_id' => 99999])->assertSessionHasErrors('category_id');

        Product::factory()->create([
            'category_id' => $category->id, 'sku' => 'CAM-001', 'slug' => 'camiseta',
        ]);
        $this->post('/admin/produtos', $base)->assertSessionHasErrors(['sku', 'slug']);
        $this->assertSame(1, Product::query()->count());
    }

    public function test_product_cannot_be_published_in_an_inactive_category(): void
    {
        $admin = $this->catalogManager();
        $category = Category::factory()->create(['is_active' => false]);
        $this->actingAs($admin)->post('/admin/produtos', [
            'category_id' => $category->id, 'name' => 'Produto', 'sku' => 'PR-1',
            'price' => '10,00', 'is_active' => '1',
        ])->assertSessionHasErrors('category_id');
    }

    public function test_image_must_be_an_existing_public_library_file(): void
    {
        $admin = $this->catalogManager();
        $category = Category::factory()->create(['is_active' => true]);
        $media = $this->media('catalog/inexistente.png', storeFile: false);
        $this->actingAs($admin)->post('/admin/produtos', [
            'category_id' => $category->id, 'main_media_id' => $media->id,
            'name' => 'Produto', 'sku' => 'PR-2', 'price' => '10,00',
        ])->assertSessionHasErrors('main_media_id');
    }

    public function test_soft_deleting_a_product_keeps_its_media_file(): void
    {
        $admin = $this->catalogManager();
        $category = Category::factory()->create();
        $media = $this->media('catalog/produto.png');
        $product = Product::factory()->create(['category_id' => $category->id, 'main_media_id' => $media->id]);

        $this->actingAs($admin)->delete(route('admin.products.destroy', $product))->assertRedirect();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertTrue(Storage::disk(MediaService::DISK)->exists($media->path));
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }

    public function test_product_image_is_registered_as_a_media_consumer(): void
    {
        $category = Category::factory()->create();
        $media = $this->media('catalog/protegida.png');
        Product::factory()->create(['category_id' => $category->id, 'main_media_id' => $media->id]);

        try {
            app(MediaService::class)->delete($media);
            $this->fail('A imagem associada a um produto deveria estar protegida.');
        } catch (MediaInUseException $exception) {
            $this->assertContains('Imagem de produto', $exception->usages);
        }
        $this->assertTrue(Storage::disk(MediaService::DISK)->exists($media->path));
    }

    public function test_catalog_permission_cannot_be_granted_by_a_request_field(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['is_active' => true]);
        $this->actingAs($user)->post('/admin/produtos', [
            'can_manage_catalog' => '1', 'category_id' => $category->id,
            'name' => 'Produto', 'sku' => 'PR-3', 'price' => '10,00',
        ])->assertForbidden();
        $this->assertFalse($user->fresh()->canManageCatalog());
    }

    private function catalogManager(): User
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['can_manage_catalog' => true]);

        return $user->fresh();
    }

    private function media(string $path, bool $storeFile = true): Media
    {
        if ($storeFile) {
            Storage::disk(MediaService::DISK)->put($path, 'png-image-test');
        }

        return Media::factory()->create([
            'disk' => MediaService::DISK, 'path' => $path, 'mime_type' => 'image/png',
        ]);
    }
}
