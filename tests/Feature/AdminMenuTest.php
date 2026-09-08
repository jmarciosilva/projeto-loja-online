<?php

namespace Tests\Feature;

use App\Enums\MenuItemType;
use App\Enums\PageStatus;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;
use App\Services\MenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Integração da administração de menus — F2.6-B.
 *
 * As invariantes de domínio já são cobertas por `MenuTest`, `MenuServiceTest` e
 * `MenuReorderTest`. Aqui o assunto é a camada HTTP: acesso, validação
 * antecipada, navegação, hierarquia, ordenação e — sobretudo — que ela consome
 * o `MenuService` em vez de reimplementar as suas regras.
 *
 * Dois pontos ganham peso próprio nesta subfase:
 *
 * ```text
 * rota aninhada → um item de outro menu não pode ser tocado por /menus/{menu}
 * campo forjado → sort_order e menu_id no POST não decidem nada
 * ```
 */
class AdminMenuTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/admin/menus';

    // --- Acesso -----------------------------------------------------------

    public function test_guest_cannot_reach_any_administrative_menu_route(): void
    {
        $menu = Menu::factory()->create(['name' => 'Menu principal', 'code' => 'main']);
        $item = MenuItem::factory()->for($menu)->create(['label' => 'Contato', 'sort_order' => 1]);

        $base = self::URI.'/'.$menu->id;
        $nested = $base.'/itens/'.$item->id;

        $rotas = [
            ['get', self::URI],
            ['get', self::URI.'/criar'],
            ['post', self::URI],
            ['get', $base.'/editar'],
            ['put', $base],
            ['delete', $base],
            ['post', $base.'/alternar'],
            ['post', $base.'/itens'],
            ['get', $nested.'/editar'],
            ['put', $nested],
            ['delete', $nested],
            ['post', $nested.'/alternar'],
            ['post', $nested.'/subir'],
            ['post', $nested.'/descer'],
        ];

        foreach ($rotas as [$metodo, $uri]) {
            $this->{$metodo}($uri, ['name' => 'Invadido', 'label' => 'Invadido'])
                ->assertRedirect('/login');
        }

        $this->assertSame(1, Menu::query()->count());
        $this->assertSame(1, MenuItem::query()->count());
        $this->assertSame('Menu principal', $menu->fresh()->name);
        $this->assertSame('Contato', $item->fresh()->label);
    }

    public function test_authenticated_user_can_open_the_listing(): void
    {
        $this->admin()->get(self::URI)->assertOk()->assertSee('Menus');
    }

    public function test_authenticated_user_can_open_the_creation_form(): void
    {
        $this->admin()->get(self::URI.'/criar')->assertOk()->assertSee('Novo menu');
    }

    public function test_authenticated_user_can_open_the_menu_edit_screen(): void
    {
        $menu = Menu::factory()->create(['name' => 'Menu principal', 'code' => 'main']);

        $this->admin()->get(self::URI.'/'.$menu->id.'/editar')
            ->assertOk()
            ->assertSee('Menu principal')
            ->assertSee('main');
    }

    public function test_authenticated_user_can_open_the_item_edit_form(): void
    {
        $menu = Menu::factory()->create();
        $item = MenuItem::factory()->for($menu)->create(['label' => 'Contato', 'sort_order' => 1]);

        $this->admin()->get(self::URI.'/'.$menu->id.'/itens/'.$item->id.'/editar')
            ->assertOk()
            ->assertSee('Editar item')
            ->assertSee('Contato');
    }

    // --- Listagem ---------------------------------------------------------

    public function test_the_listing_shows_name_code_state_and_item_count(): void
    {
        $menu = Menu::factory()->create(['name' => 'Menu principal', 'code' => 'main', 'is_active' => true]);
        MenuItem::factory()->for($menu)->count(3)->create(['sort_order' => 1]);
        Menu::factory()->create(['name' => 'Rodapé', 'code' => 'rodape']);

        $html = $this->admin()->get(self::URI)->assertOk()->getContent();

        $this->assertStringContainsString('Menu principal', $html);
        $this->assertStringContainsString('main', $html);
        $this->assertStringContainsString('Ativo', $html);
        $this->assertStringContainsString('Inativo', $html);
        $this->assertStringContainsString('>3</td>', str_replace(["\n", ' '], ['', ''], $html));
        $this->assertStringContainsString(route('admin.menus.edit', $menu), $html);
    }

    public function test_the_listing_counts_the_items_without_a_query_per_row(): void
    {
        foreach (['um', 'dois', 'tres'] as $code) {
            $menu = Menu::factory()->create(['code' => $code]);
            MenuItem::factory()->for($menu)->count(2)->create(['sort_order' => 1]);
        }

        $queries = $this->queriesOf(fn (): TestResponse => $this->admin()->get(self::URI)->assertOk());

        $this->assertSame(
            1,
            $this->matching($queries, 'menu_items'),
            'A contagem de itens deve sair de uma consulta agregada, não de uma por linha.'
        );
    }

    // --- CRUD de menu -----------------------------------------------------

    public function test_it_creates_a_menu(): void
    {
        $this->admin()
            ->post(self::URI, ['name' => 'Menu principal', 'code' => 'main', 'is_active' => '0'])
            ->assertSessionHasNoErrors();

        $menu = Menu::query()->sole();

        $this->assertSame('Menu principal', $menu->name);
        $this->assertSame('main', $menu->code);
        $this->assertFalse($menu->is_active);
    }

    public function test_a_menu_can_be_created_already_active(): void
    {
        $this->admin()->post(self::URI, ['name' => 'Rodapé', 'code' => 'rodape', 'is_active' => '1']);

        $this->assertTrue(Menu::query()->sole()->is_active);
    }

    public function test_the_menu_name_is_required(): void
    {
        $this->admin()->post(self::URI, ['name' => '', 'code' => 'main'])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Menu::query()->count());
    }

    public function test_the_menu_code_is_required(): void
    {
        $this->admin()->post(self::URI, ['name' => 'Menu principal', 'code' => ''])
            ->assertSessionHasErrors('code');

        $this->assertSame(0, Menu::query()->count());
    }

    public function test_a_code_outside_the_canonical_format_is_rejected_by_the_form(): void
    {
        $this->admin()->post(self::URI, ['name' => 'Menu principal', 'code' => 'Menu Principal'])
            ->assertSessionHasErrors('code');

        $this->assertSame(0, Menu::query()->count());
    }

    public function test_a_duplicated_code_is_rejected_by_the_form(): void
    {
        Menu::factory()->create(['code' => 'main']);

        $this->admin()->post(self::URI, ['name' => 'Outro', 'code' => 'main'])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, Menu::query()->count());
    }

    public function test_it_updates_the_menu_name_and_state(): void
    {
        $menu = Menu::factory()->create(['name' => 'Antigo', 'code' => 'main']);

        $this->admin()
            ->put(self::URI.'/'.$menu->id, ['name' => 'Novo', 'is_active' => '1'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.menus.edit', $menu));

        $menu->refresh();

        $this->assertSame('Novo', $menu->name);
        $this->assertTrue($menu->is_active);
    }

    public function test_a_code_sent_by_the_edit_form_never_changes_the_menu(): void
    {
        $menu = Menu::factory()->create(['name' => 'Antigo', 'code' => 'main']);

        // O `code` não pertence ao contrato de entrada da edição: ele é
        // descartado antes do domínio, e o salvamento continua acontecendo. Se
        // ele chegasse ao `MenuService`, a atualização seria recusada e o nome
        // também não mudaria — é o que este teste protege.
        $this->admin()
            ->put(self::URI.'/'.$menu->id, ['name' => 'Novo', 'code' => 'principal', 'is_active' => '0'])
            ->assertSessionHasNoErrors();

        $menu->refresh();

        $this->assertSame('main', $menu->code);
        $this->assertSame('Novo', $menu->name);
    }

    public function test_the_edit_screen_does_not_offer_a_code_input(): void
    {
        $menu = Menu::factory()->create(['code' => 'main']);

        $html = $this->admin()->get(self::URI.'/'.$menu->id.'/editar')->assertOk()->getContent();

        $this->assertStringNotContainsString('name="code"', $html);
        $this->assertStringContainsString('main', $html);
    }

    public function test_it_toggles_the_menu_state(): void
    {
        $menu = Menu::factory()->create(['is_active' => false]);

        $this->admin()->post(self::URI.'/'.$menu->id.'/alternar')->assertSessionHasNoErrors();
        $this->assertTrue($menu->fresh()->is_active);

        $this->admin()->post(self::URI.'/'.$menu->id.'/alternar');
        $this->assertFalse($menu->fresh()->is_active);
    }

    public function test_it_deletes_an_empty_menu(): void
    {
        $menu = Menu::factory()->create();

        $this->admin()->delete(self::URI.'/'.$menu->id)
            ->assertRedirect(route('admin.menus.index'));

        $this->assertSame(0, Menu::query()->count());
    }

    public function test_deleting_a_menu_removes_its_whole_tree(): void
    {
        $menu = Menu::factory()->create();
        $root = MenuItem::factory()->for($menu)->create(['sort_order' => 1]);
        $child = MenuItem::factory()->for($menu)->create(['parent_id' => $root->id, 'sort_order' => 1]);
        MenuItem::factory()->for($menu)->create(['parent_id' => $child->id, 'sort_order' => 1]);

        $preserved = MenuItem::factory()->create(['sort_order' => 1]);

        $this->admin()->delete(self::URI.'/'.$menu->id)->assertSessionHasNoErrors();

        $this->assertSame(0, MenuItem::query()->where('menu_id', $menu->id)->count());
        $this->assertNotNull($preserved->fresh());
    }

    // --- CRUD de item -----------------------------------------------------

    public function test_it_creates_a_page_item(): void
    {
        $menu = Menu::factory()->create();
        $page = $this->page();

        $this->admin()
            ->post(self::URI.'/'.$menu->id.'/itens', $this->pageItem($page, ['label' => 'Quem somos']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.menus.edit', $menu));

        $item = MenuItem::query()->sole();

        $this->assertSame('Quem somos', $item->label);
        $this->assertSame(MenuItemType::Page, $item->type);
        $this->assertSame($page->id, $item->page_id);
        $this->assertNull($item->url);
        $this->assertSame($menu->id, $item->menu_id);
        $this->assertNull($item->parent_id);
        $this->assertFalse($item->is_active);
    }

    public function test_it_creates_a_url_item(): void
    {
        $menu = Menu::factory()->create();

        $this->admin()
            ->post(self::URI.'/'.$menu->id.'/itens', $this->urlItem(['label' => 'Blog', 'url' => 'https://blog.exemplo.com', 'is_active' => '1']))
            ->assertSessionHasNoErrors();

        $item = MenuItem::query()->sole();

        $this->assertSame(MenuItemType::Url, $item->type);
        $this->assertSame('https://blog.exemplo.com', $item->url);
        $this->assertNull($item->page_id);
        $this->assertTrue($item->is_active);
    }

    public function test_it_updates_an_item(): void
    {
        $menu = Menu::factory()->create();
        $item = MenuItem::factory()->for($menu)->create(['label' => 'Antigo', 'sort_order' => 1]);

        $this->admin()
            ->put(self::URI.'/'.$menu->id.'/itens/'.$item->id, $this->urlItem([
                'label' => 'Novo',
                'url' => '/novo',
                'is_active' => '1',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.menus.edit', $menu));

        $item->refresh();

        $this->assertSame('Novo', $item->label);
        $this->assertSame('/novo', $item->url);
        $this->assertTrue($item->is_active);
        $this->assertSame(1, $item->sort_order);
    }

    public function test_an_item_can_change_its_destination_from_url_to_page(): void
    {
        $menu = Menu::factory()->create();
        $page = $this->page();
        $item = MenuItem::factory()->for($menu)->create(['sort_order' => 1]);

        $this->admin()
            ->put(self::URI.'/'.$menu->id.'/itens/'.$item->id, $this->pageItem($page, ['label' => $item->label]))
            ->assertSessionHasNoErrors();

        $item->refresh();

        $this->assertSame(MenuItemType::Page, $item->type);
        $this->assertSame($page->id, $item->page_id);
        $this->assertNull($item->url);
    }

    public function test_it_toggles_the_item_state(): void
    {
        $menu = Menu::factory()->create();
        $item = MenuItem::factory()->for($menu)->create(['is_active' => false, 'sort_order' => 1]);

        $this->admin()->post(self::URI.'/'.$menu->id.'/itens/'.$item->id.'/alternar')
            ->assertSessionHasNoErrors();

        $this->assertTrue($item->fresh()->is_active);
    }

    public function test_it_deletes_a_leaf_item(): void
    {
        $menu = Menu::factory()->create();
        $item = MenuItem::factory()->for($menu)->create(['sort_order' => 1]);

        $this->admin()->delete(self::URI.'/'.$menu->id.'/itens/'.$item->id)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.menus.edit', $menu));

        $this->assertSame(0, MenuItem::query()->count());
    }

    public function test_deleting_an_item_with_children_is_refused_with_a_readable_message(): void
    {
        $menu = Menu::factory()->create();
        $parent = MenuItem::factory()->for($menu)->create(['sort_order' => 1]);
        MenuItem::factory()->for($menu)->create(['parent_id' => $parent->id, 'sort_order' => 1]);

        $response = $this->admin()->delete(self::URI.'/'.$menu->id.'/itens/'.$parent->id);

        $response->assertSessionHasErrors('menu');

        $mensagem = session('errors')->first('menu');

        $this->assertStringContainsString('itens filhos', $mensagem);
        // A exceção de domínio é escrita em inglês; a interface é em PT-BR.
        $this->assertStringNotContainsString('child item', $mensagem);
        $this->assertSame(2, MenuItem::query()->count());
    }

    // --- Destino ----------------------------------------------------------

    public function test_a_page_item_requires_a_page(): void
    {
        $menu = Menu::factory()->create();

        $this->admin()
            ->post(self::URI.'/'.$menu->id.'/itens', $this->urlItem(['type' => 'page', 'url' => '']))
            ->assertSessionHasErrors('page_id');

        $this->assertSame(0, MenuItem::query()->count());
    }

    public function test_a_page_item_rejects_a_competing_url(): void
    {
        $menu = Menu::factory()->create();
        $page = $this->page();

        $this->admin()
            ->post(self::URI.'/'.$menu->id.'/itens', $this->pageItem($page, ['url' => '/concorrente']))
            ->assertSessionHasErrors('url');

        $this->assertSame(0, MenuItem::query()->count());
    }

    public function test_a_url_item_requires_a_url(): void
    {
        $menu = Menu::factory()->create();

        $this->admin()
            ->post(self::URI.'/'.$menu->id.'/itens', $this->urlItem(['url' => '']))
            ->assertSessionHasErrors('url');

        $this->assertSame(0, MenuItem::query()->count());
    }

    public function test_a_url_item_rejects_a_competing_page(): void
    {
        $menu = Menu::factory()->create();
        $page = $this->page();

        $this->admin()
            ->post(self::URI.'/'.$menu->id.'/itens', $this->urlItem(['page_id' => (string) $page->id]))
            ->assertSessionHasErrors('page_id');

        $this->assertSame(0, MenuItem::query()->count());
    }

    /**
     * A política de URL não é reescrita no Form Request: quem responde é o
     * `MenuService`. Estes casos provam que a barreira antecipada usa mesmo a
     * regra do domínio.
     */
    #[DataProvider('unsafeUrls')]
    public function test_an_unsafe_url_is_rejected_by_the_form(string $url): void
    {
        $menu = Menu::factory()->create();

        $this->admin()
            ->post(self::URI.'/'.$menu->id.'/itens', $this->urlItem(['url' => $url]))
            ->assertSessionHasErrors('url');

        $this->assertSame(0, MenuItem::query()->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeUrls(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html;base64,PHN2Zz4='],
            'protocol relative' => ['//evil.example'],
            'barra invertida' => ['/\\evil.example'],
            'esquema sem allowlist' => ['ftp://arquivos.exemplo.com'],
            'sem esquema' => ['www.exemplo.com'],
        ];
    }

    public function test_a_missing_page_is_rejected_by_the_form(): void
    {
        $menu = Menu::factory()->create();

        $this->admin()
            ->post(self::URI.'/'.$menu->id.'/itens', $this->urlItem(['type' => 'page', 'url' => '', 'page_id' => '9999']))
            ->assertSessionHasErrors('page_id');

        $this->assertSame(0, MenuItem::query()->count());
    }

    public function test_a_trashed_page_is_neither_offered_nor_accepted(): void
    {
        $menu = Menu::factory()->create();
        $page = $this->page('na-lixeira');
        $page->delete();

        $html = $this->admin()->get(self::URI.'/'.$menu->id.'/editar')->assertOk()->getContent();
        $this->assertStringNotContainsString('/paginas/na-lixeira', $html);

        $this->admin()
            ->post(self::URI.'/'.$menu->id.'/itens', $this->pageItem($page))
            ->assertSessionHasErrors('page_id');

        $this->assertSame(0, MenuItem::query()->count());
    }

    public function test_a_draft_page_stays_selectable(): void
    {
        $menu = Menu::factory()->create();
        $page = $this->page('rascunho', PageStatus::Draft);

        $this->admin()
            ->post(self::URI.'/'.$menu->id.'/itens', $this->pageItem($page))
            ->assertSessionHasNoErrors();

        $this->assertSame($page->id, MenuItem::query()->sole()->page_id);
    }

    public function test_the_page_options_are_identified_by_id_and_never_by_slug(): void
    {
        $menu = Menu::factory()->create();
        $page = $this->page('quem-somos');

        $html = $this->admin()->get(self::URI.'/'.$menu->id.'/editar')->assertOk()->getContent();

        $this->assertStringContainsString('value="'.$page->id.'"', $html);
        $this->assertStringNotContainsString('value="quem-somos"', $html);
        // Título e endereço juntos, para distinguir páginas homônimas.
        $this->assertStringContainsString('/paginas/quem-somos', $html);
    }

    // --- Hierarquia -------------------------------------------------------

    public function test_an_item_can_be_created_under_a_parent_of_the_same_menu(): void
    {
        $menu = Menu::factory()->create();
        $parent = MenuItem::factory()->for($menu)->create(['sort_order' => 1]);

        $this->admin()
            ->post(self::URI.'/'.$menu->id.'/itens', $this->urlItem(['parent_id' => (string) $parent->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($parent->id, MenuItem::query()->latest('id')->first()->parent_id);
    }

    public function test_a_parent_from_another_menu_is_refused(): void
    {
        $menu = Menu::factory()->create();
        $foreign = MenuItem::factory()->create(['sort_order' => 1]);

        $this->admin()
            ->post(self::URI.'/'.$menu->id.'/itens', $this->urlItem(['parent_id' => (string) $foreign->id]))
            ->assertSessionHasErrors('menu');

        $this->assertSame(0, MenuItem::query()->where('menu_id', $menu->id)->count());
    }

    public function test_an_item_cannot_become_its_own_parent(): void
    {
        $menu = Menu::factory()->create();
        $item = MenuItem::factory()->for($menu)->create(['sort_order' => 1]);

        $this->admin()
            ->put(self::URI.'/'.$menu->id.'/itens/'.$item->id, $this->urlItem(['parent_id' => (string) $item->id]))
            ->assertSessionHasErrors('menu');

        $this->assertNull($item->fresh()->parent_id);
    }

    public function test_a_descendant_cannot_become_the_parent(): void
    {
        $menu = Menu::factory()->create();
        $root = MenuItem::factory()->for($menu)->create(['sort_order' => 1]);
        $child = MenuItem::factory()->for($menu)->create(['parent_id' => $root->id, 'sort_order' => 1]);
        $grandchild = MenuItem::factory()->for($menu)->create(['parent_id' => $child->id, 'sort_order' => 1]);

        $this->admin()
            ->put(self::URI.'/'.$menu->id.'/itens/'.$root->id, $this->urlItem(['parent_id' => (string) $grandchild->id]))
            ->assertSessionHasErrors('menu');

        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_an_item_can_be_moved_back_to_the_root(): void
    {
        $menu = Menu::factory()->create();
        $parent = MenuItem::factory()->for($menu)->create(['sort_order' => 1]);
        $child = MenuItem::factory()->for($menu)->create(['parent_id' => $parent->id, 'sort_order' => 1]);

        $this->admin()
            ->put(self::URI.'/'.$menu->id.'/itens/'.$child->id, $this->urlItem(['parent_id' => '']))
            ->assertSessionHasNoErrors();

        $child->refresh();

        $this->assertNull($child->parent_id);
        // Mudar de grupo anexa ao fim do destino: o número antigo descrevia o
        // lugar entre outros irmãos e, no grupo novo, não descreve nada.
        $this->assertSame(2, $child->sort_order);
    }

    public function test_an_item_can_be_moved_under_another_valid_parent(): void
    {
        $menu = Menu::factory()->create();
        $first = MenuItem::factory()->for($menu)->create(['sort_order' => 1]);
        $second = MenuItem::factory()->for($menu)->create(['sort_order' => 2]);
        $child = MenuItem::factory()->for($menu)->create(['parent_id' => $first->id, 'sort_order' => 1]);

        $this->admin()
            ->put(self::URI.'/'.$menu->id.'/itens/'.$child->id, $this->urlItem(['parent_id' => (string) $second->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($second->id, $child->fresh()->parent_id);
        $this->assertSame(1, $child->fresh()->sort_order);
    }

    public function test_the_item_form_does_not_offer_the_item_or_its_descendants_as_parent(): void
    {
        $menu = Menu::factory()->create();
        $root = MenuItem::factory()->for($menu)->create(['label' => 'Raiz editada', 'sort_order' => 1]);
        MenuItem::factory()->for($menu)->create(['label' => 'Descendente proibido', 'parent_id' => $root->id, 'sort_order' => 1]);
        MenuItem::factory()->for($menu)->create(['label' => 'Irmao permitido', 'sort_order' => 2]);

        $html = $this->admin()->get(self::URI.'/'.$menu->id.'/itens/'.$root->id.'/editar')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Descendente proibido', $html);
        $this->assertStringContainsString('Irmao permitido', $html);
    }

    // --- Árvore administrativa --------------------------------------------

    public function test_the_tree_is_rendered_in_hierarchical_order(): void
    {
        $menu = Menu::factory()->create();
        $second = MenuItem::factory()->for($menu)->create(['label' => 'Segundo raiz', 'sort_order' => 2]);
        $first = MenuItem::factory()->for($menu)->create(['label' => 'Primeiro raiz', 'sort_order' => 1]);
        MenuItem::factory()->for($menu)->create(['label' => 'Filho do primeiro', 'parent_id' => $first->id, 'sort_order' => 1]);

        $html = $this->admin()->get(self::URI.'/'.$menu->id.'/editar')->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, 'Filho do primeiro'),
            strpos($html, 'Primeiro raiz'),
            'O filho deve aparecer logo abaixo do seu pai.'
        );
        $this->assertLessThan(
            strpos($html, 'Segundo raiz'),
            strpos($html, 'Filho do primeiro'),
            'A subárvore do primeiro raiz vem antes do segundo raiz.'
        );
    }

    public function test_the_tree_does_not_query_the_database_per_node(): void
    {
        $menu = Menu::factory()->create();
        $page = $this->page();
        $root = MenuItem::factory()->for($menu)->create(['sort_order' => 1]);
        $child = MenuItem::factory()->for($menu)->create(['parent_id' => $root->id, 'sort_order' => 1]);
        MenuItem::factory()->for($menu)->create([
            'parent_id' => $child->id,
            'type' => MenuItemType::Page,
            'page_id' => $page->id,
            'url' => null,
            'sort_order' => 1,
        ]);

        $queries = $this->queriesOf(
            fn (): TestResponse => $this->admin()->get(self::URI.'/'.$menu->id.'/editar')->assertOk()
        );

        $this->assertSame(
            1,
            $this->matching($queries, 'menu_items'),
            'A árvore deve sair de uma única consulta, sem varredura por nó.'
        );
        $this->assertLessThanOrEqual(
            2,
            $this->matching($queries, 'pages'),
            'As páginas devem vir do eager loading e da lista de destinos, e não uma por item.'
        );
    }

    public function test_the_tree_disables_the_impossible_move_buttons(): void
    {
        $menu = Menu::factory()->create();
        $first = MenuItem::factory()->for($menu)->create(['label' => 'Primeiro', 'sort_order' => 1]);
        $last = MenuItem::factory()->for($menu)->create(['label' => 'Ultimo', 'sort_order' => 2]);

        $html = $this->admin()->get(self::URI.'/'.$menu->id.'/editar')->assertOk()->getContent();

        // As bordas do grupo não têm para onde ir: o botão vem desabilitado.
        // O backend também trata a operação como no-op — a interface não é a
        // barreira, apenas não oferece o impossível.
        $this->assertTrue($this->buttonIsDisabled($html, 'Subir Primeiro'));
        $this->assertTrue($this->buttonIsDisabled($html, 'Descer Ultimo'));

        $this->assertFalse($this->buttonIsDisabled($html, 'Descer Primeiro'));
        $this->assertFalse($this->buttonIsDisabled($html, 'Subir Ultimo'));
    }

    // --- Ordenação --------------------------------------------------------

    public function test_a_new_item_is_appended_to_the_end_of_its_group(): void
    {
        $menu = Menu::factory()->create();
        MenuItem::factory()->for($menu)->create(['sort_order' => 1]);
        MenuItem::factory()->for($menu)->create(['sort_order' => 2]);

        $this->admin()
            ->post(self::URI.'/'.$menu->id.'/itens', $this->urlItem(['label' => 'Terceiro']))
            ->assertSessionHasNoErrors();

        $this->assertSame(3, MenuItem::query()->where('label', 'Terceiro')->sole()->sort_order);
    }

    public function test_an_item_moves_up_through_the_interface(): void
    {
        $menu = Menu::factory()->create();
        [$a, $b, $c] = $this->roots($menu, 3);

        $this->admin()->post(self::URI.'/'.$menu->id.'/itens/'.$c->id.'/subir')
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.menus.edit', $menu));

        $this->assertSame([$a->id, $c->id, $b->id], $this->orderOf($menu, null));
    }

    public function test_an_item_moves_down_through_the_interface(): void
    {
        $menu = Menu::factory()->create();
        [$a, $b, $c] = $this->roots($menu, 3);

        $this->admin()->post(self::URI.'/'.$menu->id.'/itens/'.$a->id.'/descer')
            ->assertSessionHasNoErrors();

        $this->assertSame([$b->id, $a->id, $c->id], $this->orderOf($menu, null));
    }

    public function test_moving_the_first_item_up_does_not_corrupt_the_group(): void
    {
        $menu = Menu::factory()->create();
        [$a, $b, $c] = $this->roots($menu, 3);

        $this->admin()->post(self::URI.'/'.$menu->id.'/itens/'.$a->id.'/subir')
            ->assertSessionHasNoErrors();

        $this->assertSame([$a->id, $b->id, $c->id], $this->orderOf($menu, null));
        $this->assertSame([1, 2, 3], [
            $a->fresh()->sort_order,
            $b->fresh()->sort_order,
            $c->fresh()->sort_order,
        ]);
    }

    public function test_moving_the_last_item_down_does_not_corrupt_the_group(): void
    {
        $menu = Menu::factory()->create();
        [$a, $b, $c] = $this->roots($menu, 3);

        $this->admin()->post(self::URI.'/'.$menu->id.'/itens/'.$c->id.'/descer')
            ->assertSessionHasNoErrors();

        $this->assertSame([$a->id, $b->id, $c->id], $this->orderOf($menu, null));
        $this->assertSame(3, $c->fresh()->sort_order);
    }

    public function test_moving_normalizes_the_group_to_one_through_n(): void
    {
        $menu = Menu::factory()->create();
        $a = MenuItem::factory()->for($menu)->create(['sort_order' => 2]);
        $b = MenuItem::factory()->for($menu)->create(['sort_order' => 8]);
        $c = MenuItem::factory()->for($menu)->create(['sort_order' => 40]);

        $this->admin()->post(self::URI.'/'.$menu->id.'/itens/'.$c->id.'/subir');

        $this->assertSame(1, $a->fresh()->sort_order);
        $this->assertSame(2, $c->fresh()->sort_order);
        $this->assertSame(3, $b->fresh()->sort_order);
    }

    public function test_the_root_group_is_independent_from_a_child_group(): void
    {
        $menu = Menu::factory()->create();
        $parent = MenuItem::factory()->for($menu)->create(['sort_order' => 1]);
        $sibling = MenuItem::factory()->for($menu)->create(['sort_order' => 2]);
        $firstChild = MenuItem::factory()->for($menu)->create(['parent_id' => $parent->id, 'sort_order' => 1]);
        $secondChild = MenuItem::factory()->for($menu)->create(['parent_id' => $parent->id, 'sort_order' => 2]);

        $this->admin()->post(self::URI.'/'.$menu->id.'/itens/'.$secondChild->id.'/subir');

        $this->assertSame([$secondChild->id, $firstChild->id], $this->orderOf($menu, $parent->id));
        $this->assertSame([$parent->id, $sibling->id], $this->orderOf($menu, null));
    }

    public function test_groups_of_different_parents_are_independent(): void
    {
        $menu = Menu::factory()->create();
        $left = MenuItem::factory()->for($menu)->create(['sort_order' => 1]);
        $right = MenuItem::factory()->for($menu)->create(['sort_order' => 2]);

        $leftFirst = MenuItem::factory()->for($menu)->create(['parent_id' => $left->id, 'sort_order' => 1]);
        $leftSecond = MenuItem::factory()->for($menu)->create(['parent_id' => $left->id, 'sort_order' => 2]);
        $rightFirst = MenuItem::factory()->for($menu)->create(['parent_id' => $right->id, 'sort_order' => 1]);
        $rightSecond = MenuItem::factory()->for($menu)->create(['parent_id' => $right->id, 'sort_order' => 2]);

        $this->admin()->post(self::URI.'/'.$menu->id.'/itens/'.$leftFirst->id.'/descer');

        $this->assertSame([$leftSecond->id, $leftFirst->id], $this->orderOf($menu, $left->id));
        $this->assertSame([$rightFirst->id, $rightSecond->id], $this->orderOf($menu, $right->id));
    }

    // --- Árvore inconsistente ---------------------------------------------

    /**
     * A fixture alcança mesmo o `RuntimeException` do serviço?
     *
     * Este teste existe para sustentar o seguinte: sem ele, o teste HTTP
     * passaria por qualquer motivo — inclusive por uma validação do Form
     * Request barrando a requisição antes do domínio — e não provaria nada
     * sobre o `catch` do Controller. Aqui a mesma árvore é entregue direto ao
     * `MenuService`, sem HTTP e sem mock, e a exceção real aparece.
     */
    public function test_an_inconsistent_tree_makes_the_service_raise_a_runtime_exception(): void
    {
        [$item, , $cycleEntry] = $this->inconsistentTree();

        $this->expectException(RuntimeException::class);

        app(MenuService::class)->updateItem($item, ['parent_id' => $cycleEntry->id]);
    }

    /**
     * Uma árvore inconsistente não pode virar erro 500 numa edição comum.
     *
     * `guardNoCycle()` sobe a cadeia de ancestrais do pai proposto. Quando essa
     * cadeia já contém um ciclo — gravado por fora do serviço —, a varredura
     * esgota o teto e sai por `RuntimeException`, e não pela
     * `InvalidArgumentException` das recusas comuns.
     *
     * É o único caminho da subfase em que isso acontece, porque só a edição do
     * item envia `parent_id`. Sem o `catch` correspondente no Controller, o
     * administrador recebia uma página de erro em vez de uma explicação.
     */
    public function test_an_inconsistent_tree_returns_administrative_feedback_instead_of_a_server_error(): void
    {
        [$item, $menu, $a, $b] = $this->inconsistentTree();

        $response = $this->admin()->put(
            self::URI.'/'.$menu->id.'/itens/'.$item->id,
            $this->urlItem(['label' => 'Tentativa', 'parent_id' => (string) $a->id])
        );

        // O contrato é este: recusa, e não colapso.
        $response->assertStatus(302);
        $response->assertSessionHasErrors('menu');

        $mensagem = session('errors')->first('menu');

        $this->assertStringContainsString('inconsistente', $mensagem);
        // A exceção de domínio é escrita em inglês e fica no log; a tela é
        // PT-BR. Os marcadores procurados são trechos literais dela — não a
        // palavra "inconsistent", que é prefixo do próprio "inconsistente".
        $this->assertStringNotContainsString('cannot be reparented', $mensagem);
        $this->assertStringNotContainsString('has an inconsistent item tree', $mensagem);
        $this->assertStringNotContainsString('MenuService', $mensagem);

        // O input volta preenchido: ninguém precisa redigitar o que já escreveu.
        $response->assertSessionHasInput('label', 'Tentativa');

        // E nada foi gravado — nem o campo que a mesma requisição alteraria se
        // tivesse chegado ao fim.
        $item->refresh();

        $this->assertNull($item->parent_id);
        $this->assertSame('Editado', $item->label);
        $this->assertSame($b->id, $a->fresh()->parent_id);
        $this->assertSame($a->id, $b->fresh()->parent_id);
    }

    /**
     * Menu cuja cadeia de ancestrais contém um ciclo, montado por fora do
     * serviço — que jamais o produziria.
     *
     * `A → B → A`, com o `UPDATE` direto fechando o laço: cada linha continua
     * **referencialmente válida**, e por isso a FK `RESTRICT` a aceita. O ciclo
     * só aparece percorrendo a cadeia, que é justamente o que `guardNoCycle()`
     * faz. É a mesma técnica já usada por `MenuServiceTest` para provar que a
     * exclusão não gira para sempre.
     *
     * O item editado fica **fora** do ciclo: se ele participasse, a varredura
     * o encontraria e recusaria com `InvalidArgumentException` — o caso comum
     * de "descendente de si mesmo", que já tem teste próprio e não é este.
     *
     * Devolve, nesta ordem: o item a editar, o menu e os dois nós do ciclo. O
     * primeiro nó do ciclo é também o pai proposto na edição.
     *
     * @return array{MenuItem, Menu, MenuItem, MenuItem}
     */
    private function inconsistentTree(): array
    {
        $menu = Menu::factory()->create();

        $a = MenuItem::factory()->for($menu)->create(['label' => 'A', 'sort_order' => 1]);
        $b = MenuItem::factory()->for($menu)->create(['label' => 'B', 'parent_id' => $a->id, 'sort_order' => 1]);

        DB::table('menu_items')->where('id', $a->id)->update(['parent_id' => $b->id]);

        $item = MenuItem::factory()->for($menu)->create(['label' => 'Editado', 'sort_order' => 2]);

        return [$item, $menu, $a, $b];
    }

    // --- Segurança --------------------------------------------------------

    public function test_an_item_of_another_menu_cannot_be_opened_through_the_nested_route(): void
    {
        [$menu, $other, $foreign] = $this->crossMenuFixture();

        $this->admin()->get(self::URI.'/'.$menu->id.'/itens/'.$foreign->id.'/editar')
            ->assertNotFound();
    }

    public function test_an_item_of_another_menu_cannot_be_updated(): void
    {
        [$menu, $other, $foreign] = $this->crossMenuFixture();

        $this->admin()
            ->put(self::URI.'/'.$menu->id.'/itens/'.$foreign->id, $this->urlItem(['label' => 'Invadido']))
            ->assertNotFound();

        $this->assertSame('Alheio', $foreign->fresh()->label);
        $this->assertSame($other->id, $foreign->fresh()->menu_id);
    }

    public function test_an_item_of_another_menu_cannot_be_deleted(): void
    {
        [$menu, , $foreign] = $this->crossMenuFixture();

        $this->admin()->delete(self::URI.'/'.$menu->id.'/itens/'.$foreign->id)
            ->assertNotFound();

        $this->assertNotNull($foreign->fresh());
    }

    public function test_an_item_of_another_menu_cannot_be_toggled(): void
    {
        [$menu, , $foreign] = $this->crossMenuFixture();

        $this->admin()->post(self::URI.'/'.$menu->id.'/itens/'.$foreign->id.'/alternar')
            ->assertNotFound();

        $this->assertFalse($foreign->fresh()->is_active);
    }

    public function test_an_item_of_another_menu_cannot_be_reordered(): void
    {
        $menu = Menu::factory()->create();
        $other = Menu::factory()->create();
        $first = MenuItem::factory()->for($other)->create(['sort_order' => 1]);
        $second = MenuItem::factory()->for($other)->create(['sort_order' => 2]);

        $this->admin()->post(self::URI.'/'.$menu->id.'/itens/'.$second->id.'/subir')
            ->assertNotFound();
        $this->admin()->post(self::URI.'/'.$menu->id.'/itens/'.$first->id.'/descer')
            ->assertNotFound();

        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(2, $second->fresh()->sort_order);
    }

    public function test_a_sort_order_sent_by_the_form_does_not_control_the_order(): void
    {
        $menu = Menu::factory()->create();
        MenuItem::factory()->for($menu)->create(['sort_order' => 1]);

        $this->admin()
            ->post(self::URI.'/'.$menu->id.'/itens', $this->urlItem(['label' => 'Forjado', 'sort_order' => '99']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, MenuItem::query()->where('label', 'Forjado')->sole()->sort_order);
    }

    public function test_a_menu_id_sent_by_the_form_does_not_move_the_item_between_menus(): void
    {
        $menu = Menu::factory()->create();
        $other = Menu::factory()->create();
        $item = MenuItem::factory()->for($menu)->create(['sort_order' => 1]);

        $this->admin()
            ->put(self::URI.'/'.$menu->id.'/itens/'.$item->id, $this->urlItem([
                'label' => 'Renomeado',
                'menu_id' => (string) $other->id,
            ]))
            ->assertSessionHasNoErrors();

        $item->refresh();

        $this->assertSame($menu->id, $item->menu_id);
        $this->assertSame('Renomeado', $item->label);
    }

    // --- Navegação --------------------------------------------------------

    public function test_the_sidebar_links_to_menus_now_that_the_route_exists(): void
    {
        $this->admin()->get('/admin')
            ->assertOk()
            ->assertSee('href="'.url('/admin/menus').'"', false)
            ->assertSee('Menus');
    }

    public function test_the_sidebar_marks_menus_as_the_current_section(): void
    {
        $menu = Menu::factory()->create();
        $item = MenuItem::factory()->for($menu)->create(['sort_order' => 1]);

        $padrao = '/<a\s[^>]*href="'.preg_quote(route('admin.menus.index'), '/').'"[^>]*aria-current="page"/s';

        foreach ([
            self::URI,
            self::URI.'/criar',
            self::URI.'/'.$menu->id.'/editar',
            self::URI.'/'.$menu->id.'/itens/'.$item->id.'/editar',
        ] as $uri) {
            $this->assertMatchesRegularExpression(
                $padrao,
                $this->admin()->get($uri)->assertOk()->getContent(),
                "O link Menus da sidebar deve marcar a seção atual em {$uri}."
            );
        }
    }

    public function test_the_sidebar_does_not_mark_menus_on_another_section(): void
    {
        $padrao = '/<a\s[^>]*href="'.preg_quote(route('admin.menus.index'), '/').'"[^>]*aria-current="page"/s';

        $this->assertDoesNotMatchRegularExpression(
            $padrao,
            $this->admin()->get('/admin')->assertOk()->getContent()
        );
    }

    public function test_the_breadcrumbs_follow_the_menu_screens(): void
    {
        $menu = Menu::factory()->create(['name' => 'Menu principal', 'code' => 'main']);
        $item = MenuItem::factory()->for($menu)->create(['sort_order' => 1]);

        $this->assertStringContainsString(
            'aria-current="page">Menus</li>',
            $this->admin()->get(self::URI)->getContent()
        );
        $this->assertStringContainsString(
            'aria-current="page">Novo menu</li>',
            $this->admin()->get(self::URI.'/criar')->getContent()
        );
        $this->assertStringContainsString(
            'aria-current="page">Menu principal</li>',
            $this->admin()->get(self::URI.'/'.$menu->id.'/editar')->getContent()
        );
        $this->assertStringContainsString(
            'aria-current="page">Editar item</li>',
            $this->admin()->get(self::URI.'/'.$menu->id.'/itens/'.$item->id.'/editar')->getContent()
        );
    }

    // --- Apoio ------------------------------------------------------------

    private function admin(): self
    {
        return $this->actingAs(User::factory()->create());
    }

    /**
     * Dois menus e um item que pertence ao **segundo** — a fixture das provas
     * de rota aninhada.
     *
     * @return array{Menu, Menu, MenuItem}
     */
    private function crossMenuFixture(): array
    {
        $menu = Menu::factory()->create();
        $other = Menu::factory()->create();
        $foreign = MenuItem::factory()->for($other)->create([
            'label' => 'Alheio',
            'is_active' => false,
            'sort_order' => 1,
        ]);

        return [$menu, $other, $foreign];
    }

    /**
     * Payload de item `url`, no formato que o formulário envia.
     *
     * Os campos vazios chegam como string vazia, exatamente como um `<select>`
     * ou um `<input>` em branco: o middleware padrão do Laravel os converte em
     * `null` antes da validação.
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function urlItem(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Contato',
            'type' => MenuItemType::Url->value,
            'page_id' => '',
            'url' => '/contato',
            'parent_id' => '',
            'is_active' => '0',
        ], $overrides);
    }

    /**
     * Payload de item `page`, no mesmo formato do formulário.
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function pageItem(Page $page, array $overrides = []): array
    {
        return array_merge($this->urlItem([
            'type' => MenuItemType::Page->value,
            'page_id' => (string) $page->id,
            'url' => '',
        ]), $overrides);
    }

    private function page(string $slug = 'quem-somos', PageStatus $status = PageStatus::Published): Page
    {
        return Page::create([
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'content' => '# Conteúdo',
            'status' => $status,
        ]);
    }

    /**
     * Itens raiz numerados de 1 a N, na ordem de criação.
     *
     * @return list<MenuItem>
     */
    private function roots(Menu $menu, int $total): array
    {
        $items = [];

        for ($order = 1; $order <= $total; $order++) {
            $items[] = MenuItem::factory()->for($menu)->create(['sort_order' => $order]);
        }

        return $items;
    }

    /**
     * Ids do grupo na ordem persistida.
     *
     * @return list<int>
     */
    private function orderOf(Menu $menu, ?int $parentId): array
    {
        $query = MenuItem::query()->where('menu_id', $menu->id);

        $query = $parentId === null
            ? $query->whereNull('parent_id')
            : $query->where('parent_id', $parentId);

        return array_map('intval', $query->orderBy('sort_order')->orderBy('id')->pluck('id')->all());
    }

    /**
     * SQL emitido durante uma requisição — a prova mecânica contra N+1.
     *
     * @param  callable(): TestResponse  $request
     * @return list<string>
     */
    private function queriesOf(callable $request): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $request();

        $queries = array_column(DB::getQueryLog(), 'query');

        DB::disableQueryLog();

        return $queries;
    }

    /**
     * O botão identificado por este `aria-label` veio desabilitado?
     *
     * A busca é pela tag inteira, e não pela palavra solta: `disabled:` também
     * aparece nas classes utilitárias de estilo, e contá-las provaria outra
     * coisa. `@disabled(false)` não emite atributo nenhum.
     */
    private function buttonIsDisabled(string $html, string $label): bool
    {
        $encontrado = preg_match('/<button\b[^>]*aria-label="'.preg_quote($label, '/').'"[^>]*>/s', $html, $matches);

        $this->assertSame(1, $encontrado, "O botão [{$label}] deveria existir na árvore.");

        return preg_match('/\sdisabled(?=[\s>])/', $matches[0]) === 1;
    }

    /**
     * @param  list<string>  $queries
     */
    private function matching(array $queries, string $table): int
    {
        return count(array_filter($queries, fn (string $query): bool => str_contains($query, $table)));
    }
}
