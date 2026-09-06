<?php

use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\ThemeSettingController;
use App\Http\Controllers\PageController as PublicPageController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('home'))->name('home');

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
