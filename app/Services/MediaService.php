<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncoderInterface;
use Intervention\Image\Interfaces\ImageInterface;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Service Layer da biblioteca de mídia.
 *
 * A F2.7-A fundou o núcleo: política de disco, geração de path, nome físico
 * opaco, derivação de URL e consulta paginada. A F2.7-B acrescenta o upload e o
 * processamento de imagem. Exclusão e verificação de uso pertencem à F2.7-C.
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
     * Maior lado permitido na imagem armazenada, em pixels.
     */
    private const MAX_DIMENSION = 2000;

    /**
     * Qualidade dos formatos com perdas. O PNG não aparece aqui porque o
     * `PngEncoder` da versão instalada não tem parâmetro de qualidade.
     */
    private const LOSSY_QUALITY = 85;

    /**
     * MIME aceitos independentemente das capacidades do runtime.
     *
     * @var list<string>
     */
    private const BASE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
    ];

    /**
     * MIME que só entra na lista quando o GD sabe codificá-lo.
     */
    private const WEBP_MIME_TYPE = 'image/webp';

    /**
     * MIME real → extensão canônica do arquivo armazenado.
     *
     * O mapa é a única fonte da extensão física. A extensão enviada pelo
     * cliente nunca é consultada: ela é texto controlado por quem faz o upload
     * e pode mentir sobre o conteúdo. Ele também normaliza `jpeg` em `jpg`, a
     * única forma que `generatePath()` aceita.
     *
     * @var array<string, string>
     */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        self::WEBP_MIME_TYPE => 'webp',
    ];

    public function __construct(private readonly ImageCapabilities $capabilities) {}

    /**
     * MIME aceitos pela biblioteca neste runtime.
     *
     * É a fonte central consultada tanto pelo Form Request quanto por
     * `store()`, para que a barreira HTTP e a do serviço nunca divirjam sobre
     * quais formatos existem.
     *
     * O WebP é condicional: sem suporte no GD, ele não entra na lista e é
     * rejeitado ainda na validação — em vez de ser aceito para explodir depois,
     * dentro do encoder.
     *
     * @return list<string>
     */
    public function supportedMimeTypes(): array
    {
        $mimeTypes = self::BASE_MIME_TYPES;

        if ($this->capabilities->supportsWebp()) {
            $mimeTypes[] = self::WEBP_MIME_TYPE;
        }

        return $mimeTypes;
    }

    /**
     * Transforma um arquivo enviado em uma mídia da biblioteca.
     *
     * A ordem dos passos é contrato, não detalhe: processa, grava o arquivo e
     * só então cria o registro. Banco e sistema de arquivos não compartilham
     * transação, e nascer o registro por último é o que garante que **nenhuma
     * `Media` inválida existe** — um registro só aparece depois de o arquivo
     * estar no disco.
     */
    public function store(UploadedFile $file): Media
    {
        $mimeType = $this->supportedMimeType($file);
        $extension = self::EXTENSIONS[$mimeType];

        // Passo 2: falhar aqui não gravou arquivo nem persistiu nada.
        $image = $this->process($file);
        $contents = (string) $image->encode($this->encoder($mimeType));

        $path = $this->generatePath($extension);

        // O disco `public` é configurado com `throw => false`, então uma falha
        // de gravação volta como `false` em vez de exceção. Ignorar o retorno
        // criaria um registro apontando para um arquivo que não existe.
        if (Storage::disk(self::DISK)->put($path, $contents) === false) {
            throw new RuntimeException("Unable to write the media file at [{$path}].");
        }

        try {
            return Media::create([
                'disk' => self::DISK,
                'path' => $path,
                // Metadado administrativo apenas: o nome enviado não participa
                // do path, da extensão nem do nome físico.
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $mimeType,
                // Medidos do resultado, nunca do upload: o registro descreve o
                // arquivo que está no disco.
                'size' => strlen($contents),
                'width' => $image->width(),
                'height' => $image->height(),
            ]);
        } catch (Throwable $exception) {
            // Melhor esforço: o arquivo já gravado ficaria órfão e invisível,
            // porque nenhum registro aponta para ele e o nome nunca é
            // reciclado. Não há fila nem reconciliação — a falha original é o
            // que importa, e é ela que continua propagando.
            Storage::disk(self::DISK)->delete($path);

            throw $exception;
        }
    }

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
     * MIME real do arquivo, recusando o que a biblioteca não aceita.
     *
     * O tipo sai do conteúdo — `getMimeType()` usa `fileinfo` sobre o arquivo
     * temporário —, e não de `getClientMimeType()` nem da extensão, ambos
     * fornecidos por quem envia. Um `.php` renomeado para `.jpg` cai aqui.
     *
     * O Form Request já barra o mesmo conjunto, mas esta checagem existe para
     * a chamada direta ao serviço, fora do HTTP: sem ela, um tipo arbitrário
     * chegaria à escolha de encoder.
     */
    private function supportedMimeType(UploadedFile $file): string
    {
        $mimeType = (string) $file->getMimeType();

        if (! in_array($mimeType, $this->supportedMimeTypes(), true)) {
            throw new InvalidArgumentException("The media type [{$mimeType}] is not supported.");
        }

        return $mimeType;
    }

    /**
     * Decodifica e normaliza a imagem enviada.
     *
     * `orient()` vem **antes** de qualquer medição ou redimensionamento: fotos
     * de celular chegam com a rotação apenas no EXIF, e sem aplicá-la aos
     * pixels primeiro `width()` e `height()` reportariam os lados trocados e o
     * `scaleDown()` limitaria o eixo errado.
     *
     * A auto-orientação do Intervention é **desabilitada** no `ImageManager`
     * (`autoOrientation: false`) para que a aplicação controle explicitamente a
     * ordem do pipeline. Assim `orient()` é quem aplica a rotação, antes do
     * redimensionamento, e o comportamento não depende do default da
     * biblioteca — que hoje é `true` e poderia mudar numa atualização sem que
     * nada aqui acusasse.
     *
     * `scaleDown()` — e não `scale()` ou `resize()` — porque só ele reduz sem
     * nunca ampliar e sem distorcer a proporção.
     *
     * Uma imagem já menor que o limite passa pelo mesmo caminho: é a
     * recodificação que garante que o arquivo armazenado é uma imagem válida,
     * com a orientação aplicada e sem os metadados do original — inclusive GPS.
     */
    private function process(UploadedFile $file): ImageInterface
    {
        return (new ImageManager(Driver::class, autoOrientation: false))
            ->decodeSplFileInfo($file)
            ->orient()
            ->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);
    }

    /**
     * Encoder do formato de origem — a biblioteca não converte formato.
     *
     * Converter tudo para JPEG destruiria o canal alfa de um logo; converter
     * para WebP devolveria ao administrador um formato diferente do que ele
     * enviou. Por isso `media.mime_type` é sempre igual ao MIME validado.
     *
     * O PNG não recebe `quality`: o `PngEncoder` da 4.3.2 aceita apenas
     * `interlaced` e `indexed`, e `indexed: true` quantizaria para 256 cores,
     * arruinando gradientes e transparência suave.
     */
    private function encoder(string $mimeType): EncoderInterface
    {
        return match ($mimeType) {
            'image/jpeg' => new JpegEncoder(quality: self::LOSSY_QUALITY),
            'image/png' => new PngEncoder,
            self::WEBP_MIME_TYPE => new WebpEncoder(quality: self::LOSSY_QUALITY),
            default => throw new InvalidArgumentException("The media type [{$mimeType}] has no encoder."),
        };
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
