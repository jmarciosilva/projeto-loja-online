<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(MediaService::DISK);
    }

    public function test_parent_category_page_includes_active_products_from_its_subcategory(): void
    {
        $root = Category::factory()->create(['name' => 'Livros Usados', 'slug' => 'livros-usados', 'is_active' => true]);
        $child = Category::factory()->create(['name' => 'Livros de Fantasia', 'slug' => 'livros-de-fantasia', 'parent_category_id' => $root->id, 'is_active' => true]);
        $parentProduct = Product::factory()->create(['category_id' => $root->id, 'name' => 'Produto na principal', 'slug' => 'produto-principal', 'sku' => 'LIV-ROOT', 'is_active' => true]);
        $childProduct = Product::factory()->create(['category_id' => $child->id, 'name' => 'O Hobbit', 'slug' => 'o-hobbit', 'sku' => 'LIV-HOBBIT', 'is_active' => true]);

        $response = $this->get('/categorias/livros-usados')->assertOk()
            ->assertSee('Livros de Fantasia')->assertSee('O Hobbit')->assertSee('Produto na principal');
        $this->assertSame($childProduct->id, Product::query()->where('slug', 'o-hobbit')->value('id'));
        $this->assertDatabaseHas('categories', ['id' => $child->id, 'parent_category_id' => $root->id]);
        $this->assertDatabaseHas('products', ['id' => $parentProduct->id, 'category_id' => $root->id]);
    }

    public function test_home_and_navigation_list_roots_with_subcategory_links(): void
    {
        $root = Category::factory()->create(['name' => 'Livros Usados', 'slug' => 'livros-usados', 'is_active' => true]);
        Category::factory()->create(['name' => 'Livros de Literatura', 'slug' => 'livros-literatura', 'parent_category_id' => $root->id, 'is_active' => true]);

        $html = (string) $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('Livros Usados', $html);
        $this->assertStringContainsString('Livros de Literatura', $html);
        $this->assertStringContainsString('/categorias/livros-literatura', $html);
    }

    public function test_home_category_tiles_count_only_published_products_and_include_descendants_in_root_total(): void
    {
        $root = Category::factory()->create(['name' => 'Livros Usados', 'slug' => 'livros-count', 'is_active' => true]);
        $child = Category::factory()->create(['name' => 'Fantasia', 'slug' => 'fantasia-count', 'parent_category_id' => $root->id, 'is_active' => true]);
        Product::factory()->create(['category_id' => $root->id, 'slug' => 'root-counted', 'sku' => 'CNT-ROOT', 'is_active' => true]);
        Product::factory()->create(['category_id' => $child->id, 'slug' => 'child-counted', 'sku' => 'CNT-CHILD', 'is_active' => true]);
        Product::factory()->create(['category_id' => $child->id, 'slug' => 'child-hidden-count', 'sku' => 'CNT-HIDDEN', 'is_active' => false]);

        $this->get('/')->assertOk()
            ->assertSee('Livros Usados')
            ->assertSee('2 produtos')
            ->assertSee('Fantasia')
            ->assertSee('1 produto');
    }

    public function test_inactive_parent_hides_child_category_and_products_from_public_catalog(): void
    {
        $root = Category::factory()->create(['slug' => 'livros-inativos', 'is_active' => false]);
        $child = Category::factory()->create(['slug' => 'subcat-inativa', 'parent_category_id' => $root->id, 'is_active' => true]);
        Product::factory()->create(['category_id' => $child->id, 'slug' => 'produto-subcat-inativa', 'sku' => 'SUB-INATIVA', 'is_active' => true]);

        $this->get('/categorias/subcat-inativa')->assertNotFound();
        $this->get('/produtos')->assertDontSee('produto-subcat-inativa');
    }

    public function test_admin_can_create_a_subcategory_but_nested_parent_is_rejected(): void
    {
        $admin = User::factory()->create();
        DB::table('users')->where('id', $admin->id)->update(['can_manage_catalog' => true]);
        $root = Category::factory()->create(['is_active' => true]);
        $this->actingAs($admin->fresh())->post('/admin/categorias', [
            'name' => 'Livros de Informática', 'slug' => 'livros-de-informatica',
            'parent_category_id' => $root->id, 'is_active' => '1',
        ])->assertRedirect(route('admin.categories.index'));
        $child = Category::query()->where('slug', 'livros-de-informatica')->firstOrFail();
        $this->assertSame($root->id, $child->parent_category_id);

        $this->actingAs($admin->fresh())->post('/admin/categorias', [
            'name' => 'Nível três', 'slug' => 'nivel-tres', 'parent_category_id' => $child->id,
        ])->assertSessionHasErrors('parent_category_id');
    }
}
