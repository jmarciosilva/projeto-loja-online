<?php

namespace Tests\Feature;

use App\Enums\BannerPosition;
use App\Exceptions\MediaInUseException;
use App\Models\Banner;
use App\Models\Media;
use App\Services\BannerService;
use App\Services\MediaService;
use App\Services\MediaUsageRegistry;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Invariantes de domínio do `BannerService` — F2.5-A.
 *
 * O serviço é a camada autoritativa: tudo aqui é exercitado por chamada direta,
 * sem HTTP, porque a interface administrativa da F2.5-B não pode ser a única
 * barreira das regras.
 */
class BannerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_be_resolved_by_the_laravel_container(): void
    {
        $this->assertInstanceOf(BannerService::class, app(BannerService::class));
    }

    // --- Criação ----------------------------------------------------------

    public function test_it_creates_a_banner_with_the_supported_fields(): void
    {
        $media = Media::factory()->create();

        $banner = $this->service()->create([
            'name' => 'Campanha de verão',
            'media_id' => $media->id,
            'position' => BannerPosition::Hero,
            'link_url' => '/categorias/promocoes',
            'alt_text' => 'Modelos usando a coleção de verão.',
            'is_active' => true,
        ]);

        $this->assertTrue($banner->exists);
        $this->assertDatabaseHas('banners', [
            'id' => $banner->id,
            'name' => 'Campanha de verão',
            'media_id' => $media->id,
            'position' => 'hero',
            'link_url' => '/categorias/promocoes',
            'alt_text' => 'Modelos usando a coleção de verão.',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_the_first_banner_of_a_position_receives_sort_order_one(): void
    {
        $banner = $this->create(BannerPosition::Hero);

        $this->assertSame(1, $banner->fresh()->sort_order);
    }

    public function test_the_second_banner_of_a_position_receives_sort_order_two(): void
    {
        $this->create(BannerPosition::Hero);
        $second = $this->create(BannerPosition::Hero);

        $this->assertSame(2, $second->fresh()->sort_order);
    }

    public function test_each_position_starts_its_own_sequence(): void
    {
        $firstHero = $this->create(BannerPosition::Hero);
        $secondHero = $this->create(BannerPosition::Hero);
        $firstFooter = $this->create(BannerPosition::Footer);
        $firstSidebar = $this->create(BannerPosition::Sidebar);

        // Não existe ordem global: comparar o sort_order de um hero com o de um
        // footer não significa nada.
        $this->assertSame(1, $firstHero->fresh()->sort_order);
        $this->assertSame(2, $secondHero->fresh()->sort_order);
        $this->assertSame(1, $firstFooter->fresh()->sort_order);
        $this->assertSame(1, $firstSidebar->fresh()->sort_order);
    }

    public function test_a_sort_order_sent_by_the_caller_is_ignored(): void
    {
        $this->create(BannerPosition::Hero);

        $banner = $this->create(BannerPosition::Hero, ['sort_order' => 99]);

        $this->assertSame(2, $banner->fresh()->sort_order);
    }

    public function test_a_new_banner_is_inactive_by_default(): void
    {
        $banner = $this->create(BannerPosition::Hero);

        $this->assertFalse($banner->fresh()->is_active);
    }

    public function test_a_banner_can_be_created_explicitly_active(): void
    {
        $banner = $this->create(BannerPosition::Hero, ['is_active' => true]);

        $this->assertTrue($banner->fresh()->is_active);
    }

    public function test_a_banner_can_be_created_explicitly_inactive(): void
    {
        $banner = $this->create(BannerPosition::Hero, ['is_active' => false]);

        $this->assertFalse($banner->fresh()->is_active);
    }

    public function test_a_non_boolean_state_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->create(BannerPosition::Hero, ['is_active' => 'on']);
    }

    public function test_the_name_is_required(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->create(BannerPosition::Hero, ['name' => '   ']);
    }

    public function test_the_name_is_trimmed(): void
    {
        $banner = $this->create(BannerPosition::Hero, ['name' => '  Campanha  ']);

        $this->assertSame('Campanha', $banner->fresh()->name);
    }

    public function test_a_name_longer_than_the_column_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->create(BannerPosition::Hero, ['name' => str_repeat('a', 121)]);
    }

    public function test_the_alt_text_is_required(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->create(BannerPosition::Hero, ['alt_text' => '   ']);
    }

    public function test_the_alt_text_is_never_derived_from_the_media_original_name(): void
    {
        $media = Media::factory()->create(['original_name' => 'banner-final-v2.jpg']);

        try {
            $this->service()->create([
                'name' => 'Campanha',
                'media_id' => $media->id,
                'position' => BannerPosition::Hero,
            ]);

            $this->fail('Um banner sem alt_text deveria ser recusado.');
        } catch (InvalidArgumentException) {
            // O texto alternativo é propriedade do uso da imagem, e o
            // original_name é metadado administrativo de quem fez o upload.
            $this->assertDatabaseCount('banners', 0);
        }
    }

    public function test_an_alt_text_longer_than_the_column_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->create(BannerPosition::Hero, ['alt_text' => str_repeat('a', 256)]);
    }

    public function test_the_position_accepts_its_backing_value(): void
    {
        $banner = $this->create('sidebar');

        $this->assertSame(BannerPosition::Sidebar, $banner->fresh()->position);
    }

    public function test_an_unsupported_position_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->create('topo');
    }

    // --- Mídia ------------------------------------------------------------

    public function test_an_existing_media_is_accepted(): void
    {
        $media = Media::factory()->create();

        $banner = $this->create(BannerPosition::Hero, ['media_id' => $media->id]);

        $this->assertSame($media->id, $banner->fresh()->media_id);
    }

    public function test_a_missing_media_is_rejected_as_a_domain_error(): void
    {
        // Erro de domínio, não QueryException: a FK é a barreira final, mas o
        // chamador precisa de uma violação compreensível fora do HTTP.
        $this->expectException(InvalidArgumentException::class);

        $this->service()->create([
            'name' => 'Campanha',
            'media_id' => 4_242,
            'position' => BannerPosition::Hero,
            'alt_text' => 'Campanha de verão.',
        ]);

        $this->assertDatabaseCount('banners', 0);
    }

    public function test_a_banner_requires_a_media_reference(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->create([
            'name' => 'Campanha',
            'position' => BannerPosition::Hero,
            'alt_text' => 'Campanha de verão.',
        ]);
    }

    #[DataProvider('supportedMediaTypeProvider')]
    public function test_every_media_type_of_the_library_is_accepted(string $mimeType): void
    {
        // O banner não herda a restrição PNG do favicon da F2.3-C: aquela é
        // específica daquele consumidor.
        $media = Media::factory()->create(['mime_type' => $mimeType]);

        $banner = $this->create(BannerPosition::Hero, ['media_id' => $media->id]);

        $this->assertSame($media->id, $banner->fresh()->media_id);
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

    // --- Link -------------------------------------------------------------

    public function test_a_null_link_stays_null(): void
    {
        $this->assertNull($this->create(BannerPosition::Hero, ['link_url' => null])->fresh()->link_url);
    }

    public function test_an_absent_link_stays_null(): void
    {
        $media = Media::factory()->create();

        $banner = $this->service()->create([
            'name' => 'Campanha',
            'media_id' => $media->id,
            'position' => BannerPosition::Hero,
            'alt_text' => 'Campanha de verão.',
        ]);

        $this->assertNull($banner->fresh()->link_url);
    }

    public function test_an_empty_link_becomes_null(): void
    {
        $this->assertNull($this->create(BannerPosition::Hero, ['link_url' => ''])->fresh()->link_url);
    }

    public function test_a_whitespace_only_link_becomes_null(): void
    {
        $this->assertNull($this->create(BannerPosition::Hero, ['link_url' => '   '])->fresh()->link_url);
    }

    public function test_a_link_is_trimmed(): void
    {
        $banner = $this->create(BannerPosition::Hero, ['link_url' => '  /produtos  ']);

        $this->assertSame('/produtos', $banner->fresh()->link_url);
    }

    #[DataProvider('validLinkProvider')]
    public function test_it_accepts_a_link_inside_the_contract(string $link): void
    {
        $banner = $this->create(BannerPosition::Hero, ['link_url' => $link]);

        $this->assertSame($link, $banner->fresh()->link_url);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function validLinkProvider(): array
    {
        return [
            'raiz' => ['/'],
            'caminho interno' => ['/produtos'],
            'caminho aninhado' => ['/categorias/promocoes'],
            'pagina estatica' => ['/paginas/quem-somos'],
            'https com caminho' => ['https://example.com/campanha'],
            'http com caminho' => ['http://example.com/promocao'],
            'https sem caminho' => ['https://example.com'],
        ];
    }

    #[DataProvider('invalidLinkProvider')]
    public function test_it_rejects_a_link_outside_the_contract(string $link): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->create(BannerPosition::Hero, ['link_url' => $link]);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function invalidLinkProvider(): array
    {
        return [
            'protocol relative' => ['//evil.example'],
            'barra invertida' => ['\\evil.example'],
            'barra invertida apos barra' => ['/\\evil.example'],
            'sem esquema e sem barra' => ['produtos'],
            'host sem esquema' => ['www.exemplo.com'],
            'javascript' => ['javascript:alert(1)'],
            'javascript maiusculo' => ['JavaScript:alert(1)'],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'vbscript' => ['vbscript:msgbox(1)'],
            'file' => ['file:///etc/passwd'],
            'ftp' => ['ftp://example.com/arquivo'],
            'mailto' => ['mailto:contato@example.com'],
            'http sem host' => ['http://'],
            'https sem host' => ['https://'],
        ];
    }

    public function test_a_link_longer_than_the_column_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->create(BannerPosition::Hero, ['link_url' => '/'.str_repeat('a', 2_048)]);
    }

    public function test_the_normalization_never_invents_a_scheme(): void
    {
        try {
            $this->create(BannerPosition::Hero, ['link_url' => 'www.exemplo.com']);

            $this->fail('Um valor sem esquema e sem barra inicial deveria ser recusado.');
        } catch (InvalidArgumentException) {
            // Adivinhar `https://` seria escolher por quem digitou.
            $this->assertDatabaseCount('banners', 0);
        }
    }

    // --- Atualização ------------------------------------------------------

    public function test_updating_the_name_preserves_the_order(): void
    {
        $this->assertOrderIsPreservedWhenUpdating(['name' => 'Outro nome']);
    }

    public function test_updating_the_media_preserves_the_order(): void
    {
        $other = Media::factory()->create();

        $this->assertOrderIsPreservedWhenUpdating(['media_id' => $other->id]);
    }

    public function test_updating_the_link_preserves_the_order(): void
    {
        $this->assertOrderIsPreservedWhenUpdating(['link_url' => '/novo-destino']);
    }

    public function test_updating_the_alt_text_preserves_the_order(): void
    {
        $this->assertOrderIsPreservedWhenUpdating(['alt_text' => 'Outra descrição.']);
    }

    public function test_updating_the_state_preserves_the_order(): void
    {
        $this->assertOrderIsPreservedWhenUpdating(['is_active' => true]);
    }

    public function test_resubmitting_the_same_position_preserves_the_order(): void
    {
        $this->assertOrderIsPreservedWhenUpdating(['position' => BannerPosition::Hero]);
    }

    public function test_the_updated_values_are_persisted(): void
    {
        $other = Media::factory()->create();
        $banner = $this->create(BannerPosition::Hero);

        $this->service()->update($banner, [
            'name' => 'Campanha de inverno',
            'media_id' => $other->id,
            'link_url' => '  /categorias/inverno  ',
            'alt_text' => 'Casacos da coleção de inverno.',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('banners', [
            'id' => $banner->id,
            'name' => 'Campanha de inverno',
            'media_id' => $other->id,
            'link_url' => '/categorias/inverno',
            'alt_text' => 'Casacos da coleção de inverno.',
            'is_active' => true,
        ]);
    }

    public function test_changing_the_position_moves_the_banner_to_the_end_of_the_new_one(): void
    {
        $this->create(BannerPosition::Hero);
        $moved = $this->create(BannerPosition::Hero);
        $this->create(BannerPosition::Hero);
        $this->create(BannerPosition::Footer);
        $this->create(BannerPosition::Footer);

        $this->service()->update($moved, ['position' => BannerPosition::Footer]);

        // A ordem é contextual: o número 2 descrevia o lugar dele entre os
        // hero, e no footer não descreveria nada — além de empatar com o
        // segundo footer já existente.
        $this->assertSame(BannerPosition::Footer, $moved->fresh()->position);
        $this->assertSame(3, $moved->fresh()->sort_order);
    }

    public function test_changing_to_an_empty_position_receives_sort_order_one(): void
    {
        $this->create(BannerPosition::Hero);
        $moved = $this->create(BannerPosition::Hero);

        $this->service()->update($moved, ['position' => BannerPosition::Sidebar]);

        $this->assertSame(BannerPosition::Sidebar, $moved->fresh()->position);
        $this->assertSame(1, $moved->fresh()->sort_order);
    }

    public function test_changing_the_position_does_not_touch_the_origin(): void
    {
        // A F2.5-A não compacta a posição de origem: a ordenação depende da
        // relação `sort_order ASC, id ASC`, não de uma sequência contínua.
        $first = $this->create(BannerPosition::Hero);
        $moved = $this->create(BannerPosition::Hero);
        $third = $this->create(BannerPosition::Hero);

        $this->service()->update($moved, ['position' => BannerPosition::Footer]);

        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(3, $third->fresh()->sort_order);
        $this->assertSame(
            [$first->id, $third->id],
            $this->service()->orderedForPosition(BannerPosition::Hero)->pluck('id')->all(),
        );
    }

    public function test_the_position_change_is_decided_by_the_persisted_position(): void
    {
        // Reproduz, sem concorrência, o estado em que uma tentativa anterior da
        // transação deixaria o objeto: o banco ainda diz `hero`, mas o model em
        // memória já foi mudado para o destino. Se a decisão saísse do atributo
        // atual, o serviço concluiria "não mudou de posição" e regravaria a
        // ordem velha — que é exatamente como uma repetição da transação
        // produziria `sort_order` duplicado.
        $banner = $this->create(BannerPosition::Hero);
        $this->create(BannerPosition::Sidebar);
        $this->create(BannerPosition::Sidebar);

        $banner->position = BannerPosition::Sidebar;

        $this->service()->update($banner, ['position' => BannerPosition::Sidebar]);

        $this->assertSame(BannerPosition::Sidebar, $banner->fresh()->position);
        $this->assertSame(3, $banner->fresh()->sort_order);
    }

    public function test_a_failed_update_leaves_the_banner_untouched(): void
    {
        // Rollback integral: nada do payload sobrevive quando uma invariante
        // posterior recusa a operação.
        $banner = $this->create(BannerPosition::Hero, ['name' => 'Original', 'link_url' => '/produtos']);

        try {
            $this->service()->update($banner, ['name' => 'Novo nome', 'link_url' => 'javascript:alert(1)']);

            $this->fail('A atualização deveria ter sido recusada.');
        } catch (InvalidArgumentException) {
            $fresh = $banner->fresh();

            $this->assertSame('Original', $fresh->name);
            $this->assertSame('/produtos', $fresh->link_url);
            $this->assertSame(1, $fresh->sort_order);
        }
    }

    public function test_a_failed_creation_persists_nothing(): void
    {
        $media = Media::factory()->create();

        try {
            $this->service()->create([
                'name' => 'Campanha',
                'media_id' => $media->id,
                'position' => BannerPosition::Hero,
                'alt_text' => 'Campanha.',
                'link_url' => '//evil.example',
            ]);

            $this->fail('A criação deveria ter sido recusada.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('banners', 0);
        }
    }

    public function test_a_domain_violation_is_not_retried_as_a_concurrency_error(): void
    {
        // O retry da transação cobre apenas os erros de concorrência que o
        // Laravel reconhece. Uma violação de contrato precisa falhar na
        // primeira tentativa: repeti-la três vezes só gastaria trabalho e
        // atrasaria a resposta ao chamador.
        $media = Media::factory()->create();
        $begins = 0;

        Event::listen(TransactionBeginning::class, function () use (&$begins): void {
            $begins++;
        });

        try {
            $this->service()->create([
                'name' => 'Campanha',
                'media_id' => $media->id,
                'position' => BannerPosition::Hero,
                'alt_text' => '',
            ]);

            $this->fail('A criação deveria ter sido recusada.');
        } catch (InvalidArgumentException) {
            $this->assertSame(1, $begins);
        }
    }

    public function test_an_update_validates_the_link_as_strictly_as_the_creation(): void
    {
        $banner = $this->create(BannerPosition::Hero, ['link_url' => '/produtos']);

        try {
            $this->service()->update($banner, ['link_url' => 'javascript:alert(1)']);

            $this->fail('Um esquema inseguro deveria ser recusado também na atualização.');
        } catch (InvalidArgumentException) {
            $this->assertSame('/produtos', $banner->fresh()->link_url);
        }
    }

    public function test_an_update_validates_the_media_reference(): void
    {
        $banner = $this->create(BannerPosition::Hero);
        $originalMediaId = $banner->media_id;

        try {
            $this->service()->update($banner, ['media_id' => 4_242]);

            $this->fail('Uma mídia inexistente deveria ser recusada na atualização.');
        } catch (InvalidArgumentException) {
            $this->assertSame($originalMediaId, $banner->fresh()->media_id);
        }
    }

    public function test_an_update_ignores_a_sort_order_sent_by_the_caller(): void
    {
        $this->create(BannerPosition::Hero);
        $banner = $this->create(BannerPosition::Hero);

        $this->service()->update($banner, ['sort_order' => 99]);

        $this->assertSame(2, $banner->fresh()->sort_order);
    }

    // --- Ordenação --------------------------------------------------------

    public function test_the_ordered_query_follows_sort_order_and_then_id(): void
    {
        $media = Media::factory()->create();

        $third = Banner::factory()->create(['media_id' => $media->id, 'position' => BannerPosition::Hero, 'sort_order' => 3]);
        $firstTie = Banner::factory()->create(['media_id' => $media->id, 'position' => BannerPosition::Hero, 'sort_order' => 1]);
        $secondTie = Banner::factory()->create(['media_id' => $media->id, 'position' => BannerPosition::Hero, 'sort_order' => 1]);

        $this->assertSame(
            [$firstTie->id, $secondTie->id, $third->id],
            $this->service()->orderedForPosition(BannerPosition::Hero)->pluck('id')->all(),
        );
    }

    public function test_the_ordered_query_asks_the_database_for_the_contracted_order(): void
    {
        // O desempate por `id` raramente é observável pelo resultado — no
        // InnoDB a chave primária já compõe as entradas do índice secundário, e
        // um empate tende a sair em ordem de `id` mesmo sem pedir. Por isso a
        // verificação é sobre o que chega ao banco: o contrato é a cláusula,
        // não a coincidência de um plano de execução.
        $statements = [];
        DB::listen(function (QueryExecuted $query) use (&$statements): void {
            $statements[] = $query->sql;
        });

        $this->service()->orderedForPosition(BannerPosition::Hero);

        $this->assertNotEmpty($statements);
        $this->assertMatchesRegularExpression(
            '/order by .?sort_order.? asc, .?id.? asc/i',
            (string) end($statements),
        );
    }

    public function test_the_tie_break_by_id_is_deterministic_regardless_of_insertion_order(): void
    {
        $media = Media::factory()->create();

        // Os dois empatam em sort_order; sem o desempate por id, a ordem
        // dependeria do plano escolhido pelo banco.
        $older = Banner::factory()->create(['media_id' => $media->id, 'position' => BannerPosition::Footer, 'sort_order' => 5]);
        $newer = Banner::factory()->create(['media_id' => $media->id, 'position' => BannerPosition::Footer, 'sort_order' => 5]);
        $newer->update(['name' => 'Editado depois']);

        $this->assertSame(
            [$older->id, $newer->id],
            $this->service()->orderedForPosition(BannerPosition::Footer)->pluck('id')->all(),
        );
    }

    public function test_the_ordered_query_is_scoped_to_the_requested_position(): void
    {
        $hero = $this->create(BannerPosition::Hero);
        $this->create(BannerPosition::Footer);
        $this->create(BannerPosition::Sidebar);

        $this->assertSame(
            [$hero->id],
            $this->service()->orderedForPosition(BannerPosition::Hero)->pluck('id')->all(),
        );
    }

    public function test_the_ordered_query_of_the_current_subphase_still_includes_inactive_banners(): void
    {
        // A consulta pública, que filtra `is_active`, pertence à F2.5-C.
        $inactive = $this->create(BannerPosition::Hero);
        $active = $this->create(BannerPosition::Hero, ['is_active' => true]);

        $this->assertSame(
            [$inactive->id, $active->id],
            $this->service()->orderedForPosition(BannerPosition::Hero)->pluck('id')->all(),
        );
    }

    public function test_an_empty_position_returns_an_empty_collection(): void
    {
        $this->create(BannerPosition::Hero);

        $this->assertTrue($this->service()->orderedForPosition(BannerPosition::Sidebar)->isEmpty());
    }

    // --- Exclusão ---------------------------------------------------------

    public function test_deleting_a_banner_removes_only_the_banner(): void
    {
        $banner = $this->create(BannerPosition::Hero);
        $mediaId = $banner->media_id;

        $this->service()->delete($banner);

        $this->assertDatabaseMissing('banners', ['id' => $banner->id]);
        $this->assertDatabaseHas('media', ['id' => $mediaId]);
    }

    public function test_deleting_a_banner_is_physical(): void
    {
        $banner = $this->create(BannerPosition::Hero);

        $this->service()->delete($banner);

        $this->assertDatabaseCount('banners', 0);
        $this->assertNull(Banner::query()->find($banner->id));
    }

    // --- MediaUsageRegistry -----------------------------------------------

    public function test_a_media_used_by_a_banner_is_reported_as_in_use(): void
    {
        $banner = $this->create(BannerPosition::Hero);

        $media = Media::query()->findOrFail($banner->media_id);

        $this->assertSame(['Banner'], app(MediaUsageRegistry::class)->usages($media));
        $this->assertTrue(app(MediaUsageRegistry::class)->isInUse($media));
    }

    public function test_several_banners_on_the_same_media_report_a_single_usage(): void
    {
        $media = Media::factory()->create();

        $this->create(BannerPosition::Hero, ['media_id' => $media->id]);
        $this->create(BannerPosition::Sidebar, ['media_id' => $media->id]);
        $this->create(BannerPosition::Footer, ['media_id' => $media->id]);

        // Um único checker cobre todos os banners: o rótulo identifica o tipo
        // de consumidor, não onde ele aparece.
        $this->assertSame(['Banner'], app(MediaUsageRegistry::class)->usages($media->fresh()));
    }

    public function test_the_registry_has_no_label_per_position(): void
    {
        $this->create(BannerPosition::Sidebar);

        $media = Media::query()->firstOrFail();

        foreach (['Banner hero', 'Banner sidebar', 'Banner footer'] as $label) {
            $this->assertNotContains($label, app(MediaUsageRegistry::class)->usages($media));
        }
    }

    public function test_a_media_without_banners_is_not_reported(): void
    {
        $unused = Media::factory()->create();
        $this->create(BannerPosition::Hero);

        $this->assertSame([], app(MediaUsageRegistry::class)->usages($unused->fresh()));
        $this->assertFalse(app(MediaUsageRegistry::class)->isInUse($unused->fresh()));
    }

    public function test_the_media_library_refuses_to_delete_a_media_used_by_a_banner(): void
    {
        Storage::fake(MediaService::DISK);

        $banner = $this->create(BannerPosition::Hero);
        $media = Media::query()->findOrFail($banner->media_id);

        try {
            app(MediaService::class)->delete($media);

            $this->fail('A exclusão de uma mídia em uso por banner deveria ser bloqueada.');
        } catch (MediaInUseException $exception) {
            $this->assertSame(['Banner'], $exception->usages);
            $this->assertDatabaseHas('media', ['id' => $media->id]);
        }
    }

    public function test_the_media_becomes_removable_again_when_the_last_banner_is_deleted(): void
    {
        Storage::fake(MediaService::DISK);

        $media = Media::factory()->create();
        $first = $this->create(BannerPosition::Hero, ['media_id' => $media->id]);
        $second = $this->create(BannerPosition::Footer, ['media_id' => $media->id]);

        $this->service()->delete($first);
        $this->assertSame(['Banner'], app(MediaUsageRegistry::class)->usages($media->fresh()));

        $this->service()->delete($second);
        $this->assertSame([], app(MediaUsageRegistry::class)->usages($media->fresh()));

        app(MediaService::class)->delete($media->fresh());

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    // --- Helpers ----------------------------------------------------------

    private function service(): BannerService
    {
        return app(BannerService::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function create(BannerPosition|string $position, array $overrides = []): Banner
    {
        return $this->service()->create(array_merge([
            'name' => 'Campanha',
            'media_id' => Media::factory()->create()->id,
            'position' => $position,
            'alt_text' => 'Campanha de verão.',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertOrderIsPreservedWhenUpdating(array $attributes): void
    {
        $this->create(BannerPosition::Hero);
        $banner = $this->create(BannerPosition::Hero);
        $this->create(BannerPosition::Hero);

        $this->service()->update($banner, $attributes);

        $this->assertSame(2, $banner->fresh()->sort_order);
        $this->assertSame(BannerPosition::Hero, $banner->fresh()->position);
    }
}
