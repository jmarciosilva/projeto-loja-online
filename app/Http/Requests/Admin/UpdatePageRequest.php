<?php

namespace App\Http\Requests\Admin;

use App\Models\Page;

/**
 * Mesmo contrato de entrada da criação, com uma diferença: a própria página é
 * ignorada na checagem de disponibilidade do slug. Sem isso, salvar a tela de
 * edição sem mexer no endereço seria recusado por conflito consigo mesma.
 *
 * Deixar o campo de endereço em branco aqui **não** apaga nem regenera o slug:
 * o `PageService` preserva o endereço já publicado, e é dele que essa garantia
 * vem — não desta validação.
 */
class UpdatePageRequest extends StorePageRequest
{
    protected function ignoredPageId(): ?int
    {
        $page = $this->route('page');

        return $page instanceof Page ? $page->getKey() : null;
    }
}
