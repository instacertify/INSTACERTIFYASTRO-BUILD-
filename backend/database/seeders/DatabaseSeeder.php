<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@instacertify.com');

        if (! User::where('email', $email)->exists()) {
            $password = env('ADMIN_PASSWORD') ?: Str::password(16, symbols: false);

            User::create([
                'name' => 'Instacertify Admin',
                'email' => $email,
                'password' => $password,
            ]);

            if (! env('ADMIN_PASSWORD')) {
                $this->command?->warn("Admin user created: {$email} / {$password} — change it after first login.");
            }
        }

        $this->call(ContentSeeder::class);
    }
}
