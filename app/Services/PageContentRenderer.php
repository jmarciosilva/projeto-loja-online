<?php

namespace App\Services;

use League\CommonMark\CommonMarkConverter;

/**
 * Converte o Markdown persistido em HTML seguro para exibição.
 *
 * Responsabilidade única: não consulta `Page`, não conhece status, slug, SEO
 * nem HTTP. Público e preview administrativo usam este mesmo serviço, para que
 * o que o administrador vê antes de publicar seja exatamente o que o visitante
 * recebe depois.
 *
 * O HTML produzido aqui é o único conteúdo de página que pode ser impresso sem
 * escape no Blade. `Page.content` cru nunca é.
 */
class PageContentRenderer
{
    /**
     * Contrato de segurança da renderização.
     *
     * - `html_input = strip` remove HTML cru escrito no editor, para que o
     *   conteúdo administrativo não vire vetor de script na página pública;
     * - `allow_unsafe_links = false` derruba protocolos perigosos em links,
     *   como `javascript:`;
     * - `max_nesting_level` limita profundidade patológica de Markdown, que de
     *   outro modo consumiria recursos desproporcionais ao renderizar.
     */
    private const CONFIG = [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
        'max_nesting_level' => 100,
    ];

    private ?CommonMarkConverter $converter = null;

    public function render(string $markdown): string
    {
        return (string) $this->converter()->convert($markdown);
    }

    /**
     * O conversor é construído uma vez por instância: montá-lo a cada chamada
     * repetiria a configuração do ambiente sem necessidade.
     */
    private function converter(): CommonMarkConverter
    {
        return $this->converter ??= new CommonMarkConverter(self::CONFIG);
    }
}
