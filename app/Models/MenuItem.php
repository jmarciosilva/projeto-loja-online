<?php

namespace App\Models;

use App\Enums\MenuItemType;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Um nó da árvore de navegação: rótulo, destino, ordem e estado.
 *
 * O item **pertence estruturalmente** a um menu — fora dele não significa nada,
 * não tem endereço próprio e não é compartilhável. `menu_id` é atribuído na
 * criação e não muda por atualização comum.
 *
 * O destino interno é o `page_id`, a **identidade** da página, e nunca o slug,
 * a URL pública ou o path: o slug é endereço e pode mudar, e persisti-lo faria
 * cada renomeação exigir uma varredura em `menu_items`.
 *
 * `MenuItem` **não** usa `SoftDeletes`, pela mesma razão de `Menu`.
 *
 * O model representa persistência. As invariantes — exclusividade entre
 * `page_id` e `url`, contrato da URL, atribuição do `sort_order`, ausência de
 * ciclos e pertencimento do pai ao mesmo menu — pertencem ao `MenuService`.
 */
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    /**
     * Campos de negócio da entidade.
     *
     * `sort_order` e `menu_id` são mass-assignable porque são colunas legítimas
     * e a factory dos testes precisa fixá-los para provar a ordenação e a
     * hierarquia. Quem garante que eles não venham do formulário é o
     * `MenuService`, que monta o payload a partir da própria lista de campos
     * suportados e nunca lê `sort_order` do chamador.
     *
     * @var list<string>
     */
    protected $fillable = [
        'menu_id',
        'parent_id',
        'label',
        'type',
        'page_id',
        'url',
        'sort_order',
        'is_active',
    ];

    /**
     * Sem os casts inteiros, o driver do MySQL devolveria as chaves como
     * string; sem o cast booleano, `is_active` voltaria como `1`.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'menu_id' => 'integer',
            'parent_id' => 'integer',
            'page_id' => 'integer',
            'type' => MenuItemType::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Menu ao qual este item pertence.
     *
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * Item imediatamente acima na árvore, ou `null` quando este é raiz.
     *
     * @return BelongsTo<MenuItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Filhos diretos deste item.
     *
     * @return HasMany<MenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Página de destino, quando o item é do tipo `page`.
     *
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
