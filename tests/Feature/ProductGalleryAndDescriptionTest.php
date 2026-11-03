<?php

namespace Tests\Feature;

use App\Exceptions\MediaInUseException;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Services\MediaService;
use App\Services\ProductDescription;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class ProductGalleryAndDescriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(MediaService::DISK);
    }

    public function test_rich_description_is_sanitized_and_legacy_markdown_renders_without_mutating_storage(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'description' => "**Camiseta**\n\n- Algodão\n- Confortável",
            'is_active' => true,
        ]);

        $this->get(route('catalog.show', $product->slug))->assertOk()
            ->assertSee('<strong>Camiseta</strong>', false)
            ->assertSee('<ul>', false)
            ->assertSee('Algodão');
        $this->assertSame("**Camiseta**\n\n- Algodão\n- Confortável", $product->fresh()->description);

        $safe = app(ProductDescription::class)->sanitize('<h2 onclick="x">Título</h2><p><strong>Forte</strong><script>alert(1)</script><a href="javascript:alert(1)" onmouseover="x">link</a></p>');
        $this->assertSame('<h2>Título</h2><p><strong>Forte</strong><a>link</a></p>', $safe);
        $this->assertSame('<a href="https://example.com">link</a>', app(ProductDescription::class)->sanitize('<a href="https://example.com" style="color:red">link</a>'));
    }

    public function test_product_gallery_accepts_six_images_preserves_primary_order_and_removes_associations(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $images = collect(range(1, 6))->map(fn ($i) => $this->media("gallery/{$i}.jpg"));
        $ids = $images->pluck('id')->all();

        $product = app(ProductService::class)->create([
            'category_id' => $category->id, 'name' => 'Produto galeria', 'price' => '50.00',
            'main_media_id' => $ids[2], 'gallery_media_ids' => $ids,
        ]);

        $this->assertSame(6, $product->images()->count());
        $this->assertSame($ids[2], $product->main_media_id);
        $this->assertSame($ids[0], (int) $product->images()->orderByPivot('sort_order')->value('media.id'));
        $this->assertSame($ids, $product->images()->orderByPivot('sort_order')->pluck('media.id')->map(fn ($id) => (int) $id)->all());

        app(ProductService::class)->update($product, [
            'category_id' => $category->id, 'name' => $product->name, 'price' => '50.00',
            'main_media_id' => $ids[1], 'gallery_media_ids' => [$ids[1], $ids[0]],
        ]);
        $this->assertSame([$ids[1], $ids[0]], $product->images()->orderByPivot('sort_order')->pluck('media.id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame($ids[1], $product->main_media_id);
    }

    public function test_gallery_rejects_seventh_duplicate_and_unavailable_media(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $images = collect(range(1, 7))->map(fn ($i) => $this->media("gallery/{$i}.jpg"));
        $create = fn (array $ids) => app(ProductService::class)->create([
            'category_id' => $category->id, 'name' => 'Gallery '.uniqid(), 'price' => '20',
            'gallery_media_ids' => $ids,
        ]);

        try {
            $create($images->pluck('id')->all());
            $this->fail('A sétima imagem deveria ser rejeitada.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('seis', $exception->getMessage());
        }
        $this->expectException(InvalidArgumentException::class);
        $create([$images[0]->id, $images[0]->id]);
    }

    public function test_formatted_description_is_persisted_and_existing_main_image_is_imported_when_editing(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $image = $this->media('legacy/main.jpg');
        $product = Product::factory()->create([
            'category_id' => $category->id, 'main_media_id' => $image->id,
            'description' => '**texto antigo**', 'is_active' => true,
        ]);

        app(ProductService::class)->update($product, [
            'category_id' => $category->id, 'name' => $product->name, 'price' => '59.90',
            'description' => '<h2 onclick="bad()">Título</h2><p><strong>Negrito</strong></p><script>alert(1)</script>',
        ]);

        $this->assertSame('<h2>Título</h2><p><strong>Negrito</strong></p>', $product->fresh()->description);
        $this->assertSame([$image->id], $product->images()->orderByPivot('sort_order')->pluck('media.id')->map(fn ($id) => (int) $id)->all());
        $this->get(route('catalog.show', $product->slug))->assertOk()
            ->assertSee('<h2>Título</h2>', false)->assertSee('<strong>Negrito</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_media_shared_by_products_cannot_be_deleted(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $image = $this->media('shared/photo.jpg');
        foreach (['share-one', 'share-two'] as $name) {
            app(ProductService::class)->create([
                'category_id' => $category->id, 'name' => $name, 'price' => '10',
                'main_media_id' => $image->id, 'gallery_media_ids' => [$image->id],
            ]);
        }

        try {
            app(MediaService::class)->delete($image);
            $this->fail('A mídia compartilhada deve permanecer protegida.');
        } catch (MediaInUseException $exception) {
            $this->assertContains('Imagem de produto', $exception->usages);
        }
        $this->assertTrue(Storage::disk(MediaService::DISK)->exists($image->path));
    }

    public function test_public_detail_displays_gallery_thumbnails_zoom_navigation_and_primary_image(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $images = collect(range(1, 2))->map(fn ($i) => $this->media("gallery/{$i}.jpg"));
        $product = app(ProductService::class)->create([
            'category_id' => $category->id, 'name' => 'Produto visual', 'price' => '20',
            'main_media_id' => $images[1]->id, 'gallery_media_ids' => $images->pluck('id')->all(), 'is_active' => true,
        ]);

        $this->get(route('catalog.show', $product->slug))->assertOk()
            ->assertSee('data-gallery-thumb="0"', false)
            ->assertSee('data-gallery-thumb="1"', false)
            ->assertSee('data-zoom-step="1"', false)
            ->assertSee('data-gallery-step="-1"', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('product-zoom-dialog', false)
            ->assertSee('max-h-[calc(100dvh-1rem)]', false)
            ->assertSee('touch-pan-y', false)
            ->assertSee('alt="Produto visual — imagem 2"', false)
            ->assertSee('width="1200" height="800"', false);
    }

    public function test_detail_page_uses_product_alt_text_and_never_exposes_media_filename(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        Storage::disk(MediaService::DISK)->put('uploads/product-photo.jpg', 'fake image data');
        $image = Media::factory()->create([
            'path' => 'uploads/product-photo.jpg',
            'original_name' => 'private-original-name.jpg',
        ]);
        $product = app(ProductService::class)->create([
            'category_id' => $category->id, 'name' => 'Peça artesanal', 'price' => '42.50',
            'main_media_id' => $image->id, 'gallery_media_ids' => [$image->id], 'is_active' => true,
        ]);

        $this->get(route('catalog.show', $product->slug))->assertOk()
            ->assertSee('alt="Peça artesanal — imagem 1"', false)
            ->assertDontSee('private-original-name.jpg');
    }

    private function media(string $path): Media
    {
        Storage::disk(MediaService::DISK)->put($path, 'fake image data');

        return Media::factory()->create(['path' => $path]);
    }
}
