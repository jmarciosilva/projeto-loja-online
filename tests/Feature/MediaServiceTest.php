<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Núcleo do `MediaService` — F2.7-A.
 *
 * Política de disco, geração de path, nome físico opaco, URL derivada e
 * consulta paginada. Nenhum upload, nenhum arquivo gravado e nenhuma chamada
 * ao Intervention Image: isso pertence à F2.7-B.
 */
class MediaServiceTest extends TestCase
{
    use RefreshDatabase;

    private MediaService $media;

    protected function setUp(): void
    {
        parent::setUp();

        // Resolvido pelo container: a partir da F2.7-B o serviço depende de
        // `ImageCapabilities`, e é essa injeção que permite aos testes trocar a
        // capacidade de WebP do runtime.
        $this->media = app(MediaService::class);
    }

    public function test_the_media_disk_is_explicit_and_not_the_application_default(): void
    {
        $this->assertSame('public', MediaService::DISK);

        // O padrão da aplicação é `local`, que no Laravel 12 aponta para
        // storage/app/private. Herdá-lo publicaria a biblioteca num diretório
        // privado — por isso o disco é nomeado, nunca herdado.
        $this->assertSame('local', config('filesystems.default'));
        $this->assertNotSame(config('filesystems.default'), MediaService::DISK);
        $this->assertSame(
            storage_path('app/public'),
            config('filesystems.disks.'.MediaService::DISK.'.root'),
        );
    }

    public function test_the_generated_path_follows_the_year_and_month_contract(): void
    {
        $this->travelTo('2026-09-06 10:30:00');

        $path = $this->media->generatePath('jpg');

        $this->assertMatchesRegularExpression(
            '#^media/2026/09/[0-9A-HJKMNP-TV-Z]{26}\.jpg$#',
            $path,
        );

        $this->travelBack();
    }

    public function test_the_generated_path_follows_the_frozen_clock(): void
    {
        $this->travelTo('2027-01-31 23:59:59');

        $this->assertStringStartsWith('media/2027/01/', $this->media->generatePath('png'));

        $this->travelBack();
    }

    public function test_the_generated_file_name_is_opaque(): void
    {
        $path = $this->media->generatePath('webp');
        $fileName = basename($path, '.webp');

        $this->assertSame(26, strlen($fileName));
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $fileName);
        $this->assertStringNotContainsString('/', $fileName);
        $this->assertStringNotContainsString('\\', $fileName);
        $this->assertStringNotContainsString('..', $fileName);
        $this->assertStringNotContainsString('.', $fileName);
    }

    public function test_two_generated_paths_never_collide(): void
    {
        $paths = [];

        for ($i = 0; $i < 50; $i++) {
            $paths[] = $this->media->generatePath('jpg');
        }

        $this->assertCount(50, array_unique($paths));
    }

    public function test_the_path_generator_cannot_receive_an_original_name(): void
    {
        // A garantia mais forte é estrutural: `generatePath()` só aceita a
        // extensão, então não existe parâmetro por onde o nome enviado passe.
        $parameters = (new ReflectionMethod(MediaService::class, 'generatePath'))->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertSame('extension', $parameters[0]->getName());
        $this->assertSame('string', (string) $parameters[0]->getType());
    }

    public function test_the_generated_path_cannot_be_influenced_by_an_original_name(): void
    {
        $hostileName = '../../Meu Produto Final (2) ###.PNG';

        $media = Media::factory()->create([
            'original_name' => $hostileName,
            'path' => $this->media->generatePath('png'),
        ]);

        // A expressão é exaustiva: ela descreve o caminho inteiro, então nada
        // vindo do nome enviado caberia nele. É esta asserção — e não uma lista
        // de substrings — que prova a independência.
        $this->assertMatchesRegularExpression(
            '#^media/\d{4}/\d{2}/[0-9A-HJKMNP-TV-Z]{26}\.png$#',
            $media->path,
        );

        $fileName = basename($media->path, '.png');

        $this->assertStringNotContainsString('/', $fileName);
        $this->assertStringNotContainsString('\\', $fileName);
        $this->assertStringNotContainsString('..', $media->path);

        // Reforço com os fragmentos realmente específicos do nome hostil. Todos
        // contêm minúsculas, pontuação ou espaço — nenhum deles pode surgir de
        // um ULID, cujo alfabeto é maiúsculo e alfanumérico.
        //
        // Deliberadamente **não** se afirma a ausência de "PNG": as três letras
        // pertencem ao alfabeto Crockford e um ULID legítimo pode contê-las por
        // acaso, o que tornaria o teste instável sem provar nada.
        foreach (['Meu', 'Produto', 'Final', '(2)', '###', ' '] as $fragment) {
            $this->assertStringNotContainsString($fragment, $media->path);
        }

        // O nome hostil continua disponível como metadado, intacto.
        $this->assertSame($hostileName, $media->fresh()->original_name);
    }

    /**
     * @return list<array{string}>
     */
    public static function unsupportedExtensions(): array
    {
        return [
            ['../php'],
            ['foo/bar'],
            ['jpg?x'],
            ['.jpg'],
            ['..'],
            ['php'],
            ['svg'],
            ['gif'],
            ['jpeg'],
            [''],
            ['jpg/../../etc'],
            ['jpg\\..\\win'],
        ];
    }

    /**
     * @dataProvider unsupportedExtensions
     */
    public function test_an_unsupported_extension_is_rejected(string $extension): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->media->generatePath($extension);
    }

    public function test_the_supported_extensions_are_accepted_and_canonicalised(): void
    {
        foreach (['jpg', 'png', 'webp'] as $extension) {
            $this->assertStringEndsWith('.'.$extension, $this->media->generatePath($extension));
            $this->assertStringEndsWith('.'.$extension, $this->media->generatePath(strtoupper($extension)));
        }
    }

    public function test_the_url_is_derived_from_the_disk_and_path(): void
    {
        $media = Media::factory()->create([
            'disk' => 'public',
            'path' => 'media/2026/09/01K4T7WQZ8N3B6MJX5F2VHRA9DC.jpg',
        ]);

        $this->assertSame(
            Storage::disk('public')->url('media/2026/09/01K4T7WQZ8N3B6MJX5F2VHRA9DC.jpg'),
            $this->media->url($media),
        );
        $this->assertStringEndsWith(
            '/storage/media/2026/09/01K4T7WQZ8N3B6MJX5F2VHRA9DC.jpg',
            $this->media->url($media),
        );
    }

    public function test_the_url_follows_the_configured_host_without_touching_the_record(): void
    {
        $media = Media::factory()->create([
            'disk' => 'public',
            'path' => 'media/2026/09/01K4T7WQZ8N3B6MJX5F2VHRA9DC.jpg',
        ]);

        $before = $this->media->url($media);
        $updatedAtBefore = $media->fresh()->updated_at;

        // `config/filesystems.php` monta a url do disco a partir de APP_URL no
        // boot. Reconfigurar o disco e esquecê-lo reproduz um ambiente com
        // outro APP_URL — sem isso o disco resolvido em cache devolveria o
        // valor antigo e o teste passaria por coincidência.
        config(['filesystems.disks.public.url' => 'https://loja.example/storage']);
        Storage::forgetDisk('public');

        $after = $this->media->url($media);

        $this->assertNotSame($before, $after);
        $this->assertSame(
            'https://loja.example/storage/media/2026/09/01K4T7WQZ8N3B6MJX5F2VHRA9DC.jpg',
            $after,
        );

        // A URL mudou sem que o registro fosse tocado: ela é derivada, não
        // persistida, e não existe coluna para ela.
        $this->assertEquals($updatedAtBefore, $media->fresh()->updated_at);
        $this->assertArrayNotHasKey('url', $media->fresh()->getAttributes());
    }

    public function test_the_url_uses_the_disk_stored_on_the_record(): void
    {
        // Um segundo disco local, registrado só para este teste: é o que
        // permite provar que `url()` segue `$media->disk` em vez da constante
        // da classe, sem depender de um backend remoto que a Fase 2 não usa.
        config([
            'filesystems.disks.public.url' => 'https://publico.example/storage',
            'filesystems.disks.arquivo' => [
                'driver' => 'local',
                'root' => storage_path('app/arquivo'),
                'url' => 'https://arquivo.example/arquivos',
                'visibility' => 'public',
                'throw' => false,
            ],
        ]);
        Storage::forgetDisk('public');
        Storage::forgetDisk('arquivo');

        $onPublic = Media::factory()->create([
            'disk' => 'public',
            'path' => 'media/2026/09/01HHHHHHHHHHHHHHHHHHHHHHHH.jpg',
        ]);
        $onArquivo = Media::factory()->create([
            'disk' => 'arquivo',
            'path' => 'media/2026/09/01IIIIIIIIIIIIIIIIIIIIIIII.jpg',
        ]);

        $this->assertSame(
            'https://publico.example/storage/media/2026/09/01HHHHHHHHHHHHHHHHHHHHHHHH.jpg',
            $this->media->url($onPublic),
        );
        $this->assertSame(
            'https://arquivo.example/arquivos/media/2026/09/01IIIIIIIIIIIIIIIIIIIIIIII.jpg',
            $this->media->url($onArquivo),
        );
    }

    public function test_the_library_is_paginated_with_twenty_four_items_by_default(): void
    {
        Media::factory()->count(30)->create();

        $page = $this->media->paginate();

        $this->assertSame(24, $page->perPage());
        $this->assertCount(24, $page->items());
        $this->assertSame(30, $page->total());
    }

    public function test_the_page_size_can_be_customised(): void
    {
        Media::factory()->count(12)->create();

        $page = $this->media->paginate(5);

        $this->assertSame(5, $page->perPage());
        $this->assertCount(5, $page->items());
        $this->assertSame(3, $page->lastPage());
    }

    public function test_the_newest_media_comes_first(): void
    {
        $records = Media::factory()->count(30)->create();
        $expected = $records->pluck('id')->sortDesc()->take(24)->values()->all();

        $page = $this->media->paginate();

        $this->assertSame($expected, collect($page->items())->pluck('id')->all());
        $this->assertSame($records->max('id'), $page->items()[0]->id);
    }

    public function test_the_default_ordering_is_deterministic_when_timestamps_collide(): void
    {
        // Todos os registros compartilham o mesmo `created_at`/`updated_at`.
        // Uma ordenação por timestamp não teria desempate; por `id`, tem.
        $this->travelTo('2026-09-06 12:00:00');
        $records = Media::factory()->count(10)->create();
        $this->travelBack();

        $expected = $records->pluck('id')->sortDesc()->values()->all();

        $this->assertSame($expected, collect($this->media->paginate()->items())->pluck('id')->all());
        $this->assertSame($expected, collect($this->media->paginate()->items())->pluck('id')->all());
    }

    public function test_pagination_does_not_load_the_whole_table(): void
    {
        Media::factory()->count(60)->create();

        $page = $this->media->paginate(10);

        $this->assertCount(10, $page->items());
        $this->assertSame(60, $page->total());
        $this->assertSame(6, $page->lastPage());
    }
}
