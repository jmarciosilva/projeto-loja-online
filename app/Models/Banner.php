<?php

namespace App\Models;

use App\Enums\BannerPosition;
use Database\Factories\BannerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Imagem posicionada no site, com ordem e estado.
 *
 * O banner **referencia** a mídia da F2.7 por `media_id → media.id` e nunca
 * copia `disk`, `path` ou URL: persistir caminho congelaria o armazenamento de
 * um ambiente, e trocar o disco ou o domínio invalidaria todos os registros de
 * uma vez. A URL continua sendo derivada da própria mídia.
 *
 * `Banner` **não** usa `SoftDeletes`. A razão que os justifica em `Page` —
 * manter o slug reservado, porque é endereço público — não existe aqui: o
 * banner não tem endereço próprio, é renderizado dentro de outra página.
 *
 * O model representa persistência. As invariantes — atribuição do `sort_order`,
 * ordem contextual à posição e normalização do `link_url` — pertencem ao
 * `BannerService`, e não a um Observer ou a um boot hook daqui.
 */
class Banner extends Model
{
    /** @use HasFactory<BannerFactory> */
    use HasFactory;

    /**
     * Campos de negócio da entidade.
     *
     * `sort_order` é mass-assignable porque é coluna legítima da entidade — e
     * porque a factory dos testes precisa fixá-lo para provar a ordenação. Quem
     * garante que ele não venha do formulário é o `BannerService`, que monta o
     * payload a partir da própria lista de campos suportados e nunca lê
     * `sort_order` do chamador.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'media_id',
        'position',
        'link_url',
        'alt_text',
        'sort_order',
        'is_active',
    ];

    /**
     * `media_id` e `sort_order` são inteiros; sem o cast, o driver do MySQL os
     * devolveria como string. `is_active` chega como `tinyint`, e sem o cast um
     * `assertTrue()` do consumidor receberia `1`.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'media_id' => 'integer',
            'position' => BannerPosition::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Imagem da biblioteca de mídia usada por este banner.
     *
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
