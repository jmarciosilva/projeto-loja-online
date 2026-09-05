<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\User;
use App\Services\PageContentRenderer;
use App\Services\PageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Preview administrativo das páginas.
 *
 * O preview existe para responder a uma pergunta: como isto vai ficar quando
 * for publicado. Por isso os testes insistem em duas coisas — ele mostra
 * rascunho, e mostra exatamente o mesmo conteúdo que a rota pública.
 */
class PagePreviewTest extends TestCase
{
    use RefreshDatabase;

    // --- Acesso -----------------------------------------------------------

    public function test_guest_cannot_preview(): void
    {
        $page = $this->makePage();

        $this->get($this->previewUri($page))->assertRedirect('/login');
    }

    public function test_an_authenticated_user_can_preview_a_draft(): void
    {
        $page = $this->makePage(['status' => PageStatus::Draft]);

        $this->actingAsAdmin()
            ->get($this->previewUri($page))
            ->assertOk()
            ->assertSee('Quem Somos');
    }

    public function test_an_authenticated_user_can_preview_a_published_page(): void
    {
        $page = $this->makePage(['status' => PageStatus::Published]);

        $this->actingAsAdmin()->get($this->previewUri($page))->assertOk();
    }

    public function test_a_soft_deleted_page_cannot_be_previewed(): void
    {
        $page = $this->makePage();
        app(PageService::class)->delete($page);

        $this->actingAsAdmin()->get($this->previewUri($page))->assertNotFound();
    }

    public function test_the_preview_warns_that_a_draft_is_not_public(): void
    {
        $page = $this->makePage(['status' => PageStatus::Draft]);

        $this->actingAsAdmin()
            ->get($this->previewUri($page))
            ->assertOk()
            ->assertSee('ainda não é acessível publicamente');
    }

    // --- Identidade -------------------------------------------------------

    public function test_the_preview_resolves_the_page_by_its_identity(): void
    {
        $primeira = $this->makePage();
        $segunda = $this->makePage(['title' => 'Contato', 'slug' => (string) $primeira->id]);

        $this->actingAsAdmin()
            ->get($this->previewUri($primeira))
            ->assertOk()
            ->assertSee('Quem Somos')
            ->assertDontSee('Contato');

        $this->actingAsAdmin()
            ->get($this->previewUri($segunda))
            ->assertOk()
            ->assertSee('Contato');
    }

    public function test_the_preview_url_survives_a_slug_change(): void
    {
        $page = $this->makePage();

        app(PageService::class)->update($page, ['slug' => 'sobre-a-empresa']);

        $this->actingAsAdmin()->get($this->previewUri($page))->assertOk();
    }

    // --- Preview não publica ---------------------------------------------

    public function test_the_preview_does_not_change_the_page(): void
    {
        $page = $this->makePage(['status' => PageStatus::Draft]);
        $antes = $this->snapshot($page);

        $this->actingAsAdmin()->get($this->previewUri($page))->assertOk();

        // `updated_at` entra no retrato justamente para pegar uma escrita
        // acidental: qualquer save silencioso mexeria no timestamp.
        $this->assertSame($antes, $this->snapshot($page));
        $this->assertSame(PageStatus::Draft, $page->fresh()->status);
    }

    public function test_previewing_a_draft_does_not_make_it_public(): void
    {
        $page = $this->makePage(['status' => PageStatus::Draft]);

        $this->actingAsAdmin()->get($this->previewUri($page))->assertOk();

        $this->get('/paginas/quem-somos')->assertNotFound();
    }

    // --- Mesma renderização ----------------------------------------------

    public function test_the_preview_renders_the_same_content_as_the_public_page(): void
    {
        $markdown = "## Nossa história\n\nTexto com **negrito** e [link](https://example.com).";
        $page = $this->makePage(['status' => PageStatus::Published, 'content' => $markdown]);

        $publico = $this->get('/paginas/quem-somos')->assertOk()->getContent();
        $preview = $this->actingAsAdmin()->get($this->previewUri($page))->assertOk()->getContent();

        $renderizado = app(PageContentRenderer::class)->render($markdown);

        foreach ([$publico, $preview] as $html) {
            $this->assertStringContainsString(trim($renderizado), $html);
        }
    }

    public function test_the_preview_does_not_render_raw_html(): void
    {
        $page = $this->makePage(['content' => '<script>alert(1)</script>']);

        $html = $this->actingAsAdmin()->get($this->previewUri($page))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    // --- Navegação --------------------------------------------------------

    public function test_the_edit_screen_links_to_the_preview_by_identity(): void
    {
        $page = $this->makePage();

        $this->actingAsAdmin()
            ->get('/admin/paginas/'.$page->id.'/editar')
            ->assertOk()
            ->assertSee(route('admin.pages.preview', $page), false);
    }

    public function test_the_admin_controller_does_not_render_markdown_by_itself(): void
    {
        $codigo = php_strip_whitespace(app_path('Http/Controllers/Admin/PageController.php'));

        foreach (['CommonMark', 'Markdown::', 'Str::'] as $sinal) {
            $this->assertStringNotContainsString(
                $sinal,
                $codigo,
                "O PageController administrativo não deve conter [{$sinal}]: a renderização é do PageContentRenderer."
            );
        }
    }

    private function actingAsAdmin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    /**
     * Retrato em valores escalares — comparar os models direto compararia
     * instâncias de Carbon, que nunca são idênticas entre duas leituras.
     *
     * @return array<string, string>
     */
    private function snapshot(Page $page): array
    {
        $fresh = $page->fresh();

        return [
            'title' => $fresh->title,
            'slug' => $fresh->slug,
            'status' => $fresh->status->value,
            'content' => $fresh->content,
            'updated_at' => $fresh->updated_at->toDateTimeString(),
        ];
    }

    private function previewUri(Page $page): string
    {
        return '/admin/paginas/'.$page->id.'/preview';
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
