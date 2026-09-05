<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePageRequest;
use App\Http\Requests\Admin\UpdatePageRequest;
use App\Models\Page;
use App\Services\PageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * CRUD administrativo das páginas estáticas.
 *
 * O controller é fino de propósito: toda escrita passa pelo `PageService`, que
 * é a fonte autoritativa das invariantes de slug e do ciclo de vida da página.
 * Nada de `Str::slug`, `withTrashed` ou Eloquent direto aqui — duplicar essas
 * regras na camada HTTP as faria divergir do domínio na primeira mudança.
 *
 * `{page}` resolve pela chave primária: `Page.id` é a identidade, o slug é só
 * o endereço público e pode mudar.
 */
class PageController extends Controller
{
    public function index(PageService $pages): View
    {
        return view('admin.pages.index', [
            'pages' => $pages->paginate(),
            'statuses' => $this->statusLabels(),
        ]);
    }

    public function create(): View
    {
        return view('admin.pages.create', [
            'statuses' => $this->statusLabels(),
        ]);
    }

    public function store(StorePageRequest $request, PageService $pages): RedirectResponse
    {
        $pages->create($this->payload($request->validated()));

        return redirect()
            ->route('admin.pages.index')
            ->with('status', 'Página criada com sucesso.');
    }

    public function edit(Page $page): View
    {
        return view('admin.pages.edit', [
            'page' => $page,
            'statuses' => $this->statusLabels(),
        ]);
    }

    public function update(UpdatePageRequest $request, Page $page, PageService $pages): RedirectResponse
    {
        $pages->update($page, $this->payload($request->validated()));

        return redirect()
            ->route('admin.pages.index')
            ->with('status', 'Página atualizada com sucesso.');
    }

    public function destroy(Page $page, PageService $pages): RedirectResponse
    {
        $pages->delete($page);

        return redirect()
            ->route('admin.pages.index')
            ->with('status', 'Página excluída com sucesso.');
    }

    /**
     * Normaliza o conteúdo antes de entregá-lo ao serviço.
     *
     * Um `textarea` vazio chega como null pelo middleware que converte strings
     * vazias, e a coluna `content` é NOT NULL. O endereço em branco segue como
     * null de propósito: é assim que o serviço entende "gere na criação,
     * preserve na edição".
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated): array
    {
        $validated['content'] = $validated['content'] ?? '';

        return $validated;
    }

    /**
     * Rótulos de apresentação derivados do enum — o `match` exaustivo garante
     * que um estado novo não passe despercebido pela interface.
     *
     * @return array<string, string>
     */
    private function statusLabels(): array
    {
        $labels = [];

        foreach (PageStatus::cases() as $status) {
            $labels[$status->value] = match ($status) {
                PageStatus::Draft => 'Rascunho',
                PageStatus::Published => 'Publicado',
            };
        }

        return $labels;
    }
}
