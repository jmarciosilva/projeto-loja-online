<?php

namespace App\Models;

use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Container de navegação — um conjunto ordenado e hierárquico de itens.
 *
 * `id` é a identidade interna; `code` é a **identidade técnica** pela qual um
 * consumidor público localiza o menu, e por isso é imutável depois da criação.
 * `name` é apenas identificação administrativa e pode mudar à vontade.
 *
 * `Menu` **não** é posição visual: não existem `location`, `position` ou
 * `header`/`footer` como coluna. Onde um menu aparece é decisão de quem o
 * consome — o layout pede pelo `code` que lhe interessa.
 *
 * `Menu` **não** usa `SoftDeletes`. A razão que os justifica em `Page` — manter
 * o slug reservado, porque é endereço público — não existe aqui: o menu não tem
 * endereço próprio.
 *
 * O model representa persistência. As invariantes — formato e imutabilidade do
 * `code`, e a exclusão da árvore das folhas para as raízes — pertencem ao
 * `MenuService`, e não a um Observer ou a um boot hook daqui.
 */
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    /**
     * Campos de negócio da entidade.
     *
     * `code` é mass-assignable porque é coluna legítima e precisa ser gravada
     * na criação. Quem impede que ele mude depois é o `MenuService`, que recusa
     * a alteração em vez de descartá-la em silêncio.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];

    /**
     * `is_active` chega como `tinyint`; sem o cast, um `assertTrue()` do
     * consumidor receberia `1`.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Itens deste menu — a árvore inteira, em todos os níveis.
     *
     * A relação é plana de propósito: a hierarquia vive em `parent_id`, e é
     * montada em memória por quem precisa dela. Uma relação "raízes" separada
     * esconderia que a leitura natural do menu é a lista completa.
     *
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }
}
