<?php

namespace Tests\Feature\Management;

use App\Enums\LicenseAssignmentStatus;
use App\Enums\LicenseStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Licenses\Models\License;
use App\Modules\Licenses\Models\LicenseAssignment;
use App\Modules\Sectors\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_users_only_see_licenses_from_their_own_sectors(): void
    {
        $context = $this->licenseContext();

        $licenseA = $this->createLicense($context['sectorA'], 'Microsoft', 'Microsoft 365', 'Business Standard');
        $licenseB = $this->createLicense($context['sectorB'], 'SAP', 'SAP Business One', 'Profissional');

        $this->actingAs($context['sectorAdminA'])
            ->get(route('licenses.index'))
            ->assertOk()
            ->assertSee($licenseA->product_name)
            ->assertDontSee($licenseB->product_name);

        $this->actingAs($context['technicianA'])
            ->get(route('licenses.show', $licenseA))
            ->assertOk()
            ->assertSee($licenseA->displayName());

        $this->actingAs($context['sectorAdminA'])
            ->get(route('licenses.show', $licenseB))
            ->assertForbidden();

        $this->actingAs($context['requesterA'])
            ->get(route('licenses.index'))
            ->assertForbidden();
    }

    public function test_operational_menu_shows_licenses_and_requester_does_not_see_the_link(): void
    {
        $context = $this->licenseContext();

        $this->actingAs($context['technicianA'])
            ->get(route('knowledge-base.index'))
            ->assertOk()
            ->assertSee('Licencas');

        $this->actingAs($context['requesterA'])
            ->get(route('knowledge-base.index'))
            ->assertOk()
            ->assertDontSee('Licencas');
    }

    public function test_sector_admin_can_create_license_only_inside_allowed_sector(): void
    {
        $context = $this->licenseContext();

        $response = $this->actingAs($context['sectorAdminA'])->post(route('licenses.store'), [
            'sector_id' => $context['sectorA']->id,
            'vendor_name' => 'Microsoft',
            'product_name' => 'Microsoft 365',
            'plan_name' => 'E3',
            'seats_total' => 5,
            'status' => LicenseStatus::ACTIVE->value,
            'billing_cycle' => null,
            'cost_amount' => null,
            'cost_currency' => 'BRL',
            'purchased_at' => null,
            'renewal_date' => null,
            'expires_at' => null,
            'auto_renew' => false,
            'notes' => 'Primeira carga do setor.',
        ]);

        $license = License::query()->firstOrFail();

        $response->assertRedirect(route('licenses.show', $license, absolute: false));

        $this->assertDatabaseHas('licenses', [
            'id' => $license->id,
            'sector_id' => $context['sectorA']->id,
            'vendor_name' => 'Microsoft',
            'product_name' => 'Microsoft 365',
            'plan_name' => 'E3',
            'created_by' => $context['sectorAdminA']->id,
        ]);

        $blockedResponse = $this->actingAs($context['sectorAdminA'])
            ->from(route('licenses.create'))
            ->post(route('licenses.store'), [
                'sector_id' => $context['sectorB']->id,
                'vendor_name' => 'SAP',
                'product_name' => 'SAP Business One',
                'plan_name' => 'Profissional',
                'seats_total' => 2,
                'status' => LicenseStatus::ACTIVE->value,
                'auto_renew' => false,
            ]);

        $blockedResponse->assertRedirect(route('licenses.create', absolute: false));
        $blockedResponse->assertSessionHasErrors('sector_id');
        $this->assertDatabaseCount('licenses', 1);
    }

    public function test_license_index_uses_the_compact_layout_requested(): void
    {
        $context = $this->licenseContext();
        $license = $this->createLicense($context['sectorA'], 'Microsoft', 'Microsoft 365', 'Business Standard', 4);

        $this->actingAs($context['technicianA'])
            ->get(route('licenses.index'))
            ->assertOk()
            ->assertSee('Licenca')
            ->assertSee('Tipo')
            ->assertSee('Em uso')
            ->assertSee('Disponiveis')
            ->assertSee('Abrir')
            ->assertSee($license->product_name)
            ->assertDontSee('Renovacao')
            ->assertDontSee('Assentos');
    }

    public function test_license_show_focuses_on_current_assignments_and_hides_released_lines(): void
    {
        $context = $this->licenseContext();
        $license = $this->createLicense($context['sectorA'], 'SAP', 'SAP Business One', 'Profissional', 2);

        LicenseAssignment::query()->create([
            'license_id' => $license->id,
            'user_id' => $context['collaboratorA']->id,
            'assigned_email' => $context['collaboratorA']->email,
            'display_name' => $context['collaboratorA']->name,
            'external_reference' => 'ACTIVE-REF',
            'status' => LicenseAssignmentStatus::ACTIVE->value,
            'assigned_at' => now(),
            'created_by' => $context['technicianA']->id,
        ]);

        LicenseAssignment::query()->create([
            'license_id' => $license->id,
            'assigned_email' => 'released@empresa.test',
            'display_name' => 'Conta liberada',
            'external_reference' => 'OLD-REF',
            'status' => LicenseAssignmentStatus::RELEASED->value,
            'assigned_at' => now()->subDay(),
            'released_at' => now(),
            'created_by' => $context['technicianA']->id,
        ]);

        $this->actingAs($context['technicianA'])
            ->get(route('licenses.show', $license))
            ->assertOk()
            ->assertSee('Licencas atribuidas')
            ->assertSee('Atribuir licenca')
            ->assertSee('Transferir')
            ->assertSee('Desatribuir licenca')
            ->assertSee('ACTIVE-REF')
            ->assertDontSee('OLD-REF')
            ->assertDontSee('Novo vinculo de assento');
    }

    public function test_assignment_capacity_is_enforced_and_release_returns_the_license_with_history(): void
    {
        $context = $this->licenseContext();
        $license = $this->createLicense($context['sectorA'], 'Adobe', 'Creative Cloud', 'All Apps', 1);

        $this->actingAs($context['technicianA'])->post(route('licenses.assignments.store', $license), [
            'user_id' => $context['collaboratorA']->id,
            'status' => LicenseAssignmentStatus::ACTIVE->value,
        ])->assertRedirect(route('licenses.show', $license, absolute: false));

        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => License::class,
            'subject_id' => $license->id,
            'event' => 'license.assignment.created',
        ]);

        $blockedResponse = $this->actingAs($context['technicianA'])
            ->from(route('licenses.show', $license))
            ->post(route('licenses.assignments.store', $license), [
                'assigned_email' => 'extra@empresa.test',
                'status' => LicenseAssignmentStatus::ACTIVE->value,
            ]);

        $blockedResponse->assertRedirect(route('licenses.show', $license, absolute: false));
        $blockedResponse->assertSessionHasErrors('status');
        $this->assertDatabaseCount('license_assignments', 1);

        $assignment = LicenseAssignment::query()->firstOrFail();

        $this->actingAs($context['technicianA'])
            ->post(route('licenses.assignments.release', [$license, $assignment]))
            ->assertRedirect(route('licenses.show', $license, absolute: false));

        $this->assertDatabaseHas('license_assignments', [
            'id' => $assignment->id,
            'status' => LicenseAssignmentStatus::RELEASED->value,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => License::class,
            'subject_id' => $license->id,
            'event' => 'license.assignment.released',
        ]);

        $this->actingAs($context['technicianA'])->post(route('licenses.assignments.store', $license), [
            'assigned_email' => 'extra@empresa.test',
            'external_reference' => 'NEW-REF',
            'status' => LicenseAssignmentStatus::ACTIVE->value,
        ])->assertRedirect(route('licenses.show', $license, absolute: false));

        $this->assertDatabaseCount('license_assignments', 2);
        $this->assertSame(1, $license->fresh()->activeAssignments()->count());
    }

    public function test_same_collaborator_cannot_receive_same_active_license_twice(): void
    {
        $context = $this->licenseContext();
        $license = $this->createLicense($context['sectorA'], 'Microsoft', 'Power BI', 'Pro', 3);

        $this->actingAs($context['technicianA'])->post(route('licenses.assignments.store', $license), [
            'user_id' => $context['collaboratorA']->id,
            'status' => LicenseAssignmentStatus::ACTIVE->value,
        ])->assertRedirect(route('licenses.show', $license, absolute: false));

        $response = $this->actingAs($context['technicianA'])
            ->from(route('licenses.show', $license))
            ->post(route('licenses.assignments.store', $license), [
                'user_id' => $context['collaboratorA']->id,
                'external_reference' => 'PBI-002',
                'status' => LicenseAssignmentStatus::ACTIVE->value,
            ]);

        $response->assertRedirect(route('licenses.show', $license, absolute: false));
        $response->assertSessionHasErrors('user_id');

        $this->assertDatabaseCount('license_assignments', 1);
    }

    public function test_assignment_transfer_is_direct_and_logged_in_history(): void
    {
        $context = $this->licenseContext();
        $license = $this->createLicense($context['sectorA'], 'Microsoft', 'Microsoft 365', 'Business Premium', 2);
        $targetUser = User::factory()->create([
            'sector_id' => $context['sectorA']->id,
            'role' => UserRole::REQUESTER,
        ]);

        $assignment = LicenseAssignment::query()->create([
            'license_id' => $license->id,
            'user_id' => $context['collaboratorA']->id,
            'assigned_email' => $context['collaboratorA']->email,
            'display_name' => $context['collaboratorA']->name,
            'external_reference' => 'M365-001',
            'status' => LicenseAssignmentStatus::ACTIVE->value,
            'assigned_at' => now()->subDay(),
            'created_by' => $context['technicianA']->id,
        ]);

        $this->actingAs($context['technicianA'])
            ->post(route('licenses.assignments.transfer', [$license, $assignment]), [
                'user_id' => $targetUser->id,
                'external_reference' => 'M365-002',
            ])
            ->assertRedirect(route('licenses.show', $license, absolute: false));

        $this->assertDatabaseHas('license_assignments', [
            'id' => $assignment->id,
            'user_id' => $targetUser->id,
            'assigned_email' => $targetUser->email,
            'display_name' => $targetUser->name,
            'external_reference' => 'M365-002',
            'status' => LicenseAssignmentStatus::ACTIVE->value,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => License::class,
            'subject_id' => $license->id,
            'event' => 'license.assignment.transferred',
        ]);

        $this->actingAs($context['technicianA'])
            ->get(route('licenses.show', $license))
            ->assertOk()
            ->assertSee('Licenca transferida.')
            ->assertSee($targetUser->name)
            ->assertSee('M365-002');
    }

    public function test_assignment_transfer_replaces_stale_email_with_selected_collaborator_email(): void
    {
        $context = $this->licenseContext();
        $license = $this->createLicense($context['sectorA'], 'SAP', 'SAP Business One', 'Profissional', 2);
        $targetUser = User::factory()->create([
            'sector_id' => $context['sectorA']->id,
            'role' => UserRole::REQUESTER,
            'email' => 'sap.fernanda-oliveira@aia-tech.local',
            'name' => 'FERNANDA OLIVEIRA',
        ]);

        $assignment = LicenseAssignment::query()->create([
            'license_id' => $license->id,
            'user_id' => $context['collaboratorA']->id,
            'assigned_email' => 'sap.amanda-alves@aia-tech.local',
            'display_name' => 'AMANDA ALVES',
            'external_reference' => 'AMANDA.ALVES',
            'status' => LicenseAssignmentStatus::ACTIVE->value,
            'assigned_at' => now()->subDay(),
            'created_by' => $context['technicianA']->id,
        ]);

        $this->actingAs($context['technicianA'])
            ->post(route('licenses.assignments.transfer', [$license, $assignment]), [
                'user_id' => $targetUser->id,
                'assigned_email' => 'sap.amanda-alves@aia-tech.local',
                'external_reference' => 'FERNANDA.OLIVEIRA',
            ])
            ->assertRedirect(route('licenses.show', $license, absolute: false));

        $this->assertDatabaseHas('license_assignments', [
            'id' => $assignment->id,
            'user_id' => $targetUser->id,
            'assigned_email' => $targetUser->email,
            'display_name' => $targetUser->name,
            'external_reference' => 'FERNANDA.OLIVEIRA',
        ]);
    }

    public function test_assignment_transfer_cannot_duplicate_same_active_license_for_target_collaborator(): void
    {
        $context = $this->licenseContext();
        $license = $this->createLicense($context['sectorA'], 'SAP', 'SAP Business One', 'Profissional', 2);
        $targetUser = User::factory()->create([
            'sector_id' => $context['sectorA']->id,
            'role' => UserRole::REQUESTER,
        ]);

        $assignmentToTransfer = LicenseAssignment::query()->create([
            'license_id' => $license->id,
            'user_id' => $context['collaboratorA']->id,
            'assigned_email' => $context['collaboratorA']->email,
            'display_name' => $context['collaboratorA']->name,
            'external_reference' => 'SAP-001',
            'status' => LicenseAssignmentStatus::ACTIVE->value,
            'assigned_at' => now()->subDay(),
            'created_by' => $context['technicianA']->id,
        ]);

        LicenseAssignment::query()->create([
            'license_id' => $license->id,
            'user_id' => $targetUser->id,
            'assigned_email' => $targetUser->email,
            'display_name' => $targetUser->name,
            'external_reference' => 'SAP-002',
            'status' => LicenseAssignmentStatus::ACTIVE->value,
            'assigned_at' => now()->subHours(12),
            'created_by' => $context['technicianA']->id,
        ]);

        $response = $this->actingAs($context['technicianA'])
            ->from(route('licenses.show', $license))
            ->post(route('licenses.assignments.transfer', [$license, $assignmentToTransfer]), [
                'user_id' => $targetUser->id,
                'assigned_email' => $targetUser->email,
            ]);

        $response->assertRedirect(route('licenses.show', $license, absolute: false));
        $response->assertSessionHasErrors('user_id');

        $this->assertDatabaseHas('license_assignments', [
            'id' => $assignmentToTransfer->id,
            'user_id' => $context['collaboratorA']->id,
            'assigned_email' => $context['collaboratorA']->email,
        ]);
    }

    public function test_license_cannot_be_reduced_below_active_assignments(): void
    {
        $context = $this->licenseContext();
        $license = $this->createLicense($context['sectorA'], 'Microsoft', 'Power BI', 'Pro', 2);

        LicenseAssignment::query()->create([
            'license_id' => $license->id,
            'user_id' => $context['collaboratorA']->id,
            'assigned_email' => $context['collaboratorA']->email,
            'display_name' => $context['collaboratorA']->name,
            'status' => LicenseAssignmentStatus::ACTIVE->value,
            'assigned_at' => now(),
            'created_by' => $context['sectorAdminA']->id,
        ]);

        $response = $this->actingAs($context['sectorAdminA'])
            ->from(route('licenses.edit', $license))
            ->put(route('licenses.update', $license), [
                'sector_id' => $context['sectorA']->id,
                'vendor_name' => $license->vendor_name,
                'product_name' => $license->product_name,
                'plan_name' => $license->plan_name,
                'license_reference' => $license->license_reference,
                'supplier_name' => $license->supplier_name,
                'seats_total' => 0,
                'status' => $license->status->value,
                'billing_cycle' => null,
                'cost_amount' => null,
                'cost_currency' => 'BRL',
                'purchased_at' => null,
                'renewal_date' => null,
                'expires_at' => null,
                'auto_renew' => false,
                'notes' => $license->notes,
            ]);

        $response->assertRedirect(route('licenses.edit', $license, absolute: false));
        $response->assertSessionHasErrors('seats_total');

        $this->assertSame(2, $license->fresh()->seats_total);
    }

    private function licenseContext(): array
    {
        $company = Company::query()->create([
            'name' => 'Empresa Licencas',
            'is_active' => true,
        ]);

        $sectorA = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Tecnologia',
            'slug' => 'tecnologia-licencas',
            'is_active' => true,
        ]);

        $sectorB = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Financeiro',
            'slug' => 'financeiro-licencas',
            'is_active' => true,
        ]);

        $sectorAdminA = User::factory()->create([
            'sector_id' => $sectorA->id,
            'role' => UserRole::SECTOR_ADMIN,
        ]);

        $technicianA = User::factory()->create([
            'sector_id' => $sectorA->id,
            'role' => UserRole::TECHNICIAN,
        ]);

        $requesterA = User::factory()->create([
            'sector_id' => $sectorA->id,
            'role' => UserRole::REQUESTER,
        ]);

        $collaboratorA = User::factory()->create([
            'sector_id' => $sectorA->id,
            'role' => UserRole::REQUESTER,
        ]);

        $sectorAdminB = User::factory()->create([
            'sector_id' => $sectorB->id,
            'role' => UserRole::SECTOR_ADMIN,
        ]);

        return compact('company', 'sectorA', 'sectorB', 'sectorAdminA', 'technicianA', 'requesterA', 'collaboratorA', 'sectorAdminB');
    }

    private function createLicense(
        Sector $sector,
        string $vendor,
        string $product,
        ?string $plan,
        int $seatsTotal = 5,
    ): License {
        return License::query()->create([
            'sector_id' => $sector->id,
            'vendor_name' => $vendor,
            'product_name' => $product,
            'plan_name' => $plan,
            'seats_total' => $seatsTotal,
            'status' => LicenseStatus::ACTIVE->value,
            'auto_renew' => false,
        ]);
    }
}
