<?php

namespace App\Http\Requests\Admin;

use App\Services\MediaService;
use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    /**
     * Limite de tamanho do arquivo enviado, em kilobytes — 5 MB.
     *
     * É decisão de aplicação, não de infraestrutura: `docker/php.ini` e o
     * nginx aceitam 100 MB de propósito, para que o excesso vire erro de
     * validação amigável em vez de um 413 do servidor ou um POST truncado.
     */
    private const MAX_KILOBYTES = 5120;

    /**
     * Maior dimensão aceita no arquivo enviado, em pixels.
     *
     * A guarda é contra *decompression bomb* e é independente do limite de
     * bytes: uma imagem de 12.000 × 12.000 px cabe em 5 MB comprimida e exige
     * centenas de MB para decodificar. A regra `dimensions` lê apenas o
     * cabeçalho, via `getimagesize`, portanto rejeita **antes** de o arquivo
     * chegar ao pipeline do Intervention.
     */
    private const MAX_INPUT_DIMENSION = 6000;

    /**
     * Durante a Fase 2 qualquer usuário autenticado administra a biblioteca; o
     * middleware `auth` da rota é a única barreira. Papéis e permissões são
     * escopo da Fase 3 e não devem ser antecipados aqui.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Um request carrega exatamente um arquivo.
     *
     * A regra `file` recusa um array, o que mantém a semântica de falha
     * trivial: ou a requisição criou uma mídia, ou não criou nenhuma. Upload
     * múltiplo obrigaria a decidir agora o que fazer quando 3 de 5 falham.
     *
     * `mimetypes` decide pelo **conteúdo** do arquivo, via `fileinfo`, e não
     * pela extensão nem pelo `Content-Type` do navegador — ambos controlados
     * por quem envia. É essa regra que recusa um `.php` renomeado para `.jpg`.
     * A lista vem do `MediaService`, fonte central compartilhada com o
     * serviço, de modo que `image/webp` só apareça onde o GD sabe codificá-lo.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'image',
                'mimetypes:'.implode(',', app(MediaService::class)->supportedMimeTypes()),
                'max:'.self::MAX_KILOBYTES,
                'dimensions:max_width='.self::MAX_INPUT_DIMENSION.',max_height='.self::MAX_INPUT_DIMENSION,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file' => 'arquivo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Selecione um arquivo de imagem para enviar.',
            'file.file' => 'Envie um único arquivo por vez.',
            'file.image' => 'O arquivo precisa ser uma imagem.',
            // A lista é montada a partir dos tipos realmente aceitos: num
            // runtime sem WebP, prometê-lo na mensagem seria mentir para o
            // administrador logo depois de recusar o arquivo dele.
            'file.mimetypes' => 'Formato não suportado. A biblioteca aceita '.$this->supportedFormatsLabel().'.',
            'file.max' => 'A imagem deve ter no máximo 5 MB.',
            'file.dimensions' => 'A imagem deve ter no máximo 6000 × 6000 pixels.',
        ];
    }

    /**
     * Rótulo legível dos formatos aceitos neste runtime, por exemplo
     * `JPEG, PNG e WebP`.
     */
    private function supportedFormatsLabel(): string
    {
        $labels = array_map(
            fn (string $mimeType): string => match ($mimeType) {
                'image/jpeg' => 'JPEG',
                'image/png' => 'PNG',
                'image/webp' => 'WebP',
                default => $mimeType,
            },
            app(MediaService::class)->supportedMimeTypes(),
        );

        $last = array_pop($labels);

        return $labels === [] ? $last : implode(', ', $labels).' e '.$last;
    }
}
