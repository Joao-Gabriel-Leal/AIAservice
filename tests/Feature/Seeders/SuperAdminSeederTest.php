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

    public function test_super_admin_seeder_creates_the_default_dev_account(): void
    {
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $this->seed(SuperAdminSeeder::class);

        $user = User::query()->where('email', 'admin@aiaservice.local')->first();

        $this->assertNotNull($user);
        $this->assertSame(UserRole::DEV, $user->role);
        $this->assertSame(GlobalUserRole::DEV, $user->global_role);
        $this->assertTrue($user->isGlobalAdmin());
    }
}
