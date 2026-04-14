<?php

namespace Tests\Feature\Seeders;

use App\Enums\GlobalUserRole;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_seeder_creates_the_default_account(): void
    {
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        putenv('SUPER_ADMIN_NAME=Plantao Admin');
        putenv('SUPER_ADMIN_EMAIL=plantao@example.com');
        putenv('SUPER_ADMIN_PASSWORD=secret-123');

        $this->seed(SuperAdminSeeder::class);

        $user = User::query()->where('email', 'plantao@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('Plantao Admin', $user->name);
        $this->assertSame(UserRole::SUPER_ADMIN, $user->role);
        $this->assertSame(GlobalUserRole::SUPER_ADMIN, $user->global_role);
        $this->assertTrue($user->isSuperAdmin());
    }
}
