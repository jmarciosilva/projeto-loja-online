<?php

namespace Database\Factories;

use App\Enums\BannerPosition;
use App\Models\Banner;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Banners para os testes, sem passar pelo `BannerService`.
 *
 * A factory monta apenas a linha da tabela — inclusive `sort_order`, que aqui
 * é deliberadamente explícito: os testes de ordenação precisam fixar a ordem
 * para provar a regra, e escondê-la atrás do serviço tornaria o próprio
 * serviço a fixture do teste que deveria verificá-lo.
 *
 * `media_id` sempre aponta para uma mídia real: o banner referencia a
 * biblioteca da F2.7, e a FK recusaria qualquer valor inventado.
 *
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->words(2, true)),
            'media_id' => Media::factory(),
            'position' => fake()->randomElement(BannerPosition::cases()),
            'link_url' => null,
            'alt_text' => fake()->sentence(4),
            'sort_order' => 1,
            // O default do schema é inativo; a factory o reproduz para que um
            // banner de fixture nunca apareça publicamente por acidente.
            'is_active' => false,
        ];
    }
}
