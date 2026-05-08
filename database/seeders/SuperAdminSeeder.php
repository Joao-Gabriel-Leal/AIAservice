<?php

namespace Database\Seeders;

use App\Enums\GlobalUserRole;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'admin@aiaservice.local')],
            [
                'name' => env('SUPER_ADMIN_NAME', 'Dev Admin'),
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'password')),
                'role' => UserRole::DEV,
                'global_role' => GlobalUserRole::DEV,
                'sector_id' => null,
                'room_id' => null,
                'must_change_password' => false,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
