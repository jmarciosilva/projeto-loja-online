<?php

namespace App\Models;

use App\Enums\PageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Página estática institucional ou editorial.
 *
 * `id` é a identidade interna estável da página; `slug` é apenas seu endereço
 * público e pode mudar sem que a identidade mude. Por isso a resolução de rota
 * continua pela chave primária: trocar `getRouteKeyName()` para `slug` faria o
 * endereço voltar a ser identidade.
 *
 * O model representa persistência. As invariantes — geração, normalização,
 * disponibilidade e colisão de slug — pertencem ao `PageService`, e não a um
 * Observer ou a um boot hook daqui.
 */
class Page extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'content',
        'status',
        'meta_title',
        'meta_description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
        ];
    }
}
