<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Services\MenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Contrato da ordenação administrativa — F2.6-B.
 *
 * A F2.6-A fundou a ordem: cada item nasce no fim do seu grupo de irmãos, e a
 * exclusão pode deixar lacunas. Aqui entra a única operação que **regrava** essa
 * ordem, e ela vive no `MenuService` porque é invariante de domínio, não de
 * tela: quem consumir o serviço fora do HTTP recebe as mesmas recusas.
 *
 * Está num arquivo próprio, e não em `MenuServiceTest`, porque o assunto é
 * outro — lá está o núcleo fechado da F2.6-A, que esta subfase não altera.
 *
 * O que se prova aqui:
 *
 * ```text
 * a sequência precisa descrever exatamente o grupo (menu_id, parent_id)
 * a recusa acontece antes de qualquer escrita
 * só sort_order muda
 * a gravação normaliza 1..N
 * subir/descer usam a mesma regra e nunca saem do grupo
 * ```
 */
class MenuReorderTest extends TestCase
{
    use RefreshDatabase;

    private MenuService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MenuService::class);
    }

    // --- Normalização -----------------------------------------------------

    public function test_it_rewrites_the_group_in_the_received_order(): void
    {
        $menu = Menu::factory()->create();
        [$a, $b, $c] = $this->roots($menu, 3);

        $this->service->reorderSiblings($menu, null, [$c->id, $a->id, $b->id]);

        $this->assertSame([$c->id, $a->id, $b->id], $this->orderOf($menu, null));
    }

    public function test_the_explicit_reorder_normalizes_the_group_to_one_through_n(): void
    {
        $menu = Menu::factory()->create();

        // Lacunas são resíduo legítimo de uma exclusão na F2.6-A; quem pede
        // para reordenar, porém, espera a lista renumerada.
        $a = MenuItem::factory()->for($menu)->create(['sort_order' => 4]);
        $b = MenuItem::factory()->for($menu)->create(['sort_order' => 9]);
        $c = MenuItem::factory()->for($menu)->create(['sort_order' => 17]);

        $this->service->reorderSiblings($menu, null, [$b->id, $c->id, $a->id]);

        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(2, $c->fresh()->sort_order);
        $this->assertSame(3, $a->fresh()->sort_order);
    }

    public function test_the_reorder_changes_nothing_beyond_the_sort_order(): void
    {
        $menu = Menu::factory()->create();
        $parent = MenuItem::factory()->for($menu)->create(['sort_order' => 1]);
        $child = MenuItem::factory()->for($menu)->create([
            'parent_id' => $parent->id,
            'label' => 'Filho',
            'url' => '/filho',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->service->reorderSiblings($menu, $parent->id, [$child->id]);

        $renumbered = $child->fresh();

        $this->assertSame($menu->id, $renumbered->menu_id);
        $this->assertSame($parent->id, $renumbered->parent_id);
        $this->assertSame('Filho', $renumbered->label);
        $this->assertSame('/filho', $renumbered->url);
        $this->assertTrue($renumbered->is_active);
    }

    // --- Recusas ----------------------------------------------------------

    public function test_a_duplicated_identifier_is_rejected(): void
    {
        $menu = Menu::factory()->create();
        [$a, $b] = $this->roots($menu, 2);

        $this->expectException(InvalidArgumentException::class);

        $this->service->reorderSiblings($menu, null, [$a->id, $a->id, $b->id]);
    }

    public function test_a_missing_identifier_is_rejected(): void
    {
        $menu = Menu::factory()->create();
        [$a, $b, $c] = $this->roots($menu, 3);

        // Um conjunto parcial seria ambíguo: os ausentes ficariam à deriva,
        // atrás ou à frente dos reposicionados, dependendo de números que quem
        // chamou não enxergava.
        $this->expectException(InvalidArgumentException::class);

        $this->service->reorderSiblings($menu, null, [$c->id, $a->id]);
    }

    public function test_an_unknown_identifier_is_rejected(): void
    {
        $menu = Menu::factory()->create();
        [$a, $b] = $this->roots($menu, 2);

        $this->expectException(InvalidArgumentException::class);

        $this->service->reorderSiblings($menu, null, [$a->id, $b->id, $a->id + $b->id + 999]);
    }

    public function test_an_item_from_another_menu_is_rejected(): void
    {
        $menu = Menu::factory()->create();
        [$a, $b] = $this->roots($menu, 2);
        $foreign = MenuItem::factory()->create(['sort_order' => 1]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->reorderSiblings($menu, null, [$a->id, $b->id, $foreign->id]);
    }

    public function test_an_item_from_another_parent_is_rejected(): void
    {
        $menu = Menu::factory()->create();
        [$a, $b] = $this->roots($menu, 2);
        $child = MenuItem::factory()->for($menu)->create(['parent_id' => $a->id, 'sort_order' => 1]);

        // Mesmo menu, grupo diferente: reordenar não move item entre grupos.
        $this->expectException(InvalidArgumentException::class);

        $this->service->reorderSiblings($menu, null, [$a->id, $b->id, $child->id]);
    }

    public function test_a_rejected_sequence_leaves_the_group_untouched(): void
    {
        $menu = Menu::factory()->create();
        [$a, $b, $c] = $this->roots($menu, 3);

        try {
            // O id inválido é o último: se a gravação acontecesse enquanto
            // confere, os dois primeiros já teriam sido renumerados.
            $this->service->reorderSiblings($menu, null, [$c->id, $b->id, 0]);
            $this->fail('A sequência inválida deveria ter sido recusada.');
        } catch (InvalidArgumentException) {
            // esperado
        }

        $this->assertSame(1, $a->fresh()->sort_order);
        $this->assertSame(2, $b->fresh()->sort_order);
        $this->assertSame(3, $c->fresh()->sort_order);
    }

    public function test_reordering_a_group_does_not_touch_another_group(): void
    {
        $menu = Menu::factory()->create();
        [$a, $b] = $this->roots($menu, 2);
        $first = MenuItem::factory()->for($menu)->create(['parent_id' => $a->id, 'sort_order' => 1]);
        $second = MenuItem::factory()->for($menu)->create(['parent_id' => $a->id, 'sort_order' => 2]);

        $this->service->reorderSiblings($menu, null, [$b->id, $a->id]);

        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(2, $second->fresh()->sort_order);
    }

    public function test_reordering_a_group_does_not_touch_another_menu(): void
    {
        $menu = Menu::factory()->create();
        [$a, $b] = $this->roots($menu, 2);

        $other = Menu::factory()->create();
        [$x, $y] = $this->roots($other, 2);

        $this->service->reorderSiblings($menu, null, [$b->id, $a->id]);

        $this->assertSame([$x->id, $y->id], $this->orderOf($other, null));
    }

    // --- Subir e descer ---------------------------------------------------

    public function test_an_item_moves_up_one_position(): void
    {
        $menu = Menu::factory()->create();
        [$a, $b, $c] = $this->roots($menu, 3);

        $this->service->moveItemUp($c);

        $this->assertSame([$a->id, $c->id, $b->id], $this->orderOf($menu, null));
    }

    public function test_an_item_moves_down_one_position(): void
    {
        $menu = Menu::factory()->create();
        [$a, $b, $c] = $this->roots($menu, 3);

        $this->service->moveItemDown($a);

        $this->assertSame([$b->id, $a->id, $c->id], $this->orderOf($menu, null));
    }

    public function test_moving_the_first_item_up_is_a_no_op(): void
    {
        $menu = Menu::factory()->create();
        [$a, $b, $c] = $this->roots($menu, 3);

        $this->service->moveItemUp($a);

        $this->assertSame([$a->id, $b->id, $c->id], $this->orderOf($menu, null));
        $this->assertSame(1, $a->fresh()->sort_order);
    }

    public function test_moving_the_last_item_down_is_a_no_op(): void
    {
        $menu = Menu::factory()->create();
        [$a, $b, $c] = $this->roots($menu, 3);

        $this->service->moveItemDown($c);

        $this->assertSame([$a->id, $b->id, $c->id], $this->orderOf($menu, null));
        $this->assertSame(3, $c->fresh()->sort_order);
    }

    public function test_moving_normalizes_a_group_that_had_gaps(): void
    {
        $menu = Menu::factory()->create();
        $a = MenuItem::factory()->for($menu)->create(['sort_order' => 1]);
        $b = MenuItem::factory()->for($menu)->create(['sort_order' => 7]);

        $this->service->moveItemUp($b);

        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
    }

    public function test_moving_never_leaves_the_sibling_group(): void
    {
        $menu = Menu::factory()->create();
        $parent = MenuItem::factory()->for($menu)->create(['sort_order' => 1]);
        $sibling = MenuItem::factory()->for($menu)->create(['sort_order' => 2]);
        $child = MenuItem::factory()->for($menu)->create(['parent_id' => $parent->id, 'sort_order' => 1]);

        // O primeiro filho não vira irmão do pai ao subir: a ordem é relativa
        // dentro do grupo, e trocar de grupo é mudar de pai.
        $this->service->moveItemUp($child);

        $this->assertSame($parent->id, $child->fresh()->parent_id);
        $this->assertSame(1, $child->fresh()->sort_order);
        $this->assertSame([$parent->id, $sibling->id], $this->orderOf($menu, null));
    }

    public function test_moving_a_child_only_reorders_its_own_group(): void
    {
        $menu = Menu::factory()->create();
        $parent = MenuItem::factory()->for($menu)->create(['sort_order' => 1]);
        $root = MenuItem::factory()->for($menu)->create(['sort_order' => 2]);
        $first = MenuItem::factory()->for($menu)->create(['parent_id' => $parent->id, 'sort_order' => 1]);
        $second = MenuItem::factory()->for($menu)->create(['parent_id' => $parent->id, 'sort_order' => 2]);

        $this->service->moveItemDown($first);

        $this->assertSame([$second->id, $first->id], $this->orderOf($menu, $parent->id));
        $this->assertSame([$parent->id, $root->id], $this->orderOf($menu, null));
    }

    // --- Apoio ------------------------------------------------------------

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
     * Ids do grupo na ordem persistida, lidos pela consulta do próprio domínio.
     *
     * @return list<int>
     */
    private function orderOf(Menu $menu, ?int $parentId): array
    {
        return array_map('intval', $this->service->orderedSiblings($menu, $parentId)->modelKeys());
    }
}
