<?php

namespace Tests\Feature;

use App\Enums\BannerPosition;
use App\Exceptions\MediaInUseException;
use App\Models\Banner;
use App\Models\Media;
use App\Models\User;
use App\Services\BannerService;
use App\Services\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Integração do CRUD administrativo de banners — F2.5-B.
 *
 * As invariantes de domínio já são cobertas por BannerTest e BannerServiceTest.
 * Aqui o assunto é a camada HTTP: acesso, validação, navegação, ordenação e,
 * sobretudo, que ela consome o `BannerService` em vez de reimplementar suas
 * regras.
 */
class AdminBannerTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/admin/banners';

    // --- Acesso -----------------------------------------------------------

    public function test_guest_cannot_reach_any_administrative_banner_route(): void
    {
        $banner = $this->makeBanner();

        $rotas = [
            ['get', self::URI],
            ['get', self::URI.'/criar'],
            ['post', self::URI],
            ['get', self::URI.'/'.$banner->id.'/editar'],
            ['put', self::URI.'/'.$banner->id],
            ['delete', self::URI.'/'.$banner->id],
            ['post', self::URI.'/'.$banner->id.'/mover'],
        ];

        foreach ($rotas as [$metodo, $uri]) {
            $this->{$metodo}($uri, ['name' => 'Invadido', 'direction' => 'down'])
                ->assertRedirect('/login');
        }

        $this->assertSame(1, Banner::query()->count());
        $this->assertSame('Campanha', $banner->fresh()->name);
        $this->assertSame(1, $banner->fresh()->sort_order);
    }

    public function test_authenticated_user_can_open_the_listing(): void
    {
        $this->actingAsAdmin()->get(self::URI)->assertOk()->assertSee('Banners');
    }

    public function test_authenticated_user_can_open_the_creation_form(): void
    {
        $this->actingAsAdmin()->get(self::URI.'/criar')->assertOk()->assertSee('Novo banner');
    }

    public function test_authenticated_user_can_open_the_edit_form(): void
    {
        $banner = $this->makeBanner();

        $this->actingAsAdmin()->get(self::URI.'/'.$banner->id.'/editar')
            ->assertOk()
            ->assertSee('Editar banner')
            ->assertSee('Campanha');
    }

    // --- Listagem ---------------------------------------------------------

    public function test_the_listing_shows_name_position_state_and_order(): void
    {
        Storage::fake(MediaService::DISK);

        $media = Media::factory()->create();
        $this->service()->create($this->attributes([
            'name' => 'Campanha de verão',
            'media_id' => $media->id,
            'position' => BannerPosition::Sidebar,
            'is_active' => true,
        ]));

        $html = $this->actingAsAdmin()->get(self::URI)->assertOk()->getContent();

        $this->assertStringContainsString('Campanha de verão', $html);
        $this->assertStringContainsString('Lateral', $html);
        $this->assertStringContainsString('Ativo', $html);
        // A URL é derivada da mídia pela infraestrutura da F2.7; o banner nunca
        // guarda caminho nem URL.
        $this->assertStringContainsString(app(MediaService::class)->url($media), $html);
    }

    public function test_the_listing_groups_every_position_even_when_empty(): void
    {
        $html = $this->actingAsAdmin()->get(self::URI)->getContent();

        foreach (['Destaque', 'Lateral', 'Rodapé'] as $rotulo) {
            $this->assertStringContainsString($rotulo, $html);
        }
    }

    public function test_the_listing_shows_inactive_banners(): void
    {
        $this->service()->create($this->attributes(['name' => 'Rascunho visual']));

        $this->actingAsAdmin()->get(self::URI)
            ->assertSee('Rascunho visual')
            ->assertSee('Inativo');
    }

    // --- Criação ----------------------------------------------------------

    public function test_it_creates_a_banner_through_the_service(): void
    {
        $media = Media::factory()->create();

        $this->actingAsAdmin()
            ->post(self::URI, [
                'name' => 'Campanha de verão',
                'media_id' => $media->id,
                'position' => 'hero',
                'link_url' => '  /categorias/promocoes  ',
                'alt_text' => 'Modelos usando a coleção de verão.',
                'is_active' => '1',
            ])
            ->assertRedirect(self::URI)
            ->assertSessionHas('status');

        $this->assertDatabaseHas('banners', [
            'name' => 'Campanha de verão',
            'media_id' => $media->id,
            'position' => 'hero',
            // O trim vem do serviço, não da camada HTTP.
            'link_url' => '/categorias/promocoes',
            'alt_text' => 'Modelos usando a coleção de verão.',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_a_created_banner_is_inactive_when_the_checkbox_is_not_marked(): void
    {
        // O campo oculto do formulário faz a ausência do check chegar como "0".
        $media = Media::factory()->create();

        $this->actingAsAdmin()->post(self::URI, [
            'name' => 'Campanha',
            'media_id' => $media->id,
            'position' => 'hero',
            'alt_text' => 'Campanha.',
            'is_active' => '0',
        ])->assertRedirect(self::URI);

        $this->assertFalse(Banner::query()->firstOrFail()->is_active);
    }

    public function test_a_created_banner_is_inactive_when_the_field_is_absent(): void
    {
        $media = Media::factory()->create();

        $this->actingAsAdmin()->post(self::URI, [
            'name' => 'Campanha',
            'media_id' => $media->id,
            'position' => 'hero',
            'alt_text' => 'Campanha.',
        ])->assertRedirect(self::URI);

        $this->assertFalse(Banner::query()->firstOrFail()->is_active);
    }

    public function test_the_order_is_assigned_by_the_service_and_not_by_the_request(): void
    {
        $media = Media::factory()->create();

        $this->service()->create($this->attributes(['media_id' => $media->id]));

        $this->actingAsAdmin()->post(self::URI, [
            'name' => 'Segundo',
            'media_id' => $media->id,
            'position' => 'hero',
            'alt_text' => 'Segundo banner.',
            // Enviado à força: o Controller monta o próprio payload e nunca lê
            // esta chave.
            'sort_order' => 99,
        ])->assertRedirect(self::URI);

        $this->assertSame(2, Banner::query()->where('name', 'Segundo')->firstOrFail()->sort_order);
        $this->assertDatabaseMissing('banners', ['sort_order' => 99]);
    }

    public function test_each_position_keeps_its_own_sequence_through_http(): void
    {
        $media = Media::factory()->create();

        foreach ([['A', 'hero'], ['B', 'hero'], ['C', 'footer']] as [$name, $position]) {
            $this->actingAsAdmin()->post(self::URI, [
                'name' => $name,
                'media_id' => $media->id,
                'position' => $position,
                'alt_text' => $name,
            ])->assertRedirect(self::URI);
        }

        $this->assertSame(1, Banner::query()->where('name', 'A')->firstOrFail()->sort_order);
        $this->assertSame(2, Banner::query()->where('name', 'B')->firstOrFail()->sort_order);
        $this->assertSame(1, Banner::query()->where('name', 'C')->firstOrFail()->sort_order);
    }

    // --- Validação --------------------------------------------------------

    public function test_the_name_is_required(): void
    {
        $this->submitCreation(['name' => ''])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('banners', 0);
    }

    public function test_the_name_is_limited_to_the_column(): void
    {
        $this->submitCreation(['name' => str_repeat('a', 121)])->assertSessionHasErrors('name');
    }

    public function test_the_alt_text_is_required(): void
    {
        $this->submitCreation(['alt_text' => ''])->assertSessionHasErrors('alt_text');
        $this->assertDatabaseCount('banners', 0);
    }

    public function test_the_alt_text_is_limited_to_the_column(): void
    {
        $this->submitCreation(['alt_text' => str_repeat('a', 256)])->assertSessionHasErrors('alt_text');
    }

    public function test_the_alt_text_is_never_derived_from_the_media_name(): void
    {
        $media = Media::factory()->create(['original_name' => 'banner-final-v2.jpg']);

        $this->submitCreation(['media_id' => $media->id, 'alt_text' => ''])
            ->assertSessionHasErrors('alt_text');

        $this->assertDatabaseCount('banners', 0);
    }

    public function test_the_media_is_required(): void
    {
        $this->actingAsAdmin()->post(self::URI, [
            'name' => 'Campanha',
            'position' => 'hero',
            'alt_text' => 'Campanha.',
        ])->assertSessionHasErrors('media_id');
    }

    public function test_a_missing_media_is_rejected(): void
    {
        $this->submitCreation(['media_id' => 4_242])->assertSessionHasErrors('media_id');
        $this->assertDatabaseCount('banners', 0);
    }

    public function test_an_unsupported_position_is_rejected(): void
    {
        $this->submitCreation(['position' => 'topo'])->assertSessionHasErrors('position');
        $this->assertDatabaseCount('banners', 0);
    }

    #[DataProvider('supportedMediaTypeProvider')]
    public function test_every_media_type_of_the_library_is_accepted(string $mimeType): void
    {
        // Sem herdar a restrição PNG do favicon da F2.3-C.
        $media = Media::factory()->create(['mime_type' => $mimeType]);

        $this->submitCreation(['media_id' => $media->id])->assertSessionHasNoErrors();

        $this->assertSame($media->id, Banner::query()->firstOrFail()->media_id);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function supportedMediaTypeProvider(): array
    {
        return [
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
        ];
    }

    public function test_a_link_longer_than_the_column_is_rejected(): void
    {
        $this->submitCreation(['link_url' => '/'.str_repeat('a', 2_048)])->assertSessionHasErrors('link_url');
    }

    #[DataProvider('invalidLinkProvider')]
    public function test_it_rejects_a_link_outside_the_contract(string $link): void
    {
        $this->submitCreation(['link_url' => $link])->assertSessionHasErrors('link_url');
        $this->assertDatabaseCount('banners', 0);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function invalidLinkProvider(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'vbscript' => ['vbscript:msgbox(1)'],
            'file' => ['file:///etc/passwd'],
            'ftp' => ['ftp://example.com/arquivo'],
            'protocol relative' => ['//evil.example'],
            'barra invertida' => ['\\evil.example'],
            'barra invertida apos barra' => ['/\\evil.example'],
            'host sem esquema' => ['www.exemplo.com'],
            'sem esquema e sem barra' => ['produtos'],
            'http sem host' => ['http://'],
        ];
    }

    #[DataProvider('validLinkProvider')]
    public function test_it_accepts_a_link_inside_the_contract(string $link): void
    {
        $this->submitCreation(['link_url' => $link])->assertSessionHasNoErrors();

        $this->assertSame($link, Banner::query()->firstOrFail()->link_url);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function validLinkProvider(): array
    {
        return [
            'caminho interno' => ['/produtos'],
            'caminho com query' => ['/produtos?categoria=1'],
            'https' => ['https://example.com'],
            'http com caminho' => ['http://example.com/path'],
        ];
    }

    public function test_an_empty_link_is_stored_as_null(): void
    {
        $this->submitCreation(['link_url' => ''])->assertSessionHasNoErrors();

        $this->assertNull(Banner::query()->firstOrFail()->link_url);
    }

    // --- Atualização ------------------------------------------------------

    public function test_it_updates_a_banner_through_the_service(): void
    {
        $banner = $this->makeBanner();
        $outra = Media::factory()->create();

        $this->actingAsAdmin()
            ->put(self::URI.'/'.$banner->id, [
                'name' => 'Campanha de inverno',
                'media_id' => $outra->id,
                'position' => 'hero',
                'link_url' => '/categorias/inverno',
                'alt_text' => 'Casacos da coleção de inverno.',
                'is_active' => '1',
            ])
            ->assertRedirect(self::URI)
            ->assertSessionHas('status');

        $this->assertDatabaseHas('banners', [
            'id' => $banner->id,
            'name' => 'Campanha de inverno',
            'media_id' => $outra->id,
            'link_url' => '/categorias/inverno',
            'is_active' => true,
        ]);
    }

    #[DataProvider('orderPreservingChangeProvider')]
    public function test_an_ordinary_change_preserves_the_order(string $campo, string $valor): void
    {
        $this->service()->create($this->attributes(['name' => 'Primeiro']));
        $banner = $this->service()->create($this->attributes(['name' => 'Segundo']));
        $this->service()->create($this->attributes(['name' => 'Terceiro']));

        $this->actingAsAdmin()
            ->put(self::URI.'/'.$banner->id, array_merge($this->formPayload($banner), [$campo => $valor]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $banner->fresh()->sort_order);
        $this->assertSame(BannerPosition::Hero, $banner->fresh()->position);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function orderPreservingChangeProvider(): array
    {
        return [
            'nome' => ['name', 'Outro nome'],
            'link' => ['link_url', '/novo-destino'],
            'texto alternativo' => ['alt_text', 'Outra descrição.'],
            'estado' => ['is_active', '1'],
            // Reenviar a mesma posição é o caso comum de um "salvar".
            'mesma posicao' => ['position', 'hero'],
        ];
    }

    public function test_changing_the_media_preserves_the_order(): void
    {
        $this->service()->create($this->attributes(['name' => 'Primeiro']));
        $banner = $this->service()->create($this->attributes(['name' => 'Segundo']));
        $outra = Media::factory()->create();

        $this->actingAsAdmin()
            ->put(self::URI.'/'.$banner->id, array_merge($this->formPayload($banner), ['media_id' => $outra->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $banner->fresh()->sort_order);
        $this->assertSame($outra->id, $banner->fresh()->media_id);
    }

    public function test_changing_the_position_moves_the_banner_to_the_end_of_the_destination(): void
    {
        $this->service()->create($this->attributes(['name' => 'Hero 1']));
        $banner = $this->service()->create($this->attributes(['name' => 'Hero 2']));
        $this->service()->create($this->attributes(['name' => 'Rodapé 1', 'position' => BannerPosition::Footer]));

        $this->actingAsAdmin()
            ->put(self::URI.'/'.$banner->id, array_merge($this->formPayload($banner), ['position' => 'footer']))
            ->assertSessionHasNoErrors();

        $this->assertSame(BannerPosition::Footer, $banner->fresh()->position);
        $this->assertSame(2, $banner->fresh()->sort_order);
    }

    public function test_changing_the_position_does_not_compact_the_origin(): void
    {
        $primeiro = $this->service()->create($this->attributes(['name' => 'Hero 1']));
        $banner = $this->service()->create($this->attributes(['name' => 'Hero 2']));
        $terceiro = $this->service()->create($this->attributes(['name' => 'Hero 3']));

        $this->actingAsAdmin()
            ->put(self::URI.'/'.$banner->id, array_merge($this->formPayload($banner), ['position' => 'sidebar']))
            ->assertSessionHasNoErrors();

        // Lacuna aceitável: só a ordenação explícita renumera.
        $this->assertSame(1, $primeiro->fresh()->sort_order);
        $this->assertSame(3, $terceiro->fresh()->sort_order);
    }

    public function test_an_invalid_update_does_not_change_the_banner(): void
    {
        $banner = $this->makeBanner();

        $this->actingAsAdmin()
            ->put(self::URI.'/'.$banner->id, array_merge($this->formPayload($banner), ['link_url' => 'javascript:alert(1)']))
            ->assertSessionHasErrors('link_url');

        $this->assertSame('Campanha', $banner->fresh()->name);
        $this->assertNull($banner->fresh()->link_url);
    }

    public function test_an_update_cannot_override_the_order_from_the_request(): void
    {
        $this->service()->create($this->attributes(['name' => 'Primeiro']));
        $banner = $this->service()->create($this->attributes(['name' => 'Segundo']));

        $this->actingAsAdmin()
            ->put(self::URI.'/'.$banner->id, array_merge($this->formPayload($banner), ['sort_order' => 99]))
            ->assertRedirect(self::URI)
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $banner->fresh()->sort_order);
        $this->assertDatabaseMissing('banners', ['sort_order' => 99]);
    }

    // --- Exclusão ---------------------------------------------------------

    public function test_it_deletes_the_banner_and_keeps_the_media(): void
    {
        $banner = $this->makeBanner();
        $mediaId = $banner->media_id;

        $this->actingAsAdmin()
            ->delete(self::URI.'/'.$banner->id)
            ->assertRedirect(self::URI)
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('banners', ['id' => $banner->id]);
        $this->assertDatabaseHas('media', ['id' => $mediaId]);
    }

    public function test_deleting_does_not_compact_the_remaining_order(): void
    {
        $primeiro = $this->service()->create($this->attributes(['name' => 'Hero 1']));
        $segundo = $this->service()->create($this->attributes(['name' => 'Hero 2']));
        $terceiro = $this->service()->create($this->attributes(['name' => 'Hero 3']));

        $this->actingAsAdmin()->delete(self::URI.'/'.$segundo->id)->assertRedirect(self::URI);

        // A ordem é relativa: 1 e 3 continuam descrevendo a mesma sequência.
        $this->assertSame(1, $primeiro->fresh()->sort_order);
        $this->assertSame(3, $terceiro->fresh()->sort_order);
    }

    // --- Ordenação --------------------------------------------------------

    public function test_moving_a_banner_up_swaps_it_with_the_previous_one(): void
    {
        [$a, $b, $c] = $this->threeHeroBanners();

        $this->actingAsAdmin()
            ->post(self::URI.'/'.$b->id.'/mover', ['direction' => 'up'])
            ->assertRedirect(self::URI)
            ->assertSessionHas('status');

        $this->assertOrderedNames(['Hero 2', 'Hero 1', 'Hero 3']);
        $this->assertSame([1, 2, 3], $this->orders());
    }

    public function test_moving_a_banner_down_swaps_it_with_the_next_one(): void
    {
        [$a, $b, $c] = $this->threeHeroBanners();

        $this->actingAsAdmin()
            ->post(self::URI.'/'.$b->id.'/mover', ['direction' => 'down'])
            ->assertRedirect(self::URI);

        $this->assertOrderedNames(['Hero 1', 'Hero 3', 'Hero 2']);
    }

    public function test_moving_the_first_banner_up_is_a_controlled_no_op(): void
    {
        [$a] = $this->threeHeroBanners();

        $this->actingAsAdmin()
            ->post(self::URI.'/'.$a->id.'/mover', ['direction' => 'up'])
            ->assertRedirect(self::URI)
            ->assertSessionHasNoErrors();

        $this->assertOrderedNames(['Hero 1', 'Hero 2', 'Hero 3']);
    }

    public function test_moving_the_last_banner_down_is_a_controlled_no_op(): void
    {
        [, , $c] = $this->threeHeroBanners();

        $this->actingAsAdmin()
            ->post(self::URI.'/'.$c->id.'/mover', ['direction' => 'down'])
            ->assertRedirect(self::URI)
            ->assertSessionHasNoErrors();

        $this->assertOrderedNames(['Hero 1', 'Hero 2', 'Hero 3']);
    }

    public function test_moving_normalizes_a_position_with_gaps(): void
    {
        // Ordens irregulares, como as que sobram de exclusões.
        $media = Media::factory()->create();
        $a = Banner::factory()->create(['media_id' => $media->id, 'position' => BannerPosition::Hero, 'sort_order' => 2, 'name' => 'A']);
        $b = Banner::factory()->create(['media_id' => $media->id, 'position' => BannerPosition::Hero, 'sort_order' => 7, 'name' => 'B']);
        $c = Banner::factory()->create(['media_id' => $media->id, 'position' => BannerPosition::Hero, 'sort_order' => 11, 'name' => 'C']);

        $this->actingAsAdmin()->post(self::URI.'/'.$c->id.'/mover', ['direction' => 'up'])->assertRedirect(self::URI);

        $this->assertOrderedNames(['A', 'C', 'B']);
        $this->assertSame([1, 2, 3], $this->orders());
    }

    public function test_moving_does_not_touch_another_position(): void
    {
        [, $b] = $this->threeHeroBanners();

        $footer = $this->service()->create($this->attributes(['name' => 'Rodapé 1', 'position' => BannerPosition::Footer]));
        $outro = $this->service()->create($this->attributes(['name' => 'Rodapé 2', 'position' => BannerPosition::Footer]));

        $this->actingAsAdmin()->post(self::URI.'/'.$b->id.'/mover', ['direction' => 'up'])->assertRedirect(self::URI);

        $this->assertSame(1, $footer->fresh()->sort_order);
        $this->assertSame(2, $outro->fresh()->sort_order);
    }

    public function test_inactive_banners_participate_in_the_ordering(): void
    {
        $a = $this->service()->create($this->attributes(['name' => 'Hero 1', 'is_active' => true]));
        $b = $this->service()->create($this->attributes(['name' => 'Hero 2']));
        $c = $this->service()->create($this->attributes(['name' => 'Hero 3', 'is_active' => true]));

        $this->actingAsAdmin()->post(self::URI.'/'.$c->id.'/mover', ['direction' => 'up'])->assertRedirect(self::URI);

        // O inativo não é pulado: a lista administrativa é a lista completa.
        $this->assertOrderedNames(['Hero 1', 'Hero 3', 'Hero 2']);
    }

    public function test_an_invalid_direction_is_rejected(): void
    {
        [, $b] = $this->threeHeroBanners();

        $this->actingAsAdmin()
            ->post(self::URI.'/'.$b->id.'/mover', ['direction' => 'lateral'])
            ->assertSessionHasErrors('direction');

        $this->assertOrderedNames(['Hero 1', 'Hero 2', 'Hero 3']);
    }

    public function test_a_missing_direction_is_rejected(): void
    {
        [, $b] = $this->threeHeroBanners();

        $this->actingAsAdmin()
            ->post(self::URI.'/'.$b->id.'/mover', [])
            ->assertSessionHasErrors('direction');

        $this->assertOrderedNames(['Hero 1', 'Hero 2', 'Hero 3']);
    }

    public function test_the_ordered_query_reflects_the_final_order(): void
    {
        [, $b] = $this->threeHeroBanners();

        $this->actingAsAdmin()->post(self::URI.'/'.$b->id.'/mover', ['direction' => 'up'])->assertRedirect(self::URI);

        $this->assertSame(
            ['Hero 2', 'Hero 1', 'Hero 3'],
            $this->service()->orderedForPosition(BannerPosition::Hero)->pluck('name')->all(),
        );
    }

    public function test_a_failure_during_renumbering_rolls_the_whole_move_back(): void
    {
        $media = Media::factory()->create();
        $a = Banner::factory()->create(['media_id' => $media->id, 'position' => BannerPosition::Hero, 'sort_order' => 2, 'name' => 'A']);
        $b = Banner::factory()->create(['media_id' => $media->id, 'position' => BannerPosition::Hero, 'sort_order' => 7, 'name' => 'B']);
        $c = Banner::factory()->create(['media_id' => $media->id, 'position' => BannerPosition::Hero, 'sort_order' => 11, 'name' => 'C']);
        $updatedBanners = 0;

        // A falha é injetada depois que o segundo UPDATE realmente chegou ao
        // banco. Sem a transação, pelo menos o primeiro número ficaria gravado;
        // com ela, a exceção devolve a sequência inteira ao estado anterior.
        DB::listen(function ($query) use (&$updatedBanners): void {
            if (preg_match('/^update [`"]?banners[`"]?/i', $query->sql) !== 1) {
                return;
            }

            $updatedBanners++;

            if ($updatedBanners === 2) {
                throw new RuntimeException('Falha intermediária determinística da renumeração.');
            }
        });

        try {
            $this->service()->moveUp($c);
            $this->fail('A falha intermediária deveria interromper a renumeração.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Falha intermediária determinística da renumeração.', $exception->getMessage());
        }

        $this->assertSame(2, $a->fresh()->sort_order);
        $this->assertSame(7, $b->fresh()->sort_order);
        $this->assertSame(11, $c->fresh()->sort_order);
    }

    public function test_a_tie_is_still_broken_by_id(): void
    {
        // O contrato da F2.5-A continua valendo mesmo com ordens repetidas.
        $media = Media::factory()->create();
        $primeiro = Banner::factory()->create(['media_id' => $media->id, 'position' => BannerPosition::Hero, 'sort_order' => 5, 'name' => 'Antigo']);
        $segundo = Banner::factory()->create(['media_id' => $media->id, 'position' => BannerPosition::Hero, 'sort_order' => 5, 'name' => 'Novo']);

        $this->assertSame(
            [$primeiro->id, $segundo->id],
            $this->service()->orderedForPosition(BannerPosition::Hero)->pluck('id')->all(),
        );
    }

    // --- Navegação --------------------------------------------------------

    public function test_the_sidebar_links_to_banners_now_that_the_route_exists(): void
    {
        $html = $this->actingAsAdmin()->get('/admin')->getContent();

        $this->assertStringContainsString('href="'.route('admin.banners.index').'"', $html);
        $this->assertStringContainsString('Banners', $html);
    }

    public function test_the_breadcrumb_shows_the_current_section(): void
    {
        $banner = $this->makeBanner();

        $this->actingAsAdmin()->get(self::URI)->assertSee('Banners');
        $this->actingAsAdmin()->get(self::URI.'/criar')->assertSee('Novo banner');
        $this->actingAsAdmin()->get(self::URI.'/'.$banner->id.'/editar')->assertSee('Editar');
    }

    public function test_the_creation_form_does_not_expose_the_order(): void
    {
        $html = $this->actingAsAdmin()->get(self::URI.'/criar')->getContent();

        $this->assertStringNotContainsString('name="sort_order"', $html);
    }

    public function test_the_edit_form_does_not_expose_the_order_as_an_input(): void
    {
        $banner = $this->makeBanner();

        $html = $this->actingAsAdmin()->get(self::URI.'/'.$banner->id.'/editar')->getContent();

        $this->assertStringNotContainsString('name="sort_order"', $html);
    }

    // --- Proteção de mídia ------------------------------------------------

    public function test_media_used_by_a_banner_stays_protected_from_the_library(): void
    {
        Storage::fake(MediaService::DISK);

        $banner = $this->makeBanner();
        $media = Media::query()->findOrFail($banner->media_id);

        $this->actingAsAdmin()
            ->delete('/admin/midias/'.$media->id)
            ->assertRedirect('/admin/midias')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('media', ['id' => $media->id]);

        try {
            app(MediaService::class)->delete($media);
            $this->fail('A exclusão da mídia em uso deveria ser bloqueada.');
        } catch (MediaInUseException $exception) {
            $this->assertSame(['Banner'], $exception->usages);
        }
    }

    public function test_the_media_is_released_after_the_banner_is_deleted(): void
    {
        Storage::fake(MediaService::DISK);

        $banner = $this->makeBanner();
        $media = Media::query()->findOrFail($banner->media_id);

        $this->actingAsAdmin()->delete(self::URI.'/'.$banner->id)->assertRedirect(self::URI);

        $this->actingAsAdmin()
            ->delete('/admin/midias/'.$media->id)
            ->assertRedirect('/admin/midias')
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    // --- Helpers ----------------------------------------------------------

    private function service(): BannerService
    {
        return app(BannerService::class);
    }

    private function actingAsAdmin(): self
    {
        return $this->actingAs(User::factory()->create());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function attributes(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Campanha',
            'media_id' => Media::factory()->create()->id,
            'position' => BannerPosition::Hero,
            'alt_text' => 'Campanha de verão.',
        ], $overrides);
    }

    private function makeBanner(): Banner
    {
        return $this->service()->create($this->attributes());
    }

    /**
     * Payload equivalente ao que o formulário de edição envia.
     *
     * @return array<string, mixed>
     */
    private function formPayload(Banner $banner): array
    {
        return [
            'name' => $banner->name,
            'media_id' => $banner->media_id,
            'position' => $banner->position->value,
            'link_url' => $banner->link_url,
            'alt_text' => $banner->alt_text,
            'is_active' => $banner->is_active ? '1' : '0',
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submitCreation(array $overrides = []): TestResponse
    {
        return $this->actingAsAdmin()->post(self::URI, array_merge([
            'name' => 'Campanha',
            'media_id' => Media::factory()->create()->id,
            'position' => 'hero',
            'alt_text' => 'Campanha de verão.',
        ], $overrides));
    }

    /**
     * @return array{0: Banner, 1: Banner, 2: Banner}
     */
    private function threeHeroBanners(): array
    {
        $media = Media::factory()->create();

        return [
            $this->service()->create($this->attributes(['name' => 'Hero 1', 'media_id' => $media->id])),
            $this->service()->create($this->attributes(['name' => 'Hero 2', 'media_id' => $media->id])),
            $this->service()->create($this->attributes(['name' => 'Hero 3', 'media_id' => $media->id])),
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    private function assertOrderedNames(array $expected): void
    {
        $this->assertSame(
            $expected,
            $this->service()->orderedForPosition(BannerPosition::Hero)->pluck('name')->all(),
        );
    }

    /**
     * @return list<int>
     */
    private function orders(): array
    {
        return $this->service()->orderedForPosition(BannerPosition::Hero)->pluck('sort_order')->all();
    }
}
