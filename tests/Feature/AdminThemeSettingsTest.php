<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SiteSettingService;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tema e cores: interface administrativa, contrato de cor e exposição das
 * CSS variables em runtime no layout público.
 *
 * O comportamento transacional de setMany() é coberto por
 * SiteSettingServiceTest e não é reexercitado aqui.
 */
class AdminThemeSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/admin/configuracoes/tema';

    private const DEFAULTS = [
        'primary' => '#111827',
        'secondary' => '#4B5563',
        'accent' => '#2563EB',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    // --- Acesso -----------------------------------------------------------

    public function test_guest_cannot_open_the_theme_page(): void
    {
        $this->get(self::URI)->assertRedirect('/login');
    }

    public function test_guest_cannot_update_the_theme(): void
    {
        $this->put(self::URI, $this->validPayload())->assertRedirect('/login');

        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_authenticated_user_can_open_the_theme_page(): void
    {
        $this->actingAsAdmin()
            ->get(self::URI)
            ->assertOk()
            ->assertSee('Tema e cores');
    }

    // --- Defaults ---------------------------------------------------------

    public function test_it_shows_the_default_colors_when_nothing_is_persisted(): void
    {
        $html = $this->actingAsAdmin()->get(self::URI)->assertOk()->getContent();

        foreach (self::DEFAULTS as $name => $color) {
            $this->assertSame($color, $this->inputValue($html, "{$name}_color"));
        }
    }

    public function test_opening_the_theme_page_does_not_persist_any_setting(): void
    {
        $this->actingAsAdmin()->get(self::URI)->assertOk();

        $this->assertDatabaseCount('site_settings', 0);
    }

    // --- Persistência -----------------------------------------------------

    public function test_it_shows_the_persisted_colors(): void
    {
        $this->persistColors('#AA0011', '#BB0022', '#CC0033');

        $html = $this->actingAsAdmin()->get(self::URI)->assertOk()->getContent();

        $this->assertSame('#AA0011', $this->inputValue($html, 'primary_color'));
        $this->assertSame('#BB0022', $this->inputValue($html, 'secondary_color'));
        $this->assertSame('#CC0033', $this->inputValue($html, 'accent_color'));
    }

    public function test_a_valid_update_persists_the_three_keys_as_strings(): void
    {
        $this->actingAsAdmin()
            ->put(self::URI, $this->validPayload())
            ->assertRedirect(self::URI)
            ->assertSessionHas('status');

        $this->assertDatabaseCount('site_settings', 3);

        $expected = [
            'theme.primary_color' => '#AA0011',
            'theme.secondary_color' => '#BB0022',
            'theme.accent_color' => '#CC0033',
        ];

        foreach ($expected as $key => $color) {
            $this->assertDatabaseHas('site_settings', ['key' => $key, 'type' => 'string']);
            $this->assertSame($color, $this->settings()->get($key));
        }
    }

    public function test_lowercase_input_is_persisted_uppercase(): void
    {
        $this->actingAsAdmin()
            ->put(self::URI, [
                'primary_color' => '#abcdef',
                'secondary_color' => '#0f0f0f',
                'accent_color' => '#a1b2c3',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('#ABCDEF', $this->settings()->get('theme.primary_color'));
        $this->assertSame('#0F0F0F', $this->settings()->get('theme.secondary_color'));
        $this->assertSame('#A1B2C3', $this->settings()->get('theme.accent_color'));
    }

    // --- Validação --------------------------------------------------------

    /**
     * @return list<array{0: string}>
     */
    public static function invalidColorProvider(): array
    {
        return [
            'abreviado de tres digitos' => ['#FFF'],
            'sem cerquilha' => ['fffFFF'],
            'funcao rgb' => ['rgb(1,2,3)'],
            'funcao rgba' => ['rgba(1,2,3,0.5)'],
            'funcao hsl' => ['hsl(0, 0%, 0%)'],
            'nome de cor' => ['red'],
            'variavel css' => ['var(--x)'],
            'url' => ['url(http://exemplo.test/x.png)'],
            'injecao de css' => ['#111827; } body { display: none; } .x {'],
            'digito extra' => ['#1234567'],
        ];
    }

    #[DataProvider('invalidColorProvider')]
    public function test_it_rejects_values_outside_the_color_contract(string $color): void
    {
        $this->actingAsAdmin()
            ->put(self::URI, [
                'primary_color' => $color,
                'secondary_color' => '#BB0022',
                'accent_color' => '#CC0033',
            ])
            ->assertSessionHasErrors('primary_color');

        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_every_color_is_required(): void
    {
        $this->actingAsAdmin()
            ->put(self::URI, [])
            ->assertSessionHasErrors(['primary_color', 'secondary_color', 'accent_color']);

        $this->assertDatabaseCount('site_settings', 0);
    }

    // --- Proteção ---------------------------------------------------------

    public function test_an_invalid_request_does_not_overwrite_valid_colors(): void
    {
        $this->persistColors('#AA0011', '#BB0022', '#CC0033');

        $this->actingAsAdmin()
            ->put(self::URI, [
                'primary_color' => '#DD0044',
                'secondary_color' => 'invalido',
                'accent_color' => '#FF0066',
            ])
            ->assertSessionHasErrors('secondary_color');

        $this->assertSame('#AA0011', $this->settings()->get('theme.primary_color'));
        $this->assertSame('#BB0022', $this->settings()->get('theme.secondary_color'));
        $this->assertSame('#CC0033', $this->settings()->get('theme.accent_color'));
    }

    public function test_arbitrary_keys_do_not_create_additional_settings(): void
    {
        $this->actingAsAdmin()
            ->put(self::URI, $this->validPayload() + [
                'theme.evil_color' => '#000000',
                'quaternary_color' => '#000000',
                'key' => 'arbitraria',
                'type' => 'json',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['theme.accent_color', 'theme.primary_color', 'theme.secondary_color'],
            DB::table('site_settings')->pluck('key')->sort()->values()->all(),
        );
    }

    // --- CSS variables em runtime -----------------------------------------

    public function test_the_public_layout_declares_the_theme_variables(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['--color-primary', '--color-secondary', '--color-accent'] as $variable) {
            $this->assertStringContainsString($variable, $html);
        }
    }

    public function test_the_public_layout_falls_back_to_the_default_colors(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(self::DEFAULTS['primary'], $this->cssVariable($html, '--color-primary'));
        $this->assertSame(self::DEFAULTS['secondary'], $this->cssVariable($html, '--color-secondary'));
        $this->assertSame(self::DEFAULTS['accent'], $this->cssVariable($html, '--color-accent'));
    }

    public function test_the_public_layout_uses_the_persisted_colors(): void
    {
        $this->persistColors('#AA0011', '#BB0022', '#CC0033');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame('#AA0011', $this->cssVariable($html, '--color-primary'));
        $this->assertSame('#BB0022', $this->cssVariable($html, '--color-secondary'));
        $this->assertSame('#CC0033', $this->cssVariable($html, '--color-accent'));
    }

    public function test_saving_the_theme_changes_the_next_public_request(): void
    {
        $this->assertSame(self::DEFAULTS['primary'], $this->cssVariable($this->get('/')->getContent(), '--color-primary'));

        $this->actingAsAdmin()->put(self::URI, $this->validPayload())->assertSessionHasNoErrors();

        // Sem rebuild de assets no meio: as variáveis são de runtime.
        $this->assertSame('#AA0011', $this->cssVariable($this->get('/')->getContent(), '--color-primary'));
    }

    public function test_the_variables_are_rendered_inline_and_not_in_a_built_stylesheet(): void
    {
        $this->persistColors('#AA0011', '#BB0022', '#CC0033');

        $html = $this->get('/')->assertOk()->getContent();

        // A cor precisa vir no HTML da resposta. Se dependesse do CSS compilado
        // pelo Vite, salvar o tema exigiria `npm run build` para surtir efeito.
        $this->assertMatchesRegularExpression('/<style>.*?--color-primary:\s*#AA0011/s', $html);
    }

    public function test_the_public_layout_consumes_a_theme_variable(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Declarar as variáveis não basta: algum elemento precisa usá-las.
        $this->assertStringContainsString('theme-text-primary', $html);
    }

    // --- Preview e navegação ----------------------------------------------

    public function test_the_preview_shows_the_three_persisted_colors(): void
    {
        $this->persistColors('#AA0011', '#BB0022', '#CC0033');

        $html = $this->actingAsAdmin()->get(self::URI)->assertOk()->getContent();

        foreach (['Primária', 'Secundária', 'Destaque'] as $rotulo) {
            $this->assertStringContainsString($rotulo, $html);
        }

        foreach (['#AA0011', '#BB0022', '#CC0033'] as $color) {
            $this->assertStringContainsString("background-color: {$color}", $html);
        }
    }

    public function test_the_settings_navigation_lists_only_the_existing_sections(): void
    {
        $html = $this->actingAsAdmin()->get(self::URI)->getContent();

        $this->assertStringContainsString('Gerais', $html);
        $this->assertStringContainsString('Tema e cores', $html);

        // A F2.3-C ainda não existe; anunciá-la seria caminho quebrado.
        $this->assertStringNotContainsString('Identidade visual', $html);
    }

    public function test_theme_is_the_current_section_on_the_theme_page(): void
    {
        $navegacao = $this->settingsNavigation($this->actingAsAdmin()->get(self::URI)->getContent());

        $this->assertMatchesRegularExpression($this->currentLinkPattern(route('admin.settings.theme.edit')), $navegacao);
        $this->assertDoesNotMatchRegularExpression($this->currentLinkPattern(route('admin.settings.edit')), $navegacao);
    }

    public function test_general_is_the_current_section_on_the_general_page(): void
    {
        $navegacao = $this->settingsNavigation($this->actingAsAdmin()->get('/admin/configuracoes')->getContent());

        $this->assertMatchesRegularExpression($this->currentLinkPattern(route('admin.settings.edit')), $navegacao);
        $this->assertDoesNotMatchRegularExpression($this->currentLinkPattern(route('admin.settings.theme.edit')), $navegacao);
    }

    public function test_the_breadcrumbs_show_the_three_levels_on_the_theme_page(): void
    {
        $html = $this->actingAsAdmin()->get(self::URI)->getContent();

        preg_match('/<nav[^>]*aria-label="Trilha de navegação".*?<\/nav>/s', $html, $matches);
        $trilha = $matches[0] ?? '';

        $this->assertStringContainsString('Dashboard', $trilha);
        $this->assertStringContainsString('Configurações', $trilha);
        $this->assertStringContainsString('Tema e cores', $trilha);
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>\s*Tema e cores/s', $trilha);
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
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'primary_color' => '#AA0011',
            'secondary_color' => '#BB0022',
            'accent_color' => '#CC0033',
        ];
    }

    private function persistColors(string $primary, string $secondary, string $accent): void
    {
        $values = ['primary' => $primary, 'secondary' => $secondary, 'accent' => $accent];
        $settings = [];

        foreach (app(ThemeService::class)->keys() as $name => $key) {
            $settings[$key] = ['type' => 'string', 'value' => $values[$name]];
        }

        $this->settings()->setMany($settings);
    }

    /**
     * Isola a navegação local de Configurações.
     *
     * Necessário porque o item "Configurações" da sidebar aponta para o mesmo
     * href de "Gerais" e fica ativo em toda a seção — o href sozinho não
     * distingue os dois, e a asserção sem escopo mediria o elemento errado.
     */
    private function settingsNavigation(string $html): string
    {
        preg_match('/<nav[^>]*aria-label="Seções de configurações".*?<\/nav>/s', $html, $matches);

        return $matches[0] ?? '';
    }

    private function currentLinkPattern(string $url): string
    {
        return '/<a\s[^>]*href="'.preg_quote($url, '/').'"[^>]*aria-current="page"/s';
    }

    private function cssVariable(string $html, string $variable): string
    {
        preg_match('/'.preg_quote($variable, '/').':\s*([^;]+);/', $html, $matches);

        return trim($matches[1] ?? '__NAO_ENCONTRADA__');
    }

    private function inputValue(string $html, string $name): string
    {
        preg_match('/<input[^>]*name="'.preg_quote($name, '/').'"[^>]*value="([^"]*)"/s', $html, $matches);

        return $matches[1] ?? '__NAO_ENCONTRADO__';
    }
}
