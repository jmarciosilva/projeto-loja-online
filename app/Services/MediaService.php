<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Service Layer da biblioteca de mídia.
 *
 * A F2.7-A funda o núcleo: política de disco, geração de path, nome físico
 * opaco, derivação de URL e consulta paginada. Upload e processamento de
 * imagem chegam na F2.7-B; exclusão e verificação de uso, na F2.7-C.
 */
class MediaService
{
    /**
     * Disco em que a mídia nova é gravada — fonte autoritativa única.
     *
     * É declarado explicitamente, e **não** herdado de
     * `config('filesystems.default')`: o padrão da aplicação é `local`, que no
     * Laravel 12 aponta para `storage/app/private`. Herdá-lo publicaria a
     * biblioteca num diretório privado.
     *
     * A constante define apenas onde a mídia *nova* é gravada. Leitura e
     * exclusão usam sempre `$media->disk`, para que registros antigos
     * continuem resolvendo pelo disco em que foram gravados.
     */
    public const DISK = 'public';

    /**
     * Prefixo de todos os caminhos da biblioteca.
     */
    private const PATH_PREFIX = 'media';

    /**
     * Extensões canônicas aceitas na formação do path.
     *
     * A lista é uma **defesa da API de path**, não uma política de upload:
     * quem decide quais formatos entram na biblioteca é a validação da
     * F2.7-B. Aqui ela existe para que nenhum valor arbitrário — `../php`,
     * `foo/bar`, `jpg?x` — consiga alterar o diretório de destino.
     *
     * Uma extensão por formato, de propósito: aceitar `jpg` e `jpeg` faria o
     * mesmo formato conviver sob dois nomes no armazenamento.
     *
     * @var list<string>
     */
    private const ALLOWED_EXTENSIONS = [
        'jpg',
        'png',
        'webp',
    ];

    /**
     * Monta o caminho de um arquivo novo da biblioteca.
     *
     * ```text
     * media/{YYYY}/{MM}/{ULID}.{ext}
     * ```
     *
     * O particionamento por ano e mês mantém a quantidade de entradas por
     * diretório previsível, e nenhum de seus componentes vem do usuário.
     *
     * A extensão representa o formato que o chamador já conhece. Na F2.7-B,
     * quem a determina é o processamento de imagem, a partir do formato
     * efetivamente codificado — nunca da extensão enviada pelo cliente.
     */
    public function generatePath(string $extension): string
    {
        $extension = $this->canonicalExtension($extension);

        return self::PATH_PREFIX.'/'.now()->format('Y/m').'/'.$this->generateFileName().$extension;
    }

    /**
     * Resolve a URL pública da mídia a partir do disco em que ela foi gravada.
     *
     * A URL é **derivada**, nunca persistida: ela é função do disco e da
     * configuração do ambiente (`APP_URL`, domínio, CDN futura). Guardá-la em
     * coluna congelaria o valor de um ambiente e faria todo registro antigo
     * apontar para o lugar errado assim que qualquer um deles mudasse.
     *
     * Usa `$media->disk`, e não a constante da classe: é isso que permite uma
     * migração futura de backend sem reescrever o histórico.
     */
    public function url(Media $media): string
    {
        return Storage::disk($media->disk)->url($media->path);
    }

    /**
     * Listagem da biblioteca, da mídia mais recente para a mais antiga.
     *
     * Ordena apenas por `id`, sem desempate: a mídia é imutável depois de
     * criada, então a chave primária autoincremental já é a ordem cronológica
     * inversa — e é determinística sozinha, usando o índice que já existe.
     * Difere de `PageService::paginate()`, que precisa de `updated_at` porque
     * páginas são editadas.
     *
     * @return LengthAwarePaginator<int, Media>
     */
    public function paginate(int $perPage = 24): LengthAwarePaginator
    {
        return Media::query()
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Nome físico opaco, sem qualquer vínculo com o arquivo enviado.
     *
     * O ULID entrega 26 caracteres do alfabeto `[0-9A-HJKMNP-TV-Z]`: não há
     * separador de diretório, ponto nem caractere problemático a escapar, e a
     * colisão é impraticável. Ele é **nome de arquivo**, não identidade — a
     * identidade da mídia continua sendo `Media.id`.
     */
    private function generateFileName(): string
    {
        return (string) Str::ulid();
    }

    /**
     * Reduz a extensão à forma canônica aceita, ou recusa a chamada.
     *
     * Só a diferença de caixa é normalizada. Qualquer outro valor é rejeitado
     * em vez de saneado: um caminho de destino não é lugar para adivinhar a
     * intenção de quem chamou.
     */
    private function canonicalExtension(string $extension): string
    {
        $canonical = strtolower(trim($extension));

        if (! in_array($canonical, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException("The media extension [{$extension}] is not supported.");
        }

        return '.'.$canonical;
    }
}
