<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\User;
use App\Services\PageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integração do CRUD administrativo de páginas.
 *
 * As invariantes de domínio já são cobertas por PageTest e PageServiceTest —
 * aqui o assunto é a camada HTTP: acesso, validação, navegação e, sobretudo,
 * que ela consome o `PageService` em vez de reimplementar suas regras.
 */
class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/admin/paginas';

    // --- Acesso -----------------------------------------------------------

    public function test_guest_cannot_reach_any_administrative_page_route(): void
    {
        $page = $this->makePage();

        $rotas = [
            ['get', self::URI],
            ['get', self::URI.'/criar'],
            ['post', self::URI],
            ['get', self::URI.'/'.$page->id.'/editar'],
            ['put', self::URI.'/'.$page->id],
            ['delete', self::URI.'/'.$page->id],
        ];

        foreach ($rotas as [$metodo, $uri]) {
            $this->{$metodo}($uri, ['title' => 'Invadida'])
                ->assertRedirect('/login');
        }

        $this->assertSame(1, Page::withTrashed()->count());
        $this->assertSame('Quem Somos', $page->fresh()->title);
        $this->assertNull($page->fresh()->deleted_at);
    }

    public function test_authenticated_user_can_open_the_listing(): void
    {
        $this->actingAsAdmin()->get(self::URI)->assertOk()->assertSee('Páginas');
    }

    public function test_authenticated_user_can_open_the_creation_form(): void
    {
        $this->actingAsAdmin()->get(self::URI.'/criar')->assertOk()->assertSee('Nova página');
    }

    public function test_authenticated_user_can_open_the_edit_form(): void
    {
        $page = $this->makePage();

        $this->actingAsAdmin()
            ->get(self::URI.'/'.$page->id.'/editar')
            ->assertOk()
            ->assertSee('Editar página')
            ->assertSee('Quem Somos')
            ->assertSee('quem-somos');
    }

    // --- Listagem ---------------------------------------------------------

    public function test_the_listing_shows_the_registered_pages(): void
    {
        $page = $this->makePage(['status' => PageStatus::Published]);

        $this->actingAsAdmin()
            ->get(self::URI)
            ->assertOk()
            ->assertSee('Quem Somos')
            ->assertSee('quem-somos')
            ->assertSee('Publicado')
            ->assertSee(route('admin.pages.edit', $page), false);
    }

    public function test_the_listing_shows_an_empty_state(): void
    {
        $this->actingAsAdmin()
            ->get(self::URI)
            ->assertOk()
            ->assertSee('Nenhuma página cadastrada.')
            ->assertSee('Criar primeira página');
    }

    public function test_the_listing_hides_soft_deleted_pages(): void
    {
        $page = $this->makePage();
        app(PageService::class)->delete($page);

        $this->actingAsAdmin()
            ->get(self::URI)
            ->assertOk()
            ->assertDontSee('quem-somos')
            ->assertSee('Nenhuma página cadastrada.');
    }

    // --- Criação ----------------------------------------------------------

    public function test_it_creates_a_page_with_an_explicit_slug(): void
    {
        $this->actingAsAdmin()
            ->post(self::URI, $this->payload([
                'slug' => 'privacidade',
                'status' => 'published',
                'content' => '# Privacidade',
                'meta_title' => 'Privacidade | Loja',
                'meta_description' => 'Como tratamos dados.',
            ]))
            ->assertRedirect(route('admin.pages.index'))
            ->assertSessionHas('status', 'Página criada com sucesso.');

        $page = Page::query()->firstOrFail();
        $this->assertSame('Quem Somos', $page->title);
        $this->assertSame('privacidade', $page->slug);
        $this->assertSame(PageStatus::Published, $page->status);
        $this->assertSame('# Privacidade', $page->content);
        $this->assertSame('Privacidade | Loja', $page->meta_title);
        $this->assertSame('Como tratamos dados.', $page->meta_description);
    }

    public function test_creating_without_a_slug_lets_the_service_generate_it(): void
    {
        $this->actingAsAdmin()
            ->post(self::URI, $this->payload(['slug' => '']))
            ->assertRedirect(route('admin.pages.index'));

        $this->assertSame('quem-somos', Page::query()->firstOrFail()->slug);
    }

    public function test_creating_without_content_stores_an_empty_string(): void
    {
        $this->actingAsAdmin()
            ->post(self::URI, $this->payload(['content' => '']))
            ->assertRedirect(route('admin.pages.index'));

        $this->assertSame('', Page::query()->firstOrFail()->content);
    }

    public function test_the_generated_slug_still_collides_into_a_deterministic_suffix(): void
    {
        $this->makePage();

        $this->actingAsAdmin()
            ->post(self::URI, $this->payload(['slug' => '']))
            ->assertRedirect(route('admin.pages.index'));

        $this->assertSame('quem-somos-2', Page::query()->latest('id')->firstOrFail()->slug);
    }

    // --- Atualização ------------------------------------------------------

    public function test_it_updates_every_supported_field(): void
    {
        $page = $this->makePage();

        $this->actingAsAdmin()
            ->put(self::URI.'/'.$page->id, $this->payload([
                'title' => 'Sobre a Empresa',
                'slug' => 'sobre-a-empresa',
                'status' => 'published',
                'content' => '# Sobre',
                'meta_title' => 'Sobre',
                'meta_description' => 'Nossa história.',
            ]))
            ->assertRedirect(route('admin.pages.index'))
            ->assertSessionHas('status', 'Página atualizada com sucesso.');

        $fresh = $page->fresh();
        $this->assertSame('Sobre a Empresa', $fresh->title);
        $this->assertSame('sobre-a-empresa', $fresh->slug);
        $this->assertSame(PageStatus::Published, $fresh->status);
        $this->assertSame('# Sobre', $fresh->content);
        $this->assertSame('Sobre', $fresh->meta_title);
        $this->assertSame('Nossa história.', $fresh->meta_description);
        $this->assertSame($page->id, $fresh->id);
    }

    public function test_updating_only_the_title_preserves_the_existing_slug(): void
    {
        $page = $this->makePage();

        $this->actingAsAdmin()
            ->put(self::URI.'/'.$page->id, $this->payload([
                'title' => 'Sobre a Empresa',
                'slug' => '',
            ]))
            ->assertRedirect(route('admin.pages.index'));

        $this->assertSame('Sobre a Empresa', $page->fresh()->title);
        $this->assertSame('quem-somos', $page->fresh()->slug);
    }

    public function test_resubmitting_the_current_slug_is_accepted(): void
    {
        $page = $this->makePage();

        $this->actingAsAdmin()
            ->put(self::URI.'/'.$page->id, $this->payload([
                'title' => 'Sobre a Empresa',
                'slug' => 'quem-somos',
            ]))
            ->assertRedirect(route('admin.pages.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('quem-somos', $page->fresh()->slug);
    }

    // --- Exclusão ---------------------------------------------------------

    public function test_it_soft_deletes_a_page(): void
    {
        $page = $this->makePage();

        $this->actingAsAdmin()
            ->delete(self::URI.'/'.$page->id)
            ->assertRedirect(route('admin.pages.index'))
            ->assertSessionHas('status', 'Página excluída com sucesso.');

        $this->assertSoftDeleted('pages', ['id' => $page->id]);
        $this->assertNotNull(Page::withTrashed()->find($page->id));
    }

    // --- Validação de tamanho --------------------------------------------

    public function test_it_rejects_fields_longer_than_their_columns(): void
    {
        $page = $this->makePage();

        $excessos = [
            'title' => str_repeat('a', 256),
            'slug' => str_repeat('a', 256),
            'meta_title' => str_repeat('a', 256),
            'meta_description' => str_repeat('a', 321),
        ];

        foreach ($excessos as $campo => $valor) {
            $this->actingAsAdmin()
                ->put(self::URI.'/'.$page->id, $this->payload([$campo => $valor]))
                ->assertSessionHasErrors($campo);
        }

        $fresh = $page->fresh();
        $this->assertSame('Quem Somos', $fresh->title);
        $this->assertSame('quem-somos', $fresh->slug);
        $this->assertNull($fresh->meta_title);
        $this->assertNull($fresh->meta_description);
    }

    public function test_it_accepts_fields_at_the_column_limit(): void
    {
        $this->actingAsAdmin()
            ->post(self::URI, $this->payload([
                'title' => str_repeat('a', 255),
                'slug' => str_repeat('b', 255),
                'meta_title' => str_repeat('c', 255),
                'meta_description' => str_repeat('d', 320),
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.pages.index'));

        $this->assertSame(1, Page::query()->count());
    }

    public function test_it_rejects_an_empty_title(): void
    {
        $this->actingAsAdmin()
            ->post(self::URI, $this->payload(['title' => '']))
            ->assertSessionHasErrors('title');

        $this->assertSame(0, Page::withTrashed()->count());
    }

    // --- Validação de status ---------------------------------------------

    public function test_it_accepts_the_supported_statuses(): void
    {
        foreach (['draft', 'published'] as $status) {
            $this->actingAsAdmin()
                ->post(self::URI, $this->payload(['slug' => '', 'status' => $status]))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(
            [PageStatus::Draft, PageStatus::Published],
            Page::query()->orderBy('id')->get()->pluck('status')->all()
        );
    }

    public function test_it_rejects_an_unsupported_status(): void
    {
        $this->actingAsAdmin()
            ->post(self::URI, $this->payload(['status' => 'archived']))
            ->assertSessionHasErrors('status');

        $this->assertSame(0, Page::withTrashed()->count());
    }

    // --- Validação de slug ------------------------------------------------

    public function test_it_rejects_a_slug_outside_the_canonical_format(): void
    {
        foreach (['Quem-Somos', 'quem somos', 'quem_somos', 'quem/somos', '../quem-somos', 'quem-somos#topo'] as $slug) {
            $this->actingAsAdmin()
                ->post(self::URI, $this->payload(['slug' => $slug]))
                ->assertSessionHasErrors('slug');
        }

        $this->assertSame(0, Page::withTrashed()->count());
    }

    public function test_it_does_not_silently_normalize_an_invalid_slug(): void
    {
        $this->actingAsAdmin()
            ->post(self::URI, $this->payload(['slug' => 'Quem Somos']))
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseMissing('pages', ['slug' => 'quem-somos']);
    }

    public function test_it_rejects_a_slug_already_taken(): void
    {
        $this->makePage();

        $this->actingAsAdmin()
            ->post(self::URI, $this->payload(['slug' => 'quem-somos']))
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Page::withTrashed()->count());
    }

    public function test_a_soft_deleted_page_keeps_its_slug_reserved(): void
    {
        $page = $this->makePage();
        app(PageService::class)->delete($page);

        $this->actingAsAdmin()
            ->post(self::URI, $this->payload(['slug' => 'quem-somos']))
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Page::withTrashed()->count());
    }

    public function test_it_rejects_a_slug_owned_by_another_page_on_update(): void
    {
        $page = $this->makePage();
        $this->makePage(['title' => 'Contato', 'slug' => 'contato']);

        $this->actingAsAdmin()
            ->put(self::URI.'/'.$page->id, $this->payload(['slug' => 'contato']))
            ->assertSessionHasErrors('slug');

        $this->assertSame('quem-somos', $page->fresh()->slug);
    }

    // --- Contrato arquitetural -------------------------------------------

    public function test_the_route_resolves_a_page_by_its_identity_not_its_slug(): void
    {
        // Slug numérico: se a rota resolvesse por slug, este valor casaria com
        // o id de outra página e a edição abriria o registro errado.
        $primeira = $this->makePage();
        $segunda = $this->makePage(['title' => 'Contato', 'slug' => (string) $primeira->id]);

        $this->actingAsAdmin()
            ->get(self::URI.'/'.$primeira->id.'/editar')
            ->assertOk()
            ->assertSee('Quem Somos')
            ->assertDontSee('Contato');

        $this->actingAsAdmin()
            ->get(self::URI.'/'.$segunda->id.'/editar')
            ->assertOk()
            ->assertSee('Contato');
    }

    public function test_changing_the_slug_does_not_change_the_route_identity(): void
    {
        $page = $this->makePage();

        $this->actingAsAdmin()
            ->put(self::URI.'/'.$page->id, $this->payload(['slug' => 'sobre-a-empresa']))
            ->assertRedirect(route('admin.pages.index'));

        $this->actingAsAdmin()
            ->get(self::URI.'/'.$page->id.'/editar')
            ->assertOk()
            ->assertSee('sobre-a-empresa');
    }

    public function test_the_controller_does_not_bypass_the_page_service(): void
    {
        // `php_strip_whitespace` remove os comentários: o docblock do controller
        // cita justamente o que ele não faz, e varrer o arquivo cru acusaria a
        // documentação como se fosse código.
        $codigo = php_strip_whitespace(app_path('Http/Controllers/Admin/PageController.php'));

        // Os sinais são de *bypass* — escrita ou consulta direta no model. As
        // chamadas ao serviço (`$pages->update(...)`) são justamente o padrão
        // esperado e não podem entrar nesta lista.
        $sinais = [
            'Page::create', 'Page::query', 'Page::withTrashed', 'Page::find',
            '$page->update(', '$page->delete(', '$page->save(', '$page->fill(',
            'Str::slug', 'DB::',
        ];

        foreach ($sinais as $sinal) {
            $this->assertStringNotContainsString(
                $sinal,
                $codigo,
                "O PageController não deve conter [{$sinal}]: escrita e consulta pertencem ao PageService."
            );
        }
    }

    public function test_no_public_or_preview_route_exists_yet(): void
    {
        $rotas = collect(app('router')->getRoutes())->map(fn ($rota) => $rota->getName())->filter()->all();

        $this->assertNotContains('admin.pages.preview', $rotas);
        $this->assertNotContains('pages.show', $rotas);
    }

    // --- Navegação --------------------------------------------------------

    public function test_the_sidebar_links_to_the_pages_listing(): void
    {
        $html = $this->actingAsAdmin()->get(self::URI)->getContent();

        $this->assertStringContainsString('href="'.route('admin.pages.index').'"', $html);
        $this->assertMatchesRegularExpression(
            '/<a\s[^>]*href="'.preg_quote(route('admin.pages.index'), '/').'"[^>]*aria-current="page"/s',
            $html,
            'O link Páginas da sidebar deve marcar a seção atual.'
        );
    }

    public function test_the_sidebar_marks_the_section_as_active_on_the_creation_form(): void
    {
        $html = $this->actingAsAdmin()->get(self::URI.'/criar')->getContent();

        $this->assertMatchesRegularExpression(
            '/<a\s[^>]*href="'.preg_quote(route('admin.pages.index'), '/').'"[^>]*aria-current="page"/s',
            $html
        );
    }

    public function test_the_breadcrumbs_follow_the_section(): void
    {
        $this->actingAsAdmin()->get(self::URI)->assertSee('Dashboard');
        $this->actingAsAdmin()->get(self::URI.'/criar')->assertSee('Nova página');
        $this->actingAsAdmin()->get(self::URI.'/'.$this->makePage()->id.'/editar')->assertSee('Editar');
    }

    // --- Apoio ------------------------------------------------------------

    private function actingAsAdmin(): static
    {
        return $this->actingAs(User::factory()->create());
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Quem Somos',
            'slug' => '',
            'status' => 'draft',
            'content' => '# Conteúdo',
            'meta_title' => '',
            'meta_description' => '',
        ], $overrides);
    }
}
