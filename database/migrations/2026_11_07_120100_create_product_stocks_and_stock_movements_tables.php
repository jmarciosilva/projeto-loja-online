<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity_on_hand')->default(0);
            $table->unsignedInteger('quantity_reserved')->default(0);
            $table->unsignedInteger('minimum_quantity')->default(0);
            $table->boolean('stock_control_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_stock_id')->constrained('product_stocks')->cascadeOnDelete();
            $table->string('type', 32);
            $table->integer('quantity_delta');
            $table->unsignedInteger('quantity_before');
            $table->unsignedInteger('quantity_after');
            $table->string('reason', 500);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_type', 100)->nullable();
            $table->string('reference_id', 100)->nullable();
            $table->timestamps();
            $table->index(['product_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE product_stocks ADD CONSTRAINT product_stocks_reserved_lte_physical CHECK (quantity_reserved <= quantity_on_hand)');
            DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_nonnegative_balance CHECK (quantity_after >= 0)');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER product_stocks_valid_insert BEFORE INSERT ON product_stocks WHEN NEW.quantity_reserved > NEW.quantity_on_hand OR NEW.quantity_on_hand < 0 OR NEW.quantity_reserved < 0 OR NEW.minimum_quantity < 0 BEGIN SELECT RAISE(ABORT, 'invalid product stock quantities'); END");
            DB::unprepared("CREATE TRIGGER product_stocks_valid_update BEFORE UPDATE ON product_stocks WHEN NEW.quantity_reserved > NEW.quantity_on_hand OR NEW.quantity_on_hand < 0 OR NEW.quantity_reserved < 0 OR NEW.minimum_quantity < 0 BEGIN SELECT RAISE(ABORT, 'invalid product stock quantities'); END");
            DB::unprepared("CREATE TRIGGER stock_movements_valid_insert BEFORE INSERT ON stock_movements WHEN NEW.quantity_before < 0 OR NEW.quantity_after < 0 BEGIN SELECT RAISE(ABORT, 'invalid stock movement balance'); END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_movements DROP CHECK stock_movements_nonnegative_balance');
            DB::statement('ALTER TABLE product_stocks DROP CHECK product_stocks_reserved_lte_physical');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS stock_movements_valid_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS product_stocks_valid_update');
            DB::unprepared('DROP TRIGGER IF EXISTS product_stocks_valid_insert');
        }
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_stocks');
    }
};
