<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@floty.test'],
            [
                'password' => Hash::make('password'),
                'first_name' => 'Renaud',
                'last_name' => 'Nicolas',
                'email_verified_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'michamegret.dev@gmail.com'],
            [
                'password' => Hash::make('password'),
                'first_name' => 'Micha',
                'last_name' => 'Megret',
                'email_verified_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'renaud.nicolas@sogema.net'],
            [
                'password' => Hash::make('password'),
                'first_name' => 'Renaud',
                'last_name' => 'Nicolas',
                'email_verified_at' => now(),
            ],
        );
    }
}
