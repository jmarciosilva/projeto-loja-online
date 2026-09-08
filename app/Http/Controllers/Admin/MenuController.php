<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MenuItemType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMenuItemRequest;
use App\Http\Requests\Admin\StoreMenuRequest;
use App\Http\Requests\Admin\UpdateMenuItemRequest;
use App\Http\Requests\Admin\UpdateMenuRequest;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Services\MenuService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

/**
 * Administração dos menus e dos seus itens — F2.6-B.
 *
 * Um controller só, e não dois: o item de menu não tem tela própria de
 * listagem, não tem endereço público e não existe fora do menu. A árvore, o
 * formulário de novo item e os controles de ordem moram todos na edição do
 * menu, e separar as ações em duas classes apenas duplicaria a montagem dessa
 * mesma árvore.
 *
 * O controller é fino de propósito: **toda escrita passa pelo `MenuService`**,
 * que é a fonte autoritativa das invariantes — formato e imutabilidade do
 * `code`, exclusividade entre `page_id` e `url`, pertencimento do pai ao menu,
 * ausência de ciclos, atribuição de `sort_order` e atomicidade. Não há
 * `Menu::create()`, cálculo de ordem nem `update()` de `sort_order` aqui.
 *
 * As consultas de **leitura** ficam neste controller. Elas não são invariante
 * de domínio — são o que a tela precisa mostrar —, e o contrato da F2.6-B
 * autoriza estender o `MenuService` somente para a ordenação administrativa.
 *
 * Nada de consulta pública: resolver menu por `code`, filtrar publicabilidade e
 * integrar o menu `main` ao header pertencem à F2.6-C.
 */
class MenuController extends Controller
{
    // --- Menu -------------------------------------------------------------

    /**
     * Listagem dos menus.
     *
     * `withCount('items')` traz a quantidade de itens em **uma** consulta
     * agregada: contar dentro do laço da Blade faria uma query por linha.
     *
     * Sem paginação — um catálogo de menus é curto por natureza, e a ordem
     * alfabética é a que ajuda quem procura um nome conhecido.
     */
    public function index(): View
    {
        return view('admin.menus.index', [
            'menus' => Menu::query()->withCount('items')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.menus.create');
    }

    public function store(StoreMenuRequest $request, MenuService $menus): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $menu = $menus->createMenu([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'is_active' => $request->boolean('is_active'),
            ]);
        } catch (InvalidArgumentException) {
            return $this->refuse('Não foi possível criar o menu: confira o nome e o código informados.');
        }

        return redirect()
            ->route('admin.menus.edit', $menu)
            ->with('status', 'Menu criado com sucesso. Adicione os itens abaixo.');
    }

    /**
     * Edição do menu — e, na mesma tela, a administração da sua árvore.
     *
     * A árvore é montada em memória a partir dos itens já carregados: uma
     * consulta para os itens, outra para as páginas de destino, nenhuma por nó.
     */
    public function edit(Menu $menu): View
    {
        // A mesma travessia serve às duas listas: a árvore administrativa e as
        // opções de item pai do formulário de novo item. Na criação nenhum item
        // é proibido — o item ainda não existe, e não pode ser ancestral de
        // ninguém.
        $rows = $this->rows($this->items($menu));

        return view('admin.menus.edit', [
            'menu' => $menu,
            'rows' => $rows,
            'parents' => $rows,
            'pages' => $this->availablePages(),
            'types' => $this->typeLabels(),
        ]);
    }

    /**
     * Atualiza o menu — `name` e `is_active`, jamais `code`.
     *
     * O payload é montado a partir do `UpdateMenuRequest`, que **não tem**
     * `code`: um código forçado no POST não chega ao domínio. O `MenuService`
     * continua recusando a alteração para qualquer outro consumidor.
     */
    public function update(UpdateMenuRequest $request, Menu $menu, MenuService $menus): RedirectResponse
    {
        try {
            $menus->updateMenu($menu, [
                'name' => $request->validated()['name'],
                'is_active' => $request->boolean('is_active'),
            ]);
        } catch (InvalidArgumentException) {
            return $this->refuse('Não foi possível salvar o menu: confira o nome informado.');
        }

        return redirect()
            ->route('admin.menus.edit', $menu)
            ->with('status', 'Menu atualizado com sucesso.');
    }

    /**
     * Exclui o menu e toda a sua árvore.
     *
     * Quem remove é o `MenuService`, das folhas para as raízes e numa única
     * transação. O controller não apaga item nenhum por conta própria.
     */
    public function destroy(Menu $menu, MenuService $menus): RedirectResponse
    {
        try {
            $menus->deleteMenu($menu);
        } catch (InvalidArgumentException|RuntimeException) {
            return $this->refuse('Não foi possível excluir o menu: a árvore de itens está inconsistente.');
        }

        return redirect()
            ->route('admin.menus.index')
            ->with('status', 'Menu excluído com sucesso.');
    }

    /**
     * Alterna o estado do menu sem abrir o formulário.
     *
     * A escrita continua sendo `updateMenu()`: publicar ou despublicar é uma
     * mudança de domínio como outra qualquer, e um `$menu->save()` aqui
     * contornaria a validação do serviço.
     */
    public function toggle(Menu $menu, MenuService $menus): RedirectResponse
    {
        $menus->updateMenu($menu, ['is_active' => ! $menu->is_active]);

        return redirect()
            ->route('admin.menus.index')
            ->with('status', $menu->is_active ? 'Menu ativado.' : 'Menu desativado.');
    }

    // --- Item -------------------------------------------------------------

    /**
     * Cria um item no menu da rota.
     *
     * O menu vem do **contexto**, e não do formulário: `menu_id` é estrutural.
     * `sort_order` também não vem — o item é anexado ao fim do seu grupo de
     * irmãos pelo `MenuService`.
     */
    public function storeItem(StoreMenuItemRequest $request, Menu $menu, MenuService $menus): RedirectResponse
    {
        try {
            $menus->createItem($menu, $this->itemPayload($request));
        } catch (InvalidArgumentException) {
            return $this->refuse($this->itemFailure());
        }

        return redirect()
            ->route('admin.menus.edit', $menu)
            ->with('status', 'Item criado com sucesso.');
    }

    public function editItem(Menu $menu, MenuItem $item): View
    {
        $this->guardOwnership($menu, $item);

        $items = $this->items($menu);

        return view('admin.menus.items.edit', [
            'menu' => $menu,
            'item' => $item,
            // O próprio item e os seus descendentes ficam de fora: a interface
            // não oferece um vínculo que o `MenuService` recusaria.
            'parents' => $this->rows($items, $this->subtreeIds($items, $item)),
            'pages' => $this->availablePages(),
            'types' => $this->typeLabels(),
        ]);
    }

    /**
     * Atualiza o item.
     *
     * Trocar de pai reutiliza `updateItem()`, que anexa o item ao fim do novo
     * grupo de irmãos: a ordem antiga descrevia o lugar dele entre outros
     * irmãos e, no grupo novo, não descreve nada.
     *
     * Esta é a **única** ação da subfase que alcança `guardNoCycle()`: só ela
     * chama `updateItem()` com `parent_id` no payload, e é lá que o serviço
     * percorre a cadeia de ancestrais. Se essa cadeia já estiver corrompida no
     * banco — um ciclo entre dois ancestrais, gravado por fora do serviço —, a
     * varredura esgota o teto e sai por `RuntimeException`, e não por
     * `InvalidArgumentException`. As duas são recusas de domínio e as duas
     * voltam como feedback: deixar a segunda escapar transformaria uma árvore
     * inconsistente em erro 500 no meio de uma edição comum.
     *
     * As mensagens são distintas porque os problemas são distintos. Um pai
     * inválido se resolve escolhendo outro pai; uma árvore corrompida, não —
     * e mandar o administrador tentar outro pai o faria repetir para sempre
     * uma operação que nenhuma escolha dele conserta.
     */
    public function updateItem(UpdateMenuItemRequest $request, Menu $menu, MenuItem $item, MenuService $menus): RedirectResponse
    {
        $this->guardOwnership($menu, $item);

        try {
            $menus->updateItem($item, $this->itemPayload($request));
        } catch (InvalidArgumentException) {
            return $this->refuse($this->itemFailure());
        } catch (RuntimeException) {
            return $this->refuse($this->treeFailure());
        }

        return redirect()
            ->route('admin.menus.edit', $menu)
            ->with('status', 'Item atualizado com sucesso.');
    }

    /**
     * Exclui o item — e somente ele.
     *
     * Um item com filhos é recusado pelo `MenuService`: a subárvore não é
     * cascateada, não é anulada e não é promovida. Aqui a recusa vira uma
     * mensagem que diz o que fazer antes de tentar de novo.
     */
    public function destroyItem(Menu $menu, MenuItem $item, MenuService $menus): RedirectResponse
    {
        $this->guardOwnership($menu, $item);

        try {
            $menus->deleteItem($item);
        } catch (InvalidArgumentException) {
            return $this->refuse(
                'Não é possível excluir este item enquanto ele tiver itens filhos. '
                .'Exclua ou mova os filhos antes.'
            );
        }

        return redirect()
            ->route('admin.menus.edit', $menu)
            ->with('status', 'Item excluído com sucesso.');
    }

    /**
     * Alterna o estado do item sem abrir o formulário.
     *
     * O payload leva **só** `is_active`, e é isso que estreita o que pode dar
     * errado: sem `parent_id`, o serviço não percorre ancestral nenhum, então
     * `guardNoCycle()` — e o `RuntimeException` dele — fica fora de alcance. O
     * que sobra é a corrida entre o model binding da rota e o bloqueio da
     * transação: outra sessão pode excluir o menu inteiro nesse intervalo, e
     * `lockMenu()` recusa a operação. É recusa de domínio, não defeito de
     * programação, e por isso volta como feedback.
     */
    public function toggleItem(Menu $menu, MenuItem $item, MenuService $menus): RedirectResponse
    {
        $this->guardOwnership($menu, $item);

        try {
            $menus->updateItem($item, ['is_active' => ! $item->is_active]);
        } catch (InvalidArgumentException) {
            return $this->refuse($this->staleFailure());
        }

        return redirect()
            ->route('admin.menus.edit', $menu)
            ->with('status', $item->is_active ? 'Item ativado.' : 'Item desativado.');
    }

    /**
     * Sobe o item uma posição dentro do seu grupo de irmãos.
     *
     * A ordem é gravada pelo `MenuService`, que valida a sequência inteira do
     * grupo antes de escrever. O controller não calcula número de ordem nem
     * atualiza `sort_order`.
     *
     * No topo da lista é no-op: a interface desabilita o botão, e o backend
     * simplesmente não altera nada se a requisição chegar assim mesmo.
     *
     * A ordenação não percorre ancestrais — ela olha um grupo de irmãos —, e
     * por isso não alcança o `RuntimeException` de `guardNoCycle()`. O que
     * pode recusar a operação é a árvore ter mudado entre a página exibida e o
     * clique: o menu excluído por outra sessão, ou o próprio item já removido
     * ou movido para outro pai. São recusas de domínio, e viram feedback.
     */
    public function moveItemUp(Menu $menu, MenuItem $item, MenuService $menus): RedirectResponse
    {
        $this->guardOwnership($menu, $item);

        try {
            $menus->moveItemUp($item);
        } catch (InvalidArgumentException) {
            return $this->refuse($this->staleFailure());
        }

        return redirect()
            ->route('admin.menus.edit', $menu)
            ->with('status', 'Ordem dos itens atualizada.');
    }

    /**
     * Desce o item uma posição, com o mesmo contrato de {@see self::moveItemUp()}.
     */
    public function moveItemDown(Menu $menu, MenuItem $item, MenuService $menus): RedirectResponse
    {
        $this->guardOwnership($menu, $item);

        try {
            $menus->moveItemDown($item);
        } catch (InvalidArgumentException) {
            return $this->refuse($this->staleFailure());
        }

        return redirect()
            ->route('admin.menus.edit', $menu)
            ->with('status', 'Ordem dos itens atualizada.');
    }

    // --- Apoio ------------------------------------------------------------

    /**
     * O item da rota pertence mesmo ao menu da rota?
     *
     * A rota é aninhada — `/admin/menus/{menu}/itens/{item}` —, e o binding
     * padrão do Laravel resolve os dois parâmetros de forma **independente**:
     * um id de item válido de outro menu passaria pelo model binding e chegaria
     * intacto à ação. Editar, excluir, alternar ou reordenar por essa porta
     * seria manipular a árvore de um menu através da URL de outro.
     *
     * A checagem é explícita, e não `scopeBindings()`, para que a regra fique
     * no código que a testa e não numa opção de roteamento — e para que
     * removê-la quebre um teste, em vez de passar despercebida.
     *
     * `404` e não `403`: pela URL pedida, esse item não existe.
     */
    private function guardOwnership(Menu $menu, MenuItem $item): void
    {
        abort_unless($item->menu_id === $menu->getKey(), 404);
    }

    /**
     * Itens do menu, na ordem contratada e com a página de destino já carregada.
     *
     * Uma consulta para os itens e outra para as páginas: a Blade nunca dispara
     * uma query por nó. `sort_order ASC, id ASC` é a mesma ordem de
     * `MenuService::orderedSiblings()` — `id` é o desempate determinístico.
     *
     * @return Collection<int, MenuItem>
     */
    private function items(Menu $menu): Collection
    {
        return $menu->items()
            ->with('page')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Achata a árvore na ordem de exibição, em memória.
     *
     * A coleção já vem ordenada por grupo; aqui ela só é percorrida em
     * profundidade, sem nenhuma consulta por nó. Cada linha carrega o que a
     * interface precisa saber e a Blade não deveria calcular:
     *
     * ```text
     * depth  → profundidade, para a indentação
     * first  → é o primeiro do seu grupo de irmãos? (desabilita "subir")
     * last   → é o último do seu grupo de irmãos?   (desabilita "descer")
     * ```
     *
     * `$excluded` remove um ramo inteiro da lista — é como o formulário de
     * edição deixa de oferecer o próprio item e os seus descendentes como pai.
     *
     * Um item cujo pai não esteja na coleção simplesmente não é alcançado a
     * partir da raiz: a travessia parte do grupo raiz e nunca entra num ciclo.
     *
     * @param  Collection<int, MenuItem>  $items
     * @param  list<int>  $excluded
     * @return list<array{item: MenuItem, depth: int, first: bool, last: bool}>
     */
    private function rows(Collection $items, array $excluded = []): array
    {
        $groups = [];

        foreach ($items as $item) {
            if (in_array((int) $item->getKey(), $excluded, true)) {
                continue;
            }

            $groups[$item->parent_id ?? 0][] = $item;
        }

        $rows = [];

        $walk = function (int $group, int $depth) use (&$walk, &$rows, $groups): void {
            $siblings = $groups[$group] ?? [];
            $last = count($siblings) - 1;

            foreach ($siblings as $index => $item) {
                $rows[] = [
                    'item' => $item,
                    'depth' => $depth,
                    'first' => $index === 0,
                    'last' => $index === $last,
                ];

                $walk((int) $item->getKey(), $depth + 1);
            }
        };

        $walk(0, 0);

        return $rows;
    }

    /**
     * O item e todos os seus descendentes.
     *
     * O formulário de edição não pode oferecê-los como pai: o próprio item
     * seria auto-referência, e um descendente fecharia um ciclo. O
     * `MenuService` recusa os dois de qualquer forma — a interface apenas não
     * oferece o que já sabe que seria recusado.
     *
     * A varredura é em largura sobre o mapa pai → filhos já carregado, com teto
     * no número de itens: nenhuma consulta por nível.
     *
     * @param  Collection<int, MenuItem>  $items
     * @return list<int>
     */
    private function subtreeIds(Collection $items, MenuItem $item): array
    {
        $children = [];

        foreach ($items as $candidate) {
            $children[$candidate->parent_id ?? 0][] = (int) $candidate->getKey();
        }

        $subtree = [(int) $item->getKey()];
        $queue = [(int) $item->getKey()];
        $guard = $items->count() + 1;

        while ($queue !== [] && $guard-- > 0) {
            $current = array_shift($queue);

            foreach ($children[$current] ?? [] as $child) {
                if (in_array($child, $subtree, true)) {
                    continue;
                }

                $subtree[] = $child;
                $queue[] = $child;
            }
        }

        return $subtree;
    }

    /**
     * Páginas oferecidas como destino, carregadas server-side.
     *
     * Sem AJAX, sem modal e sem seletor reutilizável: a lista inteira vai no
     * `<select>` do formulário, com título e endereço para distinguir páginas
     * homônimas. O valor é sempre `Page.id` — o slug é endereço público e
     * mutável, e usá-lo como valor faria uma renomeação quebrar o vínculo.
     *
     * Rascunhos continuam selecionáveis: apontar para uma página ainda não
     * publicada é legítimo, e quem decide o que aparece na loja é a
     * publicabilidade da F2.6-C. Páginas na lixeira ficam de fora — o escopo
     * global de `SoftDeletes` já as remove —, porque criar um vínculo novo com
     * algo recém-excluído é engano, não intenção.
     *
     * @return Collection<int, Page>
     */
    private function availablePages(): Collection
    {
        return Page::query()->orderBy('title')->get(['id', 'title', 'slug', 'status']);
    }

    /**
     * Traduz a entrada HTTP para o vocabulário do domínio.
     *
     * A conversão da caixa de seleção acontece aqui, e não no serviço: o
     * `MenuService` aceita `bool` estrito de propósito, para não adivinhar a
     * intenção de quem o chama fora do HTTP.
     *
     * `menu_id` e `sort_order` **nunca** entram no payload, nem quando enviados
     * à força: o menu é o da rota e a ordem é do serviço.
     *
     * O destino viaja **inteiro** — `type`, `page_id` e `url` juntos —, porque
     * é assim que o `MenuService` o normaliza: mandar só `url` num item `page`
     * o obrigaria a adivinhar se houve troca de tipo.
     *
     * @return array<string, mixed>
     */
    private function itemPayload(StoreMenuItemRequest $request): array
    {
        $validated = $request->validated();

        return [
            'label' => $validated['label'],
            'type' => $validated['type'],
            'page_id' => $validated['page_id'] ?? null,
            'url' => $validated['url'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /**
     * Mensagem única das recusas estruturais do item.
     *
     * O que o `MenuService` ainda pode recusar depois do Form Request é a
     * **hierarquia**: pai de outro menu, o próprio item ou um descendente. O
     * formulário não oferece nenhuma dessas opções, então chegar aqui significa
     * payload forjado ou árvore alterada por outra sessão no meio do caminho —
     * e a mensagem diz exatamente qual é o contrato violado.
     *
     * A exceção de domínio não é repassada crua: ela é escrita em inglês, para
     * o log e para quem lê o código, e a interface administrativa é em PT-BR.
     */
    private function itemFailure(): string
    {
        return 'Não foi possível salvar o item: o item pai precisa pertencer a este menu '
            .'e não pode ser o próprio item nem um de seus descendentes.';
    }

    /**
     * Recusa por árvore inconsistente.
     *
     * Separada de {@see self::itemFailure()} porque o administrador não tem o
     * que fazer de diferente: nenhuma escolha de pai conserta um ciclo já
     * gravado entre dois ancestrais. Repetir "escolha outro pai" o deixaria
     * tentando indefinidamente uma operação que a estrutura não permite.
     *
     * O texto descreve a situação sem repassar a exceção crua — ela é escrita
     * em inglês, para o log e para quem lê o código, e a interface é em PT-BR.
     */
    private function treeFailure(): string
    {
        return 'Não foi possível salvar o item: a hierarquia deste menu está inconsistente e '
            .'precisa ser corrigida antes de mover itens.';
    }

    /**
     * Recusa por estado vencido.
     *
     * A tela foi montada num instante e o clique chegou noutro. Entre os dois,
     * outra sessão pode ter excluído o menu, removido o item ou mudado o pai
     * dele — e aí a operação já não descreve nada que exista. Recarregar
     * resolve, e é isso que a mensagem pede.
     */
    private function staleFailure(): string
    {
        return 'Não foi possível concluir a ação: o menu ou o item mudaram em outra sessão. '
            .'Recarregue a página e tente de novo.';
    }

    /**
     * Devolve o administrador ao formulário com a recusa explicada.
     *
     * O input é preservado para que ninguém precise redigitar o que já havia
     * preenchido, e o erro entra no mesmo `$errors` que as Blades já exibem —
     * nada de stack trace, nada de falha silenciosa.
     */
    private function refuse(string $message): RedirectResponse
    {
        return back()->withInput()->withErrors(['menu' => $message]);
    }

    /**
     * Rótulos de apresentação derivados do enum — o `match` exaustivo garante
     * que um destino novo não passe despercebido pela interface.
     *
     * @return array<string, string>
     */
    private function typeLabels(): array
    {
        $labels = [];

        foreach (MenuItemType::cases() as $type) {
            $labels[$type->value] = match ($type) {
                MenuItemType::Page => 'Página do site',
                MenuItemType::Url => 'URL personalizada',
            };
        }

        return $labels;
    }
}
