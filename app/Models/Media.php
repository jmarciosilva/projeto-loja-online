<?php

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Arquivo de imagem da biblioteca de mídia.
 *
 * `id` é a identidade interna estável; `disk` e `path` apenas localizam o
 * arquivo e podem mudar sem que a identidade mude. Consumidores futuros
 * referenciam a mídia por `media_id → media.id`, nunca por caminho ou URL.
 *
 * Não há coluna de URL: ela é derivada do Filesystem pelo `MediaService`.
 * Persisti-la congelaria o valor de um ambiente.
 *
 * `Media` **não** usa `SoftDeletes`. A razão que justifica os soft deletes em
 * `Page` — manter o slug reservado, porque é endereço público — não existe
 * aqui: o path é opaco, gerado e nunca reciclado.
 *
 * O model representa persistência. A política de disco, a geração de path e a
 * derivação de URL pertencem ao `MediaService`, e não a um Observer ou a um
 * boot hook daqui.
 */
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    /**
     * Campos de negócio da entidade.
     *
     * `id` e os timestamps ficam de fora de propósito: a identidade é do banco.
     * Não existem `extension`, `url`, `uuid`, `hash`, `metadata`, `alt_text`,
     * `user_id` nem `deleted_at` — nenhum requisito atual os justifica.
     *
     * @var list<string>
     */
    protected $fillable = [
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'width',
        'height',
    ];

    /**
     * Bytes e pixels são grandezas inteiras. Sem o cast, o driver do MySQL
     * devolveria as três colunas como string e um `assertSame(1024, ...)` do
     * consumidor falharia por tipo, não por valor.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }
}
