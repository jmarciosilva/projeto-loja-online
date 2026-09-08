<?php

namespace App\Services;

use App\Enums\MenuItemType;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Service Layer dos menus.
 *
 * Concentra as invariantes da árvore para que valham em qualquer consumidor, e
 * não apenas no fluxo HTTP: a interface administrativa da F2.6-B será uma
 * barreira antecipada de entrada, nunca a fonte autoritativa destas regras.
 *
 * A F2.6-A funda o núcleo: menu com identidade técnica imutável, item com
 * destino exclusivo, hierarquia sem ciclos, ordem contextual ao grupo de irmãos
 * e exclusão da árvore das folhas para as raízes. A administração pertence à
 * F2.6-B, e a consulta pública — que também filtra publicabilidade — à F2.6-C.
 */
class MenuService
{
    /**
     * Limites das colunas de texto.
     *
     * O serviço garante o limite antes de gravar: deixar o banco recusar
     * transformaria uma regra de domínio em erro de driver. A contagem é de
     * caracteres, e não de bytes, porque é assim que o MySQL dimensiona
     * `VARCHAR` em utf8mb4 — `name` e `label` são texto em PT-BR.
     */
    private const NAME_MAX_LENGTH = 120;

    private const CODE_MAX_LENGTH = 64;

    private const LABEL_MAX_LENGTH = 120;

    private const URL_MAX_LENGTH = 2048;

    /**
     * Formato canônico do `code`.
     *
     * Minúsculas, dígitos e hífen simples entre segmentos. Não é slug de
     * endereço público: é chave de consumo interno, e por isso **não** passa por
     * geração automática a partir do nome nem por resolução de colisão com
     * sufixo numérico. Um código inválido é recusado, não corrigido.
     */
    private const CODE_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * Esquemas aceitos em URL absoluta.
     *
     * É uma **allowlist de dois**, e não uma blocklist a manter: `javascript:`,
     * `data:`, `vbscript:`, `file:` e `ftp:` são recusados por não estarem
     * aqui, junto com qualquer esquema que ainda nem exista.
     *
     * A política é conceitualmente a mesma do `BannerService`, mas a validação
     * é **local**: acoplar um serviço ao outro só para reaproveitar quinze
     * linhas criaria dependência entre dois domínios que não se conhecem, e
     * extrair uma abstração global agora seria inventar um terceiro conceito
     * para dois consumidores.
     *
     * @var list<string>
     */
    private const URL_SCHEMES = ['http', 'https'];

    /**
     * Cria um menu.
     *
     * `code` é obrigatório e **não** é derivado de `name`: adivinhar a chave
     * técnica a partir de um rótulo administrativo faria o consumidor público
     * depender de como alguém escreveu o nome.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createMenu(array $attributes): Menu
    {
        $menu = new Menu;
        $menu->fill([
            'name' => $this->name($attributes['name'] ?? null),
            'code' => $this->code($attributes['code'] ?? null),
            // Ausência é o caso do default do schema: um menu novo nasce
            // inativo, e criar um registro não deve publicá-lo.
            'is_active' => array_key_exists('is_active', $attributes)
                ? $this->isActive($attributes['is_active'])
                : false,
        ]);

        $this->guardCodeIsAvailable($menu->code);

        $menu->save();

        return $menu;
    }

    /**
     * Atualiza um menu — `name` e `is_active`, jamais `code`.
     *
     * Reenviar o **mesmo** `code` é o caso comum de um formulário de edição que
     * devolve todos os campos, e é aceito. Uma alteração real é **recusada**,
     * e não descartada em silêncio: quem tentou mudar precisa saber que não
     * mudou, ou passaria a acreditar num código que o banco não tem.
     *
     * O `code` é o endereço pelo qual o consumidor público encontra o menu.
     * Renomear `main` para `principal` faria a navegação do storefront sumir,
     * sem erro, sem log e sem nada na tela que explicasse o porquê.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateMenu(Menu $menu, array $attributes): Menu
    {
        if (array_key_exists('code', $attributes)) {
            $code = $this->code($attributes['code']);

            if ($code !== $menu->getOriginal('code')) {
                throw new InvalidArgumentException("The menu code [{$menu->getOriginal('code')}] is immutable.");
            }
        }

        $payload = [];

        if (array_key_exists('name', $attributes)) {
            $payload['name'] = $this->name($attributes['name']);
        }

        if (array_key_exists('is_active', $attributes)) {
            $payload['is_active'] = $this->isActive($attributes['is_active']);
        }

        $menu->fill($payload);
        $menu->save();

        return $menu;
    }

    /**
     * Exclui o menu e toda a sua árvore, das folhas para as raízes.
     *
     * A FK `menu_id` é `RESTRICT`, e não `CASCADE`, porque `parent_id` é
     * auto-referencial com `RESTRICT` e o InnoDB verifica constraints
     * **imediatamente**: um cascade apagaria um pai antes dos filhos e
     * esbarraria na própria hierarquia. Remover em camadas — folhas primeiro —
     * satisfaz as duas barreiras a cada passo.
     *
     * Tudo em **uma transação**: se qualquer camada falhar, o rollback devolve
     * o menu e a árvore inteiros, sem estado parcial. O menu é bloqueado antes
     * para que nenhuma criação simultânea insira um item na árvore que está
     * sendo removida.
     *
     * A árvore é **validada** antes: um item de fora apontando para dentro
     * faria o `RESTRICT` derrubar a operação no meio, e uma estrutura corrompida
     * — um ciclo gravado direto no banco — deixaria o laço sem folhas novas.
     * Nos dois casos a operação falha explicitamente, em vez de girar para
     * sempre. `FOREIGN_KEY_CHECKS` nunca é desligado.
     *
     * @throws RuntimeException quando a árvore não pode ser reduzida
     */
    public function deleteMenu(Menu $menu): void
    {
        DB::transaction(function () use ($menu): void {
            $locked = $this->lockMenu($menu->getKey());

            /** @var Collection<int, MenuItem> $items */
            $items = MenuItem::query()
                ->where('menu_id', $locked->getKey())
                ->lockForUpdate()
                ->get();

            $this->guardTreeIsSelfContained($locked, $items);

            $remaining = $items->keyBy(fn (MenuItem $item): int => (int) $item->getKey())->all();

            // O laço remove uma camada de folhas por vez. O teto é o número de
            // itens: cada iteração precisa remover ao menos um, então mais
            // voltas do que itens só aconteceria com estrutura inconsistente.
            $guard = count($remaining) + 1;

            while ($remaining !== [] && $guard-- > 0) {
                $parentIds = [];

                foreach ($remaining as $item) {
                    if ($item->parent_id !== null && array_key_exists($item->parent_id, $remaining)) {
                        $parentIds[$item->parent_id] = true;
                    }
                }

                $leaves = array_keys(array_diff_key($remaining, $parentIds));

                if ($leaves === []) {
                    throw new RuntimeException(
                        "The menu [{$locked->getKey()}] has an inconsistent item tree and cannot be deleted."
                    );
                }

                MenuItem::query()->whereKey($leaves)->delete();

                $remaining = array_diff_key($remaining, array_flip($leaves));
            }

            if ($remaining !== []) {
                throw new RuntimeException(
                    "The menu [{$locked->getKey()}] has an inconsistent item tree and cannot be deleted."
                );
            }

            $locked->delete();
        }, 3);
    }

    /**
     * Cria um item anexado ao fim do seu grupo de irmãos.
     *
     * O menu vem como **argumento**, e não dentro do payload: `menu_id` é
     * estrutural, definido uma vez e imutável depois. O chamador também **não**
     * fornece `sort_order` — a chave é ignorada mesmo quando enviada, porque
     * quem conhece o estado dos outros irmãos é este serviço.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createItem(Menu $menu, array $attributes): MenuItem
    {
        return DB::transaction(function () use ($menu, $attributes): MenuItem {
            $locked = $this->lockMenu($menu->getKey());

            $parentId = $this->parentId($locked, null, $attributes['parent_id'] ?? null);
            $destination = $this->destination($attributes);

            $item = new MenuItem;
            $item->fill([
                'menu_id' => $locked->getKey(),
                'parent_id' => $parentId,
                'label' => $this->label($attributes['label'] ?? null),
                'type' => $destination['type'],
                'page_id' => $destination['page_id'],
                'url' => $destination['url'],
                'sort_order' => $this->nextSortOrder($locked->getKey(), $parentId),
                'is_active' => array_key_exists('is_active', $attributes)
                    ? $this->isActive($attributes['is_active'])
                    : false,
            ]);
            $item->save();

            return $item;
        }, 3);
    }

    /**
     * Atualiza somente os campos informados.
     *
     * Alterar rótulo, destino ou estado **não** reordena nada — a ordem só muda
     * por uma decisão sobre a ordem. Quando `parent_id` muda, o item é anexado
     * ao fim do novo grupo de irmãos: o número antigo descrevia o lugar dele
     * entre outros irmãos e, no novo grupo, não descreve nada.
     *
     * `menu_id` é recusado quando difere do atual. Mover uma subárvore inteira
     * entre menus é outra operação — com contrato próprio —, não um campo
     * editável.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateItem(MenuItem $item, array $attributes): MenuItem
    {
        if (array_key_exists('menu_id', $attributes)) {
            $menuId = filter_var($attributes['menu_id'], FILTER_VALIDATE_INT);

            if ($menuId !== $item->getOriginal('menu_id')) {
                throw new InvalidArgumentException("The menu item [{$item->getKey()}] cannot change menus.");
            }
        }

        // O pai **persistido**, lido antes da transação. O rollback desfaz o
        // banco, mas não o objeto em memória: numa segunda tentativa o model já
        // carregaria o pai atribuído pela tentativa anterior, a comparação diria
        // "não mudou" e o retry pularia justamente o recálculo da ordem que ele
        // existe para refazer. É o mesmo cuidado que o `BannerService` tem com a
        // posição persistida.
        $originalParentId = $item->getOriginal('parent_id');

        return DB::transaction(function () use ($item, $attributes, $originalParentId): MenuItem {
            $locked = $this->lockMenu($item->menu_id);

            $payload = [];

            if (array_key_exists('label', $attributes)) {
                $payload['label'] = $this->label($attributes['label']);
            }

            if ($this->touchesDestination($attributes)) {
                $destination = $this->destination($attributes);
                $payload['type'] = $destination['type'];
                $payload['page_id'] = $destination['page_id'];
                $payload['url'] = $destination['url'];
            }

            if (array_key_exists('is_active', $attributes)) {
                $payload['is_active'] = $this->isActive($attributes['is_active']);
            }

            if (array_key_exists('parent_id', $attributes)) {
                $parentId = $this->parentId($locked, $item, $attributes['parent_id']);

                // Reenviar o mesmo pai é o caso comum de um formulário de
                // edição: preservar a ordem aqui é o que impede um simples
                // "salvar" de jogar o item para o fim da própria lista.
                if ($parentId !== $originalParentId) {
                    $payload['parent_id'] = $parentId;
                    $payload['sort_order'] = $this->nextSortOrder($locked->getKey(), $parentId);
                }
            }

            $item->fill($payload);
            $item->save();

            return $item;
        }, 3);
    }

    /**
     * Exclui um item — e somente ele.
     *
     * Um item com filhos é **recusado**: a subárvore não é cascateada, não é
     * anulada e não é promovida. Quem remove "Produtos" precisa decidir o que
     * fazer com os três filhos antes.
     *
     * Lacunas **não** são compactadas: excluir o item do meio pode deixar
     * `1, 3`, e isso continua válido porque a ordem é relativa, não uma
     * contagem. Compactar é assunto da reordenação explícita da F2.6-B.
     */
    public function deleteItem(MenuItem $item): void
    {
        DB::transaction(function () use ($item): void {
            $this->lockMenu($item->menu_id);

            $children = MenuItem::query()
                ->where('parent_id', $item->getKey())
                ->lockForUpdate()
                ->count();

            if ($children > 0) {
                throw new InvalidArgumentException(
                    "The menu item [{$item->getKey()}] still has {$children} child item(s) and cannot be deleted."
                );
            }

            $item->delete();
        }, 3);
    }

    /**
     * Irmãos de um grupo, na ordem contratada.
     *
     * O grupo é `(menu_id, parent_id)`: não existe ordem global do menu, e
     * comparar a ordem de um item raiz com a de um neto não significa nada.
     *
     * `id ASC` é o desempate determinístico — como não existe
     * `UNIQUE (menu_id, parent_id, sort_order)`, sem ele dois irmãos de mesma
     * ordem poderiam alternar entre requisições.
     *
     * @return Collection<int, MenuItem>
     */
    public function orderedSiblings(Menu $menu, ?int $parentId = null): Collection
    {
        return $this->siblingsQuery($menu->getKey(), $parentId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * O código cabe no contrato?
     *
     * Existe para o Form Request da F2.6-B antecipar a rejeição no formulário
     * sem reescrever a regra: quem responde é a mesma normalização usada na
     * gravação. É o mesmo arranjo de `BannerService::isSupportedLink()`.
     */
    public function isSupportedCode(mixed $value): bool
    {
        try {
            $this->code($value);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * A URL cabe no contrato?
     *
     * Mesma conveniência de interface, para o destino `url`.
     */
    public function isSupportedUrl(mixed $value): bool
    {
        try {
            $this->url($value);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Bloqueia a linha do menu — a âncora de todas as operações estruturais.
     *
     * É esta leitura que serializa criação, mudança de pai e exclusão **dentro
     * de um mesmo menu**. Ela existe porque o alvo natural do bloqueio não
     * serve: um grupo de irmãos ainda vazio não tem nenhuma linha para travar,
     * e travar o intervalo vazio recairia sobre um gap lock — compatível com
     * outro gap lock igual, de modo que duas criações simultâneas do **primeiro**
     * item de um grupo passariam as duas pela leitura e calculariam a mesma
     * ordem, ou colidiriam em deadlock na inserção.
     *
     * A linha de `menus` sempre existe, então o bloqueio recai sobre um registro
     * real: a segunda transação espera a primeira, relê o `MAX` já enxergando a
     * linha nova e recebe a ordem seguinte. Vale igualmente para o grupo raiz
     * vazio e para um grupo de filhos vazio.
     *
     * O bloqueio ser sempre o mesmo — e sempre o primeiro — também dá **ordem
     * determinística de lock**: duas operações estruturais no mesmo menu nunca
     * travam recursos em sentidos opostos, que é a receita clássica de deadlock
     * ao mover um item entre dois grupos.
     *
     * Este é um CMS administrativo: a granularidade por menu é folgada de
     * sobra, e segurança vale mais do que concorrência fina entre reordenações
     * simultâneas que ninguém faz.
     *
     * **Medido com dois processos reais e simultâneos**, em MySQL 8.4 sob
     * `REPEATABLE READ`, nos quatro cenários de `MenuConcurrencyTest`:
     *
     * ```text
     * âncora + MAX travado   →  sequência 1..N correta
     * só a âncora            →  sequência 1..N correta
     * só o MAX travado       →  sequência 1..N correta, por gap lock +
     *                           deadlock + retry, como já acontecia na F2.5
     * nenhum dos dois        →  ordem duplicada em todos os cenários
     * ```
     *
     * Ou seja: cada barreira **sozinha** já basta para a correção, e as duas
     * são mantidas de propósito. A âncora evita o caminho por deadlock, que
     * funciona mas desperdiça uma transação inteira a cada colisão; o bloqueio
     * na leitura do `MAX` garante a versão mais recente já commitada sem
     * depender de quando o read view da transação foi criado. Nenhuma das duas
     * está aqui por superstição — a última linha da tabela mostra o que
     * acontece sem elas.
     */
    private function lockMenu(int $menuId): Menu
    {
        $menu = Menu::query()->whereKey($menuId)->lockForUpdate()->first();

        if ($menu === null) {
            throw new InvalidArgumentException("The menu [{$menuId}] does not exist.");
        }

        return $menu;
    }

    /**
     * Próxima ordem livre do grupo, anexando ao fim.
     *
     * Grupo vazio começa em `1`. A leitura é *locking read* para devolver a
     * versão mais recente já commitada, e não o retrato do início da transação:
     * em `REPEATABLE READ` o read view nasce na primeira leitura consistente, e
     * depender dessa ordem sutil para uma invariante seria frágil. O bloqueio de
     * {@see self::lockMenu()} já serializa o trecho; este aqui torna a leitura
     * correta por construção, e não por consequência.
     *
     * `createItem()` e `updateItem()` usam `DB::transaction($callback, 3)`. O
     * retry cobre o deadlock transitório que o InnoDB ainda pode produzir entre
     * menus diferentes quando a tabela inteira está vazia — o gap do
     * `supremum` é um só nesse caso. A repetição reexecuta a operação
     * **inteira** e recalcula o `MAX` a partir do estado novo; esgotadas as três
     * tentativas, a exceção volta ao chamador em vez de ser engolida.
     */
    private function nextSortOrder(int $menuId, ?int $parentId): int
    {
        $current = $this->siblingsQuery($menuId, $parentId)
            ->lockForUpdate()
            ->max('sort_order');

        return $current === null ? 1 : (int) $current + 1;
    }

    /**
     * Consulta base de um grupo de irmãos.
     *
     * `parent_id` nulo é o grupo raiz, e precisa de `whereNull`: `= null` nunca
     * casa em SQL.
     *
     * @return Builder<MenuItem>
     */
    private function siblingsQuery(int $menuId, ?int $parentId)
    {
        $query = MenuItem::query()->where('menu_id', $menuId);

        return $parentId === null
            ? $query->whereNull('parent_id')
            : $query->where('parent_id', $parentId);
    }

    /**
     * Resolve e valida o pai proposto.
     *
     * A FK garante que o pai existe, mas **não** garante as duas invariantes que
     * importam aqui: que ele pertence ao mesmo menu e que a ligação não fecha um
     * ciclo. Cada linha de um ciclo é referencialmente válida — ele só aparece
     * percorrendo a cadeia de ancestrais.
     *
     * `$item` é `null` na criação: um item que ainda não existe não pode ser
     * ancestral de ninguém.
     */
    private function parentId(Menu $menu, ?MenuItem $item, mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $parentId = filter_var($value, FILTER_VALIDATE_INT);

        if ($parentId === false || $parentId < 1) {
            throw new InvalidArgumentException('The menu item parent must be a valid identifier.');
        }

        if ($item !== null && $parentId === (int) $item->getKey()) {
            throw new InvalidArgumentException(
                "The menu item [{$item->getKey()}] cannot be its own parent."
            );
        }

        $parent = MenuItem::query()->whereKey($parentId)->first();

        if ($parent === null) {
            throw new InvalidArgumentException("The menu item parent [{$parentId}] does not exist.");
        }

        if ($parent->menu_id !== $menu->getKey()) {
            throw new InvalidArgumentException(
                "The menu item parent [{$parentId}] belongs to another menu."
            );
        }

        if ($item !== null) {
            $this->guardNoCycle($menu, (int) $item->getKey(), $parent);
        }

        return $parentId;
    }

    /**
     * Recusa uma ligação que faria o item virar filho do próprio descendente.
     *
     * A cadeia de ancestrais é percorrida sobre um mapa carregado **de uma vez**
     * — a árvore de um menu é curta, e subir nó a nó com uma consulta por nível
     * pagaria N idas ao banco para responder uma pergunta local.
     *
     * O laço é limitado pelo número de itens do menu: uma cadeia mais longa do
     * que isso só existe se os dados já estiverem corrompidos, e nesse caso a
     * operação falha em vez de girar para sempre.
     */
    private function guardNoCycle(Menu $menu, int $itemId, MenuItem $parent): void
    {
        /** @var array<int, int|null> $parents */
        $parents = MenuItem::query()
            ->where('menu_id', $menu->getKey())
            ->pluck('parent_id', 'id')
            ->map(fn (mixed $value): ?int => $value === null ? null : (int) $value)
            ->all();

        $current = (int) $parent->getKey();
        $guard = count($parents) + 1;

        while ($guard-- > 0) {
            if ($current === $itemId) {
                throw new InvalidArgumentException(
                    "The menu item [{$itemId}] cannot become a descendant of itself."
                );
            }

            if (! array_key_exists($current, $parents) || $parents[$current] === null) {
                return;
            }

            $current = $parents[$current];
        }

        throw new RuntimeException(
            "The menu [{$menu->getKey()}] has an inconsistent item tree and cannot be reparented."
        );
    }

    /**
     * A árvore do menu é fechada em si mesma?
     *
     * Um item de **outro** menu apontando para dentro deste faria o `RESTRICT`
     * da hierarquia derrubar a exclusão no meio do caminho. Detectar antes
     * transforma um erro de driver a meio de uma transação em um erro de domínio
     * explícito.
     *
     * @param  Collection<int, MenuItem>  $items
     */
    private function guardTreeIsSelfContained(Menu $menu, Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $outsider = MenuItem::query()
            ->whereIn('parent_id', $items->modelKeys())
            ->where('menu_id', '!=', $menu->getKey())
            ->exists();

        if ($outsider) {
            throw new RuntimeException(
                "The menu [{$menu->getKey()}] has items referenced from another menu and cannot be deleted."
            );
        }
    }

    /**
     * O payload mexe no destino?
     *
     * Qualquer uma das três chaves conta, para que `['url' => '...']` sozinho
     * não passe despercebido num item que hoje é `page`.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function touchesDestination(array $attributes): bool
    {
        return array_key_exists('type', $attributes)
            || array_key_exists('page_id', $attributes)
            || array_key_exists('url', $attributes);
    }

    /**
     * Normaliza o destino como um trio coerente.
     *
     * O destino é **sempre informado por inteiro**: mexer nele exige o `type`
     * junto. Aceitar só `url` num item `page` obrigaria o serviço a adivinhar se
     * o chamador quis trocar de tipo ou preencher um campo que não vale para
     * aquele tipo — e a resposta errada deixaria o item com dois destinos ou
     * com nenhum.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{type: MenuItemType, page_id: ?int, url: ?string}
     */
    private function destination(array $attributes): array
    {
        $type = $this->type($attributes['type'] ?? null);

        if ($type === MenuItemType::Page) {
            if (array_key_exists('url', $attributes) && $this->url($attributes['url']) !== null) {
                throw new InvalidArgumentException('A page menu item must not carry a custom URL.');
            }

            return [
                'type' => $type,
                'page_id' => $this->pageId($attributes['page_id'] ?? null),
                'url' => null,
            ];
        }

        if (array_key_exists('page_id', $attributes) && $attributes['page_id'] !== null) {
            throw new InvalidArgumentException('A URL menu item must not reference a page.');
        }

        $url = $this->url($attributes['url'] ?? null);

        if ($url === null) {
            throw new InvalidArgumentException('A URL menu item requires a URL.');
        }

        return [
            'type' => $type,
            'page_id' => null,
            'url' => $url,
        ];
    }

    private function type(mixed $value): MenuItemType
    {
        if ($value instanceof MenuItemType) {
            return $value;
        }

        $resolved = is_string($value) ? MenuItemType::tryFrom($value) : null;

        if ($resolved === null) {
            throw new InvalidArgumentException('Unsupported menu item type.');
        }

        return $resolved;
    }

    /**
     * Identidade da página referenciada, conferida contra o catálogo.
     *
     * A FK já é a barreira final do banco, mas ela produziria uma
     * `QueryException` de driver. A checagem aqui existe para que a violação
     * chegue ao chamador como erro de domínio — inclusive fora do HTTP.
     *
     * O estado de publicação **não** é exigido: apontar para um rascunho é
     * legítimo, e a página pode ser publicada depois sem que o menu precise ser
     * reconfigurado. Quem decide o que aparece é a publicabilidade da F2.6-C.
     *
     * Uma página na lixeira, porém, é recusada: `Page` usa `SoftDeletes`, a
     * linha física continua lá e a FK aceitaria — mas criar um vínculo novo com
     * algo que o administrador acabou de excluir é engano, não intenção. Um
     * vínculo **já existente** cujo destino vá para a lixeira depois é
     * preservado; isso é outra coisa.
     */
    private function pageId(mixed $value): int
    {
        $pageId = filter_var($value, FILTER_VALIDATE_INT);

        if ($pageId === false || $pageId < 1) {
            throw new InvalidArgumentException('A page menu item requires a page reference.');
        }

        if (! Page::query()->whereKey($pageId)->exists()) {
            throw new InvalidArgumentException("The menu item page [{$pageId}] does not exist.");
        }

        return $pageId;
    }

    private function name(mixed $value): string
    {
        $name = is_string($value) ? trim($value) : '';

        if ($name === '') {
            throw new InvalidArgumentException('A menu requires a name.');
        }

        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new InvalidArgumentException('The menu name is longer than '.self::NAME_MAX_LENGTH.' characters.');
        }

        return $name;
    }

    /**
     * Identidade técnica do menu, validada e nunca corrigida.
     *
     * Só o espaço nas bordas é removido — `" main "` é erro de digitação, não
     * outro código. Tudo o mais é recusado em vez de saneado: transformar
     * `Menu Principal` em `menu-principal` faria o serviço escolher a chave por
     * quem chamou, e o consumidor público passaria a depender dessa escolha.
     */
    private function code(mixed $value): string
    {
        $code = is_string($value) ? trim($value) : '';

        if ($code === '') {
            throw new InvalidArgumentException('A menu requires a code.');
        }

        if (mb_strlen($code) > self::CODE_MAX_LENGTH) {
            throw new InvalidArgumentException('The menu code is longer than '.self::CODE_MAX_LENGTH.' characters.');
        }

        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            throw new InvalidArgumentException("The menu code [{$code}] is not in the canonical format.");
        }

        return $code;
    }

    /**
     * O código já está tomado?
     *
     * A checagem antecipa a violação como erro de domínio. O `UNIQUE` da coluna
     * continua sendo a barreira final: só ele fecha a corrida entre verificar e
     * inserir, no mesmo arranjo já usado por `pages.slug`.
     */
    private function guardCodeIsAvailable(string $code): void
    {
        if (Menu::query()->where('code', $code)->exists()) {
            throw new InvalidArgumentException("The menu code [{$code}] is already taken.");
        }
    }

    private function label(mixed $value): string
    {
        $label = is_string($value) ? trim($value) : '';

        if ($label === '') {
            throw new InvalidArgumentException('A menu item requires a label.');
        }

        if (mb_strlen($label) > self::LABEL_MAX_LENGTH) {
            throw new InvalidArgumentException('The menu item label is longer than '.self::LABEL_MAX_LENGTH.' characters.');
        }

        return $label;
    }

    /**
     * Estado explícito.
     *
     * Só `true` e `false` são aceitos. Traduzir `"on"`, `"1"` ou `null` aqui
     * faria o serviço adivinhar a intenção de quem chamou; a conversão da caixa
     * de seleção pertence à camada HTTP da F2.6-B.
     */
    private function isActive(mixed $value): bool
    {
        if (! is_bool($value)) {
            throw new InvalidArgumentException('The menu state must be a boolean.');
        }

        return $value;
    }

    /**
     * Normaliza a URL antes de validá-la.
     *
     * "Sem URL" tem **uma** representação: `null`. Guardar `''` obrigaria todo
     * leitor a tratar dois valores como a mesma ausência — e faria um item
     * `url` com espaços em branco passar por preenchido.
     */
    private function url(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('The menu item URL must be a string.');
        }

        $url = trim($value);

        if ($url === '') {
            return null;
        }

        if (mb_strlen($url) > self::URL_MAX_LENGTH) {
            throw new InvalidArgumentException('The menu item URL is longer than '.self::URL_MAX_LENGTH.' characters.');
        }

        $this->guardUrl($url);

        return $url;
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
    private function guardUrl(string $url): void
    {
        // A barra invertida não substitui `/`: alguns navegadores normalizam
        // `\` para `/`, e um caminho como `/\evil.example` passaria a valer
        // como `//evil.example`. Nenhum link legítimo precisa dela — dentro de
        // um caminho ela seria `%5C`.
        if (str_contains($url, '\\')) {
            throw new InvalidArgumentException("The menu item URL [{$url}] is not a valid internal path or HTTP URL.");
        }

        // Protocol-relative: aparenta caminho interno e leva a host externo.
        if (str_starts_with($url, '//')) {
            throw new InvalidArgumentException("The menu item URL [{$url}] is not a valid internal path or HTTP URL.");
        }

        if (str_starts_with($url, '/')) {
            return;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            throw new InvalidArgumentException("The menu item URL [{$url}] is not a valid internal path or HTTP URL.");
        }

        $scheme = isset($parts['scheme']) ? mb_strtolower($parts['scheme']) : null;

        if ($scheme === null || ! in_array($scheme, self::URL_SCHEMES, true)) {
            throw new InvalidArgumentException("The menu item URL [{$url}] uses an unsupported scheme.");
        }

        $host = $parts['host'] ?? '';

        if ($host === '' || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw new InvalidArgumentException("The menu item URL [{$url}] has no valid host.");
        }
    }
}
