<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Services\PageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class PageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_be_resolved_by_the_laravel_container(): void
    {
        $this->assertInstanceOf(PageService::class, app(PageService::class));
    }

    public function test_it_creates_a_page_with_the_supported_fields(): void
    {
        $page = $this->service()->create([
            'title' => 'Quem Somos',
            'content' => '# Quem Somos',
            'status' => PageStatus::Published,
            'meta_title' => 'Quem Somos | Loja Online',
            'meta_description' => 'A história da loja.',
        ]);

        $this->assertTrue($page->exists);
        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'title' => 'Quem Somos',
            'slug' => 'quem-somos',
            'content' => '# Quem Somos',
            'status' => 'published',
            'meta_title' => 'Quem Somos | Loja Online',
            'meta_description' => 'A história da loja.',
        ]);
    }

    public function test_a_created_page_defaults_to_draft(): void
    {
        $page = $this->service()->create(['title' => 'Quem Somos']);

        $this->assertSame(PageStatus::Draft, $page->fresh()->status);
    }

    public function test_it_rejects_a_page_without_a_title(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->create(['content' => 'sem título']);
    }

    public function test_it_rejects_an_unsupported_status(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->create(['title' => 'Quem Somos', 'status' => 'archived']);
    }

    public function test_it_does_not_persist_unsupported_fields(): void
    {
        $page = $this->service()->create([
            'id' => 999,
            'title' => 'Quem Somos',
            'author_id' => 7,
            'deleted_at' => now(),
        ]);

        $this->assertNotSame(999, $page->id);
        $this->assertNull($page->deleted_at);
        $this->assertArrayNotHasKey('author_id', $page->getAttributes());
    }

    /* Slug gerado automaticamente */

    public function test_creating_without_a_slug_derives_it_from_the_title(): void
    {
        $page = $this->service()->create(['title' => 'Quem Somos']);

        $this->assertSame('quem-somos', $page->slug);
    }

    public function test_a_generated_slug_normalizes_accents_and_spaces(): void
    {
        $page = $this->service()->create(['title' => 'Política de Privacidade']);

        $this->assertSame('politica-de-privacidade', $page->slug);
    }

    public function test_a_generated_slug_respects_the_canonical_format(): void
    {
        $page = $this->service()->create(['title' => '  Trocas   &   Devoluções!  ']);

        $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $page->slug);
    }

    public function test_the_first_generated_collision_receives_the_suffix_two(): void
    {
        $this->service()->create(['title' => 'Quem Somos']);

        $page = $this->service()->create(['title' => 'Quem Somos']);

        $this->assertSame('quem-somos-2', $page->slug);
    }

    public function test_the_second_generated_collision_receives_the_suffix_three(): void
    {
        $this->service()->create(['title' => 'Quem Somos']);
        $this->service()->create(['title' => 'Quem Somos']);

        $page = $this->service()->create(['title' => 'Quem Somos']);

        $this->assertSame('quem-somos-3', $page->slug);
    }

    public function test_the_generated_sequence_is_deterministic(): void
    {
        $slugs = [];

        foreach (range(1, 4) as $ignored) {
            $slugs[] = $this->service()->create(['title' => 'Quem Somos'])->slug;
        }

        $this->assertSame(['quem-somos', 'quem-somos-2', 'quem-somos-3', 'quem-somos-4'], $slugs);
    }

    public function test_an_empty_slug_is_treated_as_a_request_to_generate_one(): void
    {
        $page = $this->service()->create(['title' => 'Quem Somos', 'slug' => '   ']);

        $this->assertSame('quem-somos', $page->slug);
    }

    /* Slug explícito */

    public function test_an_available_canonical_slug_is_accepted(): void
    {
        $page = $this->service()->create([
            'title' => 'Política de Privacidade',
            'slug' => 'privacidade',
        ]);

        $this->assertSame('privacidade', $page->slug);
    }

    public function test_a_duplicated_explicit_slug_is_rejected(): void
    {
        $this->service()->create(['title' => 'Política de Privacidade']);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->create([
            'title' => 'Outra Política',
            'slug' => 'politica-de-privacidade',
        ]);
    }

    public function test_a_duplicated_explicit_slug_is_not_silently_suffixed(): void
    {
        $this->service()->create(['title' => 'Política de Privacidade']);

        try {
            $this->service()->create([
                'title' => 'Outra Política',
                'slug' => 'politica-de-privacidade',
            ]);
            $this->fail('An explicit duplicated slug must be rejected.');
        } catch (InvalidArgumentException) {
            // A rejeição é o contrato: o endereço pedido não pode virar outro.
        }

        $this->assertDatabaseMissing('pages', ['slug' => 'politica-de-privacidade-2']);
        $this->assertDatabaseMissing('pages', ['title' => 'Outra Política']);
        $this->assertSame(1, Page::withTrashed()->count());
    }

    public function test_an_explicit_slug_of_a_soft_deleted_page_is_rejected(): void
    {
        $page = $this->service()->create(['title' => 'Quem Somos']);
        $this->service()->delete($page);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->create(['title' => 'Quem Somos Outra Vez', 'slug' => 'quem-somos']);
    }

    public function test_an_explicit_slug_outside_the_canonical_format_is_rejected(): void
    {
        $invalidSlugs = [
            'quem somos',
            'quem/somos',
            '../quem-somos',
            'quem-somos?utm=1',
            'quem-somos#topo',
            '<b>quem-somos</b>',
            'Quem-Somos',
            'quem_somos',
            '-quem-somos',
            'quem-somos-',
            'quem--somos',
            'https://exemplo.com/quem-somos',
        ];

        foreach ($invalidSlugs as $slug) {
            try {
                $this->service()->create(['title' => 'Quem Somos', 'slug' => $slug]);
                $this->fail("The slug [{$slug}] should have been rejected.");
            } catch (InvalidArgumentException) {
                // Formato inválido não vira endereço público.
            }
        }

        $this->assertSame(0, Page::withTrashed()->count());
    }

    public function test_an_explicit_slug_with_an_invalid_type_is_rejected(): void
    {
        $invalidSlugs = [
            'integer' => 123,
            'boolean' => true,
            'array' => [],
            'float' => 12.5,
        ];

        foreach ($invalidSlugs as $type => $slug) {
            try {
                $this->service()->create(['title' => 'Quem Somos', 'slug' => $slug]);
                $this->fail("A {$type} slug should have been rejected.");
            } catch (InvalidArgumentException) {
                // Tipo inválido não é pedido de geração automática.
            }
        }

        $this->assertSame(0, Page::withTrashed()->count());
    }

    public function test_an_explicit_null_slug_still_requests_generation(): void
    {
        $page = $this->service()->create(['title' => 'Quem Somos', 'slug' => null]);

        $this->assertSame('quem-somos', $page->slug);
    }

    public function test_updating_with_an_invalid_slug_type_is_rejected(): void
    {
        $page = $this->service()->create(['title' => 'Quem Somos']);

        try {
            $this->service()->update($page, ['title' => 'Sobre a Empresa', 'slug' => 123]);
            $this->fail('An integer slug should have been rejected.');
        } catch (InvalidArgumentException) {
            // Nada é persistido quando o endereço chega malformado.
        }

        $this->assertSame('Quem Somos', $page->fresh()->title);
        $this->assertSame('quem-somos', $page->fresh()->slug);
    }

    /* Comprimento do slug */

    public function test_an_explicit_slug_with_the_maximum_length_is_accepted(): void
    {
        $slug = str_repeat('a', 255);

        $page = $this->service()->create(['title' => 'Quem Somos', 'slug' => $slug]);

        $this->assertSame($slug, $page->fresh()->slug);
        $this->assertSame(255, strlen($page->fresh()->slug));
    }

    public function test_an_explicit_slug_longer_than_the_column_is_rejected_before_persistence(): void
    {
        $slug = str_repeat('a', 256);

        try {
            $this->service()->create(['title' => 'Quem Somos', 'slug' => $slug]);
            $this->fail('A slug longer than the column should have been rejected.');
        } catch (InvalidArgumentException) {
            // A regra é do serviço; o banco é a proteção final, não a primeira.
        }

        $this->assertSame(0, Page::withTrashed()->count());
    }

    public function test_a_very_long_title_generates_a_slug_within_the_column_limit(): void
    {
        $title = $this->longTitle();
        $this->assertLessThanOrEqual(255, mb_strlen($title), 'O título precisa caber na coluna.');
        $this->assertGreaterThan(255, strlen(Str::slug($title)), 'O slug precisa estourar o limite.');

        $page = $this->service()->create(['title' => $title]);

        $this->assertLessThanOrEqual(255, strlen($page->slug));
        $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $page->slug);
        $this->assertSame($page->slug, $page->fresh()->slug);
    }

    public function test_a_slug_already_at_the_limit_makes_room_for_its_collision_suffix(): void
    {
        $title = str_repeat('a', 255);

        $first = $this->service()->create(['title' => $title]);
        $second = $this->service()->create(['title' => $title]);
        $third = $this->service()->create(['title' => $title]);

        $this->assertSame(255, strlen($first->slug));
        $this->assertSame(str_repeat('a', 253).'-2', $second->slug);
        $this->assertSame(str_repeat('a', 253).'-3', $third->slug);
        $this->assertSame(255, strlen($second->slug));
        $this->assertSame(255, strlen($third->slug));
    }

    public function test_a_long_generated_slug_still_collides_into_a_deterministic_suffix(): void
    {
        $title = $this->longTitle();

        $first = $this->service()->create(['title' => $title]);
        $second = $this->service()->create(['title' => $title]);
        $third = $this->service()->create(['title' => $title]);

        $this->assertStringEndsWith('-2', $second->slug);
        $this->assertStringEndsWith('-3', $third->slug);

        foreach ([$first, $second, $third] as $page) {
            $this->assertLessThanOrEqual(255, strlen($page->slug));
            $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $page->slug);
        }

        $this->assertCount(3, array_unique([$first->slug, $second->slug, $third->slug]));
    }

    public function test_a_long_generated_slug_is_deterministic(): void
    {
        $title = $this->longTitle();

        $first = $this->service()->create(['title' => $title])->slug;
        $second = $this->service()->create(['title' => $title])->slug;

        Page::withTrashed()->each(fn (Page $page) => $page->forceDelete());

        $this->assertSame($first, $this->service()->create(['title' => $title])->slug);
        $this->assertSame($second, $this->service()->create(['title' => $title])->slug);
    }

    /* Atualização */

    public function test_updating_only_the_title_preserves_the_slug(): void
    {
        $page = $this->service()->create(['title' => 'Quem Somos']);

        $this->service()->update($page, ['title' => 'Sobre a Empresa']);

        $this->assertSame('Sobre a Empresa', $page->fresh()->title);
        $this->assertSame('quem-somos', $page->fresh()->slug);
    }

    public function test_updating_the_slug_explicitly_persists_the_new_address(): void
    {
        $page = $this->service()->create(['title' => 'Quem Somos']);

        $this->service()->update($page, ['slug' => 'sobre-a-empresa']);

        $this->assertSame('sobre-a-empresa', $page->fresh()->slug);
    }

    public function test_updating_the_slug_does_not_change_the_identity(): void
    {
        $page = $this->service()->create(['title' => 'Quem Somos']);
        $identity = $page->id;

        $this->service()->update($page, ['title' => 'Sobre a Empresa', 'slug' => 'sobre-a-empresa']);

        $this->assertSame($identity, $page->fresh()->id);
    }

    public function test_updating_to_a_slug_owned_by_another_page_is_rejected(): void
    {
        $page = $this->service()->create(['title' => 'Quem Somos']);
        $this->service()->create(['title' => 'Contato']);

        try {
            $this->service()->update($page, ['slug' => 'contato']);
            $this->fail('A slug owned by another page must be rejected.');
        } catch (InvalidArgumentException) {
            // Unicidade preservada.
        }

        $this->assertSame('quem-somos', $page->fresh()->slug);
    }

    public function test_updating_to_a_slug_owned_by_a_soft_deleted_page_is_rejected(): void
    {
        $page = $this->service()->create(['title' => 'Quem Somos']);
        $deleted = $this->service()->create(['title' => 'Contato']);
        $this->service()->delete($deleted);

        try {
            $this->service()->update($page, ['slug' => 'contato']);
            $this->fail('A slug owned by a soft deleted page must be rejected.');
        } catch (InvalidArgumentException) {
            // O endereço continua reservado mesmo após a exclusão lógica.
        }

        $this->assertSame('quem-somos', $page->fresh()->slug);
    }

    public function test_a_page_does_not_collide_with_its_own_slug(): void
    {
        $page = $this->service()->create(['title' => 'Quem Somos']);

        $this->service()->update($page, ['title' => 'Sobre a Empresa', 'slug' => 'quem-somos']);

        $this->assertSame('quem-somos', $page->fresh()->slug);
        $this->assertSame('Sobre a Empresa', $page->fresh()->title);
    }

    public function test_updating_changes_only_the_fields_provided(): void
    {
        $page = $this->service()->create([
            'title' => 'Quem Somos',
            'content' => '# Original',
            'status' => PageStatus::Published,
            'meta_title' => 'Original',
        ]);

        $this->service()->update($page, ['title' => 'Sobre a Empresa']);

        $fresh = $page->fresh();
        $this->assertSame('# Original', $fresh->content);
        $this->assertSame(PageStatus::Published, $fresh->status);
        $this->assertSame('Original', $fresh->meta_title);
    }

    /* Exclusão lógica */

    public function test_it_soft_deletes_a_page(): void
    {
        $page = $this->service()->create(['title' => 'Quem Somos']);

        $this->service()->delete($page);

        $this->assertSoftDeleted('pages', ['id' => $page->id]);
    }

    public function test_a_soft_deleted_slug_is_not_reused_by_generation(): void
    {
        $page = $this->service()->create(['title' => 'Quem Somos']);
        $this->service()->delete($page);

        $recreated = $this->service()->create(['title' => 'Quem Somos']);

        $this->assertSame('quem-somos-2', $recreated->slug);
        $this->assertNotSame('quem-somos', $recreated->slug);
    }

    public function test_slug_availability_considers_soft_deleted_pages(): void
    {
        $page = $this->service()->create(['title' => 'Quem Somos']);

        $this->assertFalse($this->service()->slugIsAvailable('quem-somos'));

        $this->service()->delete($page);

        $this->assertFalse($this->service()->slugIsAvailable('quem-somos'));
        $this->assertTrue($this->service()->slugIsAvailable('quem-somos-2'));
        $this->assertTrue($this->service()->slugIsAvailable('quem-somos', $page->id));
    }

    private function service(): PageService
    {
        return app(PageService::class);
    }

    /**
     * Título que cabe na coluna, mas cujo slug não caberia.
     *
     * `Str::slug` transcreve `ß` como `ss`, então o slug sai com o dobro do
     * comprimento do título. O título precisa caber em `varchar(255)`: um
     * título grande demais falharia por outro motivo e o teste passaria a
     * medir a coisa errada.
     */
    private function longTitle(): string
    {
        return trim(str_repeat('ßß ', 85));
    }
}
