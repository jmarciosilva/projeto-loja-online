<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Services\ImageCapabilities;
use App\Services\MediaService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Mockery;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * Upload e processamento de imagem — F2.7-B.
 *
 * As asserções confrontam o **arquivo realmente armazenado**, e não o que o
 * pipeline diz ter produzido: é o disco que responde se o registro fala a
 * verdade.
 *
 * Os uploads são `UploadedFile` **reais**, e não `UploadedFile::fake()`: o fake
 * sobrescreve `getMimeType()` para derivar do **nome** do arquivo, o que faria
 * a detecção de tipo passar por um motivo que não existe em produção.
 */
class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    private MediaService $media;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->media = app(MediaService::class);
    }

    public function test_a_jpeg_upload_creates_the_file_and_the_record(): void
    {
        $media = $this->media->store($this->jpegUpload(800, 600, 'Foto Da Loja.jpg'));

        Storage::disk('public')->assertExists($media->path);

        $this->assertSame('public', $media->disk);
        $this->assertStringEndsWith('.jpg', $media->path);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame('Foto Da Loja.jpg', $media->original_name);
        $this->assertDatabaseCount('media', 1);

        $this->assertRecordDescribesStoredFile($media, 'image/jpeg');
    }

    public function test_a_png_upload_creates_the_file_and_the_record(): void
    {
        $media = $this->media->store($this->pngUpload(400, 300));

        Storage::disk('public')->assertExists($media->path);

        $this->assertStringEndsWith('.png', $media->path);
        $this->assertSame('image/png', $media->mime_type);

        $this->assertRecordDescribesStoredFile($media, 'image/png');
    }

    public function test_a_png_keeps_its_alpha_channel(): void
    {
        $media = $this->media->store($this->pngUpload(40, 30, transparent: true));

        $stored = imagecreatefromstring($this->storedBytes($media));

        $this->assertNotFalse($stored);

        // O canto esquerdo foi enviado totalmente transparente; o direito,
        // opaco. Ler o alfa do arquivo armazenado prova que a recodificação
        // preservou o canal, em vez de achatá-lo sobre um fundo.
        $transparent = imagecolorsforindex($stored, imagecolorat($stored, 1, 1));
        $opaque = imagecolorsforindex($stored, imagecolorat($stored, 38, 28));

        $this->assertSame(127, $transparent['alpha'], 'O pixel transparente perdeu o canal alfa.');
        $this->assertSame(0, $opaque['alpha'], 'O pixel opaco não deveria ter ganhado transparência.');
    }

    public function test_a_webp_upload_creates_the_file_and_the_record(): void
    {
        $this->assertTrue(
            app(ImageCapabilities::class)->supportsWebp(),
            'O gate técnico da F2.7-B exige suporte a WebP no GD deste ambiente.',
        );

        $media = $this->media->store($this->webpUpload(120, 90));

        Storage::disk('public')->assertExists($media->path);

        $this->assertStringEndsWith('.webp', $media->path);
        $this->assertSame('image/webp', $media->mime_type);

        // A extensão não é prova: o arquivo armazenado precisa ser um WebP
        // decodificável de verdade.
        $bytes = $this->storedBytes($media);
        $info = getimagesizefromstring($bytes);

        $this->assertSame('RIFF', substr($bytes, 0, 4));
        $this->assertSame('WEBP', substr($bytes, 8, 4));
        $this->assertSame(IMAGETYPE_WEBP, $info[2]);
        $this->assertNotFalse(imagecreatefromstring($bytes));

        $this->assertRecordDescribesStoredFile($media, 'image/webp');
    }

    public function test_the_service_refuses_webp_when_the_runtime_does_not_support_it(): void
    {
        $this->withoutWebpSupport();

        $this->expectException(InvalidArgumentException::class);

        try {
            app(MediaService::class)->store($this->webpUpload());
        } finally {
            $this->assertDatabaseCount('media', 0);
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_an_oversized_image_is_scaled_down_preserving_the_ratio(): void
    {
        $media = $this->media->store($this->jpegUpload(4000, 1000));

        $this->assertSame(2000, $media->width);
        $this->assertSame(500, $media->height);
        $this->assertRecordDescribesStoredFile($media, 'image/jpeg');
    }

    public function test_an_image_exactly_at_the_limit_is_left_untouched(): void
    {
        $media = $this->media->store($this->jpegUpload(2000, 2000));

        $this->assertSame(2000, $media->width);
        $this->assertSame(2000, $media->height);
    }

    public function test_a_small_image_is_never_upscaled(): void
    {
        $media = $this->media->store($this->jpegUpload(300, 200));

        $this->assertSame(300, $media->width);
        $this->assertSame(200, $media->height);
        $this->assertRecordDescribesStoredFile($media, 'image/jpeg');
    }

    public function test_the_exif_orientation_is_applied_to_the_pixels(): void
    {
        // A fixture é 120 × 60 com Orientation = 6 (girar 90° no sentido
        // horário): depois de `orient()` ela precisa virar 60 × 120.
        $media = $this->media->store($this->exifUpload());

        $this->assertSame(60, $media->width);
        $this->assertSame(120, $media->height);

        $stored = getimagesizefromstring($this->storedBytes($media));

        $this->assertSame(60, $stored[0]);
        $this->assertSame(120, $stored[1]);
    }

    public function test_the_exif_metadata_does_not_survive_the_re_encoding(): void
    {
        $upload = $this->exifUpload();

        // O arquivo enviado realmente carrega os metadados.
        $before = @exif_read_data($upload->getPathname());
        $this->assertSame(6, $before['Orientation'] ?? null);
        $this->assertSame('TestCam', $before['Make'] ?? null);
        $this->assertArrayHasKey('GPSLatitude', $before);

        $media = $this->media->store($upload);

        $path = Storage::disk('public')->path($media->path);
        $after = @exif_read_data($path);

        if ($after !== false) {
            $this->assertArrayNotHasKey('Make', $after);
            $this->assertArrayNotHasKey('Model', $after);
            $this->assertArrayNotHasKey('GPSLatitude', $after);
            $this->assertArrayNotHasKey('GPSLatitudeRef', $after);
            $this->assertNotSame(6, $after['Orientation'] ?? null);
        }

        // A imagem já está fisicamente orientada: não depende mais da tag.
        $this->assertSame(60, $media->width);
        $this->assertSame(120, $media->height);
    }

    public function test_a_hostile_original_name_never_reaches_the_path(): void
    {
        $hostile = '../../Minha Foto Final (2) ###.JpEg';
        $media = $this->media->store($this->upload($this->jpegBytes(60, 40), $hostile));

        // Prova estrutural: a expressão descreve o caminho inteiro, então nada
        // vindo do nome enviado caberia nele.
        $this->assertMatchesRegularExpression(
            '#^media/\d{4}/\d{2}/[0-9A-HJKMNP-TV-Z]{26}\.jpg$#',
            $media->path,
        );

        foreach (['..', 'Minha', 'Foto', 'Final', '(2)', '###', ' ', 'JpEg'] as $fragment) {
            $this->assertStringNotContainsString($fragment, $media->path);
        }

        // O nome sobrevive como metadado — já sem o `../../`, que o próprio
        // Symfony descarta ao normalizar o nome do arquivo enviado.
        $this->assertSame('Minha Foto Final (2) ###.JpEg', $media->original_name);
        $this->assertStringNotContainsString('..', $media->original_name);
    }

    public function test_the_client_extension_and_content_type_never_decide_the_stored_extension(): void
    {
        // Um PNG real, enviado com extensão `.jpeg` e `Content-Type` mentindo
        // `image/jpeg`: a extensão física sai do MIME detectado no conteúdo, e
        // `jpeg` nem sequer é uma extensão que `generatePath()` aceite.
        $file = $this->upload($this->pngBytes(50, 50), 'mentiroso.jpeg', 'image/jpeg');

        $this->assertSame('image/jpeg', $file->getClientMimeType());

        $media = $this->media->store($file);

        $this->assertSame('image/png', $media->mime_type);
        $this->assertStringEndsWith('.png', $media->path);
        $this->assertSame('mentiroso.jpeg', $media->original_name);
        $this->assertRecordDescribesStoredFile($media, 'image/png');
    }

    public function test_the_size_describes_the_encoded_result_and_not_the_upload(): void
    {
        $upload = $this->jpegUpload(4000, 1000);
        $uploadedSize = $upload->getSize();

        $media = $this->media->store($upload);

        $this->assertSame(strlen($this->storedBytes($media)), $media->size);
        $this->assertNotSame($uploadedSize, $media->size);
    }

    public function test_a_file_that_cannot_be_decoded_creates_nothing(): void
    {
        // Assinatura de JPEG com corpo inválido: passa pela detecção de MIME,
        // mas o Intervention não consegue decodificar.
        $broken = UploadedFile::fake()->createWithContent('quebrado.jpg', "\xFF\xD8\xFF\xE0".str_repeat("\x00", 200));

        try {
            $this->media->store($broken);
            $this->fail('Um arquivo indecodificável deveria interromper o processamento.');
        } catch (Throwable) {
            // A exceção de processamento propaga; o que importa é o estado.
        }

        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_unsupported_type_is_refused_by_the_service_itself(): void
    {
        $gif = $this->upload($this->gifBytes(), 'animado.gif');

        $this->expectException(InvalidArgumentException::class);

        try {
            $this->media->store($gif);
        } finally {
            $this->assertDatabaseCount('media', 0);
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_a_failed_write_creates_no_record(): void
    {
        // O disco `public` usa `throw => false`: uma falha volta como `false`,
        // e ignorá-la criaria um registro apontando para um arquivo ausente.
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->once()->andReturnFalse();
        Storage::shouldReceive('disk')->with(MediaService::DISK)->andReturn($disk);

        $this->expectException(RuntimeException::class);

        try {
            app(MediaService::class)->store($this->jpegUpload(100, 100));
        } finally {
            $this->assertDatabaseCount('media', 0);
        }
    }

    public function test_a_failed_record_removes_the_file_already_written(): void
    {
        Media::creating(function (): void {
            throw new RuntimeException('Falha simulada de persistência.');
        });

        try {
            $this->media->store($this->jpegUpload(100, 100));
            $this->fail('A falha de persistência deveria ter propagado.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Falha simulada de persistência.', $exception->getMessage());
        } finally {
            Media::flushEventListeners();
        }

        // Limpeza de melhor esforço: o arquivo não fica órfão no caminho feliz
        // da compensação, e nenhum registro é criado.
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('media', 0);
    }

    /**
     * Confronta o registro com o arquivo realmente gravado no disco.
     */
    private function assertRecordDescribesStoredFile(Media $media, string $expectedMimeType): void
    {
        $bytes = $this->storedBytes($media);
        $info = getimagesizefromstring($bytes);

        $this->assertNotFalse($info, 'O arquivo armazenado não é uma imagem legível.');
        $this->assertSame(strlen($bytes), $media->size);
        $this->assertSame($info[0], $media->width);
        $this->assertSame($info[1], $media->height);
        $this->assertSame($expectedMimeType, $info['mime']);
        $this->assertSame($expectedMimeType, $media->mime_type);
        $this->assertLessThanOrEqual(2000, max($media->width, $media->height));
    }

    private function storedBytes(Media $media): string
    {
        return (string) Storage::disk($media->disk)->get($media->path);
    }

    private function withoutWebpSupport(): void
    {
        $this->swap(ImageCapabilities::class, new class extends ImageCapabilities
        {
            public function supportsWebp(): bool
            {
                return false;
            }
        });
    }

    /**
     * Arquivo enviado **real**: o MIME é detectado a partir do conteúdo, e o
     * `Content-Type` informado pelo cliente pode até mentir.
     */
    private function upload(string $bytes, string $name, ?string $clientMimeType = null): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'f27b');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, $clientMimeType, null, true);
    }

    private function jpegUpload(int $width, int $height, string $name = 'foto.jpg'): UploadedFile
    {
        return $this->upload($this->jpegBytes($width, $height), $name);
    }

    private function pngUpload(int $width = 40, int $height = 30, bool $transparent = false): UploadedFile
    {
        return $this->upload($this->pngBytes($width, $height, $transparent), 'imagem.png');
    }

    private function webpUpload(int $width = 40, int $height = 30): UploadedFile
    {
        return $this->upload($this->webpBytes($width, $height), 'imagem.webp');
    }

    private function exifUpload(): UploadedFile
    {
        $fixture = base_path('tests/Fixtures/exif-orientation-6.jpg');

        return $this->upload((string) file_get_contents($fixture), 'camera.jpg');
    }

    private function jpegBytes(int $width, int $height): string
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 40, 120, 200));

        ob_start();
        imagejpeg($canvas);

        return (string) ob_get_clean();
    }

    private function pngBytes(int $width, int $height, bool $transparent = false): string
    {
        $canvas = imagecreatetruecolor($width, $height);

        if ($transparent) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
            imagefilledrectangle($canvas, (int) ($width / 2), 0, $width - 1, $height - 1, imagecolorallocatealpha($canvas, 255, 0, 0, 0));
        } else {
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 20, 160, 90));
        }

        ob_start();
        imagepng($canvas);

        return (string) ob_get_clean();
    }

    private function webpBytes(int $width, int $height): string
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 10, 120, 200));

        ob_start();
        imagewebp($canvas);

        return (string) ob_get_clean();
    }

    private function gifBytes(int $width = 20, int $height = 20): string
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 90, 90, 90));

        ob_start();
        imagegif($canvas);

        return (string) ob_get_clean();
    }
}
