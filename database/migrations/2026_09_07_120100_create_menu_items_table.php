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
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();

            // `restrictOnDelete()`, e **não** cascade. O `parent_id` abaixo é
            // auto-referencial com RESTRICT, e o InnoDB verifica constraints
            // imediatamente, linha a linha: um cascade do menu apagaria um item
            // pai antes dos filhos e esbarraria no próprio RESTRICT da
            // hierarquia — falhando justamente nos menus que têm árvore. Quem
            // remove a árvore é o `MenuService`, das folhas para as raízes,
            // dentro de uma transação.
            $table->foreignId('menu_id')->constrained('menus')->restrictOnDelete();

            // Adjacency list: uma coluna e uma FK, sem closure table, sem path
            // materializado e sem lft/rgt. Os menus são árvores curtas, lidas
            // inteiras de uma vez.
            //
            // RESTRICT porque excluir um pai não pode apagar silenciosamente a
            // subárvore: quem remove "Produtos" precisa ver que existem filhos.
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->restrictOnDelete();

            $table->string('label', 120);
            // VARCHAR em vez de ENUM nativo — os destinos vivem no enum PHP
            // `MenuItemType`, como já vale para `pages.status`.
            $table->string('type', 20);

            // Vínculo pela **identidade** da página, nunca pelo slug: o slug é
            // endereço público e pode mudar. A URL pública é resolvida a partir
            // da `Page` atual.
            //
            // `Page` usa SoftDeletes, então a exclusão lógica não remove a linha
            // e não dispara este RESTRICT — ele protege apenas contra remoção
            // física. Quem impede o item de aparecer publicamente é o critério
            // de publicabilidade da F2.6-C, não o banco.
            $table->foreignId('page_id')->nullable()->constrained('pages')->restrictOnDelete();
            $table->string('url', 2048)->nullable();

            // Sem default de propósito, como em `banners.sort_order`: um default
            // faria qualquer INSERT fora do `MenuService` cair silenciosamente
            // no início do grupo; sem ele, o insert falha em vez de mentir sobre
            // a ordem.
            $table->unsignedInteger('sort_order');
            // Item novo nasce inativo, pelo mesmo motivo do menu.
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            // Alinhado à consulta dominante — os irmãos de um grupo na ordem
            // contratada — e serve à leitura da árvore inteira pelo prefixo
            // `menu_id`. `id` não entra: ele permanece no ORDER BY como
            // desempate funcional, e no InnoDB a chave primária já compõe as
            // entradas dos índices secundários.
            //
            // Deliberadamente **não** é UNIQUE: a reordenação da F2.6-B passa
            // por estados intermediários com empate, e a restrição quebraria a
            // operação que ela pareceria proteger.
            $table->index(['menu_id', 'parent_id', 'sort_order']);
        });
    }

    /**
     * Reverte as migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
