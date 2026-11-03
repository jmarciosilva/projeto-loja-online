<?php

namespace Tests\Feature;

use App\Enums\MenuItemType;
use App\Enums\PageStatus;
use App\Models\Category;
use App\Models\Media;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use App\Services\MediaService;
use App\Services\SiteSettingService;
use App\Services\VisualIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorefrontBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake(MediaService::DISK);
    }

    public function test_home_uses_the_brand_defaults_and_real_public_catalog_data(): void
    {
        $category = Category::factory()->create([
            'name' => 'Lãs e linhas', 'slug' => 'las-e-linhas', 'is_active' => true,
        ]);
        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Novelo de lã', 'slug' => 'novelo-de-la', 'sku' => 'LA-1',
            'is_active' => true, 'is_featured' => true, 'price' => '25.00',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>UAU NetShopping</title>', false)
            ->assertSee('Variedade que surpreende!')
            ->assertSee('Lãs e linhas')
            ->assertSee('Novelo de lã')
            ->assertSee('R$ 25,00')
            ->assertSee(route('catalog.index'), false)
            ->assertDontSee('Adicionar ao carrinho')
            ->assertDontSee('Desconto exclusivo');
    }

    public function test_home_uses_configured_brand_name_and_slogan_without_persisting_defaults(): void
    {
        app(SiteSettingService::class)->setMany([
            'site.name' => ['type' => 'string', 'value' => 'UAU Variedades'],
            'site.slogan' => ['type' => 'string', 'value' => 'Ideias para todo dia'],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>UAU Variedades</title>', false)
            ->assertSee('UAU Variedades — Ideias para todo dia')
            ->assertSee('text-base font-bold leading-snug">Ideias para todo dia</span>', false)
            ->assertSee('content="UAU Variedades — Ideias para todo dia"', false);
    }

    public function test_configured_cms_logo_takes_precedence_over_the_static_fallback(): void
    {
        $logo = Media::factory()->create(['disk' => MediaService::DISK]);
        app(VisualIdentityService::class)->save($logo->id, null);

        $this->get('/')
            ->assertOk()
            ->assertSee('src="'.e(app(MediaService::class)->url($logo)).'"', false)
            ->assertDontSee('src="'.e(asset('images/uau-netshopping-logo-header.webp')).'"', false);
    }

    public function test_footer_does_not_invent_contact_or_institutional_claims(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<footer class="storefront-footer theme-bg-primary', $html);
        $this->assertStringNotContainsString('Institucional', $html);
        $this->assertStringContainsString('UAU NetShopping — início', $html);
        $this->assertStringNotContainsString('mailto:', $html);
        $this->assertStringNotContainsString('Política de privacidade', $html);
        $this->assertStringNotContainsString('Entrega grátis', $html);
    }

    public function test_footer_uses_only_existing_public_navigation_and_configured_contact(): void
    {
        app(SiteSettingService::class)->setMany([
            'site.support_email' => ['type' => 'string', 'value' => 'ajuda@example.test'],
            'site.phone' => ['type' => 'string', 'value' => '(11) 3333-4444'],
        ]);
        $publishedPage = Page::query()->create([
            'title' => 'Sobre a loja', 'slug' => 'sobre-a-loja-footer', 'content' => '<p>Sobre</p>',
            'status' => PageStatus::Published,
        ]);
        $draftPage = Page::query()->create([
            'title' => 'Política em revisão', 'slug' => 'politica-em-revisao-footer', 'content' => '<p>Rascunho</p>',
            'status' => PageStatus::Draft,
        ]);
        $footer = Menu::factory()->create(['code' => 'footer', 'is_active' => true]);
        MenuItem::factory()->create([
            'menu_id' => $footer->id, 'label' => 'Sobre a loja', 'type' => MenuItemType::Page,
            'page_id' => $publishedPage->id, 'url' => null, 'sort_order' => 1, 'is_active' => true,
        ]);
        MenuItem::factory()->create([
            'menu_id' => $footer->id, 'label' => 'Política em revisão', 'type' => MenuItemType::Page,
            'page_id' => $draftPage->id, 'url' => null, 'sort_order' => 2, 'is_active' => true,
        ]);
        $category = Category::factory()->create(['name' => 'Livros Usados', 'is_active' => true]);

        $this->get('/')->assertOk()
            ->assertSee('Institucional')
            ->assertSee('Sobre a loja')
            ->assertSee('href="'.route('pages.show', $publishedPage->slug).'"', false)
            ->assertDontSee('Política em revisão')
            ->assertSee('Atendimento')
            ->assertSee('mailto:ajuda@example.test', false)
            ->assertSee('href="tel:1133334444"', false)
            ->assertSee('Explore a loja')
            ->assertSee('Todos os produtos')
            ->assertSee('Livros Usados')
            ->assertDontSee('https://wa.me/')
            ->assertDontSee('WhatsApp');
    }

    public function test_product_detail_offers_only_configured_contact_channels(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);
        app(SiteSettingService::class)->setMany([
            'site.support_email' => ['type' => 'string', 'value' => 'loja@example.test'],
        ]);

        $this->get(route('catalog.show', $product->slug))->assertOk()
            ->assertSee('Dúvidas sobre este produto?')
            ->assertSee('mailto:loja@example.test', false)
            ->assertDontSee('tel:');
    }

    public function test_product_detail_does_not_show_contact_action_without_configured_channels(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $this->get(route('catalog.show', $product->slug))->assertOk()
            ->assertDontSee('Dúvidas sobre este produto?')
            ->assertDontSee('mailto:');
    }

    public function test_back_to_top_is_public_and_accessibly_named(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('x-data="backToTop"', false)
            ->assertSee('aria-label="Voltar ao topo"', false)
            ->assertSee('fixed z-50', false)
            ->assertSee('env(safe-area-inset-bottom)', false);

        $this->actingAs(User::factory()->create())->get('/admin')->assertOk()->assertDontSee('backToTop');
    }
}
