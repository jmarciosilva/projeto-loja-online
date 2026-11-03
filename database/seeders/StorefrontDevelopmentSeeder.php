<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CategoryService;
use App\Services\ProductService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Catálogo fictício de demonstração para o desenvolvimento local.
 *
 * Execução explícita: `php artisan db:seed --class=StorefrontDevelopmentSeeder`.
 * Só roda em APP_ENV=local com o banco MySQL `loja_online`. É idempotente:
 * categorias são identificadas por slug e produtos por SKU `DEMO-*`, inclusive
 * os removidos logicamente. Nada existente é alterado ou apagado. Os produtos
 * não têm imagem, porque o catálogo não deve receber mídias fictícias.
 */
class StorefrontDevelopmentSeeder extends Seeder
{
    private const STOCK_REASON = 'Saldo inicial demonstrativo — desenvolvimento local';

    private const STOCK_REFERENCE_TYPE = 'dev_demo_seed';

    /** @var array<string, array{name: string, slug: string, parent: ?string, sort: int}> */
    private const CATEGORIES = [
        'livros' => ['name' => 'Livros', 'slug' => 'livros', 'parent' => null, 'sort' => 1],
        'informatica' => ['name' => 'Livros de Informática', 'slug' => 'livros-de-informatica', 'parent' => 'livros', 'sort' => 1],
        'fantasia' => ['name' => 'Livros de Fantasia', 'slug' => 'livros-de-fantasia', 'parent' => 'livros', 'sort' => 2],
        'literatura' => ['name' => 'Literatura Brasileira', 'slug' => 'literatura-brasileira', 'parent' => 'livros', 'sort' => 3],
        'desenvolvimento' => ['name' => 'Desenvolvimento Pessoal', 'slug' => 'desenvolvimento-pessoal', 'parent' => 'livros', 'sort' => 4],
        'artesanato' => ['name' => 'Artesanato', 'slug' => 'artesanato', 'parent' => null, 'sort' => 2],
        'eva' => ['name' => 'EVA para Artesanato', 'slug' => 'eva-para-artesanato', 'parent' => 'artesanato', 'sort' => 1],
    ];

    /**
     * Produtos fictícios. `condition` é 'used' ou 'new'. Livros usados têm
     * saldo de uma unidade, com alguns esgotados para testar a vitrine.
     *
     * @var list<array{sku: string, name: string, category: string, price: string, condition: string, qty: int, featured: bool, weight: string, dims: array{0: string, 1: string, 2: string}}>
     */
    private const PRODUCTS = [
        // Livros de Informática (5)
        ['sku' => 'DEMO-INF-001', 'name' => 'Código Limpo (demonstração)', 'category' => 'informatica', 'price' => '49.90', 'condition' => 'used', 'qty' => 1, 'featured' => true, 'weight' => '0.600', 'dims' => ['23.0', '16.0', '2.5']],
        ['sku' => 'DEMO-INF-002', 'name' => 'O Programador Pragmático (demonstração)', 'category' => 'informatica', 'price' => '59.90', 'condition' => 'used', 'qty' => 1, 'featured' => false, 'weight' => '0.700', 'dims' => ['23.0', '16.0', '3.0']],
        ['sku' => 'DEMO-INF-003', 'name' => 'Use a Cabeça! Java (demonstração)', 'category' => 'informatica', 'price' => '39.90', 'condition' => 'used', 'qty' => 0, 'featured' => false, 'weight' => '1.100', 'dims' => ['23.0', '18.0', '4.0']],
        ['sku' => 'DEMO-INF-004', 'name' => 'Refatoração (demonstração)', 'category' => 'informatica', 'price' => '129.90', 'condition' => 'new', 'qty' => 4, 'featured' => false, 'weight' => '0.900', 'dims' => ['23.0', '16.0', '3.5']],
        ['sku' => 'DEMO-INF-005', 'name' => 'Arquitetura Limpa (demonstração)', 'category' => 'informatica', 'price' => '89.90', 'condition' => 'new', 'qty' => 0, 'featured' => false, 'weight' => '0.800', 'dims' => ['23.0', '16.0', '2.8']],

        // Livros de Fantasia (5)
        ['sku' => 'DEMO-FAN-001', 'name' => 'O Hobbit (demonstração)', 'category' => 'fantasia', 'price' => '29.90', 'condition' => 'used', 'qty' => 1, 'featured' => true, 'weight' => '0.400', 'dims' => ['20.0', '13.0', '2.0']],
        ['sku' => 'DEMO-FAN-002', 'name' => 'Harry Potter e a Pedra Filosofal (demonstração)', 'category' => 'fantasia', 'price' => '24.90', 'condition' => 'used', 'qty' => 1, 'featured' => false, 'weight' => '0.450', 'dims' => ['20.0', '13.0', '2.2']],
        ['sku' => 'DEMO-FAN-003', 'name' => 'O Leão, a Feiticeira e o Guarda-Roupa (demonstração)', 'category' => 'fantasia', 'price' => '19.90', 'condition' => 'used', 'qty' => 0, 'featured' => false, 'weight' => '0.350', 'dims' => ['20.0', '13.0', '1.8']],
        ['sku' => 'DEMO-FAN-004', 'name' => 'A Sociedade do Anel (demonstração)', 'category' => 'fantasia', 'price' => '69.90', 'condition' => 'new', 'qty' => 3, 'featured' => false, 'weight' => '0.900', 'dims' => ['21.0', '14.0', '4.0']],
        ['sku' => 'DEMO-FAN-005', 'name' => 'Duna (demonstração)', 'category' => 'fantasia', 'price' => '44.90', 'condition' => 'used', 'qty' => 1, 'featured' => false, 'weight' => '0.700', 'dims' => ['21.0', '14.0', '3.5']],

        // Literatura Brasileira (4)
        ['sku' => 'DEMO-LIT-001', 'name' => 'Dom Casmurro (demonstração)', 'category' => 'literatura', 'price' => '14.90', 'condition' => 'used', 'qty' => 1, 'featured' => false, 'weight' => '0.300', 'dims' => ['19.0', '12.0', '1.5']],
        ['sku' => 'DEMO-LIT-002', 'name' => 'Memórias Póstumas de Brás Cubas (demonstração)', 'category' => 'literatura', 'price' => '18.90', 'condition' => 'used', 'qty' => 1, 'featured' => false, 'weight' => '0.300', 'dims' => ['19.0', '12.0', '1.5']],
        ['sku' => 'DEMO-LIT-003', 'name' => 'O Cortiço (demonstração)', 'category' => 'literatura', 'price' => '16.90', 'condition' => 'used', 'qty' => 1, 'featured' => false, 'weight' => '0.350', 'dims' => ['19.0', '12.0', '2.0']],
        ['sku' => 'DEMO-LIT-004', 'name' => 'Vidas Secas (demonstração)', 'category' => 'literatura', 'price' => '32.90', 'condition' => 'new', 'qty' => 2, 'featured' => false, 'weight' => '0.300', 'dims' => ['20.0', '13.0', '1.6']],

        // Desenvolvimento Pessoal (3)
        ['sku' => 'DEMO-DEV-001', 'name' => 'Hábitos Atômicos (demonstração)', 'category' => 'desenvolvimento', 'price' => '52.90', 'condition' => 'new', 'qty' => 3, 'featured' => true, 'weight' => '0.500', 'dims' => ['21.0', '14.0', '2.4']],
        ['sku' => 'DEMO-DEV-002', 'name' => 'O Poder do Hábito (demonstração)', 'category' => 'desenvolvimento', 'price' => '27.90', 'condition' => 'used', 'qty' => 1, 'featured' => false, 'weight' => '0.400', 'dims' => ['21.0', '14.0', '2.2']],
        ['sku' => 'DEMO-DEV-003', 'name' => 'Pai Rico, Pai Pobre (demonstração)', 'category' => 'desenvolvimento', 'price' => '22.90', 'condition' => 'used', 'qty' => 1, 'featured' => false, 'weight' => '0.350', 'dims' => ['21.0', '14.0', '1.9']],

        // EVA para Artesanato (4) — estoque maior
        ['sku' => 'DEMO-EVA-001', 'name' => 'Placa de EVA 2 mm (demonstração)', 'category' => 'eva', 'price' => '4.90', 'condition' => 'new', 'qty' => 120, 'featured' => true, 'weight' => '0.050', 'dims' => ['40.0', '60.0', '0.3']],
        ['sku' => 'DEMO-EVA-002', 'name' => 'Placa de EVA 5 mm (demonstração)', 'category' => 'eva', 'price' => '7.90', 'condition' => 'new', 'qty' => 80, 'featured' => false, 'weight' => '0.120', 'dims' => ['40.0', '60.0', '0.6']],
        ['sku' => 'DEMO-EVA-003', 'name' => 'Kit EVA com Glitter (demonstração)', 'category' => 'eva', 'price' => '29.90', 'condition' => 'new', 'qty' => 40, 'featured' => false, 'weight' => '0.400', 'dims' => ['25.0', '20.0', '4.0']],
        ['sku' => 'DEMO-EVA-004', 'name' => 'Folhas de EVA Adesivo (demonstração)', 'category' => 'eva', 'price' => '12.90', 'condition' => 'new', 'qty' => 25, 'featured' => false, 'weight' => '0.150', 'dims' => ['30.0', '21.0', '0.4']],
    ];

    public function run(): void
    {
        $this->guardEnvironment();

        $administrator = User::query()
            ->where('can_manage_catalog', true)
            ->where('can_manage_stock', true)
            ->orderBy('id')
            ->first();

        if ($administrator === null) {
            throw new RuntimeException('Nenhum usuário local com permissão de catálogo e estoque. Conceda as permissões antes de executar o seeder.');
        }

        $categories = app(CategoryService::class);
        $products = app(ProductService::class);

        $previousUser = Auth::user();
        Auth::setUser($administrator);

        try {
            DB::transaction(function () use ($categories, $products): void {
                $models = $this->seedCategories($categories);
                $this->seedProducts($products, $models);
            });
        } finally {
            if ($previousUser !== null) {
                Auth::setUser($previousUser);
            } else {
                Auth::forgetGuards();
            }
        }
    }

    private function guardEnvironment(): void
    {
        $local = app()->environment('local')
            && DB::connection()->getDriverName() === 'mysql'
            && DB::connection()->getDatabaseName() === 'loja_online';

        if (! $local) {
            throw new RuntimeException('Seeder de demonstração bloqueado: use somente APP_ENV=local com o banco loja_online.');
        }
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(CategoryService $categories): array
    {
        $models = [];

        // Pais antes dos filhos, para resolver `parent_category_id`.
        foreach (self::CATEGORIES as $key => $definition) {
            if ($definition['parent'] !== null) {
                continue;
            }
            $models[$key] = $this->category($categories, $definition, null);
        }

        foreach (self::CATEGORIES as $key => $definition) {
            if ($definition['parent'] === null) {
                continue;
            }
            $parent = $models[$definition['parent']];
            $models[$key] = $this->category($categories, $definition, $parent);
        }

        return $models;
    }

    /**
     * @param  array{name: string, slug: string, parent: ?string, sort: int}  $definition
     */
    private function category(CategoryService $categories, array $definition, ?Category $parent): Category
    {
        $existing = Category::withTrashed()->where('slug', $definition['slug'])->first();

        if ($existing !== null) {
            // Não altera categorias existentes: apenas reaproveita.
            return $existing;
        }

        return $categories->create([
            'name' => $definition['name'],
            'slug' => $definition['slug'],
            'description' => 'Categoria de demonstração para desenvolvimento local.',
            'parent_category_id' => $parent?->getKey(),
            'is_active' => true,
            'sort_order' => $definition['sort'],
        ]);
    }

    /**
     * @param  array<string, Category>  $categories
     */
    private function seedProducts(ProductService $products, array $categories): void
    {
        foreach (self::PRODUCTS as $product) {
            if (Product::withTrashed()->where('sku', $product['sku'])->exists()) {
                continue;
            }

            $slug = $this->slugFromSku($product['sku'], $product['name']);

            $products->create([
                'category_id' => $categories[$product['category']]->getKey(),
                'main_media_id' => null,
                'gallery_media_ids' => [],
                'name' => $product['name'],
                'slug' => $slug,
                'sku' => $product['sku'],
                'description' => '<p>Produto fictício criado apenas para demonstração e testes no ambiente local. Não representa um item físico disponível para venda.</p>',
                'condition' => $product['condition'],
                'condition_notes' => $product['condition'] === 'used' ? 'Demonstração: estado de conservação fictício.' : null,
                'price' => $product['price'],
                'promotion_enabled' => false,
                'is_active' => true,
                'is_featured' => $product['featured'],
                'stock_control_enabled' => true,
                'initial_stock_quantity' => $product['qty'],
                'minimum_stock_quantity' => 0,
                'stock_initial_reason' => self::STOCK_REASON,
                'stock_initial_reference_type' => self::STOCK_REFERENCE_TYPE,
                'stock_initial_reference_id' => $product['sku'],
                'shipping_profile' => [
                    'requires_shipping' => true,
                    'weight_kg' => $product['weight'],
                    'length_cm' => $product['dims'][0],
                    'width_cm' => $product['dims'][1],
                    'height_cm' => $product['dims'][2],
                ],
            ]);
        }
    }

    /**
     * Slug estável derivado do SKU, para que reexecuções não criem variações.
     */
    private function slugFromSku(string $sku, string $name): string
    {
        return strtolower($sku).'-'.str($name)->ascii()->slug()->limit(60, '');
    }
}
