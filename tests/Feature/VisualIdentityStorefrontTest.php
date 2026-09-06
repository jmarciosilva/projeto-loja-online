<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Services\MediaService;
use App\Services\SiteSettingService;
use App\Services\VisualIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Identidade visual na vitrine — F2.3-C.
 *
 * O storefront resolve logo e favicon pela mídia configurada, sempre com URL
 * derivada, e degrada para o fallback quando não há configuração — ou quando a
 * referência aponta para uma mídia que já não existe.
 */
class VisualIdentityStorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Storage::fake('public');
    }

    public function test_without_a_logo_the_textual_fallback_is_kept(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        // A asserção precisa olhar o **link do cabeçalho**, e não a página
        // inteira: o nome da loja também aparece no <title> e no rodapé, e uma
        // busca solta passaria mesmo sem fallback nenhum no header.
        $this->assertSame(
            e((string) config('app.name')),
            $this->headerLinkContent($html),
        );
        $this->assertDoesNotMatchRegularExpression('#<img[^>]*/storage/media/#', $html);
    }

    public function test_a_configured_logo_is_rendered_as_an_image(): void
    {
        $logo = Media::factory()->create();
        $this->identity()->save($logo->id, null);

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<img[^>]*src="[^"]*'.preg_quote($logo->path, '#').'"#', $html);
    }

    public function test_the_logo_uses_the_url_derived_from_the_media(): void
    {
        $logo = Media::factory()->create();
        $this->identity()->save($logo->id, null);

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(
            'src="'.e(app(MediaService::class)->url($logo)).'"',
            $html,
        );
    }

    public function test_the_logo_has_an_alt_attribute(): void
    {
        $logo = Media::factory()->create();
        $this->identity()->save($logo->id, null);

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<img[^>]*alt="'.preg_quote(e((string) config('app.name')), '#').'"#',
            $html,
        );
    }

    public function test_the_logo_links_to_the_home_route(): void
    {
        $logo = Media::factory()->create();
        $this->identity()->save($logo->id, null);

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="'.preg_quote(e(route('home')), '#').'"[^>]*>\s*(?:\{\{--.*?--\}\}\s*)?<img#s',
            $html,
        );
    }

    public function test_without_a_favicon_no_icon_link_is_emitted(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('rel="icon"', $html);
    }

    public function test_a_configured_favicon_emits_an_icon_link(): void
    {
        $favicon = Media::factory()->create(['mime_type' => 'image/png']);
        $this->identity()->save(null, $favicon->id);

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('rel="icon"', $html);
        $this->assertStringContainsString('type="image/png"', $html);
    }

    public function test_the_favicon_uses_the_url_derived_from_the_media(): void
    {
        $favicon = Media::factory()->create(['mime_type' => 'image/png']);
        $this->identity()->save(null, $favicon->id);

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(
            'href="'.e(app(MediaService::class)->url($favicon)).'"',
            $html,
        );
    }

    public function test_a_dangling_logo_reference_does_not_break_the_storefront(): void
    {
        $logo = Media::factory()->create();
        $this->identity()->save($logo->id, null);

        // A mídia some, mas a configuração permanece apontando para o id.
        $logo->delete();

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertSame(
            e((string) config('app.name')),
            $this->headerLinkContent($html),
        );
        $this->assertStringNotContainsString($logo->path, $html);
    }

    public function test_a_dangling_favicon_reference_does_not_break_the_storefront(): void
    {
        $favicon = Media::factory()->create(['mime_type' => 'image/png']);
        $this->identity()->save(null, $favicon->id);

        $favicon->delete();

        $this->get('/')->assertOk();

        $html = (string) $this->get('/')->getContent();
        $this->assertStringNotContainsString('rel="icon"', $html);
    }

    public function test_a_corrupted_reference_type_is_treated_as_absent(): void
    {
        // Manipulação externa do banco: a chave existe com um tipo incompatível.
        app(SiteSettingService::class)
            ->set(VisualIdentityService::LOGO_KEY, 'string', 'media/2026/09/qualquer.jpg');

        $this->assertNull($this->identity()->logoMediaId());
        $this->assertNull($this->identity()->logo());

        $this->get('/')->assertOk();
    }

    /**
     * Conteúdo do link de marca no cabeçalho, já sem espaços nas bordas.
     *
     * É o único ponto onde logo e fallback textual se alternam, então é ele —
     * e não a página inteira — que precisa ser inspecionado.
     */
    private function headerLinkContent(string $html): string
    {
        $matched = preg_match(
            '#<a[^>]*href="'.preg_quote(e(route('home')), '#').'"[^>]*>(.*?)</a>#s',
            $html,
            $matches,
        );

        $this->assertSame(1, $matched, 'O link de marca do cabeçalho não foi encontrado.');

        return trim($matches[1]);
    }

    private function identity(): VisualIdentityService
    {
        return app(VisualIdentityService::class);
    }
}
