<?php

namespace App\Http\Controllers;

use App\Services\PageContentRenderer;
use App\Services\PageService;
use Illuminate\View\View;

/**
 * Exibição pública das páginas estáticas.
 *
 * Fino de propósito: a regra de publicação vive no `PageService` e a conversão
 * de Markdown no `PageContentRenderer`. O controller apenas traduz a ausência
 * de página publicada em 404.
 *
 * O 404 é deliberado — e não 403 — mesmo para visitante autenticado: responder
 * "existe, mas você não pode ver" revelaria que o rascunho existe. O preview de
 * rascunhos acontece somente pela rota administrativa.
 */
class PageController extends Controller
{
    public function show(string $slug, PageService $pages, PageContentRenderer $renderer): View
    {
        $page = $pages->findPublishedBySlug($slug);

        abort_if($page === null, 404);

        return view('pages.show', [
            'page' => $page,
            'content' => $renderer->render($page->content),
        ]);
    }
}
