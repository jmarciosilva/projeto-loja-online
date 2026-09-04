<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contratos estruturais do painel administrativo.
 *
 * Separado de AdminAuthenticationTest de propósito: ali o assunto é quem pode
 * entrar, aqui é o que a página entrega depois de entrar.
 *
 * As asserções evitam classes do Tailwind e se apoiam em semântica — rótulos,
 * aria e destino dos links —, para que o painel possa ser reestilizado sem
 * quebrar a suíte.
 */
class AdminLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function acessarPainel(?User $user = null)
    {
        return $this->actingAs($user ?? User::factory()->create())->get('/admin');
    }

    public function test_admin_renders_the_administrative_layout(): void
    {
        $this->acessarPainel()
            ->assertOk()
            ->assertSee('Área administrativa')
            ->assertSee('Navegação administrativa', false)
            ->assertSee('Trilha de navegação', false);
    }

    public function test_admin_page_declares_a_single_html_document(): void
    {
        $html = $this->acessarPainel()->getContent();

        $this->assertSame(1, substr_count($html, '<html'), 'A página deve ter um único <html>.');
        $this->assertSame(1, substr_count($html, '<body'), 'A página deve ter um único <body>.');
    }

    public function test_page_title_reaches_both_the_head_and_the_topbar(): void
    {
        $html = $this->acessarPainel()->getContent();

        $this->assertStringContainsString('<title>Dashboard', $html);
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($html, 'Dashboard'),
            'O título deve aparecer no <title> e também na topbar.'
        );
    }

    public function test_dashboard_navigation_points_to_the_admin_route(): void
    {
        $this->acessarPainel()->assertSee(route('admin'), false);
    }

    public function test_dashboard_is_marked_as_the_current_page_on_admin(): void
    {
        $html = $this->acessarPainel()->getContent();

        // Precisa ser o próprio link da sidebar: os breadcrumbs também usam
        // aria-current, então procurar a expressão solta não provaria nada.
        $this->assertMatchesRegularExpression(
            '/<a\s[^>]*href="'.preg_quote(route('admin'), '/').'"[^>]*aria-current="page"/s',
            $html,
            'O link Dashboard da sidebar deve marcar a página atual.'
        );
    }

    public function test_sidebar_does_not_link_to_pages_that_do_not_exist(): void
    {
        $html = $this->acessarPainel()->getContent();

        // As áreas futuras podem ser citadas em texto, mas não viram href.
        // `configuracoes` saiu da lista na F2.3-A: a página passou a existir, e
        // manter a guarda sobre ela impediria justamente o link legítimo.
        foreach (['paginas', 'midia', 'banners', 'menus', 'produtos', 'pedidos', 'clientes', 'usuarios'] as $secao) {
            $this->assertStringNotContainsString(
                'href="'.url('/admin/'.$secao).'"',
                $html,
                "A sidebar não deve linkar para /admin/{$secao} antes de a página existir."
            );
        }
    }

    public function test_logout_is_available_from_the_panel(): void
    {
        $this->acessarPainel()
            ->assertSee(route('logout'), false)
            ->assertSee('Sair');
    }

    public function test_authenticated_user_is_identified(): void
    {
        $user = User::factory()->create(['name' => 'Joana Pereira']);

        $this->acessarPainel($user)->assertSee('Joana Pereira');
    }

    public function test_dashboard_does_not_expose_business_metrics(): void
    {
        $html = $this->acessarPainel()->getContent();

        // A fundação do painel é estrutural: nenhum número de negócio aqui.
        foreach (['Faturamento', 'Ticket médio', 'Vendas hoje', 'Pedidos recentes', 'Conversão'] as $metrica) {
            $this->assertStringNotContainsString($metrica, $html);
        }
    }

    public function test_panel_does_not_depend_on_granular_authorization(): void
    {
        // Durante a F2.2 qualquer usuário autenticado entra: não há papéis nem
        // permissões, e nenhum atributo de autorização no modelo.
        $user = User::factory()->create();

        $this->assertFalse(method_exists($user, 'hasRole'));
        $this->assertArrayNotHasKey('is_admin', $user->getAttributes());

        $this->acessarPainel($user)->assertOk();
    }
}
