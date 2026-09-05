<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_page_can_be_persisted_with_the_supported_fields(): void
    {
        Page::create([
            'title' => 'Quem Somos',
            'slug' => 'quem-somos',
            'content' => '# Quem Somos',
            'status' => PageStatus::Draft,
            'meta_title' => 'Quem Somos | Loja Online',
            'meta_description' => 'A história da loja.',
        ]);

        $this->assertDatabaseHas('pages', [
            'title' => 'Quem Somos',
            'slug' => 'quem-somos',
            'content' => '# Quem Somos',
            'status' => 'draft',
            'meta_title' => 'Quem Somos | Loja Online',
            'meta_description' => 'A história da loja.',
        ]);
    }

    public function test_the_seo_fields_are_optional(): void
    {
        $page = Page::create([
            'title' => 'Contato',
            'slug' => 'contato',
            'content' => '',
            'status' => PageStatus::Draft,
        ]);

        $this->assertNull($page->fresh()->meta_title);
        $this->assertNull($page->fresh()->meta_description);
    }

    public function test_a_draft_status_is_read_back_as_an_enum(): void
    {
        $page = Page::create([
            'title' => 'Rascunho',
            'slug' => 'rascunho',
            'content' => '',
            'status' => PageStatus::Draft,
        ]);

        $this->assertSame(PageStatus::Draft, $page->fresh()->status);
        $this->assertDatabaseHas('pages', ['slug' => 'rascunho', 'status' => 'draft']);
    }

    public function test_a_published_status_is_read_back_as_an_enum(): void
    {
        $page = Page::create([
            'title' => 'Publicada',
            'slug' => 'publicada',
            'content' => '',
            'status' => PageStatus::Published,
        ]);

        $this->assertSame(PageStatus::Published, $page->fresh()->status);
        $this->assertDatabaseHas('pages', ['slug' => 'publicada', 'status' => 'published']);
    }

    public function test_the_status_accepts_its_backing_value(): void
    {
        $page = Page::create([
            'title' => 'Publicada',
            'slug' => 'publicada',
            'content' => '',
            'status' => 'published',
        ]);

        $this->assertSame(PageStatus::Published, $page->fresh()->status);
    }

    public function test_deleting_a_page_is_a_soft_delete(): void
    {
        $page = Page::create([
            'title' => 'Quem Somos',
            'slug' => 'quem-somos',
            'content' => '',
            'status' => PageStatus::Draft,
        ]);

        $page->delete();

        $this->assertSoftDeleted('pages', ['id' => $page->id]);
        $this->assertNull(Page::query()->find($page->id));
        $this->assertNotNull(Page::withTrashed()->find($page->id));
    }

    public function test_unsupported_fields_are_not_mass_assignable(): void
    {
        $page = Page::create([
            'id' => 999,
            'title' => 'Quem Somos',
            'slug' => 'quem-somos',
            'content' => '',
            'status' => PageStatus::Draft,
            'deleted_at' => now(),
            'author_id' => 7,
        ]);

        $this->assertNotSame(999, $page->id);
        $this->assertNull($page->deleted_at);
        $this->assertArrayNotHasKey('author_id', $page->getAttributes());
        $this->assertDatabaseHas('pages', ['slug' => 'quem-somos', 'deleted_at' => null]);
    }

    public function test_the_identity_is_stable_when_the_slug_changes(): void
    {
        $page = Page::create([
            'title' => 'Quem Somos',
            'slug' => 'quem-somos',
            'content' => '',
            'status' => PageStatus::Draft,
        ]);
        $identity = $page->id;

        $page->update(['slug' => 'sobre-a-empresa']);

        $this->assertSame($identity, $page->fresh()->id);
        $this->assertSame('sobre-a-empresa', $page->fresh()->slug);
    }

    public function test_the_route_key_remains_the_identity_and_not_the_slug(): void
    {
        $this->assertSame('id', (new Page)->getRouteKeyName());
    }

    public function test_the_slug_is_unique_in_the_database(): void
    {
        Page::create([
            'title' => 'Quem Somos',
            'slug' => 'quem-somos',
            'content' => '',
            'status' => PageStatus::Draft,
        ]);

        $this->expectException(QueryException::class);

        Page::create([
            'title' => 'Quem Somos de Novo',
            'slug' => 'quem-somos',
            'content' => '',
            'status' => PageStatus::Draft,
        ]);
    }
}
