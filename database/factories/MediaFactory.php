<?php

namespace Database\Factories;

use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Registros de mídia para os testes, **sem tocar o sistema de arquivos**.
 *
 * A factory monta apenas a linha da tabela: nenhum arquivo é gravado, nenhum
 * upload acontece e o Intervention Image não é chamado. Isso é deliberado — a
 * F2.7-A precisa ser exercitável sem HTTP e sem upload.
 *
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'disk' => MediaService::DISK,
            // O path segue o formato do contrato, mas é montado aqui em vez de
            // vir do MediaService: o teste que verifica a geração de path deve
            // exercitar o serviço, não uma cópia sua nas fixtures.
            'path' => 'media/'.now()->format('Y/m').'/'.Str::ulid().'.jpg',
            'original_name' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(1_024, 5_242_880),
            'width' => 1_200,
            'height' => 800,
        ];
    }
}
