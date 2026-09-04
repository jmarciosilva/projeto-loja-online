<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Integração da página administrativa de configurações gerais.
 *
 * O contrato de persistência, tipagem e cache já é coberto por SiteSettingTest
 * e SiteSettingServiceTest — aqui o assunto é a interface: acesso, defaults,
 * validação, normalização e navegação.
 */
class AdminSiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/admin/configuracoes';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    // --- Acesso -----------------------------------------------------------

    public function test_guest_cannot_open_the_settings_page(): void
    {
        $this->get(self::URI)->assertRedirect('/login');
    }

    public function test_guest_cannot_update_the_settings(): void
    {
        $this->put(self::URI, ['name' => 'Invadida'])->assertRedirect('/login');

        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_authenticated_user_can_open_the_settings_page(): void
    {
        $this->actingAsAdmin()
            ->get(self::URI)
            ->assertOk()
            ->assertSee('Configurações gerais');
    }

    // --- Defaults ---------------------------------------------------------

    public function test_it_falls_back_to_the_application_defaults(): void
    {
        $response = $this->actingAsAdmin()->get(self::URI);

        $response->assertOk();
        $this->assertSame(config('app.name'), $this->inputValue($response->getContent(), 'name'));
        $this->assertSame('', $this->inputValue($response->getContent(), 'support_email'));
        $this->assertSame('', $this->inputValue($response->getContent(), 'phone'));
        $this->assertSame('', $this->textareaValue($response->getContent(), 'address'));
    }

    public function test_opening_the_page_does_not_persist_any_setting(): void
    {
        // Default de leitura não é persistência: um GET não pode materializar
        // configurações que o administrador nunca salvou.
        $this->actingAsAdmin()->get(self::URI)->assertOk();

        $this->assertDatabaseCount('site_settings', 0);
    }

    // --- Leitura ----------------------------------------------------------

    public function test_it_shows_the_persisted_values(): void
    {
        $this->settings()->setMany([
            'site.name' => ['type' => 'string', 'value' => 'Loja do Márcio'],
            'site.support_email' => ['type' => 'string', 'value' => 'suporte@loja.test'],
            'site.phone' => ['type' => 'string', 'value' => '+55 11 90000-0000'],
            'site.address' => ['type' => 'string', 'value' => 'Rua das Flores, 100'],
        ]);

        $html = $this->actingAsAdmin()->get(self::URI)->assertOk()->getContent();

        $this->assertSame('Loja do Márcio', $this->inputValue($html, 'name'));
        $this->assertSame('suporte@loja.test', $this->inputValue($html, 'support_email'));
        $this->assertSame('+55 11 90000-0000', $this->inputValue($html, 'phone'));
        $this->assertSame('Rua das Flores, 100', $this->textareaValue($html, 'address'));
    }

    // --- Atualização ------------------------------------------------------

    public function test_it_persists_exactly_the_four_supported_settings(): void
    {
        $this->actingAsAdmin()
            ->put(self::URI, [
                'name' => 'Loja Nova',
                'support_email' => 'ajuda@loja.test',
                'phone' => '11 4002-8922',
                'address' => 'Avenida Central, 42',
            ])
            ->assertRedirect(self::URI);

        $this->assertDatabaseCount('site_settings', 4);

        $expected = [
            'site.name' => 'Loja Nova',
            'site.support_email' => 'ajuda@loja.test',
            'site.phone' => '11 4002-8922',
            'site.address' => 'Avenida Central, 42',
        ];

        foreach ($expected as $key => $value) {
            $this->assertDatabaseHas('site_settings', ['key' => $key, 'type' => 'string']);
            $this->assertSame($value, $this->settings()->get($key));
        }
    }

    public function test_it_reports_success_after_saving(): void
    {
        $this->actingAsAdmin()
            ->put(self::URI, ['name' => 'Loja Nova'])
            ->assertSessionHas('status');
    }

    // --- Validação --------------------------------------------------------

    public function test_it_rejects_an_invalid_support_email(): void
    {
        $this->actingAsAdmin()
            ->put(self::URI, ['name' => 'Loja Nova', 'support_email' => 'nao-e-email'])
            ->assertSessionHasErrors('support_email');

        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_it_rejects_an_empty_store_name(): void
    {
        $this->actingAsAdmin()
            ->put(self::URI, ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_an_invalid_submission_does_not_overwrite_valid_settings(): void
    {
        $this->settings()->setMany([
            'site.name' => ['type' => 'string', 'value' => 'Nome Válido'],
            'site.support_email' => ['type' => 'string', 'value' => 'valido@loja.test'],
        ]);

        $this->actingAsAdmin()
            ->put(self::URI, ['name' => 'Nome Novo', 'support_email' => 'quebrado'])
            ->assertSessionHasErrors('support_email');

        $this->assertSame('Nome Válido', $this->settings()->get('site.name'));
        $this->assertSame('valido@loja.test', $this->settings()->get('site.support_email'));
    }

    // --- Opcionais vazios -------------------------------------------------

    public function test_empty_optional_fields_are_stored_as_empty_strings(): void
    {
        $this->actingAsAdmin()
            ->put(self::URI, [
                'name' => 'Loja Nova',
                'support_email' => '',
                'phone' => '',
                'address' => '',
            ])
            ->assertSessionHasNoErrors();

        foreach (['site.support_email', 'site.phone', 'site.address'] as $key) {
            $this->assertSame('', $this->settings()->get($key), "{$key} deveria ser string vazia.");
            $this->assertDatabaseHas('site_settings', ['key' => $key, 'type' => 'string', 'value' => '']);
        }
    }

    // --- Fronteira contra CRUD genérico -----------------------------------

    public function test_arbitrary_fields_do_not_create_additional_settings(): void
    {
        $this->actingAsAdmin()
            ->put(self::URI, [
                'name' => 'Loja Nova',
                'site.evil' => 'valor injetado',
                'foo' => 'bar',
                'key' => 'chave-arbitraria',
                'type' => 'json',
            ])
            ->assertSessionHasNoErrors();

        // A interface administra um conjunto fixo de chaves; nada além dele
        // pode ser criado por quem manipular o formulário.
        $persistedKeys = DB::table('site_settings')->pluck('key')->sort()->values()->all();

        $this->assertSame(
            ['site.address', 'site.name', 'site.phone', 'site.support_email'],
            $persistedKeys,
        );
    }

    // --- Navegação --------------------------------------------------------

    public function test_the_sidebar_links_to_the_settings_page(): void
    {
        $this->actingAsAdmin()
            ->get('/admin')
            ->assertOk()
            ->assertSee(route('admin.settings.edit'), false)
            ->assertSee('Configurações');
    }

    public function test_settings_is_the_current_page_on_the_settings_screen(): void
    {
        $html = $this->actingAsAdmin()->get(self::URI)->getContent();

        // Ancorado no próprio <a>: os breadcrumbs também usam aria-current, e
        // procurar a expressão solta não provaria nada.
        $this->assertMatchesRegularExpression(
            $this->currentLinkPattern(route('admin.settings.edit')),
            $html,
            'O link Configurações deveria marcar a página atual.',
        );
    }

    public function test_dashboard_is_not_the_current_page_on_the_settings_screen(): void
    {
        $html = $this->actingAsAdmin()->get(self::URI)->getContent();

        $this->assertDoesNotMatchRegularExpression(
            $this->currentLinkPattern(route('admin')),
            $html,
            'O link Dashboard não deveria marcar a página atual fora do dashboard.',
        );
    }

    public function test_the_breadcrumbs_identify_the_settings_page(): void
    {
        $html = $this->actingAsAdmin()->get(self::URI)->getContent();

        $this->assertMatchesRegularExpression(
            '/<nav[^>]*aria-label="Trilha de navegação".*?aria-current="page"[^>]*>\s*Configurações/s',
            $html,
        );
    }

    // --- Apoio ------------------------------------------------------------

    private function actingAsAdmin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function settings(): SiteSettingService
    {
        return app(SiteSettingService::class);
    }

    /**
     * Casa um <a> cujo href é o informado e que marca a página atual.
     */
    private function currentLinkPattern(string $url): string
    {
        return '/<a\s[^>]*href="'.preg_quote($url, '/').'"[^>]*aria-current="page"/s';
    }

    private function inputValue(string $html, string $name): string
    {
        preg_match('/<input[^>]*name="'.preg_quote($name, '/').'"[^>]*value="([^"]*)"/s', $html, $matches);

        return html_entity_decode($matches[1] ?? '__NAO_ENCONTRADO__', ENT_QUOTES, 'UTF-8');
    }

    private function textareaValue(string $html, string $name): string
    {
        preg_match('/<textarea[^>]*name="'.preg_quote($name, '/').'"[^>]*>(.*?)<\/textarea>/s', $html, $matches);

        return html_entity_decode(trim($matches[1] ?? '__NAO_ENCONTRADO__'), ENT_QUOTES, 'UTF-8');
    }
}
