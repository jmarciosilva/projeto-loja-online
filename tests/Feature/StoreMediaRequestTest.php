<?php

namespace Tests\Feature;

use App\Http\Requests\Admin\StoreMediaRequest;
use App\Services\ImageCapabilities;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;
use Tests\TestCase;

/**
 * Barreira de entrada do upload — F2.7-B.
 *
 * O Form Request é exercitado diretamente pelo validador: a rota e o Controller
 * da biblioteca pertencem à F2.7-C, e criar um endpoint só para testar a
 * validação anteciparia aquela subfase.
 *
 * Os casos de tipo usam `UploadedFile` **real**, e não `UploadedFile::fake()`:
 * o fake sobrescreve `getMimeType()` para derivar do **nome** do arquivo
 * (`MimeType::from($this->name)`), o que faria a regra `mimetypes` passar por
 * um motivo que não existe em produção. Com o arquivo real, o tipo sai do
 * conteúdo, via `fileinfo` — que é o contrato desta subfase.
 */
class StoreMediaRequestTest extends TestCase
{
    public function test_a_valid_jpeg_passes(): void
    {
        $this->assertTrue($this->validate($this->upload($this->jpegBytes(800, 600), 'foto.jpg'))->passes());
    }

    public function test_a_valid_png_passes(): void
    {
        $this->assertTrue($this->validate($this->upload($this->pngBytes(400, 400), 'logo.png'))->passes());
    }

    public function test_a_valid_webp_passes_when_the_runtime_supports_it(): void
    {
        $this->assertTrue(
            app(ImageCapabilities::class)->supportsWebp(),
            'O gate técnico da F2.7-B exige suporte a WebP no GD deste ambiente.',
        );

        $this->assertTrue($this->validate($this->upload($this->webpBytes(), 'imagem.webp'))->passes());
    }

    public function test_webp_is_rejected_when_the_runtime_does_not_support_it(): void
    {
        // O caso negativo não pode depender da máquina que roda a suíte: a
        // capacidade é trocada no container, sem env nem configuração falsa.
        $this->withoutWebpSupport();

        $validator = $this->validate($this->upload($this->webpBytes(), 'imagem.webp'));

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('Formato não suportado', $validator->errors()->first('file'));
        $this->assertStringNotContainsString('WebP', $validator->errors()->first('file'));
    }

    public function test_jpeg_and_png_do_not_depend_on_the_webp_gate(): void
    {
        $this->withoutWebpSupport();

        $this->assertTrue($this->validate($this->upload($this->jpegBytes(100, 100), 'foto.jpg'))->passes());
        $this->assertTrue($this->validate($this->upload($this->pngBytes(100, 100), 'logo.png'))->passes());
    }

    public function test_a_gif_is_rejected(): void
    {
        $validator = $this->validate($this->upload($this->gifBytes(), 'animado.gif'));

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('Formato não suportado', $validator->errors()->first('file'));
    }

    public function test_an_svg_is_rejected(): void
    {
        $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>';

        $this->assertTrue($this->validate($this->upload($svg, 'icone.svg'))->fails());
    }

    public function test_a_pdf_is_rejected(): void
    {
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< >>\n%%EOF\n";

        $this->assertTrue($this->validate($this->upload($pdf, 'manual.pdf'))->fails());
    }

    public function test_a_zip_is_rejected(): void
    {
        $zip = "PK\x03\x04".str_repeat("\x00", 26);

        $this->assertTrue($this->validate($this->upload($zip, 'pacote.zip'))->fails());
    }

    public function test_a_php_file_disguised_as_a_jpeg_is_rejected(): void
    {
        // Extensão de imagem, `Content-Type` de imagem informado pelo cliente,
        // conteúdo de script. Só a detecção pelo conteúdo pega este caso.
        $file = $this->upload("<?php echo 'comprometido'; ?>", 'shell.jpg', 'image/jpeg');

        $this->assertSame('image/jpeg', $file->getClientMimeType());
        $this->assertSame('text/x-php', $file->getMimeType());

        $validator = $this->validate($file);

        $this->assertTrue($validator->fails());
        $this->assertNotSame([], $validator->errors()->get('file'));
    }

    public function test_a_file_larger_than_five_megabytes_is_rejected(): void
    {
        // O fake é adequado aqui: a regra sob teste é `max`, que lê o tamanho
        // reportado — e o arquivo gerado continua sendo uma imagem real.
        $file = UploadedFile::fake()->image('grande.jpg', 100, 100)->size(5121);

        $validator = $this->validate($file);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('5 MB', $validator->errors()->first('file'));
    }

    public function test_a_file_of_exactly_five_megabytes_is_accepted(): void
    {
        $this->assertTrue($this->validate(UploadedFile::fake()->image('limite.jpg', 100, 100)->size(5120))->passes());
    }

    public function test_an_image_wider_than_six_thousand_pixels_is_rejected(): void
    {
        $validator = $this->validate($this->upload($this->jpegBytes(6001, 10), 'larga.jpg'));

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('6000', $validator->errors()->first('file'));
    }

    public function test_an_image_taller_than_six_thousand_pixels_is_rejected(): void
    {
        $this->assertTrue($this->validate($this->upload($this->jpegBytes(10, 6001), 'alta.jpg'))->fails());
    }

    public function test_an_image_at_exactly_six_thousand_pixels_is_accepted(): void
    {
        $this->assertTrue($this->validate($this->upload($this->jpegBytes(6000, 10), 'limite.jpg'))->passes());
    }

    public function test_the_file_is_required(): void
    {
        $request = new StoreMediaRequest;
        $validator = Validator::make([], $request->rules(), $request->messages(), $request->attributes());

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('Selecione um arquivo', $validator->errors()->first('file'));
    }

    public function test_more_than_one_file_is_rejected(): void
    {
        // Um request carrega exatamente um arquivo: um array não é aceito.
        $files = [
            $this->upload($this->jpegBytes(10, 10), 'a.jpg'),
            $this->upload($this->jpegBytes(10, 10), 'b.jpg'),
        ];

        $validator = $this->validate($files);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('um único arquivo', $validator->errors()->first('file'));
    }

    /**
     * Roda as regras reais do Form Request sem HTTP e sem rota.
     */
    private function validate(mixed $file): ValidatorInstance
    {
        $request = new StoreMediaRequest;

        return Validator::make(
            ['file' => $file],
            $request->rules(),
            $request->messages(),
            $request->attributes(),
        );
    }

    /**
     * Substitui a capacidade do runtime por uma sem WebP, para exercitar o
     * caminho em que o gate técnico não foi cumprido.
     */
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

    private function jpegBytes(int $width, int $height): string
    {
        return $this->render(fn ($canvas) => imagejpeg($canvas), $width, $height);
    }

    private function pngBytes(int $width, int $height): string
    {
        return $this->render(fn ($canvas) => imagepng($canvas), $width, $height);
    }

    private function webpBytes(int $width = 40, int $height = 30): string
    {
        return $this->render(fn ($canvas) => imagewebp($canvas), $width, $height);
    }

    private function gifBytes(int $width = 20, int $height = 20): string
    {
        return $this->render(fn ($canvas) => imagegif($canvas), $width, $height);
    }

    private function render(callable $writer, int $width, int $height): string
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 40, 120, 200));

        ob_start();
        $writer($canvas);

        return (string) ob_get_clean();
    }
}
