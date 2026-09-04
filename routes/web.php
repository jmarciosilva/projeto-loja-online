<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('home'))->name('home');

Route::get('/admin', fn () => view('admin.index'))
    ->middleware('auth')
    ->name('admin');

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
