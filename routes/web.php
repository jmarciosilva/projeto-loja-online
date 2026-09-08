<?php

use App\Enums\BannerPosition;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\ThemeSettingController;
use App\Http\Controllers\Admin\VisualIdentitySettingController;
use App\Http\Controllers\PageController as PublicPageController;
use App\Services\BannerService;
use App\Services\MediaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
 * Home comercial da loja. Ela continua sendo a página existente — a F2.5-C
 * apenas acrescenta os banners de destaque no topo.
 *
 * A closure resolve o `BannerService` pelo container e entrega à Blade o que o
 * serviço já ordenou e filtrou: a view não consulta `Banner`. O `MediaService`
 * viaja junto porque é dele que sai a URL pública da imagem, no mesmo arranjo
 * já usado pela listagem administrativa de banners — o banner nunca guarda
 * caminho nem URL.
 */
Route::get('/', fn (BannerService $banners, MediaService $media) => view('home', [
    'heroBanners' => $banners->activeForPosition(BannerPosition::Hero),
    'media' => $media,
]))->name('home');

/*
 * Páginas estáticas públicas, sob o namespace `/paginas/` em vez de um
 * catch-all `/{slug}`: o storefront ainda receberá catálogo, produtos, carrinho
 * e conta do cliente, e um catch-all colocaria slugs do CMS competindo com
 * essas rotas. Sem `auth` — é conteúdo público, e apenas o que está publicado
 * é resolvido.
 */
Route::get('/paginas/{slug}', [PublicPageController::class, 'show'])->name('pages.show');

Route::get('/admin', fn () => view('admin.index'))
    ->middleware('auth')
    ->name('admin');

/*
 * Rotas administrativas nomeadas sob o prefixo `admin.`. O dashboard acima
 * mantém o nome `admin`, sem ponto, para não quebrar os testes e a navegação
 * da F2.2 — renomeá-lo seria refactor fora do escopo desta subfase.
 */
Route::middleware('auth')
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('midias', [MediaController::class, 'index'])->name('media.index');
        Route::post('midias', [MediaController::class, 'store'])->name('media.store');
        Route::delete('midias/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

        Route::get('configuracoes', [SiteSettingController::class, 'edit'])
            ->name('settings.edit');
        Route::put('configuracoes', [SiteSettingController::class, 'update'])
            ->name('settings.update');

        Route::get('configuracoes/tema', [ThemeSettingController::class, 'edit'])
            ->name('settings.theme.edit');
        Route::put('configuracoes/tema', [ThemeSettingController::class, 'update'])
            ->name('settings.theme.update');

        // Logo e favicon referenciam mídia da biblioteca da F2.7 por `Media.id`;
        // não há upload próprio nem caminho de arquivo nesta tela.
        Route::get('configuracoes/identidade-visual', [VisualIdentitySettingController::class, 'edit'])
            ->name('settings.identity.edit');
        Route::put('configuracoes/identidade-visual', [VisualIdentitySettingController::class, 'update'])
            ->name('settings.identity.update');

        // `{page}` resolve por `Page.id`: o slug é endereço público e mutável,
        // e amarrar a rota administrativa a ele faria a identidade mudar junto.
        Route::get('paginas', [PageController::class, 'index'])
            ->name('pages.index');
        Route::get('paginas/criar', [PageController::class, 'create'])
            ->name('pages.create');
        Route::post('paginas', [PageController::class, 'store'])
            ->name('pages.store');
        Route::get('paginas/{page}/editar', [PageController::class, 'edit'])
            ->name('pages.edit');
        Route::get('paginas/{page}/preview', [PageController::class, 'preview'])
            ->name('pages.preview');
        Route::put('paginas/{page}', [PageController::class, 'update'])
            ->name('pages.update');
        Route::delete('paginas/{page}', [PageController::class, 'destroy'])
            ->name('pages.destroy');

        // `banners` é a mesma palavra em português e inglês, então o segmento
        // serve às duas. `{banner}` resolve por `Banner.id`: o banner não tem
        // slug nem endereço público próprio.
        Route::get('banners', [BannerController::class, 'index'])
            ->name('banners.index');
        Route::get('banners/criar', [BannerController::class, 'create'])
            ->name('banners.create');
        Route::post('banners', [BannerController::class, 'store'])
            ->name('banners.store');
        Route::get('banners/{banner}/editar', [BannerController::class, 'edit'])
            ->name('banners.edit');
        Route::put('banners/{banner}', [BannerController::class, 'update'])
            ->name('banners.update');
        Route::delete('banners/{banner}', [BannerController::class, 'destroy'])
            ->name('banners.destroy');

        // Ordenação explícita, decidida nesta subfase: um passo por vez, dentro
        // da posição do próprio banner. POST porque mover não é idempotente —
        // repetir o pedido move de novo.
        Route::post('banners/{banner}/mover', [BannerController::class, 'move'])
            ->name('banners.move');

        // `menus` é a mesma palavra em português e inglês, então o segmento
        // serve às duas. `{menu}` resolve por `Menu.id`: o `code` é a identidade
        // técnica pela qual o consumidor público encontrará o menu, e amarrar a
        // rota administrativa a ele misturaria os dois papéis.
        Route::get('menus', [MenuController::class, 'index'])
            ->name('menus.index');
        Route::get('menus/criar', [MenuController::class, 'create'])
            ->name('menus.create');
        Route::post('menus', [MenuController::class, 'store'])
            ->name('menus.store');
        Route::get('menus/{menu}/editar', [MenuController::class, 'edit'])
            ->name('menus.edit');
        Route::put('menus/{menu}', [MenuController::class, 'update'])
            ->name('menus.update');
        Route::delete('menus/{menu}', [MenuController::class, 'destroy'])
            ->name('menus.destroy');
        Route::post('menus/{menu}/alternar', [MenuController::class, 'toggle'])
            ->name('menus.toggle');

        /*
         * Itens aninhados sob o próprio menu. Não existe `/admin/itens/{item}`:
         * o item não tem endereço próprio e não significa nada fora do menu.
         *
         * O binding dos dois parâmetros é **independente** — um id de item de
         * outro menu chegaria intacto à ação —, e por isso o `MenuController`
         * confere o pertencimento explicitamente antes de qualquer operação.
         *
         * Não há rota `items.create`: o formulário de novo item vive na própria
         * tela de edição do menu, ao lado da árvore que ele altera.
         */
        Route::post('menus/{menu}/itens', [MenuController::class, 'storeItem'])
            ->name('menus.items.store');
        Route::get('menus/{menu}/itens/{item}/editar', [MenuController::class, 'editItem'])
            ->name('menus.items.edit');
        Route::put('menus/{menu}/itens/{item}', [MenuController::class, 'updateItem'])
            ->name('menus.items.update');
        Route::delete('menus/{menu}/itens/{item}', [MenuController::class, 'destroyItem'])
            ->name('menus.items.destroy');
        Route::post('menus/{menu}/itens/{item}/alternar', [MenuController::class, 'toggleItem'])
            ->name('menus.items.toggle');

        // Ordenação um passo por vez, dentro do grupo de irmãos do item. Duas
        // rotas em vez de uma com direção no corpo: não sobra payload para
        // validar, e a URL já diz o que faz. POST porque mover não é
        // idempotente — repetir o pedido move de novo.
        Route::post('menus/{menu}/itens/{item}/subir', [MenuController::class, 'moveItemUp'])
            ->name('menus.items.move-up');
        Route::post('menus/{menu}/itens/{item}/descer', [MenuController::class, 'moveItemDown'])
            ->name('menus.items.move-down');
    });

/*
 * Health check da aplicação: confirma que o Laravel alcança MySQL e Redis.
 *
 * Não confundir com os outros dois endpoints de saúde do projeto:
 *   /health.php  → arquivo estático, responde mesmo sem o Laravel instalado.
 *                  É o que o healthcheck do container nginx usa.
 *   /up          → health nativo do Laravel 11+, só executa o framework.
 *
 * Este aqui é o único que toca as dependências externas.
 */
Route::get('/health', function () {
    $checks = [];

    try {
        DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (Throwable $e) {
        $checks['database'] = 'falhou: '.$e->getMessage();
    }

    try {
        Cache::put('health:probe', '1', 10);
        $checks['cache'] = Cache::get('health:probe') === '1' ? 'ok' : 'falhou: valor não retornou';
    } catch (Throwable $e) {
        $checks['cache'] = 'falhou: '.$e->getMessage();
    }

    $healthy = ! in_array(false, array_map(fn ($s) => $s === 'ok', $checks), true);

    return response()->json([
        'status' => $healthy ? 'ok' : 'degradado',
        'checks' => $checks,
    ], $healthy ? 200 : 503);
})->name('health');
