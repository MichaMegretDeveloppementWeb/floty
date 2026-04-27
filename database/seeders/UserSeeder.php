<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@floty.test'],
            [
                'password' => Hash::make('password'),
                'first_name' => 'Renaud',
                'last_name' => 'Nicolas',
                'email_verified_at' => now(),
                'must_change_password' => false,
            ],
        );
    }
}
