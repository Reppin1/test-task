<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Один админ для входа в Filament: ADMIN_EMAIL / ADMIN_PASSWORD из .env.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@example.com');
        $password = (string) env('ADMIN_PASSWORD', 'password');

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Desk Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        $this->command->info(sprintf('Filament admin: %s / %s', $user->email, $password));
    }
}
