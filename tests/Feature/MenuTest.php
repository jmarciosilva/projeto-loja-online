<?php

namespace Tests\Feature;

use App\Enums\MenuItemType;
use App\Enums\PageStatus;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Domínio e persistência dos menus — F2.6-A.
 *
 * Nada aqui usa HTTP nem formulário administrativo: a fundação precisa ser
 * verificável sem nenhum dos dois.
 */
class MenuTest extends TestCase
{
    use RefreshDatabase;

    // --- Schema: menus ----------------------------------------------------

    public function test_a_menu_can_be_persisted_with_the_supported_fields(): void
    {
        Menu::create([
            'name' => 'Menu principal',
            'code' => 'main',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('menus', [
            'name' => 'Menu principal',
            'code' => 'main',
            'is_active' => true,
        ]);
    }

    public function test_the_menus_schema_has_no_column_beyond_the_contract(): void
    {
        $expected = [
            'id',
            'name',
            'code',
            'is_active',
            'created_at',
            'updated_at',
        ];

        $columns = Schema::getColumnListing('menus');

        sort($expected);
        sort($columns);

        $this->assertSame($expected, $columns);
    }

    public function test_the_menu_is_not_a_visual_location(): void
    {
        // Onde o menu aparece é decisão de quem o consome, e não um dado dele.
        foreach (['position', 'location', 'slug', 'description', 'metadata', 'settings'] as $column) {
            $this->assertFalse(
                Schema::hasColumn('menus', $column),
                "A tabela menus não deve ter a coluna [{$column}]."
            );
        }
    }

    public function test_the_menu_code_is_unique_in_the_database(): void
    {
        Menu::create(['name' => 'Principal', 'code' => 'main']);

        $this->expectException(QueryException::class);

        Menu::create(['name' => 'Outro', 'code' => 'main']);
    }

    public function test_menu_is_active_defaults_to_false_in_the_database(): void
    {
        DB::table('menus')->insert([
            'name' => 'Sem estado',
            'code' => 'sem-estado',
        ]);

        $this->assertFalse((bool) DB::table('menus')->where('code', 'sem-estado')->value('is_active'));
    }

    public function test_menu_does_not_use_soft_deletes(): void
    {
        $this->assertFalse(Schema::hasColumn('menus', 'deleted_at'));
        $this->assertNotContains(SoftDeletes::class, class_uses_recursive(Menu::class));
    }

    // --- Schema: menu_items -----------------------------------------------

    public function test_a_menu_item_can_be_persisted_with_the_supported_fields(): void
    {
        $menu = Menu::factory()->create();

        MenuItem::create([
            'menu_id' => $menu->id,
            'parent_id' => null,
            'label' => 'Quem somos',
            'type' => MenuItemType::Url,
            'page_id' => null,
            'url' => '/quem-somos',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('menu_items', [
            'menu_id' => $menu->id,
            'parent_id' => null,
            'label' => 'Quem somos',
            'type' => 'url',
            'page_id' => null,
            'url' => '/quem-somos',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_the_menu_items_schema_has_no_column_beyond_the_contract(): void
    {
        $expected = [
            'id',
            'menu_id',
            'parent_id',
            'label',
            'type',
            'page_id',
            'url',
            'sort_order',
            'is_active',
            'created_at',
            'updated_at',
        ];

        $columns = Schema::getColumnListing('menu_items');

        sort($expected);
        sort($columns);

        $this->assertSame($expected, $columns);
    }

    public function test_the_menu_item_never_persists_the_page_address(): void
    {
        // O vínculo é por identidade: o slug é endereço público e pode mudar.
        foreach (['slug', 'path', 'page_url', 'target', 'rel', 'icon', 'css_class'] as $column) {
            $this->assertFalse(
                Schema::hasColumn('menu_items', $column),
                "A tabela menu_items não deve ter a coluna [{$column}]."
            );
        }
    }

    public function test_menu_item_is_active_defaults_to_false_in_the_database(): void
    {
        $menu = Menu::factory()->create();

        DB::table('menu_items')->insert([
            'menu_id' => $menu->id,
            'label' => 'Sem estado',
            'type' => 'url',
            'url' => '/sem-estado',
            'sort_order' => 1,
        ]);

        $this->assertFalse((bool) DB::table('menu_items')->where('label', 'Sem estado')->value('is_active'));
    }

    public function test_sort_order_has_no_database_default(): void
    {
        // Um default faria qualquer INSERT fora do serviço cair silenciosamente
        // no início do grupo; sem ele, o insert falha em vez de mentir.
        $menu = Menu::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('menu_items')->insert([
            'menu_id' => $menu->id,
            'label' => 'Sem ordem',
            'type' => 'url',
            'url' => '/sem-ordem',
        ]);
    }

    public function test_menu_item_does_not_use_soft_deletes(): void
    {
        $this->assertFalse(Schema::hasColumn('menu_items', 'deleted_at'));
        $this->assertNotContains(SoftDeletes::class, class_uses_recursive(MenuItem::class));
    }

    public function test_the_composite_index_follows_the_contract(): void
    {
        $columns = array_map(
            fn (array $index): array => $index['columns'],
            Schema::getIndexes('menu_items'),
        );

        $this->assertContains(['menu_id', 'parent_id', 'sort_order'], $columns);
    }

    public function test_there_is_no_unique_constraint_on_the_sibling_order(): void
    {
        // Reordenar passa por estados intermediários com empate; uma constraint
        // de unicidade obrigaria a inventar valores temporários.
        foreach (Schema::getIndexes('menu_items') as $index) {
            if ($index['columns'] === ['menu_id', 'parent_id', 'sort_order']) {
                $this->assertFalse($index['unique']);
            }
        }

        $menu = Menu::factory()->create();

        foreach ([1, 2] as $ignored) {
            MenuItem::factory()->create(['menu_id' => $menu->id, 'sort_order' => 1]);
        }

        $this->assertSame(2, MenuItem::query()->where('sort_order', 1)->count());
    }

    // --- Integridade referencial ------------------------------------------

    public function test_a_menu_with_items_cannot_be_deleted_directly(): void
    {
        // A FK é a barreira final contra a exclusão acidental por fora da
        // Service Layer — por comando, script ou correção manual.
        $menu = Menu::factory()->create();
        MenuItem::factory()->create(['menu_id' => $menu->id]);

        $this->expectException(QueryException::class);

        DB::table('menus')->where('id', $menu->id)->delete();
    }

    public function test_an_empty_menu_can_be_deleted_directly(): void
    {
        $menu = Menu::factory()->create();

        DB::table('menus')->where('id', $menu->id)->delete();

        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
    }

    public function test_a_parent_item_cannot_be_deleted_while_it_has_children(): void
    {
        // RESTRICT, e não CASCADE: excluir um pai não pode apagar a subárvore.
        $menu = Menu::factory()->create();
        $parent = MenuItem::factory()->create(['menu_id' => $menu->id]);
        MenuItem::factory()->create(['menu_id' => $menu->id, 'parent_id' => $parent->id]);

        $this->expectException(QueryException::class);

        DB::table('menu_items')->where('id', $parent->id)->delete();
    }

    public function test_a_page_referenced_by_a_menu_item_cannot_be_deleted_physically(): void
    {
        $page = $this->page();
        $menu = Menu::factory()->create();
        MenuItem::factory()->create([
            'menu_id' => $menu->id,
            'type' => MenuItemType::Page,
            'page_id' => $page->id,
            'url' => null,
        ]);

        $this->expectException(QueryException::class);

        DB::table('pages')->where('id', $page->id)->delete();
    }

    public function test_soft_deleting_a_referenced_page_does_not_trigger_the_foreign_key(): void
    {
        // Esperado: a exclusão lógica não remove a linha, então o RESTRICT não
        // é acionado. Quem impede o item de aparecer é a publicabilidade da
        // F2.6-C, não o banco.
        $page = $this->page();
        $menu = Menu::factory()->create();
        $item = MenuItem::factory()->create([
            'menu_id' => $menu->id,
            'type' => MenuItemType::Page,
            'page_id' => $page->id,
            'url' => null,
        ]);

        $page->delete();

        $this->assertSoftDeleted('pages', ['id' => $page->id]);
        $this->assertDatabaseHas('menu_items', ['id' => $item->id, 'page_id' => $page->id]);
    }

    public function test_the_foreign_keys_point_to_the_contracted_tables(): void
    {
        $targets = [];

        foreach (Schema::getForeignKeys('menu_items') as $foreignKey) {
            $targets[implode(',', $foreignKey['columns'])] = $foreignKey['foreign_table'];
        }

        $this->assertSame('menus', $targets['menu_id'] ?? null);
        $this->assertSame('menu_items', $targets['parent_id'] ?? null);
        $this->assertSame('pages', $targets['page_id'] ?? null);
    }

    // --- Enum e casts -----------------------------------------------------

    public function test_the_type_enum_has_exactly_the_contracted_cases(): void
    {
        $this->assertSame(
            ['page', 'url'],
            array_map(fn (MenuItemType $case): string => $case->value, MenuItemType::cases()),
        );
    }

    public function test_the_type_is_read_back_as_an_enum(): void
    {
        $item = MenuItem::factory()->create(['type' => MenuItemType::Url]);

        $this->assertSame(MenuItemType::Url, $item->fresh()->type);
    }

    public function test_the_numeric_and_boolean_columns_are_read_back_with_their_types(): void
    {
        $menu = Menu::factory()->create(['is_active' => true]);
        $parent = MenuItem::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create([
            'menu_id' => $menu->id,
            'parent_id' => $parent->id,
            'sort_order' => 3,
            'is_active' => true,
        ])->fresh();

        $this->assertTrue($menu->fresh()->is_active);
        $this->assertIsInt($item->menu_id);
        $this->assertIsInt($item->parent_id);
        $this->assertIsInt($item->sort_order);
        $this->assertTrue($item->is_active);
    }

    // --- Relacionamentos ---------------------------------------------------

    public function test_a_menu_has_all_of_its_items_in_every_level(): void
    {
        // A relação é plana de propósito: a hierarquia vive em `parent_id`.
        $menu = Menu::factory()->create();
        $root = MenuItem::factory()->create(['menu_id' => $menu->id]);
        $child = MenuItem::factory()->create(['menu_id' => $menu->id, 'parent_id' => $root->id]);
        $grandchild = MenuItem::factory()->create(['menu_id' => $menu->id, 'parent_id' => $child->id]);

        $this->assertEqualsCanonicalizing(
            [$root->id, $child->id, $grandchild->id],
            $menu->items()->pluck('id')->all(),
        );
    }

    public function test_an_item_belongs_to_its_menu(): void
    {
        $menu = Menu::factory()->create();
        $item = MenuItem::factory()->create(['menu_id' => $menu->id]);

        $this->assertTrue($item->menu->is($menu));
    }

    public function test_an_item_knows_its_parent_and_its_children(): void
    {
        $menu = Menu::factory()->create();
        $parent = MenuItem::factory()->create(['menu_id' => $menu->id]);
        $child = MenuItem::factory()->create(['menu_id' => $menu->id, 'parent_id' => $parent->id]);

        $this->assertTrue($child->parent->is($parent));
        $this->assertSame([$child->id], $parent->children()->pluck('id')->all());
        $this->assertNull($parent->parent);
    }

    public function test_an_item_of_type_page_belongs_to_its_page(): void
    {
        $page = $this->page();
        $item = MenuItem::factory()->create([
            'type' => MenuItemType::Page,
            'page_id' => $page->id,
            'url' => null,
        ]);

        $this->assertTrue($item->page->is($page));
    }

    public function test_an_item_of_type_url_has_no_page(): void
    {
        $item = MenuItem::factory()->create(['type' => MenuItemType::Url, 'url' => '/contato']);

        $this->assertNull($item->page);
        $this->assertNull($item->page_id);
    }

    // --- Helpers ----------------------------------------------------------

    /**
     * Página de destino para os itens do tipo `page`.
     *
     * `Page` não tem factory no projeto — os testes da F2.4 criam as páginas
     * pelo próprio model, e esta subfase segue a mesma convenção em vez de
     * introduzir uma fixture nova só para si.
     */
    private function page(string $slug = 'quem-somos'): Page
    {
        return Page::create([
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'content' => '# Conteúdo',
            'status' => PageStatus::Published,
        ]);
    }

    // --- Mass assignment ---------------------------------------------------

    public function test_unsupported_fields_are_not_mass_assignable(): void
    {
        $menu = new Menu;
        $menu->fill(['name' => 'Principal', 'code' => 'main', 'id' => 999]);

        $item = new MenuItem;
        $item->fill(['label' => 'Contato', 'id' => 999]);

        $this->assertNull($menu->id);
        $this->assertNull($item->id);
    }
}
