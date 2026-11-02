<?php

namespace Tests\Feature;

use App\Enums\MenuItemType;
use App\Enums\PageStatus;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Services\MenuService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MenuStorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_or_inactive_menu_has_no_public_tree(): void
    {
        $service = app(MenuService::class);

        $this->assertNull($service->publicTree('main'));

        Menu::factory()->create(['code' => 'main', 'is_active' => false]);

        $this->assertNull($service->publicTree('main'));
    }

    public function test_only_public_items_and_pages_are_exposed_and_hidden_ancestors_hide_descendants(): void
    {
        $menu = $this->menu();
        $published = $this->page('pagina-publicada', PageStatus::Published);
        $draft = $this->page('pagina-rascunho', PageStatus::Draft);
        $deleted = $this->page('pagina-excluida', PageStatus::Published);
        $deleted->delete();

        $this->item($menu, [
            'label' => 'Página publicada',
            'type' => MenuItemType::Page,
            'page_id' => $published->id,
            'url' => null,
        ]);
        $draftParent = $this->item($menu, [
            'label' => 'Rascunho',
            'type' => MenuItemType::Page,
            'page_id' => $draft->id,
            'url' => null,
        ]);
        $this->item($menu, [
            'label' => 'Filho do rascunho',
            'parent_id' => $draftParent->id,
            'url' => '/filho-oculto',
        ]);
        $this->item($menu, [
            'label' => 'Página excluída',
            'type' => MenuItemType::Page,
            'page_id' => $deleted->id,
            'url' => null,
        ]);
        $this->item($menu, ['label' => 'Item inativo', 'is_active' => false]);

        $tree = app(MenuService::class)->publicTree('main');

        $this->assertSame(['Página publicada'], array_column(array_column($tree, 'item'), 'label'));
        $this->assertSame(route('pages.show', ['slug' => $published->slug]), $tree[0]['url']);
        $this->assertSame([], $tree[0]['children']);
    }

    public function test_tree_order_is_deterministic_and_page_urls_use_the_current_slug(): void
    {
        $menu = $this->menu();
        $page = $this->page('slug-antigo', PageStatus::Published);
        $firstTie = $this->item($menu, ['label' => 'Primeiro empate', 'sort_order' => 2]);
        $secondTie = $this->item($menu, ['label' => 'Segundo empate', 'sort_order' => 2]);
        $last = $this->item($menu, ['label' => 'Último', 'sort_order' => 4]);
        $pageItem = $this->item($menu, [
            'label' => 'Destino interno',
            'type' => MenuItemType::Page,
            'page_id' => $page->id,
            'url' => null,
            'sort_order' => 1,
        ]);
        $page->update(['slug' => 'slug-atual']);

        $tree = app(MenuService::class)->publicTree('main');

        $this->assertSame(
            [$pageItem->id, $firstTie->id, $secondTie->id, $last->id],
            array_map(fn (array $node): int => $node['item']->id, $tree),
        );
        $this->assertSame(route('pages.show', ['slug' => 'slug-atual']), $tree[0]['url']);
    }

    public function test_public_tree_loads_a_deep_page_tree_with_a_fixed_number_of_queries(): void
    {
        $menu = $this->menu();
        $parentId = null;

        for ($i = 0; $i < 12; $i++) {
            $page = $this->page("pagina-{$i}", PageStatus::Published);
            $item = $this->item($menu, [
                'label' => "Nível {$i}",
                'parent_id' => $parentId,
                'type' => MenuItemType::Page,
                'page_id' => $page->id,
                'url' => null,
            ]);
            $parentId = $item->id;
        }

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_starts_with(mb_strtolower(ltrim($query->sql)), 'select')) {
                $queries[] = $query->sql;
            }
        });

        $tree = app(MenuService::class)->publicTree('main');

        $this->assertCount(3, $queries, 'A leitura deve consultar menu, itens e páginas, sem consulta por nível.');
        $this->assertCount(1, $tree);
        $this->assertSame(12, $this->depth($tree[0]));
    }

    public function test_home_renders_public_menu_labels_and_urls_escaped(): void
    {
        $menu = $this->menu();
        $this->item($menu, [
            'label' => '<script>alert("menu")</script>',
            'url' => '/destino?x=1&y=2',
        ]);

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Navegação principal"', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;menu&quot;)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert("menu")</script>', $html);
        $this->assertStringContainsString('href="/destino?x=1&amp;y=2"', $html);
    }

    public function test_mobile_navigation_is_accessible_and_renders_only_visible_nested_cms_links(): void
    {
        $menu = $this->menu();
        $published = $this->page('pagina-mobile-publica', PageStatus::Published);
        $draft = $this->page('pagina-mobile-rascunho', PageStatus::Draft);
        $parent = $this->item($menu, ['label' => 'Coleção', 'url' => '/colecao']);
        $child = $this->item($menu, [
            'label' => 'Sobre nós', 'parent_id' => $parent->id,
            'type' => MenuItemType::Page, 'page_id' => $published->id, 'url' => null,
        ]);
        $this->item($menu, [
            'label' => 'Rascunho oculto', 'parent_id' => $parent->id,
            'type' => MenuItemType::Page, 'page_id' => $draft->id, 'url' => null,
        ]);
        $grandchild = $this->item($menu, ['label' => 'Contato', 'parent_id' => $child->id, 'url' => '/contato']);

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('aria-controls="mobile-navigation"', $html);
        $this->assertStringContainsString(':aria-expanded="mobileMenuOpen.toString()"', $html);
        $this->assertStringContainsString('aria-label="Navegação móvel"', $html);
        $this->assertStringContainsString('id="menu-submenu-'.$parent->id.'"', $html);
        $this->assertStringContainsString('id="menu-submenu-'.$child->id.'"', $html);
        $this->assertStringContainsString('href="'.route('pages.show', $published->slug).'"', $html);
        $this->assertStringContainsString('href="/contato"', $html);
        $this->assertStringNotContainsString('Rascunho oculto', $html);
        $this->assertStringNotContainsString(route('pages.show', $draft->slug), $html);
        $this->assertStringContainsString('mobileMenuOpen = false', $html);
        $this->assertSame($grandchild->url, '/contato');

        $this->get(route('pages.show', $published->slug))->assertOk()->assertSee('aria-current="page"', false);
    }

    private function menu(): Menu
    {
        return Menu::factory()->create(['code' => 'main', 'is_active' => true]);
    }

    private function page(string $slug, PageStatus $status): Page
    {
        return Page::create([
            'title' => $slug,
            'slug' => $slug,
            'content' => 'Conteúdo',
            'status' => $status,
        ]);
    }

    private function item(Menu $menu, array $attributes = []): MenuItem
    {
        return MenuItem::factory()->create([
            'menu_id' => $menu->id,
            'parent_id' => null,
            'type' => MenuItemType::Url,
            'page_id' => null,
            'url' => '/destino',
            'sort_order' => 1,
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function depth(array $node): int
    {
        return 1 + ($node['children'] === [] ? 0 : $this->depth($node['children'][0]));
    }
}
