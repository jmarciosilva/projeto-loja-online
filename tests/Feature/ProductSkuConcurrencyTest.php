<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

class ProductSkuConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('A prova concorrente de SKU exige o MySQL de testes.');
        }
        if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            $this->markTestSkipped('A prova concorrente de SKU exige pcntl e posix.');
        }
    }

    public function test_two_processes_create_distinct_automatic_skus_from_database_ids(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $categoryId = $category->id;
        $startAt = microtime(true) + 0.15;
        $pids = [];

        // Cada worker abre sua própria conexão e inicia a gravação no mesmo instante.
        DB::disconnect();
        for ($worker = 1; $worker <= 2; $worker++) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'Não foi possível iniciar o worker concorrente.');

            if ($pid === 0) {
                try {
                    DB::reconnect();
                    while (microtime(true) < $startAt) {
                        // Alinha o início dos dois processos para exercitar a corrida.
                    }
                    app(ProductService::class)->create([
                        'category_id' => $categoryId,
                        'name' => 'Produto concorrente '.$worker,
                        'price' => '10,00',
                    ]);
                } catch (Throwable) {
                    // A verificação no processo pai detecta qualquer gravação ausente.
                }
                posix_kill(getmypid(), SIGKILL);
            }

            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        DB::reconnect();

        $products = Product::query()->orderBy('id')->get(['id', 'sku']);
        $this->assertCount(2, $products);
        $this->assertSame(2, $products->pluck('sku')->unique()->count());
        foreach ($products as $product) {
            $this->assertSame('PRD-'.str_pad((string) $product->id, 6, '0', STR_PAD_LEFT), $product->sku);
        }
    }
}
