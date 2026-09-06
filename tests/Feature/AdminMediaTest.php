<?php

namespace Tests\Feature;

use App\Exceptions\MediaInUseException;
use App\Models\Media;
use App\Models\User;
use App\Services\MediaService;
use App\Services\MediaUsageRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AdminMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_media_operations(): void
    {
        $media = Media::factory()->create();
        foreach ([['get', '/admin/midias'], ['post', '/admin/midias'], ['delete', '/admin/midias/'.$media->id]] as [$method, $uri]) {
            $this->{$method}($uri)->assertRedirect('/login');
        }
    }

    public function test_empty_registry_allows_deletion_and_removes_file(): void
    {
        Storage::fake('public');
        $media = Media::factory()->create(['disk' => 'public', 'path' => 'media/test.jpg']);
        Storage::disk('public')->put($media->path, 'image');

        app(MediaService::class)->delete($media);

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($media->path);
    }

    public function test_empty_and_false_checkers_do_not_mark_media_as_used(): void
    {
        $media = Media::factory()->create();
        $registry = app(MediaUsageRegistry::class);
        $this->assertSame([], $registry->usages($media));
        $this->assertFalse($registry->isInUse($media));
        $registry->register('Ignorado', fn (): bool => false);
        $this->assertSame([], $registry->usages($media));
        $this->assertFalse($registry->isInUse($media));
    }

    public function test_shared_registry_blocks_media_in_use(): void
    {
        Storage::fake('public');
        $media = Media::factory()->create(['disk' => 'public', 'path' => 'media/used.jpg']);
        Storage::disk('public')->put($media->path, 'image');
        app(MediaUsageRegistry::class)->register('Uso de teste', fn (Media $candidate): bool => $candidate->is($media));

        $exception = null;
        try {
            app(MediaService::class)->delete($media);
        } catch (MediaInUseException $caught) {
            $exception = $caught;
        }
        $this->assertInstanceOf(MediaInUseException::class, $exception);
        $this->assertSame(['Uso de teste'], $exception->usages);
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_multiple_checkers_return_only_the_labels_in_use(): void
    {
        $media = Media::factory()->create();
        $registry = app(MediaUsageRegistry::class);
        $registry->register('A', fn (): bool => false);
        $registry->register('B', fn (): bool => true);
        $registry->register('C', fn (): bool => true);
        $this->assertSame(['B', 'C'], $registry->usages($media));
        $this->assertTrue($registry->isInUse($media));
    }

    public function test_authenticated_user_can_upload_and_delete_media(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user)->post('/admin/midias', ['file' => UploadedFile::fake()->image('imagem.jpg', 50, 50)])
            ->assertRedirect(route('admin.media.index'))->assertSessionHas('status');
        $media = Media::query()->sole();
        Storage::disk('public')->assertExists($media->path);
        $this->actingAs($user)->delete('/admin/midias/'.$media->id)
            ->assertRedirect(route('admin.media.index'))->assertSessionHas('status');
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($media->path);
    }

    public function test_invalid_upload_and_missing_media_follow_laravel_defaults(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/admin/midias', [])->assertSessionHasErrors('file');
        $this->assertSame(0, Media::query()->count());
        $this->actingAs($user)->delete('/admin/midias/99999')->assertNotFound();
    }

    public function test_http_delete_keeps_media_in_use_and_shows_its_label(): void
    {
        Storage::fake('public');
        $media = Media::factory()->create(['disk' => 'public', 'path' => 'media/locked.jpg']);
        Storage::disk('public')->put($media->path, 'image');
        app(MediaUsageRegistry::class)->register('Uso de teste', fn (): bool => true);
        $this->actingAs(User::factory()->create())->delete('/admin/midias/'.$media->id)
            ->assertRedirect(route('admin.media.index'))->assertSessionHas('error', 'Não é possível excluir esta mídia: em uso por Uso de teste.');
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_failed_physical_delete_keeps_database_deletion_and_logs_context(): void
    {
        $media = Media::factory()->create(['disk' => 'public', 'path' => 'media/fail.jpg']);
        $filesystem = Mockery::mock();
        Storage::shouldReceive('disk')->once()->with('public')->andReturn($filesystem);
        $filesystem->shouldReceive('delete')->once()->with($media->path)->andReturnFalse();
        Log::shouldReceive('warning')->once()->withArgs(function (string $message, array $context) use ($media): bool {
            return $context === ['media_id' => $media->id, 'disk' => 'public', 'path' => $media->path];
        });

        app(MediaService::class)->delete($media);

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    public function test_exception_from_physical_delete_keeps_database_deletion_and_logs_context(): void
    {
        $media = Media::factory()->create(['disk' => 'public', 'path' => 'media/exception.jpg']);
        $filesystem = Mockery::mock();
        Storage::shouldReceive('disk')->once()->with('public')->andReturn($filesystem);
        $filesystem->shouldReceive('delete')->once()->with($media->path)->andThrow(new \RuntimeException('filesystem'));
        Log::shouldReceive('warning')->once()->withArgs(function (string $message, array $context) use ($media): bool {
            return $context['media_id'] === $media->id && $context['disk'] === 'public' && $context['path'] === $media->path;
        });

        app(MediaService::class)->delete($media);

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    public function test_database_failure_propagates_before_filesystem_is_touched(): void
    {
        $media = Media::factory()->create(['disk' => 'public', 'path' => 'media/database.jpg']);
        Storage::shouldReceive('disk')->never();
        Media::deleting(fn (): never => throw new \RuntimeException('database failure'));

        try {
            app(MediaService::class)->delete($media);
            $this->fail('A falha do banco deveria ser propagada.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('database failure', $exception->getMessage());
        } finally {
            Media::flushEventListeners();
        }

        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }

    public function test_authenticated_user_sees_media_listing(): void
    {
        $media = Media::factory()->create(['original_name' => '<script>alert(1)</script>']);

        $this->actingAs(User::factory()->create())->get('/admin/midias')
            ->assertOk()->assertSee('Mídia')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
