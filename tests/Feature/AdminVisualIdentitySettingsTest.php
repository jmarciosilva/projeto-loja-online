<?php

namespace Tests\Feature;

use App\Exceptions\MediaInUseException;
use App\Models\Media;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\MediaService;
use App\Services\MediaUsageRegistry;
use App\Services\SiteSettingService;
use App\Services\VisualIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Identidade visual — F2.3-C.
 *
 * A configuração guarda `Media.id`, nunca caminho ou URL, e a proteção contra
 * exclusão de mídia em uso é feita pelo `MediaUsageRegistry` da F2.7 — esta
 * subfase apenas registra seus consumidores.
 */
class AdminVisualIdentitySettingsTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/admin/configuracoes/identidade-visual';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Storage::fake('public');
    }

    // --- Acesso -----------------------------------------------------------

    public function test_guest_cannot_open_the_identity_page(): void
    {
        $this->get(self::URI)->assertRedirect('/login');
    }

    public function test_guest_cannot_update_the_identity(): void
    {
        $this->put(self::URI, ['logo_media_id' => null, 'favicon_media_id' => null])
            ->assertRedirect('/login');

        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_authenticated_user_can_open_the_identity_page(): void
    {
        $this->actingAsAdmin()
            ->get(self::URI)
            ->assertOk()
            ->assertSee('Identidade visual');
    }

    public function test_the_settings_navigation_offers_the_identity_section(): void
    {
        // O item só existe porque a rota passou a existir nesta subfase.
        $this->actingAsAdmin()
            ->get('/admin/configuracoes')
            ->assertOk()
            ->assertSee('Identidade visual')
            ->assertSee(self::URI);
    }

    public function test_the_active_section_is_marked_on_the_identity_page(): void
    {
        $html = $this->actingAsAdmin()->get(self::URI)->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="[^"]*'.preg_quote(self::URI, '#').'"[^>]*aria-current="page"#',
            (string) $html,
        );
    }

    // --- Persistência -----------------------------------------------------

    public function test_it_saves_both_references_as_media_ids(): void
    {
        $logo = Media::factory()->create();
        $favicon = Media::factory()->create(['mime_type' => 'image/png']);

        $this->actingAsAdmin()
            ->put(self::URI, [
                'logo_media_id' => $logo->id,
                'favicon_media_id' => $favicon->id,
            ])
            ->assertRedirect(self::URI)
            ->assertSessionHas('status');

        $this->assertDatabaseHas('site_settings', [
            'key' => VisualIdentityService::LOGO_KEY,
            'type' => 'integer',
            'value' => (string) $logo->id,
        ]);
        $this->assertDatabaseHas('site_settings', [
            'key' => VisualIdentityService::FAVICON_KEY,
            'type' => 'integer',
            'value' => (string) $favicon->id,
        ]);
    }

    public function test_the_stored_value_is_the_id_and_never_the_path_or_url(): void
    {
        $logo = Media::factory()->create();

        $this->actingAsAdmin()->put(self::URI, ['logo_media_id' => $logo->id]);

        $stored = SiteSetting::query()->where('key', VisualIdentityService::LOGO_KEY)->firstOrFail();

        $this->assertSame($logo->id, $stored->value);
        $this->assertIsInt($stored->value);
        $this->assertStringNotContainsString('media/', (string) $stored->getRawOriginal('value'));
        $this->assertStringNotContainsString('http', (string) $stored->getRawOriginal('value'));
        $this->assertStringNotContainsString($logo->path, (string) $stored->getRawOriginal('value'));
    }

    public function test_an_empty_submission_clears_the_logo(): void
    {
        $logo = Media::factory()->create();
        $this->identity()->save($logo->id, null);

        $this->actingAsAdmin()
            ->put(self::URI, ['logo_media_id' => null, 'favicon_media_id' => null])
            ->assertRedirect(self::URI);

        $this->assertDatabaseHas('site_settings', [
            'key' => VisualIdentityService::LOGO_KEY,
            'type' => 'null',
            'value' => null,
        ]);
        $this->assertNull($this->identity()->logoMediaId());
    }

    public function test_an_empty_submission_clears_the_favicon(): void
    {
        $favicon = Media::factory()->create(['mime_type' => 'image/png']);
        $this->identity()->save(null, $favicon->id);

        $this->actingAsAdmin()
            ->put(self::URI, ['logo_media_id' => null, 'favicon_media_id' => null])
            ->assertRedirect(self::URI);

        $this->assertDatabaseHas('site_settings', [
            'key' => VisualIdentityService::FAVICON_KEY,
            'type' => 'null',
            'value' => null,
        ]);
        $this->assertNull($this->identity()->faviconMediaId());
    }

    public function test_the_persisted_values_appear_selected_on_the_form(): void
    {
        $logo = Media::factory()->create();
        $this->identity()->save($logo->id, null);

        $html = (string) $this->actingAsAdmin()->get(self::URI)->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<option value="'.$logo->id.'"[^>]*\bselected\b#',
            $html,
        );
    }

    public function test_both_settings_are_written_in_a_single_batch(): void
    {
        $logo = Media::factory()->create();
        $favicon = Media::factory()->create(['mime_type' => 'image/png']);

        $this->actingAsAdmin()->put(self::URI, [
            'logo_media_id' => $logo->id,
            'favicon_media_id' => $favicon->id,
        ]);

        // Caminho feliz: as duas chaves nascem juntas. Isto **não** é prova de
        // rollback — a atomicidade é provada pelos dois testes seguintes.
        $this->assertDatabaseCount('site_settings', 2);
    }

    /**
     * Prova B da atomicidade: a identidade visual entrega logo e favicon em uma
     * única chamada a `setMany()`, com o payload exato.
     *
     * Combinada com a prova A — `SiteSettingServiceTest` já demonstra que
     * `setMany()` sofre rollback integral —, é o que garante que nenhuma tela
     * consegue gravar metade da identidade visual.
     */
    public function test_the_service_sends_both_keys_in_a_single_set_many_call(): void
    {
        $logo = Media::factory()->create();
        $favicon = Media::factory()->create(['mime_type' => 'image/png']);

        $siteSettings = $this->mock(SiteSettingService::class);
        $siteSettings->shouldReceive('setMany')
            ->once()
            ->withArgs(function (array $batch) use ($logo, $favicon): bool {
                return $batch === [
                    VisualIdentityService::LOGO_KEY => ['type' => 'integer', 'value' => $logo->id],
                    VisualIdentityService::FAVICON_KEY => ['type' => 'integer', 'value' => $favicon->id],
                ];
            })
            ->andReturn([]);

        // Nenhuma outra escrita é permitida: `set()` gravaria uma chave por vez,
        // fora da transação do lote.
        $siteSettings->shouldNotReceive('set');

        app(VisualIdentityService::class)->save($logo->id, $favicon->id);
    }

    public function test_clearing_sends_the_null_contract_for_both_keys_in_a_single_call(): void
    {
        $siteSettings = $this->mock(SiteSettingService::class);
        $siteSettings->shouldReceive('setMany')
            ->once()
            ->withArgs(function (array $batch): bool {
                return $batch === [
                    VisualIdentityService::LOGO_KEY => ['type' => 'null', 'value' => null],
                    VisualIdentityService::FAVICON_KEY => ['type' => 'null', 'value' => null],
                ];
            })
            ->andReturn([]);

        $siteSettings->shouldNotReceive('set');

        app(VisualIdentityService::class)->save(null, null);
    }

    /**
     * Prova A aplicada às chaves desta subfase: uma falha ao persistir a
     * **segunda** configuração desfaz também a primeira.
     *
     * A falha é induzida dentro de `persistSetting()`, e não antes da chamada ao
     * serviço — é um `saving` do model que rejeita a chave do favicon —, então
     * ela acontece com a transação de `setMany()` já aberta e a logo já gravada.
     */
    public function test_a_failure_persisting_the_second_setting_rolls_the_whole_batch_back(): void
    {
        $logoAntiga = Media::factory()->create();
        $faviconAntigo = Media::factory()->create(['mime_type' => 'image/png']);
        $novaLogo = Media::factory()->create();
        $novoFavicon = Media::factory()->create(['mime_type' => 'image/png']);

        $this->identity()->save($logoAntiga->id, $faviconAntigo->id);

        SiteSetting::saving(function (SiteSetting $setting): void {
            if ($setting->key === VisualIdentityService::FAVICON_KEY) {
                throw new RuntimeException('Falha simulada ao persistir o favicon.');
            }
        });

        try {
            $this->identity()->save($novaLogo->id, $novoFavicon->id);
            $this->fail('A falha na segunda configuração deveria ter propagado.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Falha simulada ao persistir o favicon.', $exception->getMessage());
        } finally {
            SiteSetting::flushEventListeners();
        }

        // Nada de estado intermediário: a logo **não** ficou com o valor novo.
        $this->assertDatabaseHas('site_settings', [
            'key' => VisualIdentityService::LOGO_KEY,
            'value' => (string) $logoAntiga->id,
        ]);
        $this->assertDatabaseHas('site_settings', [
            'key' => VisualIdentityService::FAVICON_KEY,
            'value' => (string) $faviconAntigo->id,
        ]);
        $this->assertDatabaseMissing('site_settings', [
            'key' => VisualIdentityService::LOGO_KEY,
            'value' => (string) $novaLogo->id,
        ]);
        $this->assertDatabaseCount('site_settings', 2);

        // E a leitura, já sem o cache do lote revertido, confirma o estado antigo.
        Cache::flush();
        $this->assertSame($logoAntiga->id, $this->identity()->logoMediaId());
        $this->assertSame($faviconAntigo->id, $this->identity()->faviconMediaId());
    }

    // --- Formato: logo aceita tudo, favicon só PNG ------------------------

    /**
     * @return list<array{0: string}>
     */
    public static function logoMimeProvider(): array
    {
        return [
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
        ];
    }

    #[DataProvider('logoMimeProvider')]
    public function test_the_logo_accepts_every_format_the_library_stores(string $mimeType): void
    {
        $logo = Media::factory()->create(['mime_type' => $mimeType]);

        $this->actingAsAdmin()
            ->put(self::URI, ['logo_media_id' => $logo->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($logo->id, $this->identity()->logoMediaId());
    }

    public function test_a_png_is_accepted_as_favicon(): void
    {
        $favicon = Media::factory()->create(['mime_type' => 'image/png']);

        $this->actingAsAdmin()
            ->put(self::URI, ['favicon_media_id' => $favicon->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($favicon->id, $this->identity()->faviconMediaId());
    }

    /**
     * @return list<array{0: string}>
     */
    public static function nonPngFaviconProvider(): array
    {
        return [
            'jpeg' => ['image/jpeg'],
            'webp' => ['image/webp'],
        ];
    }

    /**
     * Submissão manual: mesmo com a interface oferecendo apenas PNG, um POST
     * forjado com o id de uma mídia JPEG ou WebP precisa ser recusado.
     */
    #[DataProvider('nonPngFaviconProvider')]
    public function test_a_non_png_media_is_rejected_as_favicon(string $mimeType): void
    {
        $logoAtual = Media::factory()->create(['mime_type' => 'image/png']);
        $faviconAtual = Media::factory()->create(['mime_type' => 'image/png']);
        $this->identity()->save($logoAtual->id, $faviconAtual->id);

        $novaLogo = Media::factory()->create(['mime_type' => 'image/png']);
        $faviconInvalido = Media::factory()->create(['mime_type' => $mimeType]);

        $response = $this->actingAsAdmin()->put(self::URI, [
            'logo_media_id' => $novaLogo->id,
            'favicon_media_id' => $faviconInvalido->id,
        ]);

        $response->assertSessionHasErrors('favicon_media_id');
        $this->assertStringContainsString(
            'PNG',
            (string) session('errors')->first('favicon_media_id'),
        );

        // A logo válida enviada na mesma requisição também não é persistida:
        // a validação falha antes de qualquer escrita.
        Cache::flush();
        $this->assertSame($logoAtual->id, $this->identity()->logoMediaId());
        $this->assertSame($faviconAtual->id, $this->identity()->faviconMediaId());
        $this->assertDatabaseCount('site_settings', 2);
    }

    public function test_the_service_itself_refuses_a_non_png_favicon(): void
    {
        // A interface não pode ser a única barreira: a chamada direta ao
        // serviço, fora do HTTP, precisa recusar do mesmo jeito.
        $favicon = Media::factory()->create(['mime_type' => 'image/webp']);

        $this->expectException(InvalidArgumentException::class);

        try {
            $this->identity()->save(null, $favicon->id);
        } finally {
            $this->assertDatabaseCount('site_settings', 0);
        }
    }

    public function test_clearing_the_favicon_remains_allowed(): void
    {
        $favicon = Media::factory()->create(['mime_type' => 'image/png']);
        $this->identity()->save(null, $favicon->id);

        $this->actingAsAdmin()
            ->put(self::URI, ['logo_media_id' => null, 'favicon_media_id' => null])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->identity()->faviconMediaId());
    }

    public function test_only_png_media_is_offered_as_favicon(): void
    {
        $png = Media::factory()->create(['mime_type' => 'image/png', 'original_name' => 'icone-png.png']);
        $jpeg = Media::factory()->create(['mime_type' => 'image/jpeg', 'original_name' => 'foto-jpeg.jpg']);
        $webp = Media::factory()->create(['mime_type' => 'image/webp', 'original_name' => 'arte-webp.webp']);

        $html = (string) $this->actingAsAdmin()->get(self::URI)->assertOk()->getContent();

        $seletorLogo = $this->selectOptions($html, 'logo_media_id');
        $seletorFavicon = $this->selectOptions($html, 'favicon_media_id');

        // A logo continua oferecendo tudo o que a biblioteca armazena.
        foreach ([$png, $jpeg, $webp] as $media) {
            $this->assertStringContainsString('value="'.$media->id.'"', $seletorLogo);
        }

        // O favicon oferece apenas PNG — a asserção olha o <select> do
        // favicon, e não a página, porque as demais aparecem no seletor da logo.
        $this->assertStringContainsString('value="'.$png->id.'"', $seletorFavicon);
        $this->assertStringNotContainsString('value="'.$jpeg->id.'"', $seletorFavicon);
        $this->assertStringNotContainsString('value="'.$webp->id.'"', $seletorFavicon);
    }

    public function test_available_favicon_media_only_contains_png(): void
    {
        $png = Media::factory()->create(['mime_type' => 'image/png']);
        Media::factory()->create(['mime_type' => 'image/jpeg']);
        Media::factory()->create(['mime_type' => 'image/webp']);

        $this->assertSame([$png->id], $this->identity()->availableFaviconMedia()->pluck('id')->all());
        $this->assertCount(3, $this->identity()->availableMedia());
    }

    // --- Validação --------------------------------------------------------

    public function test_a_missing_media_id_is_rejected(): void
    {
        $this->actingAsAdmin()
            ->put(self::URI, ['logo_media_id' => 999_999_999])
            ->assertSessionHasErrors('logo_media_id');

        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_a_negative_media_id_is_rejected(): void
    {
        $this->actingAsAdmin()
            ->put(self::URI, ['favicon_media_id' => -1])
            ->assertSessionHasErrors('favicon_media_id');

        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_a_non_integer_media_id_is_rejected(): void
    {
        $this->actingAsAdmin()
            ->put(self::URI, ['logo_media_id' => 'abc'])
            ->assertSessionHasErrors('logo_media_id');
    }

    public function test_an_array_media_id_is_rejected(): void
    {
        $this->actingAsAdmin()
            ->put(self::URI, ['logo_media_id' => [1, 2]])
            ->assertSessionHasErrors('logo_media_id');
    }

    public function test_an_invalid_submission_does_not_overwrite_a_valid_configuration(): void
    {
        $logo = Media::factory()->create();
        $this->identity()->save($logo->id, null);

        $this->actingAsAdmin()
            ->put(self::URI, ['logo_media_id' => 999_999_999])
            ->assertSessionHasErrors('logo_media_id');

        $this->assertSame($logo->id, $this->identity()->logoMediaId());
    }

    public function test_arbitrary_fields_do_not_create_settings(): void
    {
        $this->actingAsAdmin()->put(self::URI, [
            'logo_media_id' => null,
            'favicon_media_id' => null,
            'site.name' => 'Loja Invadida',
            'theme.primary_color' => '#FF0000',
        ]);

        // Somente as duas chaves da identidade visual existem.
        $this->assertDatabaseCount('site_settings', 2);
        $this->assertDatabaseMissing('site_settings', ['key' => 'site.name']);
        $this->assertDatabaseMissing('site_settings', ['key' => 'theme.primary_color']);
    }

    // --- Resolução --------------------------------------------------------

    public function test_the_configuration_resolves_the_media_record(): void
    {
        $logo = Media::factory()->create();
        $this->identity()->save($logo->id, null);

        $resolved = $this->identity()->logo();

        $this->assertNotNull($resolved);
        $this->assertSame($logo->id, $resolved->id);
        $this->assertSame($logo->path, $resolved->path);
    }

    public function test_the_url_is_derived_from_the_media_and_not_persisted(): void
    {
        $logo = Media::factory()->create();
        $this->identity()->save($logo->id, null);

        $this->assertSame(
            app(MediaService::class)->url($logo),
            $this->identity()->url($logo),
        );

        $stored = SiteSetting::query()->where('key', VisualIdentityService::LOGO_KEY)->firstOrFail();
        $this->assertIsInt($stored->value);
    }

    public function test_a_dangling_reference_resolves_to_nothing(): void
    {
        $logo = Media::factory()->create();
        $this->identity()->save($logo->id, null);

        // Simula manipulação externa: a mídia some, mas a configuração fica.
        $logo->delete();

        $this->assertNull($this->identity()->logo());
        $this->assertSame($logo->id, $this->identity()->logoMediaId());
    }

    public function test_available_media_is_ordered_by_id_descending(): void
    {
        $records = Media::factory()->count(3)->create();

        $this->assertSame(
            $records->pluck('id')->sortDesc()->values()->all(),
            $this->identity()->availableMedia()->pluck('id')->all(),
        );
    }

    // --- MediaUsageRegistry -----------------------------------------------

    public function test_the_logo_consumer_is_registered_with_its_label(): void
    {
        $logo = Media::factory()->create();
        $this->identity()->save($logo->id, null);

        $this->assertSame(['Logo do site'], app(MediaUsageRegistry::class)->usages($logo->fresh()));
    }

    public function test_the_favicon_consumer_is_registered_with_its_label(): void
    {
        $favicon = Media::factory()->create(['mime_type' => 'image/png']);
        $this->identity()->save(null, $favicon->id);

        $this->assertSame(['Favicon do site'], app(MediaUsageRegistry::class)->usages($favicon->fresh()));
    }

    public function test_one_media_used_as_both_reports_both_labels(): void
    {
        $media = Media::factory()->create(['mime_type' => 'image/png']);
        $this->identity()->save($media->id, $media->id);

        $this->assertSame(
            ['Logo do site', 'Favicon do site'],
            app(MediaUsageRegistry::class)->usages($media->fresh()),
        );
    }

    public function test_an_unrelated_media_is_not_reported_as_used(): void
    {
        $logo = Media::factory()->create();
        $other = Media::factory()->create();
        $this->identity()->save($logo->id, null);

        $this->assertSame([], app(MediaUsageRegistry::class)->usages($other->fresh()));
        $this->assertFalse(app(MediaUsageRegistry::class)->isInUse($other->fresh()));
    }

    public function test_no_media_is_reported_as_used_when_nothing_is_configured(): void
    {
        $media = Media::factory()->create();

        $this->assertSame([], app(MediaUsageRegistry::class)->usages($media));
    }

    // --- Exclusão protegida -----------------------------------------------

    public function test_media_used_as_logo_cannot_be_deleted(): void
    {
        $logo = Media::factory()->create();
        Storage::disk('public')->put($logo->path, 'conteudo');
        $this->identity()->save($logo->id, null);

        try {
            app(MediaService::class)->delete($logo->fresh());
            $this->fail('A exclusão de uma mídia em uso deveria ter sido bloqueada.');
        } catch (MediaInUseException $exception) {
            $this->assertSame(['Logo do site'], $exception->usages);
        }

        // Registro e arquivo permanecem intactos.
        $this->assertDatabaseHas('media', ['id' => $logo->id]);
        Storage::disk('public')->assertExists($logo->path);
    }

    public function test_media_used_as_favicon_cannot_be_deleted(): void
    {
        $favicon = Media::factory()->create(['mime_type' => 'image/png']);
        Storage::disk('public')->put($favicon->path, 'conteudo');
        $this->identity()->save(null, $favicon->id);

        try {
            app(MediaService::class)->delete($favicon->fresh());
            $this->fail('A exclusão de uma mídia em uso deveria ter sido bloqueada.');
        } catch (MediaInUseException $exception) {
            $this->assertSame(['Favicon do site'], $exception->usages);
        }

        $this->assertDatabaseHas('media', ['id' => $favicon->id]);
        Storage::disk('public')->assertExists($favicon->path);
    }

    public function test_media_used_as_both_reports_both_usages_when_blocked(): void
    {
        $media = Media::factory()->create(['mime_type' => 'image/png']);
        $this->identity()->save($media->id, $media->id);

        try {
            app(MediaService::class)->delete($media->fresh());
            $this->fail('A exclusão de uma mídia em uso deveria ter sido bloqueada.');
        } catch (MediaInUseException $exception) {
            $this->assertSame(['Logo do site', 'Favicon do site'], $exception->usages);
            $this->assertStringContainsString('Logo do site', $exception->getMessage());
            $this->assertStringContainsString('Favicon do site', $exception->getMessage());
        }

        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }

    public function test_the_previous_logo_is_released_after_the_reference_changes(): void
    {
        $antiga = Media::factory()->create();
        $nova = Media::factory()->create();
        Storage::disk('public')->put($antiga->path, 'conteudo');
        $this->identity()->save($antiga->id, null);

        $this->actingAsAdmin()->put(self::URI, ['logo_media_id' => $nova->id]);

        // Trocada a referência, a mídia antiga volta a ser excluível.
        app(MediaService::class)->delete($antiga->fresh());

        $this->assertDatabaseMissing('media', ['id' => $antiga->id]);
        $this->assertDatabaseHas('media', ['id' => $nova->id]);
    }

    public function test_the_previous_favicon_is_released_after_the_configuration_is_cleared(): void
    {
        $favicon = Media::factory()->create(['mime_type' => 'image/png']);
        Storage::disk('public')->put($favicon->path, 'conteudo');
        $this->identity()->save(null, $favicon->id);

        $this->actingAsAdmin()->put(self::URI, ['logo_media_id' => null, 'favicon_media_id' => null]);

        app(MediaService::class)->delete($favicon->fresh());

        $this->assertDatabaseMissing('media', ['id' => $favicon->id]);
    }

    // --- Auxiliares -------------------------------------------------------

    /**
     * Recorta o conteúdo de um `<select>` pelo atributo `name`.
     *
     * As asserções de formato precisam olhar o seletor certo: uma mídia JPEG
     * legitimamente aparece no seletor da logo, e procurá-la na página inteira
     * daria falso positivo no do favicon.
     */
    private function selectOptions(string $html, string $name): string
    {
        $matched = preg_match(
            '#<select[^>]*name="'.preg_quote($name, '#').'"[^>]*>(.*?)</select>#s',
            $html,
            $matches,
        );

        $this->assertSame(1, $matched, "O seletor [{$name}] não foi encontrado.");

        return $matches[1];
    }

    private function identity(): VisualIdentityService
    {
        return app(VisualIdentityService::class);
    }

    private function actingAsAdmin(): static
    {
        return $this->actingAs(User::factory()->create());
    }
}
