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
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Invariantes de domínio do `MenuService` — F2.6-A.
 *
 * O serviço é a camada autoritativa: tudo aqui é exercitado por chamada direta,
 * sem HTTP, porque a interface administrativa da F2.6-B não pode ser a única
 * barreira das regras.
 */
class MenuServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_be_resolved_by_the_laravel_container(): void
    {
        $this->assertInstanceOf(MenuService::class, app(MenuService::class));
    }

    // --- Menu: criação -----------------------------------------------------

    public function test_it_creates_a_menu_with_the_supported_fields(): void
    {
        $menu = $this->service()->createMenu([
            'name' => 'Menu principal',
            'code' => 'main',
            'is_active' => true,
        ]);

        $this->assertTrue($menu->exists);
        $this->assertDatabaseHas('menus', [
            'id' => $menu->id,
            'name' => 'Menu principal',
            'code' => 'main',
            'is_active' => true,
        ]);
    }

    public function test_a_new_menu_is_inactive_by_default(): void
    {
        $this->assertFalse($this->service()->createMenu(['name' => 'Principal', 'code' => 'main'])->is_active);
    }

    public function test_the_menu_name_is_required(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->createMenu(['name' => '   ', 'code' => 'main']);
    }

    public function test_the_menu_name_is_trimmed(): void
    {
        $menu = $this->service()->createMenu(['name' => '  Principal  ', 'code' => 'main']);

        $this->assertSame('Principal', $menu->fresh()->name);
    }

    public function test_a_menu_name_longer_than_the_column_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->createMenu(['name' => str_repeat('a', 121), 'code' => 'main']);
    }

    public function test_a_non_boolean_menu_state_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->createMenu(['name' => 'Principal', 'code' => 'main', 'is_active' => '1']);
    }

    // --- Menu: code --------------------------------------------------------

    public function test_the_menu_code_is_required(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->createMenu(['name' => 'Principal', 'code' => '  ']);
    }

    #[DataProvider('validCodeProvider')]
    public function test_it_accepts_a_code_inside_the_contract(string $code): void
    {
        $menu = $this->service()->createMenu(['name' => 'Principal', 'code' => $code]);

        $this->assertSame($code, $menu->fresh()->code);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function validCodeProvider(): array
    {
        return [
            'simples' => ['main'],
            'numerico' => ['menu2'],
            'hifenizado' => ['menu-institucional'],
            'varios segmentos' => ['menu-do-rodape-secundario'],
            'so digitos' => ['2026'],
        ];
    }

    #[DataProvider('invalidCodeProvider')]
    public function test_it_rejects_a_code_outside_the_contract(string $code): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->createMenu(['name' => 'Principal', 'code' => $code]);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function invalidCodeProvider(): array
    {
        return [
            'maiuscula' => ['Main'],
            'espaco interno' => ['menu principal'],
            'underscore' => ['menu_principal'],
            'acento' => ['institucional-rodapé'],
            'hifen inicial' => ['-main'],
            'hifen final' => ['main-'],
            'hifen duplo' => ['menu--principal'],
            'barra' => ['menu/principal'],
            'ponto' => ['menu.principal'],
        ];
    }

    public function test_the_code_is_trimmed_but_never_transformed(): void
    {
        // O espaço nas bordas é erro de digitação; o resto é recusado em vez de
        // saneado, para que o serviço não escolha a chave por quem chamou.
        $menu = $this->service()->createMenu(['name' => 'Principal', 'code' => '  main  ']);

        $this->assertSame('main', $menu->fresh()->code);
    }

    public function test_the_code_is_never_derived_from_the_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->createMenu(['name' => 'Menu Principal']);
    }

    public function test_a_code_longer_than_the_column_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->createMenu(['name' => 'Principal', 'code' => str_repeat('a', 65)]);
    }

    public function test_a_duplicated_code_is_rejected_as_a_domain_error(): void
    {
        $this->service()->createMenu(['name' => 'Principal', 'code' => 'main']);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->createMenu(['name' => 'Outro', 'code' => 'main']);
    }

    public function test_the_supported_code_check_mirrors_the_persistence_rule(): void
    {
        $this->assertTrue($this->service()->isSupportedCode('menu-principal'));
        $this->assertFalse($this->service()->isSupportedCode('Menu Principal'));
    }

    // --- Menu: atualização -------------------------------------------------

    public function test_it_updates_the_menu_name_and_state(): void
    {
        $menu = $this->service()->createMenu(['name' => 'Principal', 'code' => 'main']);

        $this->service()->updateMenu($menu, ['name' => 'Navegação principal', 'is_active' => true]);

        $this->assertDatabaseHas('menus', [
            'id' => $menu->id,
            'name' => 'Navegação principal',
            'code' => 'main',
            'is_active' => true,
        ]);
    }

    public function test_resubmitting_the_same_code_is_accepted(): void
    {
        // É o caso comum de um formulário de edição que devolve todos os campos.
        $menu = $this->service()->createMenu(['name' => 'Principal', 'code' => 'main']);

        $this->service()->updateMenu($menu, ['name' => 'Outro nome', 'code' => 'main']);

        $this->assertSame('main', $menu->fresh()->code);
        $this->assertSame('Outro nome', $menu->fresh()->name);
    }

    public function test_changing_the_code_is_rejected_and_not_silently_dropped(): void
    {
        // Uma tentativa de mutação precisa ser detectável: engoli-la faria quem
        // chamou acreditar num código que o banco não tem.
        $menu = $this->service()->createMenu(['name' => 'Principal', 'code' => 'main']);

        try {
            $this->service()->updateMenu($menu, ['name' => 'Outro nome', 'code' => 'principal']);

            $this->fail('A alteração do code deveria ser recusada.');
        } catch (InvalidArgumentException) {
            $this->assertSame('main', $menu->fresh()->code);
            // A recusa acontece antes de qualquer escrita.
            $this->assertSame('Principal', $menu->fresh()->name);
        }
    }

    public function test_an_invalid_code_format_is_rejected_on_update_too(): void
    {
        $menu = $this->service()->createMenu(['name' => 'Principal', 'code' => 'main']);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->updateMenu($menu, ['code' => 'Main']);
    }

    // --- Menu: exclusão ----------------------------------------------------

    public function test_an_empty_menu_is_deleted(): void
    {
        $menu = $this->service()->createMenu(['name' => 'Principal', 'code' => 'main']);

        $this->service()->deleteMenu($menu);

        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
    }

    public function test_deleting_a_menu_removes_its_whole_tree(): void
    {
        // A árvore tem três níveis de propósito: com dois, uma remoção em
        // camada única passaria por engano.
        //
        // A
        // ├── B
        // │   └── C
        // └── D
        $menu = $this->menu();
        $a = $this->item($menu, ['label' => 'A']);
        $b = $this->item($menu, ['label' => 'B', 'parent_id' => $a->id]);
        $c = $this->item($menu, ['label' => 'C', 'parent_id' => $b->id]);
        $d = $this->item($menu, ['label' => 'D', 'parent_id' => $a->id]);

        $this->service()->deleteMenu($menu);

        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);

        foreach ([$a, $b, $c, $d] as $item) {
            $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
        }
    }

    public function test_deleting_a_menu_removes_the_items_from_the_leaves_up(): void
    {
        $menu = $this->menu();
        $a = $this->item($menu, ['label' => 'A']);
        $b = $this->item($menu, ['label' => 'B', 'parent_id' => $a->id]);
        $c = $this->item($menu, ['label' => 'C', 'parent_id' => $b->id]);

        $deleted = [];
        DB::listen(function (QueryExecuted $query) use (&$deleted): void {
            if (str_starts_with(mb_strtolower($query->sql), 'delete from') && str_contains($query->sql, 'menu_items')) {
                // O Eloquent embute as chaves inteiras direto na cláusula `in`,
                // então a ordem das camadas se lê no SQL, não nos bindings.
                preg_match_all('/\d+/', $query->sql, $matches);
                $deleted[] = array_map('intval', $matches[0]);
            }
        });

        $this->service()->deleteMenu($menu);

        // Uma remoção por camada, da folha para a raiz: C, depois B, depois A.
        $this->assertSame([[$c->id], [$b->id], [$a->id]], $deleted);
    }

    public function test_deleting_a_menu_does_not_touch_another_menu(): void
    {
        $menu = $this->menu('main');
        $this->item($menu, ['label' => 'A']);

        $other = $this->menu('footer');
        $kept = $this->item($other, ['label' => 'Mantido']);

        $this->service()->deleteMenu($menu);

        $this->assertDatabaseHas('menus', ['id' => $other->id]);
        $this->assertDatabaseHas('menu_items', ['id' => $kept->id]);
    }

    public function test_a_menu_with_an_external_tree_reference_is_rejected_before_any_write(): void
    {
        // Estado inconsistente montado direto no banco: um item de outro menu
        // aponta para dentro deste. O RESTRICT da hierarquia derrubaria a
        // exclusão no meio, então a operação recusa **antes** do primeiro
        // DELETE — e nada é removido.
        //
        // Este teste prova a pré-validação, e não o rollback depois de uma
        // escrita parcial: nenhuma linha chega a ser excluída aqui. A prova de
        // rollback real, com uma camada já removida, exige injetar uma falha no
        // meio da operação e vive em `MenuConcurrencyTest`, que roda no MySQL.
        $menu = $this->menu('main');
        $a = $this->item($menu, ['label' => 'A']);
        $b = $this->item($menu, ['label' => 'B', 'parent_id' => $a->id]);

        $other = $this->menu('footer');
        $intruder = MenuItem::factory()->create(['menu_id' => $other->id, 'parent_id' => $b->id]);

        try {
            $this->service()->deleteMenu($menu);

            $this->fail('A exclusão de um menu com árvore inconsistente deveria falhar.');
        } catch (RuntimeException) {
            $this->assertDatabaseHas('menus', ['id' => $menu->id]);
            $this->assertDatabaseHas('menu_items', ['id' => $a->id]);
            $this->assertDatabaseHas('menu_items', ['id' => $b->id]);
            $this->assertDatabaseHas('menu_items', ['id' => $intruder->id]);
        }
    }

    public function test_an_item_cycle_written_directly_in_the_database_does_not_hang_the_deletion(): void
    {
        // Ciclo impossível de criar pelo serviço, montado por fora. O laço
        // precisa falhar, e não girar para sempre.
        $menu = $this->menu();
        $a = $this->item($menu, ['label' => 'A']);
        $b = $this->item($menu, ['label' => 'B', 'parent_id' => $a->id]);
        DB::table('menu_items')->where('id', $a->id)->update(['parent_id' => $b->id]);

        $this->expectException(RuntimeException::class);

        $this->service()->deleteMenu($menu);
    }

    // --- Item: criação -----------------------------------------------------

    public function test_it_creates_an_item_with_the_supported_fields(): void
    {
        $menu = $this->menu();

        $item = $this->service()->createItem($menu, [
            'label' => 'Contato',
            'type' => MenuItemType::Url,
            'url' => '/contato',
            'is_active' => true,
        ]);

        $this->assertTrue($item->exists);
        $this->assertDatabaseHas('menu_items', [
            'id' => $item->id,
            'menu_id' => $menu->id,
            'parent_id' => null,
            'label' => 'Contato',
            'type' => 'url',
            'page_id' => null,
            'url' => '/contato',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_a_new_item_is_inactive_by_default(): void
    {
        $this->assertFalse($this->item($this->menu())->is_active);
    }

    public function test_the_item_label_is_required(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->item($this->menu(), ['label' => '   ']);
    }

    public function test_the_item_label_is_trimmed(): void
    {
        $item = $this->item($this->menu(), ['label' => '  Contato  ']);

        $this->assertSame('Contato', $item->fresh()->label);
    }

    public function test_an_item_label_longer_than_the_column_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->item($this->menu(), ['label' => str_repeat('a', 121)]);
    }

    public function test_the_label_is_stored_as_plain_text(): void
    {
        // `label` é texto: nada é convertido, escapado ou removido na gravação.
        // O escape pertence à renderização.
        $item = $this->item($this->menu(), ['label' => 'Ofertas & <b>novidades</b>']);

        $this->assertSame('Ofertas & <b>novidades</b>', $item->fresh()->label);
    }

    public function test_a_sort_order_sent_by_the_caller_is_ignored(): void
    {
        $menu = $this->menu();
        $this->item($menu);

        $item = $this->item($menu, ['sort_order' => 99]);

        $this->assertSame(2, $item->fresh()->sort_order);
    }

    public function test_the_item_menu_comes_from_the_argument_and_not_from_the_payload(): void
    {
        $menu = $this->menu('main');
        $other = $this->menu('footer');

        $item = $this->service()->createItem($menu, [
            'label' => 'Contato',
            'type' => MenuItemType::Url,
            'url' => '/contato',
            'menu_id' => $other->id,
        ]);

        $this->assertSame($menu->id, $item->fresh()->menu_id);
    }

    // --- Item: destino -----------------------------------------------------

    public function test_a_page_item_stores_the_page_identity_and_no_url(): void
    {
        $menu = $this->menu();
        $page = $this->page();

        $item = $this->service()->createItem($menu, [
            'label' => 'Quem somos',
            'type' => MenuItemType::Page,
            'page_id' => $page->id,
        ]);

        $this->assertSame(MenuItemType::Page, $item->fresh()->type);
        $this->assertSame($page->id, $item->fresh()->page_id);
        $this->assertNull($item->fresh()->url);
    }

    public function test_a_page_item_can_point_to_a_draft_page(): void
    {
        // A publicabilidade é assunto da F2.6-C: publicar a página depois deve
        // restaurar a navegação sem reconfigurar o menu.
        $menu = $this->menu();
        $draft = $this->page('rascunho', PageStatus::Draft);

        $item = $this->service()->createItem($menu, [
            'label' => 'Rascunho',
            'type' => MenuItemType::Page,
            'page_id' => $draft->id,
        ]);

        $this->assertSame($draft->id, $item->fresh()->page_id);
    }

    public function test_a_page_item_requires_a_page_reference(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->createItem($this->menu(), [
            'label' => 'Sem destino',
            'type' => MenuItemType::Page,
        ]);
    }

    public function test_a_page_item_cannot_carry_a_url(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->createItem($this->menu(), [
            'label' => 'Ambíguo',
            'type' => MenuItemType::Page,
            'page_id' => $this->page()->id,
            'url' => '/outra-coisa',
        ]);
    }

    public function test_a_missing_page_is_rejected_as_a_domain_error(): void
    {
        try {
            $this->service()->createItem($this->menu(), [
                'label' => 'Fantasma',
                'type' => MenuItemType::Page,
                'page_id' => 987654,
            ]);

            $this->fail('Uma página inexistente deveria ser recusada.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('987654', $exception->getMessage());
            $this->assertSame(0, MenuItem::query()->count());
        }
    }

    public function test_a_trashed_page_cannot_be_linked_by_a_new_item(): void
    {
        // Criar um vínculo novo com algo que o administrador acabou de excluir é
        // engano. Um vínculo já existente cujo destino vá para a lixeira depois
        // é preservado — isso é outra coisa.
        $menu = $this->menu();
        $page = $this->page();
        $page->delete();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->createItem($menu, [
            'label' => 'Na lixeira',
            'type' => MenuItemType::Page,
            'page_id' => $page->id,
        ]);
    }

    public function test_a_url_item_stores_the_url_and_no_page(): void
    {
        $item = $this->item($this->menu(), ['url' => '/promocoes']);

        $this->assertSame(MenuItemType::Url, $item->fresh()->type);
        $this->assertSame('/promocoes', $item->fresh()->url);
        $this->assertNull($item->fresh()->page_id);
    }

    public function test_a_url_item_requires_a_url(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->createItem($this->menu(), [
            'label' => 'Sem destino',
            'type' => MenuItemType::Url,
        ]);
    }

    public function test_a_whitespace_only_url_does_not_make_a_valid_item(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->item($this->menu(), ['url' => '   ']);
    }

    public function test_a_url_item_cannot_reference_a_page(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->createItem($this->menu(), [
            'label' => 'Ambíguo',
            'type' => MenuItemType::Url,
            'url' => '/contato',
            'page_id' => $this->page()->id,
        ]);
    }

    public function test_an_unsupported_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->createItem($this->menu(), [
            'label' => 'Produto',
            'type' => 'product',
            'url' => '/produto',
        ]);
    }

    #[DataProvider('validUrlProvider')]
    public function test_it_accepts_a_url_inside_the_contract(string $url): void
    {
        $item = $this->item($this->menu(), ['url' => $url]);

        $this->assertSame($url, $item->fresh()->url);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function validUrlProvider(): array
    {
        return [
            'raiz' => ['/'],
            'caminho interno' => ['/contato'],
            'caminho aninhado' => ['/paginas/quem-somos'],
            'com query string' => ['/busca?cor=azul&tamanho=m'],
            'https com caminho' => ['https://example.com/campanha'],
            'http com caminho' => ['http://example.com/promocao'],
            'https sem caminho' => ['https://example.com'],
        ];
    }

    #[DataProvider('invalidUrlProvider')]
    public function test_it_rejects_a_url_outside_the_contract(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->item($this->menu(), ['url' => $url]);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function invalidUrlProvider(): array
    {
        return [
            'protocol relative' => ['//example.com'],
            'barra invertida' => ['\\contato'],
            'barra invertida apos barra' => ['/\\evil.example'],
            'sem esquema e sem barra' => ['contato'],
            'host sem esquema' => ['www.example.com'],
            'javascript' => ['javascript:alert(1)'],
            'javascript maiusculo' => ['JavaScript:alert(1)'],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'vbscript' => ['vbscript:msgbox(1)'],
            'file' => ['file:///etc/passwd'],
            'ftp' => ['ftp://example.com/arquivo'],
            'mailto' => ['mailto:contato@example.com'],
            'http sem host' => ['http://'],
            'https sem host' => ['https://'],
        ];
    }

    public function test_the_normalization_never_invents_a_scheme(): void
    {
        try {
            $this->item($this->menu(), ['url' => 'www.example.com']);

            $this->fail('Um host sem esquema deveria ser recusado.');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, MenuItem::query()->where('url', 'https://www.example.com')->count());
        }
    }

    public function test_a_url_longer_than_the_column_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->item($this->menu(), ['url' => '/'.str_repeat('a', 2048)]);
    }

    public function test_the_supported_url_check_mirrors_the_persistence_rule(): void
    {
        $this->assertTrue($this->service()->isSupportedUrl('/contato'));
        $this->assertFalse($this->service()->isSupportedUrl('javascript:alert(1)'));
    }

    // --- Item: hierarquia ---------------------------------------------------

    public function test_an_item_can_be_created_under_a_parent(): void
    {
        $menu = $this->menu();
        $parent = $this->item($menu, ['label' => 'Produtos']);

        $child = $this->item($menu, ['label' => 'Masculino', 'parent_id' => $parent->id]);

        $this->assertSame($parent->id, $child->fresh()->parent_id);
    }

    public function test_the_tree_accepts_more_than_three_levels(): void
    {
        // Nenhum teto artificial de dois ou três níveis foi introduzido.
        $menu = $this->menu();
        $parentId = null;
        $ids = [];

        foreach (range(1, 5) as $level) {
            $item = $this->item($menu, ['label' => "Nível {$level}", 'parent_id' => $parentId]);
            $ids[] = $item->id;
            $parentId = $item->id;
        }

        $this->assertSame(5, MenuItem::query()->where('menu_id', $menu->id)->count());
        $this->assertSame($ids[3], MenuItem::query()->findOrFail($ids[4])->parent_id);
    }

    public function test_a_parent_from_another_menu_is_rejected(): void
    {
        // A FK não protege esta invariante: o pai existe, só está no menu errado.
        $menu = $this->menu('main');
        $other = $this->menu('footer');
        $foreignParent = $this->item($other, ['label' => 'De outro menu']);

        $this->expectException(InvalidArgumentException::class);

        $this->item($menu, ['label' => 'Órfão', 'parent_id' => $foreignParent->id]);
    }

    public function test_a_missing_parent_is_rejected_as_a_domain_error(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->item($this->menu(), ['parent_id' => 987654]);
    }

    public function test_an_item_cannot_be_its_own_parent(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->updateItem($item, ['parent_id' => $item->id]);
    }

    public function test_a_direct_cycle_is_rejected(): void
    {
        // A vira filho de B, que já é filho de A.
        $menu = $this->menu();
        $a = $this->item($menu, ['label' => 'A']);
        $b = $this->item($menu, ['label' => 'B', 'parent_id' => $a->id]);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->updateItem($a, ['parent_id' => $b->id]);
    }

    public function test_an_indirect_cycle_is_rejected(): void
    {
        // A → B → C; tentar A.parent = C fecharia o ciclo dois níveis abaixo.
        $menu = $this->menu();
        $a = $this->item($menu, ['label' => 'A']);
        $b = $this->item($menu, ['label' => 'B', 'parent_id' => $a->id]);
        $c = $this->item($menu, ['label' => 'C', 'parent_id' => $b->id]);

        try {
            $this->service()->updateItem($a, ['parent_id' => $c->id]);

            $this->fail('Um ciclo indireto deveria ser recusado.');
        } catch (InvalidArgumentException) {
            $this->assertNull($a->fresh()->parent_id);
            $this->assertSame($b->id, $c->fresh()->parent_id);
        }
    }

    public function test_a_deep_indirect_cycle_is_rejected(): void
    {
        $menu = $this->menu();
        $parentId = null;
        $items = [];

        foreach (range(1, 5) as $level) {
            $items[] = $item = $this->item($menu, ['label' => "N{$level}", 'parent_id' => $parentId]);
            $parentId = $item->id;
        }

        $this->expectException(InvalidArgumentException::class);

        $this->service()->updateItem($items[0], ['parent_id' => $items[4]->id]);
    }

    public function test_the_menu_of_an_item_is_immutable(): void
    {
        $menu = $this->menu('main');
        $other = $this->menu('footer');
        $item = $this->item($menu);

        try {
            $this->service()->updateItem($item, ['menu_id' => $other->id, 'label' => 'Movido']);

            $this->fail('Mover um item entre menus deveria ser recusado.');
        } catch (InvalidArgumentException) {
            $this->assertSame($menu->id, $item->fresh()->menu_id);
            $this->assertNotSame('Movido', $item->fresh()->label);
        }
    }

    public function test_resubmitting_the_same_menu_id_is_accepted(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu);

        $this->service()->updateItem($item, ['menu_id' => $menu->id, 'label' => 'Renomeado']);

        $this->assertSame('Renomeado', $item->fresh()->label);
    }

    // --- Item: ordenação ----------------------------------------------------

    public function test_the_first_item_of_a_group_receives_sort_order_one(): void
    {
        $this->assertSame(1, $this->item($this->menu())->fresh()->sort_order);
    }

    public function test_the_second_item_of_a_group_receives_sort_order_two(): void
    {
        $menu = $this->menu();
        $this->item($menu);

        $this->assertSame(2, $this->item($menu)->fresh()->sort_order);
    }

    public function test_each_sibling_group_starts_its_own_sequence(): void
    {
        // Não existe ordem global: comparar a ordem de um raiz com a de um neto
        // não significa nada.
        $menu = $this->menu();
        $firstRoot = $this->item($menu, ['label' => 'Produtos']);
        $secondRoot = $this->item($menu, ['label' => 'Contato']);
        $firstChild = $this->item($menu, ['label' => 'Masculino', 'parent_id' => $firstRoot->id]);
        $secondChild = $this->item($menu, ['label' => 'Feminino', 'parent_id' => $firstRoot->id]);
        $otherChild = $this->item($menu, ['label' => 'Mapa', 'parent_id' => $secondRoot->id]);

        $this->assertSame(1, $firstRoot->fresh()->sort_order);
        $this->assertSame(2, $secondRoot->fresh()->sort_order);
        $this->assertSame(1, $firstChild->fresh()->sort_order);
        $this->assertSame(2, $secondChild->fresh()->sort_order);
        $this->assertSame(1, $otherChild->fresh()->sort_order);
    }

    public function test_each_menu_starts_its_own_root_sequence(): void
    {
        $menu = $this->menu('main');
        $other = $this->menu('footer');

        $this->item($menu);
        $secondOfMenu = $this->item($menu);
        $firstOfOther = $this->item($other);

        $this->assertSame(2, $secondOfMenu->fresh()->sort_order);
        $this->assertSame(1, $firstOfOther->fresh()->sort_order);
    }

    public function test_the_ordered_siblings_query_follows_sort_order_and_then_id(): void
    {
        $menu = $this->menu();

        $third = MenuItem::factory()->create(['menu_id' => $menu->id, 'sort_order' => 3]);
        $firstTie = MenuItem::factory()->create(['menu_id' => $menu->id, 'sort_order' => 1]);
        $secondTie = MenuItem::factory()->create(['menu_id' => $menu->id, 'sort_order' => 1]);

        $this->assertSame(
            [$firstTie->id, $secondTie->id, $third->id],
            $this->service()->orderedSiblings($menu)->pluck('id')->all(),
        );
    }

    public function test_the_ordered_siblings_query_asks_the_database_for_the_contracted_order(): void
    {
        // O desempate por `id` raramente é observável pelo resultado — no InnoDB
        // a chave primária já compõe as entradas do índice secundário. Por isso
        // a verificação é sobre a cláusula, não sobre a coincidência do plano.
        $statements = [];
        DB::listen(function (QueryExecuted $query) use (&$statements): void {
            $statements[] = $query->sql;
        });

        $this->service()->orderedSiblings($this->menu());

        $this->assertMatchesRegularExpression(
            '/order by .?sort_order.? asc, .?id.? asc/i',
            (string) end($statements),
        );
    }

    public function test_the_ordered_siblings_query_is_scoped_to_the_group(): void
    {
        $menu = $this->menu();
        $root = $this->item($menu, ['label' => 'Produtos']);
        $child = $this->item($menu, ['label' => 'Masculino', 'parent_id' => $root->id]);

        $this->assertSame([$root->id], $this->service()->orderedSiblings($menu)->pluck('id')->all());
        $this->assertSame([$child->id], $this->service()->orderedSiblings($menu, $root->id)->pluck('id')->all());
    }

    public function test_an_empty_group_returns_an_empty_collection(): void
    {
        $this->assertTrue($this->service()->orderedSiblings($this->menu())->isEmpty());
    }

    // --- Item: atualização --------------------------------------------------

    #[DataProvider('ordinaryUpdateProvider')]
    public function test_an_ordinary_update_preserves_the_order(string $field, mixed $value): void
    {
        $menu = $this->menu();
        $this->item($menu);
        $item = $this->item($menu);
        $this->item($menu);

        $payload = $field === 'destination'
            ? ['type' => MenuItemType::Url, 'url' => '/novo-destino']
            : [$field => $value];

        $this->service()->updateItem($item, $payload);

        $this->assertSame(2, $item->fresh()->sort_order);
        $this->assertNull($item->fresh()->parent_id);
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function ordinaryUpdateProvider(): array
    {
        return [
            'label' => ['label', 'Outro rótulo'],
            'estado' => ['is_active', true],
            'destino' => ['destination', null],
        ];
    }

    public function test_resubmitting_the_same_parent_preserves_the_order(): void
    {
        $menu = $this->menu();
        $parent = $this->item($menu, ['label' => 'Produtos']);
        $this->item($menu, ['label' => 'Masculino', 'parent_id' => $parent->id]);
        $item = $this->item($menu, ['label' => 'Feminino', 'parent_id' => $parent->id]);

        $this->service()->updateItem($item, ['parent_id' => $parent->id, 'label' => 'Feminino adulto']);

        $this->assertSame(2, $item->fresh()->sort_order);
    }

    public function test_the_updated_values_are_persisted(): void
    {
        $menu = $this->menu();
        $page = $this->page();
        $item = $this->item($menu, ['label' => 'Antigo', 'url' => '/antigo']);

        $this->service()->updateItem($item, [
            'label' => 'Quem somos',
            'type' => MenuItemType::Page,
            'page_id' => $page->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('menu_items', [
            'id' => $item->id,
            'label' => 'Quem somos',
            'type' => 'page',
            'page_id' => $page->id,
            'url' => null,
            'is_active' => true,
        ]);
    }

    public function test_changing_a_page_item_into_a_url_item_clears_the_page(): void
    {
        $menu = $this->menu();
        $item = $this->service()->createItem($menu, [
            'label' => 'Quem somos',
            'type' => MenuItemType::Page,
            'page_id' => $this->page()->id,
        ]);

        $this->service()->updateItem($item, ['type' => MenuItemType::Url, 'url' => '/contato']);

        $this->assertNull($item->fresh()->page_id);
        $this->assertSame('/contato', $item->fresh()->url);
    }

    public function test_touching_the_destination_without_the_type_is_rejected(): void
    {
        // Aceitar só `url` num item `page` obrigaria o serviço a adivinhar se o
        // chamador quis trocar de tipo ou preencher um campo que não vale ali.
        $menu = $this->menu();
        $item = $this->service()->createItem($menu, [
            'label' => 'Quem somos',
            'type' => MenuItemType::Page,
            'page_id' => $this->page()->id,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->updateItem($item, ['url' => '/contato']);
    }

    public function test_an_update_validates_the_url_as_strictly_as_the_creation(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu, ['url' => '/contato']);

        try {
            $this->service()->updateItem($item, ['type' => MenuItemType::Url, 'url' => 'javascript:alert(1)']);

            $this->fail('Um esquema inseguro deveria ser recusado também na atualização.');
        } catch (InvalidArgumentException) {
            $this->assertSame('/contato', $item->fresh()->url);
        }
    }

    public function test_an_update_ignores_a_sort_order_sent_by_the_caller(): void
    {
        $menu = $this->menu();
        $this->item($menu);
        $item = $this->item($menu);

        $this->service()->updateItem($item, ['label' => 'Outro', 'sort_order' => 99]);

        $this->assertSame(2, $item->fresh()->sort_order);
    }

    // --- Item: mudança de pai -----------------------------------------------

    public function test_changing_the_parent_moves_the_item_to_the_end_of_the_destination(): void
    {
        $menu = $this->menu();
        $origin = $this->item($menu, ['label' => 'Origem']);
        $destination = $this->item($menu, ['label' => 'Destino']);

        $this->item($menu, ['label' => 'Já lá', 'parent_id' => $destination->id]);
        $moved = $this->item($menu, ['label' => 'Movido', 'parent_id' => $origin->id]);

        $this->service()->updateItem($moved, ['parent_id' => $destination->id]);

        $this->assertSame($destination->id, $moved->fresh()->parent_id);
        $this->assertSame(2, $moved->fresh()->sort_order);
    }

    public function test_changing_to_an_empty_parent_receives_sort_order_one(): void
    {
        $menu = $this->menu();
        $origin = $this->item($menu, ['label' => 'Origem']);
        $destination = $this->item($menu, ['label' => 'Destino']);
        $moved = $this->item($menu, ['label' => 'Movido', 'parent_id' => $origin->id]);

        $this->service()->updateItem($moved, ['parent_id' => $destination->id]);

        $this->assertSame(1, $moved->fresh()->sort_order);
    }

    public function test_moving_an_item_to_the_root_appends_it_to_the_end_of_the_roots(): void
    {
        $menu = $this->menu();
        $first = $this->item($menu, ['label' => 'Primeiro']);
        $this->item($menu, ['label' => 'Segundo']);
        $child = $this->item($menu, ['label' => 'Filho', 'parent_id' => $first->id]);

        $this->service()->updateItem($child, ['parent_id' => null]);

        $this->assertNull($child->fresh()->parent_id);
        $this->assertSame(3, $child->fresh()->sort_order);
    }

    public function test_changing_the_parent_does_not_compact_the_origin(): void
    {
        $menu = $this->menu();
        $origin = $this->item($menu, ['label' => 'Origem']);
        $destination = $this->item($menu, ['label' => 'Destino']);

        $first = $this->item($menu, ['label' => 'A', 'parent_id' => $origin->id]);
        $second = $this->item($menu, ['label' => 'B', 'parent_id' => $origin->id]);
        $third = $this->item($menu, ['label' => 'C', 'parent_id' => $origin->id]);

        $this->service()->updateItem($second, ['parent_id' => $destination->id]);

        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(3, $third->fresh()->sort_order);
    }

    // --- Item: exclusão -----------------------------------------------------

    public function test_a_leaf_is_deleted(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu);

        $this->service()->deleteItem($item);

        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
        $this->assertDatabaseHas('menus', ['id' => $menu->id]);
    }

    public function test_deleting_an_item_is_physical(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu);

        $this->service()->deleteItem($item);

        $this->assertSame(0, MenuItem::query()->withoutGlobalScopes()->whereKey($item->id)->count());
    }

    public function test_deleting_an_item_with_children_is_refused(): void
    {
        $menu = $this->menu();
        $parent = $this->item($menu, ['label' => 'Produtos']);
        $child = $this->item($menu, ['label' => 'Masculino', 'parent_id' => $parent->id]);

        try {
            $this->service()->deleteItem($parent);

            $this->fail('Excluir um item com filhos deveria ser recusado.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseHas('menu_items', ['id' => $parent->id]);
            // Os filhos não são promovidos nem anulados.
            $this->assertDatabaseHas('menu_items', ['id' => $child->id, 'parent_id' => $parent->id]);
        }
    }

    public function test_deleting_an_item_does_not_compact_the_remaining_order(): void
    {
        $menu = $this->menu();
        $first = $this->item($menu, ['label' => 'A']);
        $second = $this->item($menu, ['label' => 'B']);
        $third = $this->item($menu, ['label' => 'C']);

        $this->service()->deleteItem($second);

        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(3, $third->fresh()->sort_order);
    }

    public function test_deleting_an_item_keeps_its_page(): void
    {
        $menu = $this->menu();
        $page = $this->page();
        $item = $this->service()->createItem($menu, [
            'label' => 'Quem somos',
            'type' => MenuItemType::Page,
            'page_id' => $page->id,
        ]);

        $this->service()->deleteItem($item);

        $this->assertDatabaseHas('pages', ['id' => $page->id]);
    }

    // --- Atomicidade ---------------------------------------------------------

    public function test_a_failed_item_creation_persists_nothing(): void
    {
        $menu = $this->menu();
        $this->item($menu);

        try {
            $this->service()->createItem($menu, ['label' => '', 'type' => MenuItemType::Url, 'url' => '/x']);

            $this->fail('A criação inválida deveria falhar.');
        } catch (InvalidArgumentException) {
            $this->assertSame(1, MenuItem::query()->where('menu_id', $menu->id)->count());
        }
    }

    public function test_a_failed_update_leaves_the_item_untouched(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu, ['label' => 'Original']);

        try {
            $this->service()->updateItem($item, ['label' => 'Novo', 'parent_id' => 987654]);

            $this->fail('A atualização inválida deveria falhar.');
        } catch (InvalidArgumentException) {
            $this->assertSame('Original', $item->fresh()->label);
        }
    }

    // --- Helpers -------------------------------------------------------------

    private function service(): MenuService
    {
        return app(MenuService::class);
    }

    private function menu(string $code = 'main'): Menu
    {
        return $this->service()->createMenu(['name' => 'Menu '.$code, 'code' => $code]);
    }

    /**
     * Item criado pelo serviço, com destino `url` por padrão.
     *
     * O destino padrão não exige nenhuma outra entidade, enquanto `page`
     * obrigaria todo teste de árvore a criar páginas que ele não usa.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function item(Menu $menu, array $overrides = []): MenuItem
    {
        return $this->service()->createItem($menu, array_merge([
            'label' => 'Item',
            'type' => MenuItemType::Url,
            'url' => '/item',
        ], $overrides));
    }

    /**
     * Página de destino para os itens do tipo `page`.
     *
     * `Page` não tem factory no projeto — os testes da F2.4 criam as páginas
     * pelo próprio model, e esta subfase segue a mesma convenção.
     */
    private function page(string $slug = 'quem-somos', PageStatus $status = PageStatus::Published): Page
    {
        return Page::create([
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'content' => '# Conteúdo',
            'status' => $status,
        ]);
    }
}
