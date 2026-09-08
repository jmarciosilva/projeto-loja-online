<?php

namespace Database\Factories;

use App\Enums\MenuItemType;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Itens de menu para os testes, sem passar pelo `MenuService`.
 *
 * A factory monta apenas a linha da tabela — inclusive `sort_order`, que aqui é
 * deliberadamente explícito: os testes de ordenação precisam fixar a ordem para
 * provar a regra, e escondê-la atrás do serviço tornaria o próprio serviço a
 * fixture do teste que deveria verificá-lo.
 *
 * O destino padrão é `url` com um caminho interno: ele não exige nenhuma outra
 * entidade, enquanto `page` obrigaria toda fixture de árvore a criar páginas
 * que o teste não usa.
 *
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_id' => Menu::factory(),
            'parent_id' => null,
            'label' => ucfirst(fake()->words(2, true)),
            'type' => MenuItemType::Url,
            'page_id' => null,
            'url' => '/'.fake()->slug(2),
            'sort_order' => 1,
            // O default do schema é inativo; a factory o reproduz para que um
            // item de fixture nunca apareça publicamente por acidente.
            'is_active' => false,
        ];
    }
}
