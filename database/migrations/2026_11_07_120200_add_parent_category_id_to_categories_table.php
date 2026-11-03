<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->foreignId('parent_category_id')->nullable()->after('id')
                ->constrained('categories')->restrictOnDelete();
            $table->index(['parent_category_id', 'is_active', 'sort_order', 'id'], 'categories_parent_public_order_index');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex('categories_parent_public_order_index');
            $table->dropConstrainedForeignId('parent_category_id');
        });
    }
};
