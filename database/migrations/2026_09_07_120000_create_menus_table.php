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
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            // Identificação administrativa humana: é como o administrador
            // reconhece o menu na listagem — "Menu principal", "Rodapé".
            $table->string('name', 120);
            // Identidade técnica estável pela qual um consumidor público
            // localiza o menu. O UNIQUE é a barreira final do banco: o serviço
            // confere antes, mas só o índice fecha a corrida entre verificar e
            // inserir, no mesmo arranjo já usado por `pages.slug`.
            $table->string('code', 64)->unique();
            // Um menu novo nasce inativo: montar uma árvore de navegação leva
            // vários passos, e nenhum deles deve aparecer pela metade no site.
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverte as migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
