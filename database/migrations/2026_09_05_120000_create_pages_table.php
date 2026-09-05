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
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            // A constraint UNIQUE é a proteção final da unicidade do slug: o
            // PageService consulta a disponibilidade antes de gravar, mas só o
            // banco fecha a corrida entre verificar e inserir.
            $table->string('slug')->unique();
            // `content` guarda Markdown; a renderização segura para HTML é da F2.4-C.
            $table->longText('content');
            // VARCHAR indexado em vez de ENUM nativo — os estados vivem no enum PHP.
            $table->string('status', 20)->index();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverte as migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
