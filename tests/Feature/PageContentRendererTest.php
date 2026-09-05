<?php

namespace Tests\Feature;

use App\Services\PageContentRenderer;
use Tests\TestCase;

/**
 * Contrato de renderização do Markdown das páginas.
 *
 * As asserções descrevem o comportamento real do CommonMark configurado, não a
 * configuração em si: o que importa é o HTML que chega ao visitante.
 */
class PageContentRendererTest extends TestCase
{
    public function test_it_can_be_resolved_by_the_laravel_container(): void
    {
        $this->assertInstanceOf(PageContentRenderer::class, app(PageContentRenderer::class));
    }

    // --- Markdown normal --------------------------------------------------

    public function test_it_renders_headings(): void
    {
        $this->assertStringContainsString('<h1>Quem Somos</h1>', $this->render('# Quem Somos'));
        $this->assertStringContainsString('<h2>Nossa história</h2>', $this->render('## Nossa história'));
    }

    public function test_it_renders_bold_and_italic(): void
    {
        $this->assertStringContainsString('<strong>negrito</strong>', $this->render('**negrito**'));
        $this->assertStringContainsString('<em>itálico</em>', $this->render('*itálico*'));
    }

    public function test_it_renders_lists(): void
    {
        $html = $this->render("- primeiro\n- segundo");

        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>primeiro</li>', $html);
    }

    public function test_it_renders_safe_links(): void
    {
        $html = $this->render('[Contato](https://example.com/contato)');

        $this->assertStringContainsString('<a href="https://example.com/contato">Contato</a>', $html);
    }

    public function test_it_renders_an_empty_string_without_failing(): void
    {
        $this->assertSame('', trim($this->render('')));
    }

    public function test_it_renders_plain_text_as_a_paragraph(): void
    {
        $this->assertStringContainsString('<p>Texto simples.</p>', $this->render('Texto simples.'));
    }

    // --- Segurança --------------------------------------------------------

    public function test_it_strips_a_script_block(): void
    {
        $html = $this->render('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
    }

    public function test_it_strips_dangerous_attributes(): void
    {
        $html = $this->render('<img src=x onerror=alert(1)>');

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function test_it_strips_inline_html_but_keeps_its_text(): void
    {
        $html = $this->render('texto <b>marcado</b> fim');

        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('texto marcado fim', $html);
    }

    public function test_it_strips_an_iframe(): void
    {
        $html = $this->render('<iframe src="https://example.com"></iframe>');

        $this->assertStringNotContainsString('<iframe', $html);
    }

    public function test_it_blocks_javascript_links(): void
    {
        $html = $this->render('[teste](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('href', $html);
        $this->assertStringContainsString('teste', $html);
    }

    public function test_it_blocks_unsafe_image_sources(): void
    {
        $html = $this->render('![alt](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_it_survives_deeply_nested_markdown(): void
    {
        // Bem abaixo do limite configurado: o objetivo é provar que aninhamento
        // legítimo continua funcionando, não exercitar o corte.
        $markdown = str_repeat('> ', 20).'citação';

        $this->assertStringContainsString('citação', $this->render($markdown));
    }

    private function render(string $markdown): string
    {
        return app(PageContentRenderer::class)->render($markdown);
    }
}
