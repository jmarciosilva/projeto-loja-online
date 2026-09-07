<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BannerPosition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MoveBannerRequest;
use App\Http\Requests\Admin\StoreBannerRequest;
use App\Http\Requests\Admin\UpdateBannerRequest;
use App\Models\Banner;
use App\Services\BannerService;
use App\Services\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * CRUD administrativo dos banners.
 *
 * O controller é fino de propósito: toda escrita passa pelo `BannerService`,
 * que é a fonte autoritativa das invariantes — atribuição da ordem,
 * normalização do link, validação da mídia e atomicidade. Nada de
 * `Banner::create()`, cálculo de `sort_order` ou consulta ao Eloquent aqui;
 * duplicar essas regras na camada HTTP as faria divergir do domínio na
 * primeira mudança.
 */
class BannerController extends Controller
{
    /**
     * Listagem agrupada por posição.
     *
     * Sem paginação, e isso é deliberado: a ordem é relativa **dentro de cada
     * posição**, e paginar quebraria justamente a operação de ordenar — subir
     * um banner que está no topo de uma página exigiria atravessar a fronteira
     * da paginação. Cada grupo é uma lista curta por natureza.
     *
     * A consulta é `orderedForPosition()`, a mesma do domínio: inclui banners
     * inativos, porque a tela administrativa mostra a lista completa.
     */
    public function index(BannerService $banners, MediaService $media): View
    {
        $grouped = [];

        foreach (BannerPosition::cases() as $position) {
            $grouped[$position->value] = $banners->orderedForPosition($position);
        }

        return view('admin.banners.index', [
            'grouped' => $grouped,
            'positions' => $this->positionLabels(),
            'media' => $media,
        ]);
    }

    public function create(BannerService $banners): View
    {
        return view('admin.banners.create', [
            'positions' => $this->positionLabels(),
            'availableMedia' => $banners->availableMedia(),
        ]);
    }

    public function store(StoreBannerRequest $request, BannerService $banners): RedirectResponse
    {
        $banners->create($this->payload($request));

        return redirect()
            ->route('admin.banners.index')
            ->with('status', 'Banner criado com sucesso.');
    }

    public function edit(Banner $banner, BannerService $banners): View
    {
        return view('admin.banners.edit', [
            'banner' => $banner,
            'positions' => $this->positionLabels(),
            'availableMedia' => $banners->availableMedia(),
        ]);
    }

    public function update(UpdateBannerRequest $request, Banner $banner, BannerService $banners): RedirectResponse
    {
        $banners->update($banner, $this->payload($request));

        return redirect()
            ->route('admin.banners.index')
            ->with('status', 'Banner atualizado com sucesso.');
    }

    /**
     * Remove somente o banner. A mídia continua na biblioteca — ela é um
     * arquivo compartilhável, e o banner era apenas um de seus consumidores.
     */
    public function destroy(Banner $banner, BannerService $banners): RedirectResponse
    {
        $banners->delete($banner);

        return redirect()
            ->route('admin.banners.index')
            ->with('status', 'Banner excluído com sucesso.');
    }

    /**
     * Ordenação explícita, um passo por vez, dentro da própria posição.
     *
     * A escolha por subir/descer em vez de enviar a sequência inteira mantém o
     * payload mínimo: o banner vem da rota e a direção tem dois valores
     * possíveis. Não há lista de ids para conferir, nem número de ordem que o
     * administrador precise digitar — e, sem drag-and-drop ou AJAX, montar a
     * sequência completa exigiria expor `sort_order` como campo livre, que o
     * contrato recusa.
     */
    public function move(MoveBannerRequest $request, Banner $banner, BannerService $banners): RedirectResponse
    {
        if ($request->validated()['direction'] === MoveBannerRequest::UP) {
            $banners->moveUp($banner);
        } else {
            $banners->moveDown($banner);
        }

        return redirect()
            ->route('admin.banners.index')
            ->with('status', 'Ordem dos banners atualizada.');
    }

    /**
     * Traduz a entrada HTTP para o vocabulário do domínio.
     *
     * A conversão da caixa de seleção acontece aqui, e não no serviço: o
     * `BannerService` aceita `bool` estrito de propósito, para não adivinhar a
     * intenção de quem o chama fora do HTTP. `"0"`/`"1"` são forma de
     * formulário, não de domínio.
     *
     * `sort_order` nunca entra no payload — nem quando enviado à força.
     *
     * @return array<string, mixed>
     */
    private function payload(StoreBannerRequest $request): array
    {
        $validated = $request->validated();

        return [
            'name' => $validated['name'],
            'media_id' => $validated['media_id'],
            'position' => $validated['position'],
            'link_url' => $validated['link_url'] ?? null,
            'alt_text' => $validated['alt_text'],
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /**
     * Rótulos de apresentação derivados do enum — o `match` exaustivo garante
     * que uma posição nova não passe despercebida pela interface.
     *
     * @return array<string, string>
     */
    private function positionLabels(): array
    {
        $labels = [];

        foreach (BannerPosition::cases() as $position) {
            $labels[$position->value] = match ($position) {
                BannerPosition::Hero => 'Destaque',
                BannerPosition::Sidebar => 'Lateral',
                BannerPosition::Footer => 'Rodapé',
            };
        }

        return $labels;
    }
}
