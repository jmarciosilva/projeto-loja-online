<?php

namespace App\Services;

use App\Enums\BannerPosition;
use App\Models\Banner;
use App\Models\Media;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service Layer dos banners.
 *
 * Concentra as invariantes da entidade para que valham em qualquer consumidor,
 * e não apenas no fluxo HTTP. Controller e Form Requests chegam na F2.5-B;
 * quando chegarem, a validação deles será uma barreira antecipada de entrada,
 * nunca a fonte autoritativa destas regras.
 *
 * A F2.5-A funda o núcleo: criação, atualização, exclusão, atribuição da ordem,
 * normalização do link e consulta ordenada por posição. A consulta pública —
 * que também filtra `is_active` — pertence à F2.5-C, e a reordenação explícita
 * à F2.5-B.
 */
class BannerService
{
    /**
     * Limites das colunas de texto.
     *
     * O serviço garante o limite antes de gravar: deixar o banco recusar
     * transformaria uma regra de domínio em erro de driver. A contagem é de
     * caracteres, e não de bytes, porque é assim que o MySQL dimensiona
     * `VARCHAR` em utf8mb4 — `name` e `alt_text` são texto em PT-BR.
     */
    private const NAME_MAX_LENGTH = 120;

    private const ALT_TEXT_MAX_LENGTH = 255;

    private const LINK_URL_MAX_LENGTH = 2048;

    /**
     * Esquemas aceitos em URL absoluta.
     *
     * É uma **allowlist de dois**, e não uma blocklist a manter: `javascript:`,
     * `data:`, `vbscript:`, `file:` e `ftp:` são recusados por não estarem
     * aqui, junto com qualquer esquema que ainda nem exista.
     *
     * @var list<string>
     */
    private const LINK_SCHEMES = ['http', 'https'];

    /**
     * Cria um banner anexado ao fim da sua posição.
     *
     * O chamador **não** fornece `sort_order`: a chave é ignorada mesmo quando
     * enviada. Quem conhece o estado das outras linhas da posição é este
     * serviço, e espalhar o cálculo pela camada HTTP faria a invariante
     * divergir na primeira chamada que não passasse por lá.
     *
     * A transação admite três tentativas: criar o primeiro banner de uma
     * posição vazia pode colidir com outra criação simultânea, e a nova
     * tentativa recalcula o `MAX` do zero. O motivo está detalhado em
     * {@see self::nextSortOrder()}.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Banner
    {
        return DB::transaction(function () use ($attributes): Banner {
            $position = $this->position($attributes['position'] ?? null);

            $banner = new Banner;
            $banner->fill([
                'name' => $this->name($attributes['name'] ?? null),
                'media_id' => $this->mediaId($attributes['media_id'] ?? null),
                'position' => $position,
                'link_url' => $this->linkUrl($attributes['link_url'] ?? null),
                'alt_text' => $this->altText($attributes['alt_text'] ?? null),
                'sort_order' => $this->nextSortOrder($position),
                // Ausência é o caso do default do schema: um banner novo nasce
                // inativo, e criar um registro não deve publicá-lo.
                'is_active' => array_key_exists('is_active', $attributes)
                    ? $this->isActive($attributes['is_active'])
                    : false,
            ]);
            $banner->save();

            return $banner;
        }, 3);
    }

    /**
     * Atualiza somente os campos informados.
     *
     * Alterar nome, imagem, link, texto alternativo ou estado **não** reordena
     * nada — a ordem só muda por uma decisão sobre a ordem. Quando `position`
     * muda, o banner é anexado ao fim da nova posição: o número antigo
     * descrevia o lugar dele entre os banners da posição anterior e, na nova,
     * não descreve nada.
     *
     * Mudar de posição e receber a nova ordem são **uma única operação
     * lógica** — daí a transação envolver as duas, com as mesmas três
     * tentativas da criação. A repetição reexecuta a operação **inteira**, e
     * não apenas a leitura do `MAX`: mover para uma posição que outra sessão
     * acabou de povoar exige recalcular a ordem a partir do estado novo.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Banner $banner, array $attributes): Banner
    {
        // A posição **persistida**, lida uma vez e fora da transação.
        //
        // O rollback desfaz o banco, mas não o objeto em memória: numa segunda
        // tentativa o model já carregaria a posição atribuída pela tentativa
        // anterior, a comparação diria "não mudou" e o retry pularia justamente
        // o recálculo da ordem que ele existe para refazer — gravando de novo
        // uma ordem calculada contra um estado que não vale mais.
        //
        // `getOriginal()` em vez do atributo atual porque uma gravação
        // bem-sucedida seguida de falha no commit já teria sincronizado o
        // original; capturar antes de abrir a transação fecha esse caso.
        $originalPosition = $banner->getOriginal('position');

        return DB::transaction(function () use ($banner, $attributes, $originalPosition): Banner {
            $payload = [];

            if (array_key_exists('name', $attributes)) {
                $payload['name'] = $this->name($attributes['name']);
            }

            if (array_key_exists('media_id', $attributes)) {
                $payload['media_id'] = $this->mediaId($attributes['media_id']);
            }

            if (array_key_exists('link_url', $attributes)) {
                $payload['link_url'] = $this->linkUrl($attributes['link_url']);
            }

            if (array_key_exists('alt_text', $attributes)) {
                $payload['alt_text'] = $this->altText($attributes['alt_text']);
            }

            if (array_key_exists('is_active', $attributes)) {
                $payload['is_active'] = $this->isActive($attributes['is_active']);
            }

            if (array_key_exists('position', $attributes)) {
                $position = $this->position($attributes['position']);

                // Reenviar a mesma posição é o caso comum de um formulário de
                // edição: preservar a ordem aqui é o que impede um simples
                // "salvar" de jogar o banner para o fim da própria lista.
                if ($position !== $originalPosition) {
                    $payload['position'] = $position;
                    $payload['sort_order'] = $this->nextSortOrder($position);
                }
            }

            $banner->fill($payload);
            $banner->save();

            return $banner;
        }, 3);
    }

    /**
     * Exclusão física do banner — e somente dele.
     *
     * A mídia permanece na biblioteca: ela é um arquivo compartilhável, e o
     * banner era apenas um de seus consumidores. Quando o último desaparece,
     * ela volta a ser removível pela própria biblioteca.
     */
    public function delete(Banner $banner): void
    {
        $banner->delete();
    }

    /**
     * Banners de uma posição, na ordem contratada.
     *
     * `id ASC` é o desempate determinístico: como não existe
     * `UNIQUE (position, sort_order)` — reordenar passa por estados
     * intermediários com empates —, sem ele dois banners de mesma ordem
     * poderiam alternar entre requisições.
     *
     * Devolve **todos** os banners da posição, ativos ou não. Esta é a consulta
     * de domínio; a consulta pública, que também filtra `is_active`, pertence à
     * F2.5-C.
     *
     * @return Collection<int, Banner>
     */
    public function orderedForPosition(BannerPosition $position): Collection
    {
        return Banner::query()
            ->where('position', $position->value)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Próxima ordem livre da posição, anexando ao fim.
     *
     * Posição vazia começa em `1`. O `lockForUpdate()` existe pela janela entre
     * ler o `MAX` e gravar a linha: sem ele, duas criações simultâneas na mesma
     * posição calculariam o mesmo número. A leitura acontece como *locking
     * read* dentro da mesma transação, para serializar atribuições concorrentes
     * conforme o comportamento de locking do InnoDB no isolamento usado pelo
     * projeto — `REPEATABLE READ`, o padrão do servidor, sem configuração
     * própria.
     *
     * Comportamento medido em MySQL 8.4 com duas sessões reais e simultâneas:
     *
     * ```text
     * posição já populada  →  a segunda sessão espera a primeira e recebe a
     *                         ordem seguinte
     * posição ainda vazia  →  as duas obtêm ao mesmo tempo o gap lock do
     *                         `supremum`, porque gap locks são compatíveis
     *                         entre si; o INSERT de cada uma então espera o gap
     *                         lock da outra, e o InnoDB derruba uma das
     *                         transações por deadlock
     * ```
     *
     * Isto é: **nenhum `sort_order` duplicado é produzido** em nenhum dos dois
     * casos, mas o segundo é um deadlock — e como o gap de uma tabela
     * inteiramente vazia é um só, ele pode ocorrer até entre posições
     * diferentes; com dados na tabela, posições diferentes não se bloqueiam.
     *
     * Por isso `create()` e `update()` usam `DB::transaction($callback, 3)`. O
     * deadlock é transitório: o InnoDB derruba uma das transações por inteiro,
     * e o próprio `DB::transaction()` reexecuta a closure completa nos erros de
     * concorrência que reconhece — a nova tentativa lê um `MAX` novo, já
     * enxergando a linha que a outra sessão gravou, e grava a ordem seguinte.
     * Não há loop próprio, `sleep` nem captura manual de `QueryException`.
     *
     * O retry é **limitado a três tentativas**: esgotadas, a exceção volta a
     * ser propagada ao chamador. Um deadlock que persista não é contenção
     * passageira, e engoli-lo esconderia o problema.
     *
     * Esta garantia depende do `REPEATABLE READ` do MySQL canônico do projeto:
     * em `READ COMMITTED` não há gap lock, e um range vazio deixaria de ser
     * protegido. O isolamento não é fixado pela aplicação — é o padrão do
     * servidor.
     *
     * `UNIQUE (position, sort_order)` continua fora: ela quebraria a
     * reordenação da F2.5-B, que precisa de estados intermediários com empate.
     */
    private function nextSortOrder(BannerPosition $position): int
    {
        $current = Banner::query()
            ->where('position', $position->value)
            ->lockForUpdate()
            ->max('sort_order');

        return $current === null ? 1 : (int) $current + 1;
    }

    private function name(mixed $value): string
    {
        $name = is_string($value) ? trim($value) : '';

        if ($name === '') {
            throw new InvalidArgumentException('A banner requires a name.');
        }

        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new InvalidArgumentException('The banner name is longer than '.self::NAME_MAX_LENGTH.' characters.');
        }

        return $name;
    }

    /**
     * Texto alternativo do **uso** da imagem.
     *
     * Não há fallback para `media.original_name`: aquele é metadado
     * administrativo, muitas vezes um nome de arquivo escolhido por quem fez o
     * upload, e não descreve a imagem para quem usa leitor de tela.
     */
    private function altText(mixed $value): string
    {
        $altText = is_string($value) ? trim($value) : '';

        if ($altText === '') {
            throw new InvalidArgumentException('A banner requires an alternative text.');
        }

        if (mb_strlen($altText) > self::ALT_TEXT_MAX_LENGTH) {
            throw new InvalidArgumentException('The banner alternative text is longer than '.self::ALT_TEXT_MAX_LENGTH.' characters.');
        }

        return $altText;
    }

    private function position(mixed $value): BannerPosition
    {
        if ($value instanceof BannerPosition) {
            return $value;
        }

        $resolved = is_string($value) ? BannerPosition::tryFrom($value) : null;

        if ($resolved === null) {
            throw new InvalidArgumentException('Unsupported banner position.');
        }

        return $resolved;
    }

    /**
     * Identidade da mídia referenciada, conferida contra a biblioteca.
     *
     * A FK já é a barreira final do banco, mas ela produziria uma
     * `QueryException` de driver. A checagem aqui existe para que a violação
     * chegue ao chamador como erro de domínio — inclusive fora do HTTP, onde
     * não há Form Request para antecipá-la.
     */
    private function mediaId(mixed $value): int
    {
        $mediaId = filter_var($value, FILTER_VALIDATE_INT);

        if ($mediaId === false || $mediaId < 1) {
            throw new InvalidArgumentException('A banner requires a media reference.');
        }

        if (! Media::query()->whereKey($mediaId)->exists()) {
            throw new InvalidArgumentException("The banner media [{$mediaId}] does not exist in the media library.");
        }

        return $mediaId;
    }

    /**
     * Estado explícito do banner.
     *
     * Só `true` e `false` são aceitos. Traduzir `"on"`, `"1"` ou `null` aqui
     * faria o serviço adivinhar a intenção de quem chamou; a conversão da caixa
     * de seleção pertence à camada HTTP da F2.5-B.
     */
    private function isActive(mixed $value): bool
    {
        if (! is_bool($value)) {
            throw new InvalidArgumentException('The banner state must be a boolean.');
        }

        return $value;
    }

    /**
     * Normaliza o link antes de validá-lo.
     *
     * "Sem link" tem **uma** representação no banco: `null`. Guardar `''`
     * obrigaria todo leitor a tratar dois valores como a mesma ausência.
     */
    private function linkUrl(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('The banner link must be a string.');
        }

        $link = trim($value);

        if ($link === '') {
            return null;
        }

        if (mb_strlen($link) > self::LINK_URL_MAX_LENGTH) {
            throw new InvalidArgumentException('The banner link is longer than '.self::LINK_URL_MAX_LENGTH.' characters.');
        }

        $this->guardLinkUrl($link);

        return $link;
    }

    /**
     * Recusa tudo que não seja um caminho interno ou uma URL HTTP(S).
     *
     * A validação **não inventa protocolo**: `www.exemplo.com` não vira
     * `https://www.exemplo.com`, porque adivinhar o esquema é escolher por quem
     * digitou, e a escolha errada leva o visitante a outro lugar. Um valor sem
     * esquema e sem `/` inicial é simplesmente inválido.
     *
     * @throws InvalidArgumentException
     */
    private function guardLinkUrl(string $link): void
    {
        // A barra invertida não substitui `/`: alguns navegadores normalizam
        // `\` para `/`, e um caminho como `/\evil.example` passaria a valer
        // como `//evil.example`. Nenhum link legítimo precisa dela — dentro de
        // um caminho ela seria `%5C`.
        if (str_contains($link, '\\')) {
            throw new InvalidArgumentException("The banner link [{$link}] is not a valid internal path or HTTP URL.");
        }

        // Protocol-relative: aparenta caminho interno e leva a host externo.
        if (str_starts_with($link, '//')) {
            throw new InvalidArgumentException("The banner link [{$link}] is not a valid internal path or HTTP URL.");
        }

        if (str_starts_with($link, '/')) {
            return;
        }

        $parts = parse_url($link);

        if ($parts === false) {
            throw new InvalidArgumentException("The banner link [{$link}] is not a valid internal path or HTTP URL.");
        }

        $scheme = isset($parts['scheme']) ? mb_strtolower($parts['scheme']) : null;

        if ($scheme === null || ! in_array($scheme, self::LINK_SCHEMES, true)) {
            throw new InvalidArgumentException("The banner link [{$link}] uses an unsupported scheme.");
        }

        $host = $parts['host'] ?? '';

        if ($host === '' || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw new InvalidArgumentException("The banner link [{$link}] has no valid host.");
        }
    }
}
