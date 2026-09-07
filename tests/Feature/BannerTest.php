<?php

namespace Tests\Feature;

use App\Enums\BannerPosition;
use App\Models\Banner;
use App\Models\Media;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Domínio e persistência do banner — F2.5-A.
 *
 * Nada aqui usa HTTP, formulário administrativo ou upload: a fundação precisa
 * ser verificável sem nenhum dos três.
 */
class BannerTest extends TestCase
{
    use RefreshDatabase;

    // --- Schema -----------------------------------------------------------

    public function test_a_banner_can_be_persisted_with_the_supported_fields(): void
    {
        $media = Media::factory()->create();

        Banner::create([
            'name' => 'Campanha de verão',
            'media_id' => $media->id,
            'position' => BannerPosition::Hero,
            'link_url' => '/categorias/promocoes',
            'alt_text' => 'Modelos usando a coleção de verão.',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('banners', [
            'name' => 'Campanha de verão',
            'media_id' => $media->id,
            'position' => 'hero',
            'link_url' => '/categorias/promocoes',
            'alt_text' => 'Modelos usando a coleção de verão.',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_the_schema_has_no_column_beyond_the_contract(): void
    {
        $expected = [
            'id',
            'name',
            'media_id',
            'position',
            'link_url',
            'alt_text',
            'sort_order',
            'is_active',
            'created_at',
            'updated_at',
        ];

        $columns = Schema::getColumnListing('banners');

        sort($expected);
        sort($columns);

        $this->assertSame($expected, $columns);
    }

    public function test_the_banner_never_persists_the_media_path_or_url(): void
    {
        // A imagem é referência, nunca cópia: trocar o disco ou o domínio não
        // pode invalidar nenhum banner.
        foreach (['disk', 'path', 'url', 'mime_type', 'width', 'height'] as $column) {
            $this->assertFalse(
                Schema::hasColumn('banners', $column),
                "A tabela banners não deve duplicar a coluna [{$column}] da mídia."
            );
        }
    }

    public function test_the_identity_is_an_auto_incrementing_integer(): void
    {
        $first = Banner::factory()->create();
        $second = Banner::factory()->create();

        $this->assertSame('int', (new Banner)->getKeyType());
        $this->assertTrue((new Banner)->getIncrementing());
        $this->assertIsInt($first->id);
        $this->assertIsInt($second->id);
        $this->assertNotSame($first->id, $second->id);
    }

    public function test_banner_does_not_use_soft_deletes(): void
    {
        $this->assertFalse(Schema::hasColumn('banners', 'deleted_at'));
        $this->assertNotContains(SoftDeletes::class, class_uses_recursive(Banner::class));
    }

    public function test_the_composite_index_follows_the_contract(): void
    {
        $columns = array_map(
            fn (array $index): array => $index['columns'],
            Schema::getIndexes('banners'),
        );

        $this->assertContains(['position', 'is_active', 'sort_order'], $columns);
    }

    public function test_there_is_no_unique_constraint_on_position_and_sort_order(): void
    {
        // Reordenar passa por estados intermediários com empate; uma constraint
        // de unicidade obrigaria a inventar valores temporários.
        foreach (Schema::getIndexes('banners') as $index) {
            if ($index['columns'] === ['position', 'sort_order']) {
                $this->assertFalse($index['unique']);
            }
        }

        $media = Media::factory()->create();

        foreach ([1, 2] as $ignored) {
            Banner::create([
                'name' => 'Empate',
                'media_id' => $media->id,
                'position' => BannerPosition::Hero,
                'alt_text' => 'Empate de ordem.',
                'sort_order' => 1,
            ]);
        }

        $this->assertSame(2, Banner::query()->where('sort_order', 1)->count());
    }

    // --- Defaults e obrigatoriedade ---------------------------------------

    public function test_is_active_defaults_to_false_in_the_database(): void
    {
        $media = Media::factory()->create();

        // O INSERT omite a coluna de propósito: quem responde é o default do
        // schema, não um valor montado pelo model.
        DB::table('banners')->insert([
            'name' => 'Sem estado informado',
            'media_id' => $media->id,
            'position' => 'hero',
            'alt_text' => 'Banner recém-cadastrado.',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse(Banner::query()->firstOrFail()->is_active);
        $this->assertDatabaseHas('banners', ['name' => 'Sem estado informado', 'is_active' => false]);
    }

    public function test_sort_order_has_no_database_default(): void
    {
        // Um DEFAULT 0 faria qualquer insert fora do serviço cair no início da
        // lista em silêncio. Sem ele, o insert falha em vez de mentir.
        $media = Media::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('banners')->insert([
            'name' => 'Sem ordem',
            'media_id' => $media->id,
            'position' => 'hero',
            'alt_text' => 'Banner sem ordem.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_the_media_reference_is_required_by_the_database(): void
    {
        $this->expectException(QueryException::class);

        DB::table('banners')->insert([
            'name' => 'Sem imagem',
            'media_id' => null,
            'position' => 'hero',
            'alt_text' => 'Banner sem imagem.',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_the_alt_text_is_required_by_the_database(): void
    {
        $media = Media::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('banners')->insert([
            'name' => 'Sem texto alternativo',
            'media_id' => $media->id,
            'position' => 'hero',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // --- Integridade referencial ------------------------------------------

    public function test_a_media_referenced_by_a_banner_cannot_be_deleted(): void
    {
        // Barreira final do banco: fecha a corrida entre verificar o uso e
        // apagar o arquivo, que o MediaUsageRegistry sozinho não fecha.
        $banner = Banner::factory()->create();

        $this->expectException(QueryException::class);

        Media::query()->whereKey($banner->media_id)->delete();
    }

    public function test_a_media_without_banners_can_be_deleted(): void
    {
        $media = Media::factory()->create();

        Media::query()->whereKey($media->id)->delete();

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    public function test_the_foreign_key_points_to_the_media_table(): void
    {
        $references = array_map(
            fn (array $key): array => [$key['columns'], $key['foreign_table'], $key['foreign_columns']],
            Schema::getForeignKeys('banners'),
        );

        $this->assertContains([['media_id'], 'media', ['id']], $references);
    }

    // --- Enum e casts -----------------------------------------------------

    public function test_the_position_enum_has_exactly_the_contracted_cases(): void
    {
        $this->assertSame(
            ['hero', 'sidebar', 'footer'],
            array_map(fn (BannerPosition $case): string => $case->value, BannerPosition::cases()),
        );
    }

    public function test_the_position_is_read_back_as_an_enum(): void
    {
        $banner = Banner::factory()->create(['position' => BannerPosition::Sidebar]);

        $this->assertSame(BannerPosition::Sidebar, $banner->fresh()->position);
        $this->assertDatabaseHas('banners', ['id' => $banner->id, 'position' => 'sidebar']);
    }

    public function test_the_position_accepts_its_backing_value(): void
    {
        $banner = Banner::factory()->create(['position' => 'footer']);

        $this->assertSame(BannerPosition::Footer, $banner->fresh()->position);
    }

    public function test_the_numeric_and_boolean_columns_are_read_back_with_their_types(): void
    {
        $banner = Banner::factory()->create(['sort_order' => 7, 'is_active' => true]);

        $fresh = $banner->fresh();

        $this->assertSame(7, $fresh->sort_order);
        $this->assertIsInt($fresh->media_id);
        $this->assertTrue($fresh->is_active);
    }

    public function test_an_absent_link_is_persisted_as_null(): void
    {
        $banner = Banner::factory()->create(['link_url' => null]);

        $this->assertNull($banner->fresh()->link_url);
    }

    // --- Relacionamento ---------------------------------------------------

    public function test_a_banner_belongs_to_its_media(): void
    {
        $media = Media::factory()->create();
        $banner = Banner::factory()->create(['media_id' => $media->id]);

        $resolved = $banner->fresh()->media;

        $this->assertInstanceOf(Media::class, $resolved);
        $this->assertTrue($resolved->is($media));
        $this->assertSame('media_id', $banner->media()->getForeignKeyName());
        $this->assertSame('media', $resolved->getTable());
    }

    public function test_two_banners_can_share_the_same_media(): void
    {
        $media = Media::factory()->create();

        Banner::factory()->create(['media_id' => $media->id, 'position' => BannerPosition::Hero]);
        Banner::factory()->create(['media_id' => $media->id, 'position' => BannerPosition::Footer]);

        $this->assertSame(2, Banner::query()->where('media_id', $media->id)->count());
    }

    // --- Mass assignment --------------------------------------------------

    public function test_unsupported_fields_are_not_mass_assignable(): void
    {
        $media = Media::factory()->create();

        $banner = Banner::create([
            'id' => 999,
            'name' => 'Campanha',
            'media_id' => $media->id,
            'position' => BannerPosition::Hero,
            'alt_text' => 'Campanha de verão.',
            'sort_order' => 1,
            'starts_at' => now(),
            'target_blank' => true,
            'user_id' => 7,
        ]);

        $this->assertNotSame(999, $banner->id);
        $this->assertArrayNotHasKey('starts_at', $banner->getAttributes());
        $this->assertArrayNotHasKey('target_blank', $banner->getAttributes());
        $this->assertArrayNotHasKey('user_id', $banner->getAttributes());
    }
}
