<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Crée le compte admin au premier déploiement, à partir de ADMIN_EMAIL / ADMIN_PASSWORD.
 * Ne touche jamais à un compte existant.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('app.admin_email');
        $password = env('ADMIN_PASSWORD');

        if (! $password || User::query()->where('email', $email)->exists()) {
            return;
        }

        User::create([
            'name' => env('ADMIN_NAME', 'Admin BIG BOSS'),
            'email' => $email,
            'password' => $password,
            'is_active' => true,
        ]);

        $this->command?->info("Compte admin créé : {$email}");
    }
}
