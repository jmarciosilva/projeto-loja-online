<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Administrador de desenvolvimento local.
 *
 * E-mail e senha vêm de DEV_ADMIN_EMAIL e DEV_ADMIN_PASSWORD no .env local,
 * fora do Git. A senha é armazenada como hash pelo cast `hashed` do model.
 *
 * Execução: `php artisan db:seed --class=DevelopmentAdminSeeder`.
 * Usuários existentes não têm a senha alterada. Só recebem as permissões
 * administrativas que ainda não possuem. Nenhum outro usuário é modificado.
 */
class DevelopmentAdminSeeder extends Seeder
{
    /** Permissões do sistema necessárias para catálogo e estoque. */
    private const PERMISSIONS = [
        'can_manage_catalog',
        'can_manage_stock',
        'can_view_stock_movements',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DevelopmentAdminSeeder bloqueado fora dos ambientes local e testing.');
        }

        $email = config('app.dev_admin.email');
        $password = config('app.dev_admin.password');

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Defina DEV_ADMIN_EMAIL com um e-mail válido no .env local.');
        }

        if (! is_string($password) || $password === '') {
            throw new RuntimeException('Defina DEV_ADMIN_PASSWORD no .env local.');
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => config('app.dev_admin.name'),
                'password' => $password,
            ],
        );

        $missing = array_filter(
            self::PERMISSIONS,
            fn (string $permission): bool => ! (bool) $user->{$permission},
        );

        if ($missing !== []) {
            $user->forceFill(array_fill_keys($missing, true))->save();
        }
    }
}
