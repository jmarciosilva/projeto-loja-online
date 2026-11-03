<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

/** Este teste só migra um banco explicitamente isolado, com nome *_test. */
class StockConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) env('E05_MYSQL_TEST_DATABASE', '');
        if (app()->environment() !== 'testing' || preg_match('/^[A-Za-z0-9_]+_test$/', $database) !== 1
            || $database === 'loja_online') {
            $this->markTestSkipped('Concorrência de estoque exige MySQL de testes isolado com nome terminado em _test.');
        }

        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.database', $database);
        DB::purge('mysql');
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('A conexão MySQL isolada não está disponível.');
        }
        $schemaExists = DB::selectOne('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$database]) !== null;
        $tableCount = (int) DB::selectOne('SELECT COUNT(*) AS count FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [$database])->count;
        if (! $schemaExists || $tableCount !== 0) {
            $this->markTestSkipped('O schema MySQL de teste deve existir e estar vazio antes da migração da prova concorrente.');
        }
        if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            $this->markTestSkipped('A prova de concorrência requer pcntl e posix.');
        }

        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    public function test_two_concurrent_exits_cannot_sell_more_than_the_available_balance(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $user = User::factory()->create();
        $user->forceFill(['can_manage_catalog' => true, 'can_manage_stock' => true])->save();
        app(StockService::class)->configureInitial($product, true, 1, 0, $user);
        $productId = $product->id;
        $userId = $user->id;
        $startAt = microtime(true) + 0.2;
        $children = [];

        DB::disconnect();
        for ($worker = 0; $worker < 2; $worker++) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'Não foi possível criar worker concorrente.');
            if ($pid === 0) {
                try {
                    DB::purge();
                    DB::reconnect();
                    while (microtime(true) < $startAt) {
                        usleep(1000);
                    }
                    app(StockService::class)->manualExit(
                        Product::query()->findOrFail($productId),
                        1,
                        'Saída concorrente de teste',
                        User::query()->findOrFail($userId),
                    );
                } catch (Throwable) {
                    // Um dos workers deve ser recusado após obter o bloqueio da linha.
                }
                exit(0);
            }
            $children[] = $pid;
        }

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }
        DB::reconnect();

        $finalStock = Product::query()->findOrFail($productId)->stock;
        $this->assertSame(0, $finalStock->quantity_on_hand);
        $this->assertSame(0, $finalStock->quantity_reserved);
        $this->assertSame(2, StockMovement::query()->where('product_id', $productId)->count());
        $this->assertSame(1, StockMovement::query()->where('product_id', $productId)->where('type', 'manual_exit')->count());
    }
}
