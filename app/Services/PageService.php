<?php

namespace App\Services;

use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Service Layer das páginas estáticas.
 *
 * Concentra as invariantes da entidade — em especial as do slug — para que
 * valham em qualquer consumidor, e não apenas no fluxo HTTP. Controller e Form
 * Request chegam na F2.4-B; quando chegarem, a validação deles será uma
 * barreira antecipada de entrada, não a fonte autoritativa destas regras.
 */
class PageService
{
    /**
     * Formato canônico do endereço público, conforme o contrato da F2.4.
     */
    private const SLUG_FORMAT = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * Limite da coluna `slug`. O serviço garante o limite antes de gravar: deixar
     * o banco recusar transformaria uma regra de domínio em erro de driver.
     */
    private const SLUG_MAX_LENGTH = 255;

    /**
     * Campos de negócio aceitos pelo serviço.
     *
     * O payload é montado a partir desta lista em vez de repassar o array
     * recebido: assim uma chave não suportada nunca chega ao model, mesmo que
     * o `$fillable` mude.
     *
     * @var list<string>
     */
    private const SUPPORTED_FIELDS = [
        'title',
        'content',
        'status',
        'meta_title',
        'meta_description',
    ];

    /**
     * Cria uma página, resolvendo o slug quando ele não for informado.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Page
    {
        $title = $this->title($attributes);
        $requestedSlug = $this->requestedSlug($attributes);

        $page = new Page;
        $page->fill([
            'title' => $title,
            'content' => $attributes['content'] ?? '',
            'status' => $this->status($attributes['status'] ?? PageStatus::Draft),
            'meta_title' => $attributes['meta_title'] ?? null,
            'meta_description' => $attributes['meta_description'] ?? null,
            'slug' => $requestedSlug === null
                ? $this->generatedSlug($title)
                : $this->explicitSlug($requestedSlug),
        ]);
        $page->save();

        return $page;
    }

    /**
     * Atualiza somente os campos informados.
     *
     * Alterar apenas o título preserva o slug já publicado — regenerá-lo
     * quebraria as URLs divulgadas. O slug só muda quando é informado
     * explicitamente, e a identidade `Page.id` nunca muda.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Page $page, array $attributes): Page
    {
        $requestedSlug = $this->requestedSlug($attributes);
        $payload = [];

        foreach (self::SUPPORTED_FIELDS as $field) {
            if (array_key_exists($field, $attributes)) {
                $payload[$field] = $attributes[$field];
            }
        }

        if (array_key_exists('status', $payload)) {
            $payload['status'] = $this->status($payload['status']);
        }

        if ($requestedSlug !== null) {
            $payload['slug'] = $this->explicitSlug($requestedSlug, $page->getKey());
        }

        $page->fill($payload);
        $page->save();

        return $page;
    }

    /**
     * Exclusão lógica. Restore, lixeira e force delete não pertencem à F2.4.
     */
    public function delete(Page $page): void
    {
        $page->delete();
    }

    /**
     * Um slug está disponível quando nenhuma outra página o ocupa — inclusive
     * as excluídas logicamente.
     *
     * Reaproveitar o slug de uma página soft-deleted faria uma URL antiga
     * passar a servir conteúdo diferente para quem a tivesse guardado, e é por
     * isso que a consulta usa `withTrashed()`.
     */
    public function slugIsAvailable(string $slug, ?int $ignorePageId = null): bool
    {
        return ! Page::withTrashed()
            ->where('slug', $slug)
            ->when($ignorePageId !== null, fn ($query) => $query->whereKeyNot($ignorePageId))
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function title(array $attributes): string
    {
        $title = is_string($attributes['title'] ?? null) ? trim($attributes['title']) : '';

        if ($title === '') {
            throw new InvalidArgumentException('A page requires a title.');
        }

        return $title;
    }

    private function status(mixed $status): PageStatus
    {
        if ($status instanceof PageStatus) {
            return $status;
        }

        $resolved = is_string($status) ? PageStatus::tryFrom($status) : null;

        if ($resolved === null) {
            throw new InvalidArgumentException('Unsupported page status.');
        }

        return $resolved;
    }

    /**
     * Separa "gere um slug para mim" de um endereço escolhido deliberadamente.
     *
     * Ausência, `null` e string vazia ou só com whitespace são pedidos de
     * geração automática e retornam `null`. Um valor de qualquer outro tipo,
     * porém, não é um pedido de geração: é um endereço malformado, e aceitá-lo
     * em silêncio publicaria uma URL que ninguém escolheu.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function requestedSlug(array $attributes): ?string
    {
        if (! array_key_exists('slug', $attributes) || $attributes['slug'] === null) {
            return null;
        }

        $slug = $attributes['slug'];

        if (! is_string($slug)) {
            throw new InvalidArgumentException('The page slug must be a string.');
        }

        $slug = trim($slug);

        return $slug === '' ? null : $slug;
    }

    /**
     * Valida um endereço escolhido pelo consumidor.
     *
     * Um slug explícito já ocupado é rejeitado, e não corrigido em silêncio
     * para `-2`: quem pediu um endereço específico precisa saber que não o
     * recebeu, em vez de descobrir depois que a URL publicada é outra.
     */
    private function explicitSlug(string $slug, ?int $ignorePageId = null): string
    {
        if (preg_match(self::SLUG_FORMAT, $slug) !== 1) {
            throw new InvalidArgumentException("The page slug [{$slug}] is not a canonical slug.");
        }

        if (strlen($slug) > self::SLUG_MAX_LENGTH) {
            throw new InvalidArgumentException('The page slug is longer than '.self::SLUG_MAX_LENGTH.' characters.');
        }

        if (! $this->slugIsAvailable($slug, $ignorePageId)) {
            throw new InvalidArgumentException("The page slug [{$slug}] is already taken.");
        }

        return $slug;
    }

    /**
     * Deriva o slug do título e resolve colisões por sufixo determinístico:
     * `quem-somos`, `quem-somos-2`, `quem-somos-3` — nunca um valor aleatório,
     * que tornaria o endereço imprevisível para quem cria a página.
     */
    private function generatedSlug(string $title): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            throw new InvalidArgumentException('The page title does not produce a slug.');
        }

        $slug = $this->fitSlug($base, self::SLUG_MAX_LENGTH);
        $suffix = 2;

        while (! $this->slugIsAvailable($slug)) {
            $marker = "-{$suffix}";
            // A base é encurtada pelo tamanho exato do sufixo — inclusive quando
            // ele cresce para `-10` —, de modo que o resultado nunca ultrapasse
            // o limite da coluna.
            $slug = $this->fitSlug($base, self::SLUG_MAX_LENGTH - strlen($marker)).$marker;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Encurta um slug já canônico para caber em `$length`.
     *
     * O corte pode cair logo depois de um hífen, e um hífen final quebraria o
     * formato canônico — daí o `rtrim`. `Str::slug` produz apenas ASCII, então
     * contar bytes e caracteres dá no mesmo aqui.
     */
    private function fitSlug(string $slug, int $length): string
    {
        if (strlen($slug) <= $length) {
            return $slug;
        }

        return rtrim(substr($slug, 0, $length), '-');
    }
}
