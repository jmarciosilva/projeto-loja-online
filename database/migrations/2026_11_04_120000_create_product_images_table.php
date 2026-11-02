<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('sort_order');
            $table->timestamps();
            $table->unique(['product_id', 'media_id']);
            $table->unique(['product_id', 'sort_order']);
            $table->index(['media_id', 'product_id']);
        });

        // Preserve existing primary-media references as the first gallery item.
        DB::table('products')->whereNotNull('main_media_id')->orderBy('id')->chunkById(500, function ($products): void {
            $now = now();
            DB::table('product_images')->insert($products->map(fn ($product) => [
                'product_id' => $product->id,
                'media_id' => $product->main_media_id,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
