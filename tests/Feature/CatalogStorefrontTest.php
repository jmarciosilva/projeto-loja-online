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
        $this->get('/')->assertOk()->assertSee('Novidades no catálogo')
            ->assertSee('Produtos publicados aparecerão aqui quando estiverem disponíveis no catálogo.')
            ->assertSee('As categorias da loja aparecerão aqui em breve.');

        $category = Category::factory()->create(['is_active' => true]);
        $featured = $this->product($category, [
            'name' => 'Destaque da loja', 'slug' => 'destaque-da-loja', 'sku' => 'D-1',
            'is_active' => true, 'is_featured' => true, 'price' => '59.90', 'promotional_price' => null,
        ]);
        $this->product($category, ['name' => 'Oculto não destaque', 'slug' => 'oculto', 'sku' => 'D-2', 'is_active' => true]);
        for ($index = 2; $index <= 6; $index++) {
            $this->product($category, [
                'name' => "Produto destaque {$index}", 'slug' => "destaque-{$index}", 'sku' => "DF-{$index}",
                'is_active' => true, 'is_featured' => true,
            ]);
        }

        $home = (string) $this->get('/')->assertOk()->assertSee('Destaque da loja')->assertSee('R$ 59,90')
            ->assertDontSee('Oculto não destaque')->getContent();
        $this->assertStringNotContainsString('Produto destaque 6', $home);
        $this->assertStringContainsString(route('catalog.show', $featured->slug), $home);
    }

    public function test_home_completes_two_explicit_highlights_with_recent_public_products_without_duplicates(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $firstFeatured = $this->product($category, [
            'name' => 'Destaque primeiro', 'slug' => 'destaque-primeiro', 'sku' => 'HOME-F1',
            'is_active' => true, 'is_featured' => true, 'sort_order' => 1,
        ]);
        $secondFeatured = $this->product($category, [
            'name' => 'Destaque segundo', 'slug' => 'destaque-segundo', 'sku' => 'HOME-F2',
            'is_active' => true, 'is_featured' => true, 'sort_order' => 2,
        ]);
        $recent = collect();
        for ($index = 1; $index <= 5; $index++) {
            $recent->push($this->product($category, [
                'name' => "Recente {$index}", 'slug' => "recente-{$index}", 'sku' => "HOME-R{$index}",
                'is_active' => true, 'created_at' => now()->addSeconds($index),
            ]));
        }
        $this->product($category, [
            'name' => 'Produto não publicado', 'slug' => 'produto-nao-publicado', 'sku' => 'HOME-HIDDEN',
            'is_active' => false,
        ]);
        $archived = $this->product($category, [
            'name' => 'Produto arquivado', 'slug' => 'produto-arquivado-home', 'sku' => 'HOME-ARCHIVED',
            'is_active' => true, 'created_at' => now()->addMinute(),
        ]);
        $archived->delete();

        $selection = app(ProductService::class)->homepageSelection(5);

        $this->assertTrue($selection['has_featured']);
        $this->assertSame(
            [$firstFeatured->id, $secondFeatured->id, $recent[4]->id, $recent[3]->id, $recent[2]->id],
            $selection['products']->modelKeys(),
        );
        $this->assertCount(5, $selection['products']);
        $this->assertSame(9, Product::withTrashed()->count());

        $html = (string) $this->get('/')->assertOk()->getContent();
        foreach (['Destaque primeiro', 'Destaque segundo', 'Recente 5', 'Recente 4', 'Recente 3'] as $name) {
            $this->assertStringContainsString($name, $html);
        }
        $this->assertStringNotContainsString('Recente 2', $html);
        $this->assertStringNotContainsString('Produto não publicado', $html);
        $this->assertStringNotContainsString('Produto arquivado', $html);
        $this->assertSame(2, Product::query()->where('is_featured', true)->count());
    }

    public function test_home_selection_handles_zero_one_and_at_least_five_featured_products(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        for ($index = 1; $index <= 3; $index++) {
            $this->product($category, [
                'name' => "Produto recente {$index}", 'slug' => "produto-recente-{$index}",
                'sku' => "HOME-Z{$index}", 'is_active' => true,
            ]);
        }

        $withoutHighlights = app(ProductService::class)->homepageSelection(5);
        $this->assertFalse($withoutHighlights['has_featured']);
        $this->assertCount(3, $withoutHighlights['products']);

        $one = $this->product($category, [
            'name' => 'Um destaque', 'slug' => 'um-destaque', 'sku' => 'HOME-ONE',
            'is_active' => true, 'is_featured' => true,
        ]);
        $oneHighlight = app(ProductService::class)->homepageSelection(5);
        $this->assertTrue($oneHighlight['has_featured']);
        $this->assertSame($one->id, $oneHighlight['products']->first()->id);
        $this->assertCount(4, $oneHighlight['products']);

        for ($index = 2; $index <= 6; $index++) {
            $this->product($category, [
                'name' => "Destaque {$index}", 'slug' => "destaque-{$index}", 'sku' => "HOME-M{$index}",
                'is_active' => true, 'is_featured' => true, 'sort_order' => $index,
            ]);
        }
        $manyHighlights = app(ProductService::class)->homepageSelection(5);
        $this->assertCount(5, $manyHighlights['products']);
        $this->assertTrue($manyHighlights['has_featured']);
        $this->assertSame(5, $manyHighlights['products']->where('is_featured', true)->count());
    }

    public function test_manual_highlights_are_kept_even_when_sold_out_and_fill_candidates_prefer_confirmed_stock(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $manual = collect();
        for ($index = 1; $index <= 2; $index++) {
            $manual->push($this->product($category, [
                'name' => "Escolha manual {$index}", 'slug' => "manual-priority-{$index}",
                'sku' => "PRIORITY-M{$index}", 'is_active' => true, 'is_featured' => true,
                'sort_order' => $index,
            ]));
        }
        $this->setPublicStock($manual[0], 1);
        $this->setPublicStock($manual[1], 0);

        $available = collect();
        for ($index = 1; $index <= 10; $index++) {
            $product = $this->product($category, [
                'name' => "Com estoque {$index}", 'slug' => "com-estoque-{$index}",
                'sku' => "PRIORITY-A{$index}", 'is_active' => true,
            ]);
            $this->setPublicStock($product, 2);
            $available->push($product);
        }
        $unreported = $this->product($category, [
            'name' => 'Estoque não informado mais recente', 'slug' => 'estoque-nao-informado-recente',
            'sku' => 'PRIORITY-U', 'is_active' => true,
        ]);
        DB::table('products')->where('id', $unreported->id)->update(['created_at' => now()->addDay()]);

        DB::table('products')->whereIn('id', $available->pluck('id'))->update(['created_at' => '2026-01-01 00:00:00']);
        $selection = app(ProductService::class)->homepageSelection(5);

        $selectedIds = $selection['products']->pluck('id')->all();
        $this->assertSame($manual->pluck('id')->all(), array_slice($selectedIds, 0, 2));
        $expectedAvailable = $available->pluck('id')->all();
        rsort($expectedAvailable);
        $this->assertSame(array_slice($expectedAvailable, 0, 3), array_slice($selectedIds, 2));
        $this->assertCount(5, $selection['products']);
        $this->assertCount(5, array_unique($selectedIds));
        $this->assertContains($manual[1]->id, $selectedIds);
        $this->assertNotContains($unreported->id, $selectedIds);
        $this->assertSame(2, Product::query()->where('is_featured', true)->count());
    }

    public function test_five_manual_highlights_are_not_replaced_when_one_is_sold_out(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $manual = collect();
        for ($index = 1; $index <= 5; $index++) {
            $product = $this->product($category, [
                'name' => "Manual completo {$index}", 'slug' => "manual-completo-{$index}",
                'sku' => "PRIORITY-F{$index}", 'is_active' => true, 'is_featured' => true,
                'sort_order' => $index,
            ]);
            $manual->push($product);
        }
        $this->setPublicStock($manual[2], 0);
        $extraAvailable = $this->product($category, [
            'name' => 'Disponível não destacado', 'slug' => 'disponivel-nao-destacado',
            'sku' => 'PRIORITY-FX', 'is_active' => true,
        ]);
        $this->setPublicStock($extraAvailable, 4);

        $selection = app(ProductService::class)->homepageSelection(5);

        $this->assertSame($manual->pluck('id')->all(), $selection['products']->pluck('id')->all());
        $this->assertNotContains($extraAvailable->id, $selection['products']->pluck('id')->all());
    }

    public function test_auto_highlight_fallback_uses_unavailable_products_only_after_available_candidates(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $manual = collect();
        for ($index = 1; $index <= 2; $index++) {
            $manual->push($this->product($category, [
                'name' => "Destaque manual C{$index}", 'slug' => "manual-c{$index}",
                'sku' => "PRIORITY-CM{$index}", 'is_active' => true, 'is_featured' => true,
                'sort_order' => $index,
            ]));
        }

        $available = collect();
        for ($index = 1; $index <= 2; $index++) {
            $product = $this->product($category, [
                'name' => "Disponível C{$index}", 'slug' => "disponivel-c{$index}",
                'sku' => "PRIORITY-CA{$index}", 'is_active' => true,
            ]);
            $this->setPublicStock($product, 1);
            $available->push($product);
        }
        DB::table('products')->whereIn('id', $available->pluck('id'))->update(['created_at' => '2026-01-01 00:00:00']);

        $secondary = collect();
        for ($index = 1; $index <= 5; $index++) {
            $product = $this->product($category, [
                'name' => "Esgotado C{$index}", 'slug' => "esgotado-c{$index}",
                'sku' => "PRIORITY-CS{$index}", 'is_active' => true,
            ]);
            $this->setPublicStock($product, 0);
            $secondary->push($product);
        }
        $selection = app(ProductService::class)->homepageSelection(5);

        $selectedIds = $selection['products']->pluck('id')->all();
        $this->assertSame($manual->pluck('id')->all(), array_slice($selectedIds, 0, 2));
        $expectedAvailable = $available->pluck('id')->all();
        rsort($expectedAvailable);
        $this->assertSame($expectedAvailable, array_slice($selectedIds, 2, 2));
        $lastCandidate = $selection['products']->last();
        $this->assertContains($lastCandidate->id, $secondary->pluck('id')->all());
        $this->assertCount(5, $selection['products']);
        $this->assertCount(5, array_unique($selectedIds));
    }

    public function test_auto_highlight_order_is_deterministic_when_products_share_a_creation_time(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $timestamp = '2026-01-01 00:00:00';
        $first = $this->product($category, [
            'name' => 'Disponível empatado 1', 'slug' => 'disponivel-empate-1', 'sku' => 'TIE-1', 'is_active' => true,
        ]);
        $second = $this->product($category, [
            'name' => 'Disponível empatado 2', 'slug' => 'disponivel-empate-2', 'sku' => 'TIE-2', 'is_active' => true,
        ]);
        foreach ([$first, $second] as $product) {
            $this->setPublicStock($product, 1);
            DB::table('products')->where('id', $product->id)->update(['created_at' => $timestamp]);
        }

        $selection = app(ProductService::class)->homepageSelection(5);

        $this->assertSame([$second->id, $first->id], $selection['products']->pluck('id')->all());
    }

    public function test_home_selection_returns_fewer_than_five_without_duplicates_when_catalog_is_small(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $manual = $this->product($category, [
            'name' => 'Único destaque', 'slug' => 'unico-destaque-pequeno', 'sku' => 'SMALL-F',
            'is_active' => true, 'is_featured' => true,
        ]);
        $other = $this->product($category, [
            'name' => 'Único complemento', 'slug' => 'unico-complemento-pequeno', 'sku' => 'SMALL-R',
            'is_active' => true,
        ]);

        $selection = app(ProductService::class)->homepageSelection(5);

        $this->assertSame([$manual->id, $other->id], $selection['products']->modelKeys());
        $this->assertSame(2, $selection['products']->count());
    }

    public function test_product_cards_keep_images_contained_titles_clamped_and_real_stock_states(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $available = $this->product($category, [
            'name' => 'Produto com título suficientemente longo para ocupar mais de duas linhas',
            'slug' => 'produto-titulo-longo-card', 'sku' => 'CARD-1', 'is_active' => true,
            'condition' => 'used', 'is_featured' => true,
        ]);
        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('aspect-[4/3] overflow-hidden bg-slate-50', $html);
        $this->assertStringContainsString('object-contain', $html);
        $this->assertStringContainsString('line-clamp-2', $html);
        $this->assertStringContainsString('Usado', $html);
        $this->assertStringContainsString('Disponibilidade não informada', $html);
        $this->assertSame('Produto com título suficientemente longo para ocupar mais de duas linhas', $available->fresh()->name);
    }

    public function test_home_presents_active_category_children_as_discovery_links(): void
    {
        $root = Category::factory()->create([
            'name' => 'Livros Usados', 'slug' => 'livros-usados-descoberta', 'is_active' => true,
        ]);
        $fantasy = Category::factory()->create([
            'name' => 'Fantasia', 'slug' => 'fantasia-descoberta', 'parent_category_id' => $root->id,
            'is_active' => true, 'sort_order' => 2,
        ]);
        Category::factory()->create([
            'name' => 'Arquivada', 'slug' => 'arquivada-descoberta', 'parent_category_id' => $root->id,
            'is_active' => false, 'sort_order' => 1,
        ]);

        $html = (string) $this->get('/')->assertOk()
            ->assertSee('Explore por categoria')
            ->assertSee('Livros Usados')
            ->assertSee('Fantasia')
            ->assertDontSee('Arquivada')
            ->getContent();

        $this->assertStringContainsString(route('categories.show', $fantasy->slug), $html);
    }

    public function test_marketplace_header_has_working_search_and_does_not_simulate_unavailable_customer_features(): void
    {
        $html = (string) $this->get('/')->assertOk()
            ->assertSee('aria-label="Buscar"', false)
            ->assertSee('store-search-mobile')
            ->assertSee('Abrir menu')
            ->assertDontSee('Minha conta')
            ->assertDontSee('Favoritos')
            ->assertDontSee('Pagamento seguro')
            ->assertDontSee('Entrega para todo o Brasil')
            ->getContent();

        $this->assertStringContainsString('action="'.route('catalog.index').'"', $html);
        $this->assertStringContainsString('aria-controls="mobile-navigation"', $html);
    }

    public function test_category_subcategory_navigation_marks_current_category_and_sort_is_separate_from_filters(): void
    {
        $root = Category::factory()->create([
            'name' => 'Livros Usados', 'slug' => 'livros-usados-filtros', 'is_active' => true,
        ]);
        $fantasy = Category::factory()->create([
            'name' => 'Fantasia', 'slug' => 'fantasia-filtros', 'parent_category_id' => $root->id,
            'is_active' => true,
        ]);

        $html = (string) $this->get(route('categories.show', $fantasy->slug).'?condition=used&sort=price_asc')
            ->assertOk()
            ->assertSee('Filtrar produtos')
            ->assertSee('Ordenar:')
            ->assertSee('name="sort" value="price_asc"', false)
            ->assertSee('Fantasia')
            ->getContent();

        $this->assertMatchesRegularExpression('/<a[^>]+aria-current="page"[^>]+>Fantasia<\/a>/', $html);
        $this->assertLessThan(strpos($html, 'Ordenar:'), strpos($html, 'name="condition"'));
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

    public function test_cms_navigation_does_not_repeat_home_or_category_links_in_catalog_shortcuts(): void
    {
        $root = Category::factory()->create(['name' => 'Livros Usados', 'slug' => 'livros-usados-nav', 'is_active' => true]);
        $child = Category::factory()->create([
            'name' => 'Fantasia', 'slug' => 'fantasia-nav', 'parent_category_id' => $root->id, 'is_active' => true,
        ]);
        $menu = Menu::factory()->create(['code' => 'main', 'is_active' => true]);
        MenuItem::factory()->create([
            'menu_id' => $menu->id, 'label' => 'Início', 'type' => MenuItemType::Url,
            'url' => '/', 'sort_order' => 1, 'is_active' => true,
        ]);
        MenuItem::factory()->create([
            'menu_id' => $menu->id, 'label' => 'Fantasia', 'type' => MenuItemType::Url,
            'url' => route('categories.show', $child->slug), 'sort_order' => 2, 'is_active' => true,
        ]);

        $html = (string) $this->get('/')->assertOk()->getContent();
        preg_match('#<nav[^>]*aria-label="Navegação principal"[^>]*>.*?</nav>#s', $html, $desktopMenu);
        preg_match('#<nav[^>]*aria-label="Navegação móvel"[^>]*>.*?</nav>#s', $html, $mobileMenu);
        preg_match('#<nav[^>]*aria-label="Atalhos da loja"[^>]*>.*?</nav>#s', $html, $catalogShortcuts);

        $homeLabel = '>Início</a>';
        $childUrl = 'href="'.route('categories.show', $child->slug).'"';
        $this->assertSame(1, substr_count($desktopMenu[0] ?? '', $homeLabel));
        $this->assertSame(1, substr_count($mobileMenu[0] ?? '', $homeLabel));
        $this->assertSame(1, substr_count($desktopMenu[0] ?? '', $childUrl));
        $this->assertSame(1, substr_count($mobileMenu[0] ?? '', $childUrl));
        $this->assertSame(0, substr_count($catalogShortcuts[0] ?? '', 'Início'));
        $this->assertSame(0, substr_count($catalogShortcuts[0] ?? '', $childUrl));
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

        $this->assertStringContainsString('grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4', $home);
        $this->assertStringContainsString('grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5', $catalog);
        $this->assertStringContainsString('name="condition"', $categoryPage);
        $this->assertStringContainsString('md:grid-cols-2', $detail);
        $this->assertStringContainsString('aspect-square w-full', $detail);
        $this->assertStringContainsString('max-h-[calc(100dvh-1rem)]', $detail);
        $this->assertStringContainsString('touch-pan-y', $detail);
        $this->assertStringContainsString("document.body.style.overflow = 'hidden'", $detail);
        $this->assertStringContainsString(":aria-label=\"mobileMenuOpen ? 'Fechar menu' : 'Abrir menu'\"", $home);
    }

    public function test_catalog_searches_public_name_description_and_sku_and_keeps_archived_products_hidden(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $this->product($category, [
            'name' => 'O Hobbit', 'slug' => 'o-hobbit-busca', 'sku' => 'LIV-HOBBIT',
            'description' => 'Uma aventura de fantasia', 'is_active' => true,
        ]);
        $archived = $this->product($category, [
            'name' => 'O Hobbit arquivado', 'slug' => 'o-hobbit-arquivado', 'sku' => 'LIV-ARQ', 'is_active' => true,
        ]);
        $archived->delete();

        $this->get('/produtos?q=Hobbit')->assertOk()->assertSee('O Hobbit')->assertDontSee('arquivado');
        $this->get('/produtos?q=LIV-HOBBIT')->assertOk()->assertSee('O Hobbit');
        $this->get('/produtos?q=aventura')->assertOk()->assertSee('O Hobbit');
    }

    public function test_catalog_filters_condition_price_and_stock_and_sorts_by_effective_price(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $lower = $this->product($category, [
            'name' => 'Livro usado econômico', 'slug' => 'livro-economico', 'sku' => 'FILTER-1',
            'condition' => 'used', 'is_active' => true, 'price' => '20.00',
        ]);
        $higher = $this->product($category, [
            'name' => 'Livro usado com promoção', 'slug' => 'livro-promocional', 'sku' => 'FILTER-2',
            'condition' => 'used', 'is_active' => true, 'price' => '50.00', 'promotional_price' => '15.00',
            'promotion_enabled' => true,
        ]);
        $this->product($category, [
            'name' => 'Livro novo', 'slug' => 'livro-novo-filtro', 'sku' => 'FILTER-3',
            'condition' => 'new', 'is_active' => true, 'price' => '10.00',
        ]);
        DB::table('product_stocks')->insert([
            ['product_id' => $lower->id, 'quantity_on_hand' => 1, 'quantity_reserved' => 0, 'minimum_quantity' => 0, 'stock_control_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $higher->id, 'quantity_on_hand' => 0, 'quantity_reserved' => 0, 'minimum_quantity' => 0, 'stock_control_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->get('/produtos?condition=used&availability=available&sort=price_asc')->assertOk()
            ->assertSee('Livro usado econômico')->assertDontSee('Livro usado com promoção')->assertDontSee('Livro novo');
        $response = $this->get('/produtos?price_max=16')->assertOk();
        $response->assertSee('Livro usado com promoção');
        $priceFiltered = app(ProductService::class)->paginatePublic(filters: ['price_max' => 16]);
        $this->assertContains('livro-promocional', $priceFiltered->getCollection()->pluck('slug')->all());
        $this->assertNotContains('livro-economico', $priceFiltered->getCollection()->pluck('slug')->all());
        $html = (string) $this->get('/produtos?condition=used&sort=price_asc')->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'Livro usado econômico'), strpos($html, 'Livro usado com promoção'));
    }

    public function test_catalog_rejects_an_invalid_price_range(): void
    {
        $this->get('/produtos?price_min=30&price_max=20')->assertRedirect()
            ->assertSessionHasErrors('price_max');
    }

    private function product(Category $category, array $attributes = []): Product
    {
        return Product::factory()->create(['category_id' => $category->id, ...$attributes]);
    }

    private function setPublicStock(Product $product, int $quantity): void
    {
        DB::table('product_stocks')->insert([
            'product_id' => $product->id,
            'quantity_on_hand' => $quantity,
            'quantity_reserved' => 0,
            'minimum_quantity' => 0,
            'stock_control_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
