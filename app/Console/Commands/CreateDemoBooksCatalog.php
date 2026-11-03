<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CategoryService;
use App\Services\MediaService;
use App\Services\ProductService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CreateDemoBooksCatalog extends Command
{
    protected $signature = 'loja:catalogo-demo-livros';

    protected $description = 'Cria, somente no ambiente local, o catálogo demonstrativo de livros usados E05.1.';

    /** @var list<string> Arquivos gravados nesta execução, para limpeza se a transação falhar. */
    private array $storedPaths = [];

    /** @var array<string, array{name:string,slug:string,sort:int}> */
    private const CATEGORIES = [
        'livros-informatica' => ['name' => 'Livros de Informática', 'slug' => 'livros-de-informatica', 'sort' => 1],
        'livros-fantasia' => ['name' => 'Livros de Fantasia', 'slug' => 'livros-de-fantasia', 'sort' => 2],
        'livros-literatura' => ['name' => 'Livros de Literatura', 'slug' => 'livros-de-literatura', 'sort' => 3],
    ];

    /** @var list<array<string, string|int>> */
    private const BOOKS = [
        ['category' => 'livros-informatica', 'name' => 'Código Limpo', 'slug' => 'codigo-limpo-demo', 'sku' => 'DEMO-LIV-INFO-001', 'author' => 'Robert C. Martin', 'price' => '49.90', 'notes' => 'Bom estado, leves marcas de manuseio.', 'quantity' => 1],
        ['category' => 'livros-informatica', 'name' => 'O Programador Pragmático', 'slug' => 'o-programador-pragmatico-demo', 'sku' => 'DEMO-LIV-INFO-002', 'author' => 'Andrew Hunt e David Thomas', 'price' => '59.90', 'notes' => 'Muito bom estado, páginas preservadas.', 'quantity' => 2],
        ['category' => 'livros-informatica', 'name' => 'Use a Cabeça! Java', 'slug' => 'use-a-cabeca-java-demo', 'sku' => 'DEMO-LIV-INFO-003', 'author' => 'Kathy Sierra e Bert Bates', 'price' => '39.90', 'notes' => 'Marcas de uso na capa, miolo preservado.', 'quantity' => 0],
        ['category' => 'livros-fantasia', 'name' => 'O Hobbit', 'slug' => 'o-hobbit-demo', 'sku' => 'DEMO-LIV-FANT-001', 'author' => 'J. R. R. Tolkien', 'price' => '29.90', 'notes' => 'Bom estado geral.', 'quantity' => 1],
        ['category' => 'livros-fantasia', 'name' => 'Harry Potter e a Pedra Filosofal', 'slug' => 'harry-potter-pedra-filosofal-demo', 'sku' => 'DEMO-LIV-FANT-002', 'author' => 'J. K. Rowling', 'price' => '24.90', 'notes' => 'Pequenas marcas de manuseio.', 'quantity' => 3],
        ['category' => 'livros-fantasia', 'name' => 'O Leão, a Feiticeira e o Guarda-Roupa', 'slug' => 'o-leao-a-feiticeira-e-o-guarda-roupa-demo', 'sku' => 'DEMO-LIV-FANT-003', 'author' => 'C. S. Lewis', 'price' => '19.90', 'notes' => 'Estado regular, capa com sinais de uso.', 'quantity' => 0],
        ['category' => 'livros-literatura', 'name' => 'Dom Casmurro', 'slug' => 'dom-casmurro-demo', 'sku' => 'DEMO-LIV-LIT-001', 'author' => 'Machado de Assis', 'price' => '14.90', 'notes' => 'Bom estado, páginas levemente amareladas.', 'quantity' => 1],
        ['category' => 'livros-literatura', 'name' => '1984', 'slug' => '1984-demo', 'sku' => 'DEMO-LIV-LIT-002', 'author' => 'George Orwell', 'price' => '27.90', 'notes' => 'Muito bom estado.', 'quantity' => 2],
        ['category' => 'livros-literatura', 'name' => 'A Metamorfose', 'slug' => 'a-metamorfose-demo', 'sku' => 'DEMO-LIV-LIT-003', 'author' => 'Franz Kafka', 'price' => '17.90', 'notes' => 'Bom estado de conservação.', 'quantity' => 1],
    ];

    public function handle(CategoryService $categories, ProductService $products, MediaService $media): int
    {
        if (! $this->safeEnvironment()) {
            $this->components->error('Operação bloqueada: use somente APP_ENV=local e o banco MySQL loja_online.');

            return self::FAILURE;
        }

        $administrators = User::query()->where('can_manage_catalog', true)->where('can_manage_stock', true)
            ->where('can_view_stock_movements', true)->get();
        if ($administrators->count() !== 1) {
            $this->components->error('É necessário exatamente um administrador local autorizado para catálogo e estoque.');

            return self::FAILURE;
        }
        $administrator = $administrators->sole();
        $previousUser = Auth::user();
        Auth::setUser($administrator);

        try {
            $this->preflight($administrator);
            DB::transaction(function () use ($categories, $products, $media): void {
                $root = $this->category($categories, 'Livros Usados', 'livros-usados', null, 0, 'Livros usados para catálogo demonstrativo local.');
                $categoryModels = [];
                foreach (self::CATEGORIES as $key => $data) {
                    $categoryModels[$key] = $this->category($categories, $data['name'], $data['slug'], $root, $data['sort'], null);
                }

                foreach (self::BOOKS as $book) {
                    if (Product::withTrashed()->where('sku', $book['sku'])->exists()) {
                        continue;
                    }
                    $uploaded = $this->placeholder($book);
                    try {
                        $image = $media->store($uploaded);
                        $this->storedPaths[] = $image->path;
                    } finally {
                        @unlink($uploaded->getPathname());
                    }
                    $products->create([
                        'category_id' => $categoryModels[$book['category']]->id,
                        'main_media_id' => $image->id,
                        'gallery_media_ids' => [$image->id],
                        'name' => $book['name'],
                        'slug' => $book['slug'],
                        'sku' => $book['sku'],
                        'description' => '<p>Autor(a): '.e($book['author']).'.</p><p>Registro e saldo demonstrativos para desenvolvimento local. A imagem é um placeholder e não representa capa ou edição específica.</p>',
                        'condition' => 'used',
                        'condition_notes' => $book['notes'],
                        'price' => $book['price'],
                        'promotion_enabled' => false,
                        'is_active' => true,
                        'is_featured' => false,
                        'stock_control_enabled' => true,
                        'initial_stock_quantity' => $book['quantity'],
                        'minimum_stock_quantity' => 0,
                        'stock_initial_reason' => 'Saldo inicial demonstrativo — E05.1',
                        'stock_initial_reference_type' => 'local_demo_catalog',
                        'stock_initial_reference_id' => $book['sku'],
                        'shipping_profile' => ['requires_shipping' => true],
                    ]);
                }

                $this->removeDemoShirt($categories, $products);
            });
        } catch (Throwable $exception) {
            foreach ($this->storedPaths as $path) {
                Storage::disk(MediaService::DISK)->delete($path);
            }
            $this->components->error('Catálogo não concluído: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($previousUser !== null) {
                Auth::setUser($previousUser);
            } else {
                Auth::forgetGuards();
            }
        }

        $this->components->info('Catálogo local E05.1 criado ou já íntegro. Os registros comerciais são demonstrativos.');

        return self::SUCCESS;
    }

    private function safeEnvironment(): bool
    {
        return (app()->environment('testing') && DB::connection()->getDriverName() === 'sqlite')
            || (app()->environment('local') && DB::connection()->getDriverName() === 'mysql'
                && DB::connection()->getDatabaseName() === 'loja_online');
    }

    private function preflight(User $administrator): void
    {
        $root = Category::withTrashed()->where('slug', 'livros-usados')->first();
        if ($root !== null && ($root->trashed() || $root->name !== 'Livros Usados' || $root->parent_category_id !== null)) {
            throw new RuntimeException('O slug livros-usados já está ocupado por um registro incompatível.');
        }
        foreach (self::CATEGORIES as $data) {
            $category = Category::withTrashed()->where('slug', $data['slug'])->first();
            if ($category !== null && ($category->trashed() || $category->name !== $data['name'])) {
                throw new RuntimeException("O slug {$data['slug']} já está ocupado por uma categoria incompatível.");
            }
        }
        foreach (self::BOOKS as $book) {
            $product = Product::withTrashed()->where(fn ($query) => $query->where('sku', $book['sku'])->orWhere('slug', $book['slug']))->first();
            if ($product !== null && ($product->trashed() || $product->sku !== $book['sku'] || $product->slug !== $book['slug']
                || $product->name !== $book['name'] || $product->condition !== 'used' || $product->condition_notes !== $book['notes']
                || (string) $product->price !== $book['price'] || $product->cost_price !== null || $product->target_margin !== null
                || $product->promotional_price !== null || $product->promotion_enabled
                || $product->promotion_starts_at !== null || $product->promotion_ends_at !== null || ! $product->is_active
                || $product->category_id !== Category::query()->where('slug', self::CATEGORIES[$book['category']]['slug'])->value('id'))) {
                throw new RuntimeException("O produto {$book['sku']} já existe com dados divergentes; nenhum saldo será alterado.");
            }
            if ($product !== null) {
                $stock = $product->stock;
                $movement = $product->stockMovements()->where('type', 'initial_balance')->first();
                $product->loadMissing(['mainMedia', 'images', 'shippingProfile']);
                if ($stock === null || ! $stock->stock_control_enabled || $stock->quantity_on_hand !== $book['quantity']
                    || $stock->quantity_reserved !== 0 || $stock->minimum_quantity !== 0 || $product->stockMovements()->count() !== 1
                    || $movement?->quantity_delta !== $book['quantity'] || $movement?->quantity_before !== 0
                    || $movement?->quantity_after !== $book['quantity'] || $movement?->reason !== 'Saldo inicial demonstrativo — E05.1'
                    || $movement?->user_id !== $administrator->id || $movement?->reference_type !== 'local_demo_catalog'
                    || $movement?->reference_id !== $book['sku'] || $product->mainMedia === null
                    || ! str_starts_with($product->mainMedia->original_name, 'Imagem demonstrativa - ')
                    || $product->images->count() !== 1 || $product->images->first()?->id !== $product->main_media_id
                    || ! $product->shippingProfile?->requires_shipping || $product->shippingProfile->hasProductMeasurements()
                    || $product->shippingProfile->hasPackageMeasurements()) {
                    throw new RuntimeException("Os dados ou o estoque de {$book['sku']} divergem do catálogo demonstrativo esperado.");
                }
                if (! Storage::disk($product->mainMedia->disk)->exists($product->mainMedia->path)) {
                    throw new RuntimeException("A imagem de {$book['sku']} está ausente.");
                }
            }
        }
    }

    private function category(CategoryService $service, string $name, string $slug, ?Category $parent, int $order, ?string $description): Category
    {
        $existing = Category::withTrashed()->where('slug', $slug)->first();
        if ($existing !== null) {
            if ($existing->parent_category_id !== $parent?->id || ! $existing->is_active) {
                throw new RuntimeException("A categoria {$slug} possui hierarquia ou estado divergente.");
            }

            return $existing;
        }

        return $service->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'parent_category_id' => $parent?->id,
            'is_active' => true,
            'sort_order' => $order,
        ]);
    }

    /** @param array<string, string|int> $book */
    private function placeholder(array $book): UploadedFile
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('A extensão PHP GD é necessária para criar placeholders.');
        }

        $image = imagecreatetruecolor(800, 1000);
        $palettes = [
            'livros-informatica' => ['#092B57', '#FF6900', '#E8EEF6'],
            'livros-fantasia' => ['#28345D', '#E78C32', '#F0E4D6'],
            'livros-literatura' => ['#334155', '#C66A30', '#EEE8DF'],
        ];
        [$base, $accent, $paper] = $palettes[$book['category']];
        $background = $this->color($image, $base);
        $highlight = $this->color($image, $accent);
        $page = $this->color($image, $paper);
        imagefill($image, 0, 0, $background);
        imagefilledrectangle($image, 60, 55, 740, 945, $page);
        imagefilledrectangle($image, 60, 55, 87, 945, $highlight);
        imagefilledrectangle($image, 120, 120, 680, 128, $highlight);
        $this->bitmapText($image, 'IMAGEM DEMONSTRATIVA', 120, 145, 2, $background);
        $title = wordwrap(Str::upper(Str::ascii($book['name'])), 20, "\n", true);
        $lines = explode("\n", $title);
        $y = 355 - ((count($lines) - 1) * 25);
        foreach ($lines as $line) {
            $this->bitmapText($image, $line, 120, $y, 3, $background);
            $y += 75;
        }
        imagefilledrectangle($image, 120, 680, 680, 685, $highlight);
        $this->bitmapText($image, 'PLACEHOLDER TIPOGRAFICO', 120, 725, 2, $background);
        $this->bitmapText($image, 'NAO REPRESENTA CAPA / EDICAO', 120, 775, 2, $background);
        $this->bitmapText($image, 'UAU NETSHOPPING | TESTE LOCAL', 120, 850, 2, $background);

        $tempPath = tempnam(sys_get_temp_dir(), 'uau-book-demo-');
        if ($tempPath === false || ! imagepng($image, $tempPath, 8)) {
            imagedestroy($image);
            throw new RuntimeException('Não foi possível gerar uma imagem placeholder.');
        }
        imagedestroy($image);

        return new UploadedFile($tempPath, 'Imagem demonstrativa - '.Str::ascii($book['name']).'.png', 'image/png', UPLOAD_ERR_OK, true);
    }

    private function color(\GdImage $image, string $hex): int
    {
        $value = ltrim($hex, '#');

        return imagecolorallocate($image, hexdec(substr($value, 0, 2)), hexdec(substr($value, 2, 2)), hexdec(substr($value, 4, 2)));
    }

    private function bitmapText(\GdImage $image, string $text, int $x, int $y, int $scale, int $color): void
    {
        $text = Str::ascii($text);
        $width = max(1, strlen($text) * imagefontwidth(5) + 4);
        $height = imagefontheight(5) + 4;
        $layer = imagecreatetruecolor($width, $height);
        $transparent = imagecolorallocate($layer, 255, 0, 255);
        imagefill($layer, 0, 0, $transparent);
        imagecolortransparent($layer, $transparent);
        $textColor = imagecolorsforindex($image, $color);
        $opaqueInk = imagecolorallocate($layer, $textColor['red'], $textColor['green'], $textColor['blue']);
        imagefilledrectangle($layer, 0, 0, $width, $height, $transparent);
        imagecolortransparent($layer, $transparent);
        imagestring($layer, 5, 2, 2, $text, $opaqueInk);
        imagecopyresized($image, $layer, $x, $y, 0, 0, $width * $scale, $height * $scale, $width, $height);
        imagedestroy($layer);
    }

    private function removeDemoShirt(CategoryService $categories, ProductService $products): void
    {
        $product = Product::query()->where('slug', 'camiseta-de-algodao-basica')->first();
        $category = Category::query()->where('slug', 'camisetas')->first();
        if ($product === null && $category === null) {
            return;
        }
        if ($product === null || $category === null || $product->name !== 'Camiseta de Algodão Básica'
            || $product->category_id !== $category->id || Product::withTrashed()->where('category_id', $category->id)->count() !== 1
            || $category->children()->exists() || DB::table('product_stocks')->where('product_id', $product->id)->exists()
            || DB::table('stock_movements')->where('product_id', $product->id)->exists()) {
            $this->warn('Camiseta/Camisetas não foram removidas: a verificação encontrou diferenças ou histórico.');

            return;
        }
        foreach (['orders', 'order_items', 'carts', 'cart_items'] as $table) {
            if (Schema::hasTable($table)) {
                $this->warn("Camiseta/Camisetas preservadas: a tabela {$table} existe e exige avaliação de dependências.");

                return;
            }
        }

        $products->delete($product);
        $categories->delete($category);
        $this->line('Produto demonstrativo Camiseta de Algodão Básica e categoria Camisetas removidos da vitrine por exclusão lógica; mídias preservadas.');
    }
}
