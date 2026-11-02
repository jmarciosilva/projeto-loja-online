<?php

namespace Tests\Feature;

use App\Enums\MenuItemType;
use App\Models\Category;
use App\Models\Media;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Product;
use App\Services\MediaService;
use App\Services\ProductService;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogStorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(MediaService::DISK);
    }

    public function test_public_catalog_category_and_detail_only_show_publishable_products(): void
    {
        $active = Category::factory()->create(['name' => 'Camisetas', 'slug' => 'camisetas', 'is_active' => true]);
        $inactive = Category::factory()->create(['slug' => 'inativa', 'is_active' => false]);
        $visible = $this->product($active, ['name' => 'Camiseta Básica', 'slug' => 'camiseta-basica', 'sku' => 'CAM-1', 'is_active' => true]);
        $this->product($active, ['name' => 'Produto inativo', 'slug' => 'produto-inativo', 'sku' => 'CAM-2', 'is_active' => false]);
        $this->product($inactive, ['name' => 'Categoria oculta', 'slug' => 'categoria-oculta', 'sku' => 'CAM-3', 'is_active' => true]);
        $deleted = $this->product($active, ['name' => 'Produto excluído', 'slug' => 'produto-excluido', 'sku' => 'CAM-4', 'is_active' => true]);
        $deleted->delete();

        $this->get('/produtos')->assertOk()->assertSee('Camiseta Básica')
            ->assertDontSee('Produto inativo')->assertDontSee('Categoria oculta')->assertDontSee('Produto excluído');
        $this->get('/categorias/camisetas')->assertOk()->assertSee('Camiseta Básica');
        $this->get('/categorias/inativa')->assertNotFound();
        $this->get('/produtos/camiseta-basica')->assertOk()->assertSee('Camiseta Básica');
        $this->get('/produtos/produto-inativo')->assertNotFound();
        $this->assertSame($visible->id, Product::query()->where('slug', 'camiseta-basica')->value('id'));
    }

    public function test_catalog_filter_pagination_and_prices_are_rendered(): void
    {
        $one = Category::factory()->create(['name' => 'Camisetas', 'slug' => 'camisetas', 'is_active' => true]);
        $two = Category::factory()->create(['name' => 'Acessórios', 'slug' => 'acessorios', 'is_active' => true]);
        $featured = $this->product($one, [
            'name' => 'Camiseta de algodão', 'slug' => 'camiseta-algodao', 'sku' => 'CAM-10',
            'is_active' => true, 'price' => '59.90', 'promotional_price' => '49.90',
            'promotion_enabled' => true,
        ]);
        $this->product($two, ['name' => 'Bolsa', 'slug' => 'bolsa', 'sku' => 'ACC-1', 'is_active' => true]);

        $this->get('/produtos?categoria=camisetas')->assertOk()
            ->assertSee('Camiseta de algodão')->assertDontSee('Bolsa')
            ->assertSee('R$ 59,90')->assertSee('R$ 49,90')
            ->assertSee(route('catalog.show', $featured->slug), false);
        $this->get('/produtos?categoria=nao-existe')->assertNotFound();

        $pageCategory = Category::factory()->create(['is_active' => true]);
        for ($index = 1; $index <= 13; $index++) {
            $this->product($pageCategory, [
                'name' => "Peça {$index}", 'slug' => "peca-{$index}", 'sku' => "P-{$index}", 'is_active' => true,
            ]);
        }
        $firstPage = $this->get('/produtos?categoria='.$pageCategory->slug)->assertOk();
        $firstPage->assertSee('Peça 1')->assertDontSee('Peça 13');
        $this->get('/produtos?categoria='.$pageCategory->slug.'&page=2')->assertOk()->assertSee('Peça 13');
    }

    public function test_home_shows_featured_products_and_handles_an_empty_catalog(): void
    {
        $this->get('/')->assertOk()->assertSee('Produtos em destaque')
            ->assertSee('Novidades da loja aparecerão aqui em breve.');

        $category = Category::factory()->create(['is_active' => true]);
        $featured = $this->product($category, [
            'name' => 'Destaque da loja', 'slug' => 'destaque-da-loja', 'sku' => 'D-1',
            'is_active' => true, 'is_featured' => true, 'price' => '59.90', 'promotional_price' => null,
        ]);
        $this->product($category, ['name' => 'Oculto não destaque', 'slug' => 'oculto', 'sku' => 'D-2', 'is_active' => true]);
        for ($index = 2; $index <= 5; $index++) {
            $this->product($category, [
                'name' => "Produto destaque {$index}", 'slug' => "destaque-{$index}", 'sku' => "DF-{$index}",
                'is_active' => true, 'is_featured' => true,
            ]);
        }

        $home = (string) $this->get('/')->assertOk()->assertSee('Destaque da loja')->assertSee('R$ 59,90')
            ->assertDontSee('Oculto não destaque')->getContent();
        $this->assertStringNotContainsString('Produto destaque 5', $home);
        $this->assertStringContainsString(route('catalog.show', $featured->slug), $home);
    }

    public function test_product_images_use_the_media_library_and_missing_images_use_a_placeholder(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $media = Media::factory()->create(['disk' => MediaService::DISK, 'path' => 'catalog/vestido.png', 'mime_type' => 'image/png']);
        Storage::disk(MediaService::DISK)->put($media->path, 'image-bytes');
        $product = $this->product($category, [
            'name' => 'Vestido', 'slug' => 'vestido', 'sku' => 'V-1', 'is_active' => true,
            'main_media_id' => $media->id,
        ]);
        $withoutImage = $this->product($category, [
            'name' => 'Sem imagem', 'slug' => 'sem-imagem', 'sku' => 'V-2', 'is_active' => true,
        ]);

        $this->get('/produtos')->assertOk()
            ->assertSee('src="'.e(app(MediaService::class)->url($media)).'"', false)
            ->assertSee('Imagem em breve');
        $this->get('/produtos/'.$product->slug)->assertOk()->assertSee('Vestido');
        $this->get('/produtos/'.$withoutImage->slug)->assertOk()->assertSee('Imagem em breve');
    }

    public function test_catalog_query_eager_loads_category_and_media_for_a_page_of_products(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        for ($index = 1; $index <= 12; $index++) {
            $media = Media::factory()->create();
            $this->product($category, [
                'name' => "Produto {$index}", 'slug' => "produto-{$index}", 'sku' => "QUERY-{$index}",
                'is_active' => true, 'main_media_id' => $media->id,
            ]);
        }

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_starts_with(mb_strtolower(ltrim($query->sql)), 'select')) {
                $queries[] = mb_strtolower($query->sql);
            }
        });
        $products = app(ProductService::class)->paginatePublic();

        $this->assertCount(12, $products);
        $this->assertCount(1, array_filter($queries, fn (string $sql): bool => str_contains($sql, 'where "categories"."id" in')));
        $this->assertCount(1, array_filter($queries, fn (string $sql): bool => str_contains($sql, 'where "media"."id" in')));
    }

    public function test_catalog_and_category_content_are_escaped_and_products_link_to_the_existing_main_menu(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $menu = Menu::factory()->create(['code' => 'main', 'is_active' => true]);
        MenuItem::factory()->create([
            'menu_id' => $menu->id, 'label' => 'Produtos', 'type' => MenuItemType::Url,
            'url' => '/produtos', 'sort_order' => 1, 'is_active' => true,
        ]);
        $this->product($category, [
            'name' => '<script>alert("x")</script>', 'slug' => 'item-script', 'sku' => 'SEC-1',
            'description' => '<img src=x onerror=alert(1)>', 'is_active' => true,
        ]);

        $home = (string) $this->get('/')->assertOk()->getContent();
        preg_match('#<nav[^>]*aria-label="Navegação principal"[^>]*>.*?</nav>#s', $home, $navigation);
        $this->assertSame(1, substr_count($navigation[0] ?? '', 'href="/produtos"'));
        $detail = (string) $this->get('/produtos/item-script')->assertOk()->getContent();
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $detail);
        $this->assertStringNotContainsString('onerror', $detail);
        $this->assertStringNotContainsString('<img src="x"', $detail);
        $this->assertStringNotContainsString('<script>alert("x")</script>', $detail);
    }

    public function test_home_catalog_and_detail_share_scheduled_price_and_never_publish_cost_data(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $start = Carbon::parse('2026-11-08 10:00:00', config('app.timezone'));
        $end = $start->copy()->addDay();
        $product = $this->product($category, [
            'name' => 'Promoção programada', 'slug' => 'promocao-programada', 'sku' => 'PROMO-1',
            'is_active' => true, 'is_featured' => true, 'price' => '50.00',
            'cost_price' => '30.00', 'target_margin' => '40.00',
            'promotional_price' => '45.00', 'promotion_enabled' => true,
            'promotion_starts_at' => $start, 'promotion_ends_at' => $end,
        ]);

        try {
            Carbon::setTestNow($start);
            foreach (['/', '/produtos', '/produtos/'.$product->slug] as $url) {
                $html = (string) $this->get($url)->assertOk()->getContent();
                $this->assertStringContainsString('R$ 45,00', $html);
                $this->assertStringNotContainsString('30.00', $html);
                $this->assertStringNotContainsString('cost_price', $html);
                $this->assertStringNotContainsString('target_margin', $html);
            }

            Carbon::setTestNow($end);
            foreach (['/', '/produtos', '/produtos/'.$product->slug] as $url) {
                $html = (string) $this->get($url)->assertOk()->getContent();
                $this->assertStringContainsString('R$ 50,00', $html);
                $this->assertStringNotContainsString('R$ 45,00', $html);
            }
            $this->assertSame('45.00', $product->fresh()->promotional_price);
            $this->assertTrue($product->fresh()->promotion_enabled);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_existing_promotional_price_is_preserved_but_not_activated_by_default(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = $this->product($category, [
            'name' => 'Produto anterior', 'slug' => 'produto-anterior', 'sku' => 'OLD-1',
            'is_active' => true, 'price' => '59.90', 'promotional_price' => '49.90',
        ])->fresh();

        $this->assertFalse($product->promotion_enabled);
        $this->assertSame('49.90', $product->promotional_price);
        $this->assertSame('59.90', $product->effectivePrice());
        $this->get('/produtos/produto-anterior')->assertOk()
            ->assertSee('R$ 59,90')->assertDontSee('R$ 49,90');
    }

    public function test_home_catalog_and_product_detail_emit_mobile_first_layout_and_touch_controls(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $media = Media::factory()->create(['disk' => MediaService::DISK, 'path' => 'responsive/product.png']);
        $product = $this->product($category, [
            'name' => 'Camiseta Básica de Algodão', 'slug' => 'camiseta-responsiva', 'sku' => 'MOB-1',
            'main_media_id' => $media->id, 'is_active' => true, 'is_featured' => true,
        ]);

        $home = (string) $this->get('/')->assertOk()->getContent();
        $catalog = (string) $this->get('/produtos')->assertOk()->getContent();
        $categoryPage = (string) $this->get('/categorias/'.$category->slug)->assertOk()->getContent();
        $detail = (string) $this->get('/produtos/'.$product->slug)->assertOk()->getContent();

        $this->assertStringContainsString('grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4', $home);
        $this->assertStringContainsString('grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4', $catalog);
        $this->assertStringContainsString('name="categoria"', $categoryPage);
        $this->assertStringContainsString('md:grid-cols-2', $detail);
        $this->assertStringContainsString('aspect-square w-full', $detail);
        $this->assertStringContainsString('max-h-[calc(100dvh-1rem)]', $detail);
        $this->assertStringContainsString('touch-pan-y', $detail);
        $this->assertStringContainsString("document.body.style.overflow = 'hidden'", $detail);
        $this->assertStringContainsString(":aria-label=\"mobileMenuOpen ? 'Fechar menu' : 'Abrir menu'\"", $home);
    }

    private function product(Category $category, array $attributes = []): Product
    {
        return Product::factory()->create(['category_id' => $category->id, ...$attributes]);
    }
}
