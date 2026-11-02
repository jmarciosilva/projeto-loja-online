<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Os preços promocionais existentes são mantidos, mas começam desativados.
     * Só uma ativação explícita poderá publicá-los sob a regra de período.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost_price', 12, 2)->nullable()->after('description');
            $table->decimal('target_margin', 5, 2)->nullable()->after('cost_price');
            $table->boolean('promotion_enabled')->default(false)->after('promotional_price');
            $table->dateTime('promotion_starts_at')->nullable()->after('promotion_enabled');
            $table->dateTime('promotion_ends_at')->nullable()->after('promotion_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'cost_price',
                'target_margin',
                'promotion_enabled',
                'promotion_starts_at',
                'promotion_ends_at',
            ]);
        });
    }
};
