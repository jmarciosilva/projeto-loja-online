<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Executa as migrations.
     */
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 191)->unique();
            // `value` é codificado conforme `type`; JSON é usado apenas para valores estruturados.
            $table->text('value')->nullable();
            $table->string('type', 20);
            $table->timestamps();
        });
    }

    /**
     * Reverte as migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
