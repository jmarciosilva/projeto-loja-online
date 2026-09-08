<?php

namespace Database\Factories;

use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Menus para os testes, sem passar pelo `MenuService`.
 *
 * A factory monta apenas a linha da tabela. O `code` é gerado com um sufixo
 * aleatório porque a coluna é `UNIQUE` e os testes criam vários menus na mesma
 * transação — mas ele segue o formato canônico, para que uma fixture nunca
 * grave um valor que o serviço recusaria.
 *
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->words(2, true)),
            'code' => 'menu-'.Str::lower(Str::random(12)),
            // O default do schema é inativo; a factory o reproduz para que um
            // menu de fixture nunca apareça publicamente por acidente.
            'is_active' => false,
        ];
    }
}
