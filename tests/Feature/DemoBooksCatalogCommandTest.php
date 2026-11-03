<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\User;
use App\Services\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoBooksCatalogCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(MediaService::DISK);
    }

    public function test_local_demo_command_creates_books_with_auditable_initial_stock_and_is_idempotent(): void
    {
        $admin = User::factory()->create();
        DB::table('users')->where('id', $admin->id)->update([
            'can_manage_catalog' => true,
            'can_manage_stock' => true,
            'can_view_stock_movements' => true,
        ]);

        $this->runDemoCommand();
        $this->runDemoCommand();

        $this->assertSame(4, Category::query()->count());
        $this->assertSame(9, Product::query()->count());
        $this->assertSame(9, Media::query()->count());
        $this->assertSame(9, DB::table('product_stocks')->where('stock_control_enabled', true)->count());
        $this->assertSame(9, DB::table('stock_movements')->count());
        $this->assertSame(9, DB::table('stock_movements')->where('reason', 'Saldo inicial demonstrativo — E05.1')->count());
        $this->assertSame(1, DB::table('stock_movements')->distinct()->count('user_id'));
        $this->assertSame(9, Product::query()->where('condition', 'used')->whereNull('promotional_price')->count());
        $this->assertSame(9, Product::query()->whereHas('shippingProfile', fn ($query) => $query->where('requires_shipping', true)->whereNull('weight_kg'))->count());
    }

    public function test_demo_catalog_command_soft_deletes_only_the_verified_demo_shirt_and_preserves_its_media(): void
    {
        $admin = User::factory()->create();
        DB::table('users')->where('id', $admin->id)->update([
            'can_manage_catalog' => true,
            'can_manage_stock' => true,
            'can_view_stock_movements' => true,
        ]);
        $category = Category::factory()->create(['name' => 'Camisetas', 'slug' => 'camisetas', 'is_active' => true]);
        $media = Media::factory()->create(['disk' => MediaService::DISK, 'path' => 'demo/camiseta.png']);
        Storage::disk(MediaService::DISK)->put($media->path, 'demo-image');
        $shirt = Product::factory()->create([
            'category_id' => $category->id,
            'main_media_id' => $media->id,
            'name' => 'Camiseta de Algodão Básica',
            'slug' => 'camiseta-de-algodao-basica',
            'sku' => 'PRD-000001',
        ]);

        $this->runDemoCommand();

        $this->assertSoftDeleted('products', ['id' => $shirt->id]);
        $this->assertSoftDeleted('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        $this->assertTrue(Storage::disk(MediaService::DISK)->exists($media->path));
    }

    public function test_demo_catalog_command_refuses_to_overwrite_divergent_demo_stock(): void
    {
        $admin = User::factory()->create();
        DB::table('users')->where('id', $admin->id)->update([
            'can_manage_catalog' => true,
            'can_manage_stock' => true,
            'can_view_stock_movements' => true,
        ]);

        $this->runDemoCommand();
        DB::table('product_stocks')->where('product_id', Product::query()->where('sku', 'DEMO-LIV-INFO-001')->value('id'))
            ->update(['quantity_on_hand' => 5]);

        $this->assertSame(1, Artisan::call('loja:catalogo-demo-livros'));
        $this->assertDatabaseHas('product_stocks', ['product_id' => Product::query()->where('sku', 'DEMO-LIV-INFO-001')->value('id'), 'quantity_on_hand' => 5]);
    }

    public function test_demo_command_fails_clearly_without_one_authorized_administrator(): void
    {
        $this->assertSame(1, Artisan::call('loja:catalogo-demo-livros'));
        $this->assertStringContainsString('exatamente um administrador local autorizado', Artisan::output());
        $this->assertSame(0, Category::query()->count());
        $this->assertSame(0, Product::query()->count());
    }

    public function test_demo_command_refuses_local_sqlite_instead_of_mutating_it(): void
    {
        $admin = User::factory()->create();
        DB::table('users')->where('id', $admin->id)->update([
            'can_manage_catalog' => true,
            'can_manage_stock' => true,
            'can_view_stock_movements' => true,
        ]);
        $this->app['env'] = 'local';

        try {
            $this->assertSame(1, Artisan::call('loja:catalogo-demo-livros'));
            $this->assertStringContainsString('APP_ENV=local e o banco MySQL loja_online', Artisan::output());
            $this->assertSame(0, Category::query()->count());
            $this->assertSame(0, Product::query()->count());
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    private function runDemoCommand(): void
    {
        $result = Artisan::call('loja:catalogo-demo-livros');
        $output = Artisan::output();
        $this->assertSame(0, $result, $output);
    }
}
