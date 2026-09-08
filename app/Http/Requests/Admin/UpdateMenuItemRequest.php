<?php

namespace App\Http\Requests\Admin;

/**
 * Mesmo contrato de entrada da criação.
 *
 * A edição não tem exceção a abrir: nenhum campo é único, nenhum depende do
 * próprio registro, e `menu_id` e `sort_order` continuam fora do formulário. A
 * diferença de comportamento entre criar e editar vive no `MenuService` —
 * reenviar o mesmo pai preserva a ordem, e trocar de pai anexa o item ao fim do
 * novo grupo —, não nesta validação.
 *
 * O formulário de edição também não oferece o próprio item nem os seus
 * descendentes como pai; quem recusa uma escolha forjada é o `MenuService`.
 */
class UpdateMenuItemRequest extends StoreMenuItemRequest {}
