<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Default Super Admin',
                'email' => 'superadmin@example.com',
                'role' => User::ROLE_SUPERADMIN,
            ],
            [
                'name' => 'Default Admin',
                'email' => 'admin@example.com',
                'role' => User::ROLE_ADMIN,
            ],
            [
                'name' => 'Default Encoder',
                'email' => 'encoder@example.com',
                'role' => User::ROLE_ENCODER,
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('Password123!'),
                    'role' => $user['role'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
