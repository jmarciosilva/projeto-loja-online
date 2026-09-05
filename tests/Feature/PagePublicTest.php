<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\User;
use App\Services\PageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exibição pública das páginas estáticas.
 *
 * Cobre a regra de publicação, a renderização do Markdown e o SEO mínimo. A
 * conversão em si é contrato do PageContentRendererTest — aqui o assunto é o
 * que o visitante recebe.
 */
class PagePublicTest extends TestCase
{
    use RefreshDatabase;

    // --- Publicação -------------------------------------------------------

    public function test_a_published_page_is_reachable(): void
    {
        $this->makePage(['status' => PageStatus::Published]);

        $this->get('/paginas/quem-somos')
            ->assertOk()
            ->assertSee('Quem Somos');
    }

    public function test_a_draft_returns_404(): void
    {
        $this->makePage(['status' => PageStatus::Draft]);

        $this->get('/paginas/quem-somos')->assertNotFound();
    }

    public function test_an_authenticated_visitor_does_not_get_a_draft(): void
    {
        $this->makePage(['status' => PageStatus::Draft]);

        // Estar autenticado no painel não torna um rascunho público: o preview
        // acontece exclusivamente pela rota administrativa.
        $this->actingAs(User::factory()->create())
            ->get('/paginas/quem-somos')
            ->assertNotFound();
    }

    public function test_a_soft_deleted_page_returns_404(): void
    {
        $page = $this->makePage(['status' => PageStatus::Published]);
        app(PageService::class)->delete($page);

        $this->get('/paginas/quem-somos')->assertNotFound();
    }

    public function test_an_unknown_slug_returns_404(): void
    {
        $this->get('/paginas/nao-existe')->assertNotFound();
    }

    public function test_changing_the_slug_invalidates_the_old_url(): void
    {
        $page = $this->makePage(['status' => PageStatus::Published]);

        app(PageService::class)->update($page, ['slug' => 'sobre-a-empresa']);

        $this->get('/paginas/quem-somos')->assertNotFound();
        $this->get('/paginas/sobre-a-empresa')->assertOk()->assertSee('Quem Somos');
    }

    public function test_publishing_a_draft_makes_it_reachable(): void
    {
        $page = $this->makePage(['status' => PageStatus::Draft]);
        $this->get('/paginas/quem-somos')->assertNotFound();

        app(PageService::class)->update($page, ['status' => 'published']);

        $this->get('/paginas/quem-somos')->assertOk();
    }

    // --- Conteúdo ---------------------------------------------------------

    public function test_it_renders_the_markdown_content(): void
    {
        $this->makePage([
            'status' => PageStatus::Published,
            'content' => "## Nossa história\n\nTexto com **negrito**.",
        ]);

        $html = $this->get('/paginas/quem-somos')->assertOk()->getContent();

        $this->assertStringContainsString('<h2>Nossa história</h2>', $html);
        $this->assertStringContainsString('<strong>negrito</strong>', $html);
    }

    public function test_it_does_not_render_raw_html_from_the_content(): void
    {
        $this->makePage([
            'status' => PageStatus::Published,
            'content' => '<script>alert(1)</script>'."\n\n".'<img src=x onerror=alert(1)>',
        ]);

        $html = $this->get('/paginas/quem-somos')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function test_it_does_not_render_unsafe_links_from_the_content(): void
    {
        $this->makePage([
            'status' => PageStatus::Published,
            'content' => '[clique](javascript:alert(1))',
        ]);

        $html = $this->get('/paginas/quem-somos')->assertOk()->getContent();

        $this->assertStringNotContainsString('javascript:alert', $html);
    }

    public function test_it_does_not_expose_internal_fields(): void
    {
        $this->makePage([
            'status' => PageStatus::Published,
            'meta_title' => 'Meta interno',
        ]);

        $html = $this->get('/paginas/quem-somos')->assertOk()->getContent();

        // O slug não vira texto visível, e o status nunca aparece.
        $this->assertStringNotContainsString('>quem-somos<', $html);
        $this->assertStringNotContainsString('Rascunho', $html);
        $this->assertStringNotContainsString('Publicado', $html);
    }

    // --- SEO --------------------------------------------------------------

    public function test_the_title_uses_the_meta_title_when_present(): void
    {
        $this->makePage([
            'status' => PageStatus::Published,
            'meta_title' => 'Quem Somos | Loja Online',
        ]);

        $html = $this->get('/paginas/quem-somos')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Quem Somos | Loja Online</title>', $html);
    }

    public function test_the_title_falls_back_to_the_page_title(): void
    {
        $this->makePage(['status' => PageStatus::Published, 'meta_title' => null]);

        $html = $this->get('/paginas/quem-somos')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Quem Somos</title>', $html);
    }

    public function test_the_meta_description_is_emitted_when_present(): void
    {
        $this->makePage([
            'status' => PageStatus::Published,
            'meta_description' => 'A história da loja.',
        ]);

        $html = $this->get('/paginas/quem-somos')->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="description" content="A história da loja.">', $html);
    }

    public function test_no_meta_description_tag_is_emitted_when_absent(): void
    {
        $this->makePage(['status' => PageStatus::Published, 'meta_description' => null]);

        $html = $this->get('/paginas/quem-somos')->assertOk()->getContent();

        $this->assertStringNotContainsString('name="description"', $html);
    }

    public function test_seo_values_are_escaped(): void
    {
        $this->makePage([
            'status' => PageStatus::Published,
            'title' => 'Aspas "duplas" & <script>alert(1)</script>',
            'slug' => 'seo',
            'meta_title' => 'Meta "com" <script>alert(2)</script>',
            'meta_description' => 'Descrição "com" <script>alert(3)</script>',
        ]);

        $html = $this->get('/paginas/seo')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(2)</script>', $html);
        $this->assertStringNotContainsString('<script>alert(3)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&quot;com&quot;', $html);
    }

    public function test_the_page_title_is_escaped_in_the_body(): void
    {
        $this->makePage([
            'status' => PageStatus::Published,
            'title' => 'Título <script>alert(1)</script>',
            'slug' => 'escapado',
            'meta_title' => null,
        ]);

        $html = $this->get('/paginas/escapado')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    // --- Layout e fronteiras ---------------------------------------------

    public function test_it_uses_the_public_layout(): void
    {
        $this->makePage(['status' => PageStatus::Published]);

        $this->get('/paginas/quem-somos')
            ->assertOk()
            ->assertSee(route('home'), false)
            ->assertDontSee('Área administrativa');
    }

    public function test_the_home_route_is_not_a_page(): void
    {
        $this->makePage(['status' => PageStatus::Published, 'slug' => 'home']);

        $this->get('/')->assertOk()->assertDontSee('# Quem Somos');
    }

    public function test_the_public_controller_does_not_touch_eloquent_or_commonmark(): void
    {
        $codigo = php_strip_whitespace(app_path('Http/Controllers/PageController.php'));

        foreach (['Page::', 'CommonMark', 'Str::', 'DB::', 'withTrashed'] as $sinal) {
            $this->assertStringNotContainsString(
                $sinal,
                $codigo,
                "O PageController público não deve conter [{$sinal}]: consulta e renderização são dos serviços."
            );
        }
    }

    public function test_no_view_prints_the_raw_page_content(): void
    {
        $views = array_merge(
            glob(resource_path('views/pages/*.blade.php')),
            glob(resource_path('views/pages/partials/*.blade.php')),
            glob(resource_path('views/admin/pages/*.blade.php')),
            glob(resource_path('views/admin/pages/partials/*.blade.php')),
        );

        foreach ($views as $view) {
            $this->assertDoesNotMatchRegularExpression(
                '/\{!!\s*\$page->content\s*!!\}/',
                file_get_contents($view),
                basename($view).' não pode imprimir o Markdown cru sem passar pelo renderer.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePage(array $overrides = []): Page
    {
        return app(PageService::class)->create(array_merge([
            'title' => 'Quem Somos',
            'content' => '# Quem Somos',
            'status' => PageStatus::Draft,
        ], $overrides));
    }
}
