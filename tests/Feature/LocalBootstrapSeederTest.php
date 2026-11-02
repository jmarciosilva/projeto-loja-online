<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LocalBootstrapSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_creates_only_the_explicitly_configured_catalog_administrator(): void
    {
        config([
            'app.admin.name' => 'Admin local',
            'app.admin.email' => 'admin@example.test',
            'app.admin.password' => 'uma-senha-local-segura',
        ]);
        $this->app['env'] = 'local';

        app(DatabaseSeeder::class)->run();

        $admin = User::query()->sole();
        $this->assertSame('Admin local', $admin->name);
        $this->assertSame('admin@example.test', $admin->email);
        $this->assertTrue(Hash::check('uma-senha-local-segura', $admin->password));
        $this->assertTrue($admin->canManageCatalog());
        $this->assertDatabaseCount('pages', 0);
        $this->assertDatabaseCount('menus', 0);
    }

    public function test_bootstrap_without_administrator_configuration_creates_no_account(): void
    {
        config([
            'app.admin.email' => null,
            'app.admin.password' => null,
        ]);
        $this->app['env'] = 'local';

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('pages', 0);
    }

    public function test_bootstrap_rerun_preserves_existing_credentials_and_profile(): void
    {
        $originalPassword = Hash::make('senha-antiga-da-conta');
        $admin = User::factory()->create([
            'name' => 'Nome mantido',
            'email' => 'admin@example.test',
            'password' => $originalPassword,
            'email_verified_at' => now()->subDay(),
        ]);
        config([
            'app.admin.name' => 'Outro nome',
            'app.admin.email' => $admin->email,
            'app.admin.password' => 'senha-nova-que-nao-deve-ser-usada',
        ]);
        $this->app['env'] = 'local';

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $admin->refresh();
        $this->assertSame('Nome mantido', $admin->name);
        $this->assertSame($originalPassword, $admin->password);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue($admin->canManageCatalog());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_bootstrap_does_not_create_admin_outside_local_environment(): void
    {
        config([
            'app.admin.email' => 'admin@example.test',
            'app.admin.password' => 'uma-senha-local-segura',
        ]);
        $this->app['env'] = 'production';

        app(DatabaseSeeder::class)->run();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_user_without_catalog_permission_is_denied_by_catalog_policy(): void
    {
        $this->app['env'] = 'local';
        $user = User::factory()->create(['can_manage_catalog' => false]);

        $this->actingAs($user)->get('/admin/categorias')->assertForbidden();
        $this->actingAs($user)->get('/admin/produtos')->assertForbidden();
        $this->assertFalse($user->fresh()->canManageCatalog());
    }
}
