<?php

namespace Tests\Feature;

use App\Models\Media;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Domínio e persistência da mídia — F2.7-A.
 *
 * Nada aqui usa HTTP, upload ou Intervention Image: a fundação precisa ser
 * verificável sem nenhum dos dois.
 */
class MediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_media_record_can_be_persisted_with_the_supported_fields(): void
    {
        Media::create([
            'disk' => 'public',
            'path' => 'media/2026/09/01K4T7WQZ8N3B6MJX5F2VHRA9DC.jpg',
            'original_name' => 'Minha Logo Final (2).PNG',
            'mime_type' => 'image/jpeg',
            'size' => 204_800,
            'width' => 1_600,
            'height' => 900,
        ]);

        $this->assertDatabaseHas('media', [
            'disk' => 'public',
            'path' => 'media/2026/09/01K4T7WQZ8N3B6MJX5F2VHRA9DC.jpg',
            'original_name' => 'Minha Logo Final (2).PNG',
            'mime_type' => 'image/jpeg',
            'size' => 204_800,
            'width' => 1_600,
            'height' => 900,
        ]);
    }

    public function test_the_table_is_media_and_not_medias(): void
    {
        $this->assertSame('media', (new Media)->getTable());
        $this->assertTrue(Schema::hasTable('media'));
        $this->assertFalse(Schema::hasTable('medias'));
    }

    public function test_the_identity_is_an_auto_incrementing_integer(): void
    {
        $first = Media::factory()->create();
        $second = Media::factory()->create();

        $this->assertSame('int', (new Media)->getKeyType());
        $this->assertTrue((new Media)->getIncrementing());

        $this->assertIsInt($first->id);
        $this->assertIsInt($second->id);
        $this->assertNotNull($first->fresh());
        $this->assertNotNull($second->fresh());

        // Registros distintos recebem identidades distintas. A sequência **não**
        // é verificada: `AUTO_INCREMENT` pode legitimamente abrir lacunas depois
        // de rollback, insert abortado ou concorrência, e o contrato exige
        // identidade estável, não numeração contígua.
        $this->assertNotSame($first->id, $second->id);
    }

    public function test_the_identity_is_stable_when_the_path_changes(): void
    {
        $media = Media::factory()->create([
            'path' => 'media/2026/09/01AAAAAAAAAAAAAAAAAAAAAAAA.jpg',
        ]);
        $id = $media->id;

        $media->update(['path' => 'media/2026/10/01BBBBBBBBBBBBBBBBBBBBBBBB.png']);

        $this->assertSame($id, $media->fresh()->id);
        $this->assertSame('media/2026/10/01BBBBBBBBBBBBBBBBBBBBBBBB.png', $media->fresh()->path);
    }

    public function test_the_disk_and_path_are_preserved_exactly_as_written(): void
    {
        $media = Media::factory()->create([
            'disk' => 'public',
            'path' => 'media/2026/09/01K4T7WQZ8N3B6MJX5F2VHRA9DC.webp',
        ]);

        $fresh = $media->fresh();

        $this->assertSame('public', $fresh->disk);
        $this->assertSame('media/2026/09/01K4T7WQZ8N3B6MJX5F2VHRA9DC.webp', $fresh->path);
    }

    public function test_the_size_and_dimensions_are_read_back_as_integers(): void
    {
        $media = Media::factory()->create([
            'size' => 51_200,
            'width' => 800,
            'height' => 600,
        ]);

        $fresh = $media->fresh();

        $this->assertSame(51_200, $fresh->size);
        $this->assertSame(800, $fresh->width);
        $this->assertSame(600, $fresh->height);
    }

    public function test_media_does_not_use_soft_deletes(): void
    {
        $this->assertFalse(Schema::hasColumn('media', 'deleted_at'));
        $this->assertArrayNotHasKey('deleted_at', Media::factory()->create()->getAttributes());
        $this->assertNotContains(
            SoftDeletes::class,
            class_uses_recursive(Media::class),
        );
    }

    public function test_the_schema_has_no_column_beyond_the_contract(): void
    {
        $expected = [
            'id',
            'disk',
            'path',
            'original_name',
            'mime_type',
            'size',
            'width',
            'height',
            'created_at',
            'updated_at',
        ];

        $columns = Schema::getColumnListing('media');

        sort($expected);
        sort($columns);

        $this->assertSame($expected, $columns);
    }

    public function test_the_width_is_required_by_the_database(): void
    {
        $this->expectException(QueryException::class);

        Media::create([
            'disk' => 'public',
            'path' => 'media/2026/09/01CCCCCCCCCCCCCCCCCCCCCCCC.jpg',
            'original_name' => 'sem-largura.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1_024,
            'height' => 600,
        ]);
    }

    public function test_the_height_is_required_by_the_database(): void
    {
        $this->expectException(QueryException::class);

        Media::create([
            'disk' => 'public',
            'path' => 'media/2026/09/01DDDDDDDDDDDDDDDDDDDDDDDD.jpg',
            'original_name' => 'sem-altura.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1_024,
            'width' => 800,
        ]);
    }

    public function test_the_same_disk_and_path_cannot_be_persisted_twice(): void
    {
        Media::factory()->create([
            'disk' => 'public',
            'path' => 'media/2026/09/01EEEEEEEEEEEEEEEEEEEEEEEE.jpg',
        ]);

        $this->expectException(QueryException::class);

        Media::factory()->create([
            'disk' => 'public',
            'path' => 'media/2026/09/01EEEEEEEEEEEEEEEEEEEEEEEE.jpg',
        ]);
    }

    public function test_the_same_path_is_allowed_on_a_different_disk(): void
    {
        // A chave é composta de propósito: o mesmo caminho em backends
        // distintos identifica arquivos distintos.
        Media::factory()->create([
            'disk' => 'public',
            'path' => 'media/2026/09/01FFFFFFFFFFFFFFFFFFFFFFFF.jpg',
        ]);

        Media::factory()->create([
            'disk' => 'arquivo',
            'path' => 'media/2026/09/01FFFFFFFFFFFFFFFFFFFFFFFF.jpg',
        ]);

        $this->assertSame(2, Media::query()->count());
    }

    public function test_unsupported_fields_are_not_mass_assignable(): void
    {
        $media = Media::create([
            'id' => 999,
            'disk' => 'public',
            'path' => 'media/2026/09/01GGGGGGGGGGGGGGGGGGGGGGGG.jpg',
            'original_name' => 'logo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1_024,
            'width' => 100,
            'height' => 100,
            'url' => 'https://exemplo.test/storage/logo.jpg',
            'extension' => 'jpg',
            'metadata' => '{"exif":true}',
            'user_id' => 7,
            'deleted_at' => now(),
        ]);

        // O `fill()` precisa ter descartado as chaves: se alguma tivesse
        // passado, o INSERT teria falhado por coluna inexistente — e o assert
        // abaixo garante que nem sequer viraram atributo do model.
        $this->assertNotSame(999, $media->id);

        foreach (['url', 'extension', 'metadata', 'user_id', 'deleted_at'] as $field) {
            $this->assertArrayNotHasKey($field, $media->getAttributes());
        }

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'path' => 'media/2026/09/01GGGGGGGGGGGGGGGGGGGGGGGG.jpg',
        ]);
    }
}
