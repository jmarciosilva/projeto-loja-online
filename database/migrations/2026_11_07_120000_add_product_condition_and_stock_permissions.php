<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            // NULL preserva a condição desconhecida dos produtos já cadastrados.
            $table->string('condition', 16)->nullable()->after('description');
            $table->string('condition_notes', 500)->nullable()->after('condition');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('can_manage_stock')->default(false)->after('can_manage_shipping_integrations');
            $table->boolean('can_view_stock_movements')->default(false)->after('can_manage_stock');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['can_manage_stock', 'can_view_stock_movements']);
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['condition', 'condition_notes']);
        });
    }
};
