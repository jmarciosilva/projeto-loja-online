<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Inicializa somente a conta explicitamente configurada para o ambiente local. */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $email = config('app.admin.email');

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->command?->warn('Administrador não inicializado. Defina ADMIN_EMAIL e execute novamente em ambiente local.');

            return;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            if (! $user->canManageCatalog()) {
                $user->forceFill(['can_manage_catalog' => true])->save();
            }

            return;
        }

        $password = config('app.admin.password');

        if (! is_string($password) || mb_strlen($password) < 12) {
            $this->command?->warn('Administrador não criado. Defina ADMIN_PASSWORD com pelo menos 12 caracteres.');

            return;
        }

        $user = User::query()->create([
            'name' => config('app.admin.name', 'Administrador da Loja'),
            'email' => $email,
            'password' => $password,
        ]);
        $user->forceFill(['can_manage_catalog' => true])->save();
    }
}
