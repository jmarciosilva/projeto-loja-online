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
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            // Identificação administrativa, não título público: é como o
            // administrador reconhece o banner na listagem. O storefront
            // renderiza a imagem e o `alt_text`, nunca o `name`.
            $table->string('name', 120);
            // A imagem vem sempre da biblioteca da F2.7, por referência.
            // `restrictOnDelete()` é a barreira final do banco contra excluir
            // uma mídia referenciada: o `MediaUsageRegistry` avisa antes, mas
            // só a FK fecha a corrida entre verificar e apagar.
            $table->foreignId('media_id')->constrained('media')->restrictOnDelete();
            // VARCHAR em vez de ENUM nativo — as posições vivem no enum PHP
            // `BannerPosition`, como já vale para `pages.status`.
            $table->string('position', 32);
            $table->string('link_url', 2048)->nullable();
            // Pertence ao **uso** da imagem, não ao arquivo: a mesma mídia pode
            // significar coisas diferentes em posições diferentes, e por isso
            // não se herda `media.original_name`.
            $table->string('alt_text', 255);
            // Sem `DEFAULT 0` de propósito. Um default faria qualquer INSERT
            // fora do `BannerService` — seeder, comando, correção manual — cair
            // silenciosamente no início da lista; sem ele, o insert falha em vez
            // de mentir sobre a ordem.
            $table->unsignedInteger('sort_order');
            // Um banner novo nasce inativo: criar um registro não deve
            // publicá-lo. A interface pode informar `true` explicitamente.
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            // Alinhado à consulta pública dominante — igualdade em `position` e
            // `is_active`, depois a primeira coluna da ordenação. `id` não entra
            // explicitamente: ele permanece no ORDER BY como desempate
            // funcional, e no InnoDB a chave primária já compõe as entradas dos
            // índices secundários.
            $table->index(['position', 'is_active', 'sort_order']);
        });
    }

    /**
     * Reverte as migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
