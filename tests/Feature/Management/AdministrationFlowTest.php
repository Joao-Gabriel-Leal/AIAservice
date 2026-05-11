<?php

namespace Tests\Feature\Management;

use App\Enums\GlobalUserRole;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Users\Http\Controllers\UserController;
use App\Modules\Users\Notifications\AccountCreatedNotification;
use App\Modules\Users\Notifications\DefaultPasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdministrationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_admin_can_manage_core_administration_entities(): void
    {
        Notification::fake();

        $admin = User::factory()->developer()->create();
        $this->actingAs($admin);

        $companyResponse = $this->post(route('companies.store'), [
            'name' => 'Empresa Base',
            'legal_name' => 'Empresa Base LTDA',
            'document' => '00.000.000/0001-00',
            'email' => 'contato@empresa.test',
            'phone' => '11999990000',
            'is_active' => '1',
        ]);

        $company = Company::query()->firstOrFail();
        $companyResponse->assertRedirect(route('companies.show', $company, absolute: false));

        $this->get(route('companies.show', $company))
            ->assertOk()
            ->assertSee('Empresa Base')
            ->assertSee('Nenhum setor cadastrado para esta empresa.');

        $this->get(route('sectors.create', [
            'company_id' => $company->id,
            'return_to_company_id' => $company->id,
        ]))
            ->assertOk()
            ->assertSee('Empresa Base');

        $this->post(route('sectors.store'), [
            'company_id' => $company->id,
            'name' => 'Tecnologia',
            'color' => '#1D4ED8',
            'description' => 'Setor de TI',
            'is_active' => '1',
            'return_to_company_id' => $company->id,
        ])->assertRedirect(route('companies.show', $company, absolute: false));

        $sector = Sector::query()->firstOrFail();
        $this->assertNotNull($sector->board()->first());
        $this->assertSame('#1D4ED8', $sector->displayColor());

        $this->get(route('rooms.create', [
            'sector_id' => $sector->id,
            'return_to_company_id' => $company->id,
        ]))
            ->assertOk()
            ->assertSee('Tecnologia');

        $this->post(route('rooms.store'), [
            'sector_id' => $sector->id,
            'name' => 'Sala 01',
            'description' => 'Sala principal',
            'is_active' => '1',
            'return_to_company_id' => $company->id,
        ])->assertRedirect(route('companies.show', $company, absolute: false));

        $this->get(route('companies.show', $company))
            ->assertOk()
            ->assertSee('Tecnologia')
            ->assertSee('Sala 01');

        $temporaryPassword = null;

        $this->post(route('users.store'), [
            'name' => 'Tecnico Padrao',
            'email' => 'tecnico@example.com',
            'global_role' => GlobalUserRole::COLLABORATOR->value,
            'sector_accesses' => [
                $sector->id => 'technician',
            ],
            'must_change_password' => '1',
            'is_active' => '1',
        ])
            ->assertRedirect(route('users.index', absolute: false))
            ->assertSessionHas('created_user_access', function (array $access) use (&$temporaryPassword) {
                $temporaryPassword = $access['password'] ?? null;

                return ($access['email'] ?? null) === 'tecnico@example.com'
                    && ($access['login_url'] ?? null) === url('/')
                    && filled($temporaryPassword);
            });

        $createdUser = User::query()->where('email', 'tecnico@example.com')->firstOrFail();

        $this->assertNotNull($temporaryPassword);
        $this->assertTrue(Hash::check($temporaryPassword, $createdUser->password));
        $this->assertTrue($createdUser->must_change_password);

        $this->assertDatabaseHas('users', [
            'id' => $createdUser->id,
            'email' => 'tecnico@example.com',
            'global_role' => GlobalUserRole::COLLABORATOR->value,
            'role' => UserRole::TECHNICIAN->value,
            'sector_id' => $sector->id,
            'room_id' => null,
        ]);

        $this->assertDatabaseHas('user_sector_accesses', [
            'user_id' => $createdUser->id,
            'sector_id' => $sector->id,
            'access_level' => 'technician',
        ]);

        Notification::assertSentTo(
            $createdUser,
            AccountCreatedNotification::class,
            fn (AccountCreatedNotification $notification, array $channels) => in_array('database', $channels, true)
                && in_array('mail', $channels, true)
                && data_get($notification->toArray($createdUser), 'must_change_password') === true
        );
    }

    public function test_developer_has_full_global_administration_access(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Dev Rooms',
            'is_active' => true,
        ]);
        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Infra',
            'slug' => 'infra',
            'is_active' => true,
        ]);
        $developer = User::factory()->developer()->create();

        $this->actingAs($developer)
            ->get(route('rooms.index'))
            ->assertOk();

        $this->get(route('sectors.index'))->assertOk();

        $this->post(route('rooms.store'), [
            'sector_id' => $sector->id,
            'name' => 'Sala Dev',
            'description' => 'Criada por perfil Dev',
            'is_active' => '1',
        ])->assertRedirect(route('rooms.index', absolute: false));

        $this->assertDatabaseHas('rooms', [
            'sector_id' => $sector->id,
            'name' => 'Sala Dev',
        ]);

        $this->get(route('users.index'))->assertOk();
        $this->get(route('companies.index'))->assertOk();
    }

    public function test_global_admin_can_open_user_profile_from_users_index(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Perfil',
            'is_active' => true,
        ]);
        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Tecnologia',
            'slug' => 'tecnologia',
            'is_active' => true,
        ]);

        $admin = User::factory()->developer()->create();
        $profileUser = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@aiaservice.local',
            'job_title' => 'TESTE',
            'location' => 'Guara -DF',
            'birth_date' => '2002-06-09',
            'work_status' => 'medical_leave',
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $this->actingAs($admin);

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee(route('users.show', $profileUser, absolute: false));

        $this->get(route('users.show', $profileUser))
            ->assertOk()
            ->assertSeeText('Super Admin')
            ->assertSeeText('TESTE')
            ->assertSeeText('admin@aiaservice.local')
            ->assertSeeText('Guara -DF')
            ->assertSeeText('09/06/2002')
            ->assertSeeText('Licenca medica')
            ->assertSeeText('Editar usuario')
            ->assertSeeText('Nome')
            ->assertSeeText('Perfil global')
            ->assertSeeText('Acessos por setor')
            ->assertSee(route('users.update', $profileUser, absolute: false));

        $this->get(route('users.edit', $profileUser))
            ->assertRedirect(route('users.show', $profileUser, absolute: false).'#editar-usuario');

        $this->put(route('users.update', $profileUser), [
            'name' => 'Esley Atualizado',
            'email' => 'esley.atualizado@aiaservice.local',
            'global_role' => GlobalUserRole::COLLABORATOR->value,
            'sector_accesses' => [
                $sector->id => 'technician',
            ],
            'is_active' => '1',
        ])->assertRedirect(route('users.show', $profileUser, absolute: false));

        $this->assertDatabaseHas('users', [
            'id' => $profileUser->id,
            'name' => 'Esley Atualizado',
            'email' => 'esley.atualizado@aiaservice.local',
        ]);
    }

    public function test_global_admin_can_reset_user_to_default_password_and_notify(): void
    {
        Notification::fake();

        $company = Company::query()->create([
            'name' => 'Empresa Reset Senha',
            'is_active' => true,
        ]);
        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Suporte Reset',
            'slug' => 'suporte-reset',
            'is_active' => true,
        ]);

        $admin = User::factory()->developer()->create();
        $profileUser = User::factory()->create([
            'name' => 'Usuario Reset',
            'email' => 'usuario.reset@aiaservice.local',
            'password' => Hash::make('SenhaAntiga@123'),
            'must_change_password' => false,
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $this->actingAs($admin)
            ->get(route('users.show', $profileUser))
            ->assertOk()
            ->assertSee(route('users.reset-default-password', $profileUser, absolute: false))
            ->assertSeeText('Aplicar senha padrao');

        $this->actingAs($admin)
            ->post(route('users.reset-default-password', $profileUser))
            ->assertRedirect(route('users.show', $profileUser, absolute: false))
            ->assertSessionHas('status', 'Senha redefinida para o padrao e e-mail enviado ao usuario.');

        $profileUser->refresh();

        $this->assertTrue(Hash::check(UserController::DEFAULT_PASSWORD, $profileUser->password));
        $this->assertTrue($profileUser->must_change_password);

        Notification::assertSentTo(
            $profileUser,
            DefaultPasswordResetNotification::class,
            fn (DefaultPasswordResetNotification $notification, array $channels): bool => in_array('database', $channels, true)
                && in_array('mail', $channels, true)
                && $notification->temporaryPassword() === UserController::DEFAULT_PASSWORD
                && data_get($notification->toArray($profileUser), 'must_change_password') === true
        );
    }

    public function test_company_context_redirects_after_sector_and_room_changes(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Contexto',
            'is_active' => true,
        ]);

        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Infra',
            'slug' => 'infra',
            'is_active' => true,
        ]);

        $room = Room::query()->create([
            'sector_id' => $sector->id,
            'name' => 'Sala Antiga',
            'is_active' => true,
        ]);

        $developer = User::factory()->developer()->create();
        $this->actingAs($developer);

        $this->patch(route('sectors.update', $sector), [
            'company_id' => $company->id,
            'name' => 'Infraestrutura',
            'color' => '#1D4ED8',
            'description' => 'Setor atualizado',
            'is_active' => '1',
            'return_to_company_id' => $company->id,
        ])->assertRedirect(route('companies.show', $company, absolute: false));

        $this->patch(route('rooms.update', $room), [
            'sector_id' => $sector->id,
            'name' => 'Sala 02',
            'description' => 'Sala atualizada',
            'is_active' => '1',
            'return_to_company_id' => $company->id,
        ])->assertRedirect(route('companies.show', $company, absolute: false));

        $this->delete(route('rooms.destroy', $room), [
            'return_to_company_id' => $company->id,
        ])->assertRedirect(route('companies.show', $company, absolute: false));

        $this->delete(route('sectors.destroy', $sector), [
            'return_to_company_id' => $company->id,
        ])->assertRedirect(route('companies.show', $company, absolute: false));
    }

    public function test_sector_admin_cannot_access_other_sector_records(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Scope',
            'is_active' => true,
        ]);

        $sectorA = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Setor A',
            'slug' => 'setor-a',
            'is_active' => true,
        ]);

        $sectorB = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Setor B',
            'slug' => 'setor-b',
            'is_active' => true,
        ]);

        $roomA = Room::query()->create([
            'sector_id' => $sectorA->id,
            'name' => 'Sala A',
            'is_active' => true,
        ]);

        $roomB = Room::query()->create([
            'sector_id' => $sectorB->id,
            'name' => 'Sala B',
            'is_active' => true,
        ]);

        $sectorAdmin = User::factory()->create([
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $sectorA->id,
            'room_id' => $roomA->id,
        ]);

        $foreignUser = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sectorB->id,
            'room_id' => $roomB->id,
        ]);

        $this->actingAs($sectorAdmin);

        $this->get(route('companies.index'))->assertForbidden();
        $this->get(route('users.show', $foreignUser))->assertForbidden();
        $this->get(route('users.edit', $foreignUser))->assertForbidden();
        $this->get(route('rooms.index'))->assertForbidden();
    }
}
