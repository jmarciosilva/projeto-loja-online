<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_shipping_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->boolean('requires_shipping');
            $table->decimal('weight_kg', 8, 3)->nullable();
            $table->decimal('height_cm', 7, 2)->nullable();
            $table->decimal('width_cm', 7, 2)->nullable();
            $table->decimal('length_cm', 7, 2)->nullable();
            // Medidas distintas das dimensões do produto: referem-se ao pacote
            // completo de uma unidade e não recebem valores presumidos.
            $table->decimal('package_weight_kg', 8, 3)->nullable();
            $table->decimal('package_height_cm', 7, 2)->nullable();
            $table->decimal('package_width_cm', 7, 2)->nullable();
            $table->decimal('package_length_cm', 7, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_shipping_profiles');
    }
};
