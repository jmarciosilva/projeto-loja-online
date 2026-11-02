<?php

namespace Tests\Feature;

use App\Enums\BannerPosition;
use App\Enums\MenuItemType;
use App\Enums\PageStatus;
use App\Models\Category;
use App\Models\Media;
use App\Models\Menu;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\BannerService;
use App\Services\MediaService;
use App\Services\MenuService;
use App\Services\PageService;
use App\Services\SiteSettingService;
use Database\Seeders\DemoStorefrontSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoStorefrontSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['env'] = 'local';
        Storage::fake(MediaService::DISK);
    }

    public function test_initial_run_creates_a_complete_demo_storefront_without_catalog_records(): void
    {
        app(DemoStorefrontSeeder::class)->run();

        $this->assertDatabaseCount('pages', 3);
        $this->assertDatabaseCount('menus', 1);
        $this->assertDatabaseCount('menu_items', 4);
        $this->assertDatabaseCount('banners', 1);
        $this->assertDatabaseCount('site_settings', 3);
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('products', 0);

        $menu = Menu::query()->where('code', 'main')->firstOrFail();
        $this->assertSame(4, $menu->items()->count());
        $this->assertSame(3, $menu->items()->where('type', MenuItemType::Page)->count());
        $this->assertDatabaseHas('banners', [
            'media_id' => Media::query()->sole()->id,
            'position' => BannerPosition::Hero->value,
        ]);
        $this->assertDatabaseHas('site_settings', ['key' => 'theme.primary_color', 'value' => '#3F5B4B']);
    }

    public function test_second_run_does_not_duplicate_demo_records(): void
    {
        $this->seed(DemoStorefrontSeeder::class);
        $counts = collect(['pages', 'menus', 'menu_items', 'banners', 'media', 'site_settings'])
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
            ->all();

        $this->seed(DemoStorefrontSeeder::class);

        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
    }

    public function test_existing_pages_menus_banners_and_settings_are_preserved(): void
    {
        $page = app(PageService::class)->create([
            'title' => 'Página personalizada',
            'slug' => 'sobre-a-loja',
            'content' => 'Conteúdo editado pelo proprietário.',
            'status' => PageStatus::Draft,
        ]);
        $menu = app(MenuService::class)->createMenu([
            'name' => 'Menu personalizado', 'code' => 'main', 'is_active' => false,
        ]);
        app(MenuService::class)->createItem($menu, [
            'label' => 'Link próprio', 'type' => MenuItemType::Url, 'url' => '/contato-proprio',
        ]);
        $media = Media::factory()->create();
        $banner = app(BannerService::class)->create([
            'name' => 'Banner comercial',
            'media_id' => $media->id,
            'position' => BannerPosition::Hero,
            'alt_text' => 'Imagem comercial',
            'is_active' => true,
        ]);
        app(SiteSettingService::class)->set('theme.primary_color', 'string', '#123456');
        $category = Category::factory()->create(['name' => 'Categoria comercial']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Produto comercial',
            'price' => '129.90',
            'promotional_price' => '99.90',
        ]);

        $this->seed(DemoStorefrontSeeder::class);

        $this->assertSame('Página personalizada', $page->fresh()->title);
        $this->assertSame('Conteúdo editado pelo proprietário.', $page->fresh()->content);
        $this->assertSame(PageStatus::Draft, $page->fresh()->status);
        $this->assertSame('Menu personalizado', $menu->fresh()->name);
        $this->assertFalse($menu->fresh()->is_active);
        $this->assertDatabaseCount('menu_items', 1);
        $this->assertSame('Banner comercial', $banner->fresh()->name);
        $this->assertSame($media->id, $banner->fresh()->media_id);
        $this->assertSame('#123456', SiteSetting::query()->where('key', 'theme.primary_color')->firstOrFail()->value);
        $this->assertSame('Produto comercial', $product->fresh()->name);
        $this->assertSame('129.90', $product->fresh()->price);
        $this->assertSame('99.90', $product->fresh()->promotional_price);
        $this->assertSame('Categoria comercial', $category->fresh()->name);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('banners', 1);
    }

    public function test_deleted_demo_page_is_not_restored_or_recreated(): void
    {
        $page = app(PageService::class)->create([
            'title' => 'Página removida',
            'slug' => 'sobre-a-loja',
            'content' => 'Conteúdo que foi removido.',
        ]);
        $page->delete();

        $this->seed(DemoStorefrontSeeder::class);

        $this->assertSoftDeleted('pages', ['id' => $page->id]);
        $this->assertDatabaseCount('pages', 3);
    }

    public function test_demo_seeder_is_inert_outside_local_environment(): void
    {
        $this->app['env'] = 'production';

        app(DemoStorefrontSeeder::class)->run();

        $this->assertDatabaseCount('pages', 0);
        $this->assertDatabaseCount('menus', 0);
        $this->assertDatabaseCount('banners', 0);
        $this->assertDatabaseCount('media', 0);
    }
}
