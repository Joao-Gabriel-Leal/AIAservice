<?php

namespace Tests\Feature\Exports;

use App\Enums\AssetStatus;
use App\Enums\GlobalUserRole;
use App\Enums\KnowledgeBaseVisibility;
use App\Enums\LicenseAssignmentStatus;
use App\Enums\LicenseStatus;
use App\Enums\TicketPriority;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Assets\Services\AssetMovementService;
use App\Modules\Companies\Models\Company;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use App\Modules\Licenses\Models\License;
use App\Modules\Licenses\Models\LicenseAssignment;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Services\SectorProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithExcelDownloads;
use Tests\TestCase;

class ExcelExportTest extends TestCase
{
    use InteractsWithExcelDownloads;
    use RefreshDatabase;

    public function test_assets_export_respects_current_filters(): void
    {
        ['sector' => $sector, 'room' => $room, 'collaborator' => $collaborator] = $this->assetScope();
        $admin = User::factory()->superAdmin()->create();
        $service = app(AssetMovementService::class);

        $expected = $service->register([
            'name' => 'Notebook Export',
            'serial_number' => 'EXP-001',
            'status' => AssetStatus::EM_USO->value,
            'current_sector_id' => $sector->id,
            'current_room_id' => $room->id,
            'current_user_id' => $collaborator->id,
        ], $admin);

        $service->register([
            'name' => 'Monitor Fora',
            'serial_number' => 'EXP-002',
            'status' => AssetStatus::DISPONIVEL->value,
            'current_sector_id' => $sector->id,
            'current_room_id' => $room->id,
            'current_user_id' => null,
        ], $admin);

        $spreadsheet = $this->spreadsheetFromResponse(
            $this->actingAs($admin)->get(route('assets.export', [
                'status' => AssetStatus::EM_USO->value,
                'user_id' => $collaborator->id,
            ])),
        );

        $rows = $this->sheetValues($spreadsheet->getSheet(0));
        $content = implode("\n", $rows);

        $this->assertStringContainsString('PAT-000001 | Notebook Export | EXP-001', $content);
        $this->assertStringContainsString($collaborator->name, $content);
        $this->assertStringNotContainsString('Monitor Fora', $content);
        $this->assertSame('Patrimonios', $spreadsheet->getSheet(0)->getTitle());
        $this->assertSame('PAT-000001', $expected->asset_code);
    }

    public function test_licenses_export_respects_sector_scope_and_search_filters(): void
    {
        $company = Company::query()->create(['name' => 'Empresa Licencas Export', 'is_active' => true]);
        $sectorA = Sector::query()->create(['company_id' => $company->id, 'name' => 'TI Export', 'slug' => 'ti-export', 'is_active' => true]);
        $sectorB = Sector::query()->create(['company_id' => $company->id, 'name' => 'RH Export', 'slug' => 'rh-export', 'is_active' => true]);
        $technician = User::factory()->create(['sector_id' => $sectorA->id, 'role' => UserRole::TECHNICIAN]);
        $collaborator = User::factory()->create(['sector_id' => $sectorA->id]);

        $visibleLicense = License::query()->create([
            'sector_id' => $sectorA->id,
            'vendor_name' => 'Microsoft',
            'product_name' => 'Microsoft 365',
            'plan_name' => 'Business Standard',
            'seats_total' => 3,
            'status' => LicenseStatus::ACTIVE->value,
            'auto_renew' => false,
        ]);

        LicenseAssignment::query()->create([
            'license_id' => $visibleLicense->id,
            'user_id' => $collaborator->id,
            'assigned_email' => $collaborator->email,
            'display_name' => $collaborator->name,
            'status' => LicenseAssignmentStatus::ACTIVE->value,
            'assigned_at' => now(),
        ]);

        License::query()->create([
            'sector_id' => $sectorB->id,
            'vendor_name' => 'SAP',
            'product_name' => 'SAP Business One',
            'plan_name' => 'Profissional',
            'seats_total' => 1,
            'status' => LicenseStatus::ACTIVE->value,
            'auto_renew' => false,
        ]);

        $spreadsheet = $this->spreadsheetFromResponse(
            $this->actingAs($technician)->get(route('licenses.export', [
                'search' => $collaborator->email,
            ])),
        );

        $rows = $this->sheetValues($spreadsheet->getSheet(0));
        $content = implode("\n", $rows);

        $this->assertStringContainsString('Microsoft | Microsoft 365 | Business Standard', $content);
        $this->assertStringNotContainsString('SAP Business One', $content);
        $this->assertSame('Licencas', $spreadsheet->getSheet(0)->getTitle());
    }

    public function test_dashboard_export_generates_all_analytical_tabs(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketScope();
        $requester = User::factory()->create(['sector_id' => $sector->id, 'room_id' => $room->id]);

        Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Chamado exportado',
            'description' => 'Teste dashboard',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        $spreadsheet = $this->spreadsheetFromResponse(
            $this->actingAs($requester)->get(route('dashboard.export', ['period' => 30])),
        );

        $this->assertSame([
            'Resumo',
            'Fila de atencao',
            'Chamados recentes',
            'Volume por dia',
            'Distribuicao por status',
            'Distribuicao por prioridade',
            'Licencas',
            'Ativos',
            'Base de conhecimento',
        ], $spreadsheet->getSheetNames());
        $summaryRows = $this->sheetValues($spreadsheet->getSheet(0));
        $this->assertContains('Periodo | 30 dias', $summaryRows);
        $this->assertStringContainsString('Chamado exportado', implode("\n", $this->sheetValues($spreadsheet->getSheet(2))));
    }

    public function test_tickets_export_respects_selected_sector(): void
    {
        ['sector' => $sectorA, 'room' => $roomA, 'board' => $boardA, 'group' => $groupA, 'status' => $statusA] = $this->ticketScope('Tecnologia');
        ['sector' => $sectorB, 'room' => $roomB, 'board' => $boardB, 'group' => $groupB, 'status' => $statusB] = $this->ticketScope('Financeiro', 'Empresa Tickets 2');
        $technician = User::factory()->create(['sector_id' => $sectorA->id, 'room_id' => $roomA->id, 'role' => UserRole::TECHNICIAN]);
        $requester = User::factory()->create(['sector_id' => $sectorA->id, 'room_id' => $roomA->id]);

        Ticket::query()->create([
            'sector_id' => $sectorA->id,
            'ticket_board_id' => $boardA->id,
            'ticket_group_id' => $groupA->id,
            'ticket_status_id' => $statusA->id,
            'room_id' => $roomA->id,
            'title' => 'Chamado TI',
            'description' => 'Visible',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::HIGH,
            'last_activity_at' => now(),
        ]);

        Ticket::query()->create([
            'sector_id' => $sectorB->id,
            'ticket_board_id' => $boardB->id,
            'ticket_group_id' => $groupB->id,
            'ticket_status_id' => $statusB->id,
            'room_id' => $roomB->id,
            'title' => 'Chamado Financeiro',
            'description' => 'Hidden',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::LOW,
            'last_activity_at' => now(),
        ]);

        $spreadsheet = $this->spreadsheetFromResponse(
            $this->actingAs($technician)->get(route('tickets.export', ['sector' => $sectorA->id])),
        );

        $rows = $this->sheetValues($spreadsheet->getSheet(0));
        $this->assertStringContainsString('Chamado TI', implode("\n", $rows));
        $this->assertStringNotContainsString('Chamado Financeiro', implode("\n", $rows));
    }

    public function test_knowledge_base_export_respects_search(): void
    {
        $sector = $this->knowledgeSector();
        $user = User::factory()->create([
            'global_role' => GlobalUserRole::COLLABORATOR,
            'sector_id' => $sector->id,
        ]);

        KnowledgeBaseArticle::query()->create([
            'sector_id' => $sector->id,
            'created_by' => User::factory()->superAdmin()->create()->id,
            'title' => 'Reset de senha',
            'summary' => 'Fluxo para redefinir credenciais',
            'content' => 'Conteudo senha',
            'visibility' => KnowledgeBaseVisibility::PUBLIC,
            'is_active' => true,
        ]);

        KnowledgeBaseArticle::query()->create([
            'sector_id' => $sector->id,
            'created_by' => User::factory()->superAdmin()->create()->id,
            'title' => 'VPN corporativa',
            'summary' => 'Acesso remoto',
            'content' => 'Conteudo vpn',
            'visibility' => KnowledgeBaseVisibility::PUBLIC,
            'is_active' => true,
        ]);

        $spreadsheet = $this->spreadsheetFromResponse(
            $this->actingAs($user)->get(route('knowledge-base.export', ['search' => 'senha'])),
        );

        $rows = $this->sheetValues($spreadsheet->getSheet(0));
        $this->assertStringContainsString('Reset de senha', implode("\n", $rows));
        $this->assertStringNotContainsString('VPN corporativa', implode("\n", $rows));
    }

    public function test_users_export_respects_role_status_and_sector_filters(): void
    {
        $company = Company::query()->create(['name' => 'Empresa Usuarios', 'is_active' => true]);
        $sectorA = Sector::query()->create(['company_id' => $company->id, 'name' => 'TI', 'slug' => 'ti', 'is_active' => true]);
        $sectorB = Sector::query()->create(['company_id' => $company->id, 'name' => 'RH', 'slug' => 'rh', 'is_active' => true]);
        $admin = User::factory()->superAdmin()->create();

        User::query()->create([
            'name' => 'Tecnico Ativo',
            'email' => 'tecnico@empresa.test',
            'password' => 'password',
            'role' => UserRole::TECHNICIAN,
            'global_role' => GlobalUserRole::COLLABORATOR,
            'sector_id' => $sectorA->id,
            'is_active' => true,
        ])->sectorAccesses()->create(['sector_id' => $sectorA->id, 'access_level' => 'technician']);

        User::query()->create([
            'name' => 'Solicitante Inativo',
            'email' => 'requester@empresa.test',
            'password' => 'password',
            'role' => UserRole::REQUESTER,
            'global_role' => GlobalUserRole::COLLABORATOR,
            'sector_id' => $sectorB->id,
            'is_active' => false,
        ])->sectorAccesses()->create(['sector_id' => $sectorB->id, 'access_level' => 'requester']);

        $spreadsheet = $this->spreadsheetFromResponse(
            $this->actingAs($admin)->get(route('users.export', [
                'status' => 'active',
                'sector_id' => $sectorA->id,
                'global_role' => GlobalUserRole::COLLABORATOR->value,
            ])),
        );

        $rows = $this->sheetValues($spreadsheet->getSheet(0));
        $this->assertStringContainsString('Tecnico Ativo', implode("\n", $rows));
        $this->assertStringNotContainsString('Solicitante Inativo', implode("\n", $rows));
    }

    public function test_companies_sectors_and_rooms_exports_follow_filters(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $companyA = Company::query()->create(['name' => 'Empresa Alfa', 'is_active' => true]);
        $companyB = Company::query()->create(['name' => 'Empresa Beta', 'is_active' => false]);
        $sectorA = Sector::query()->create(['company_id' => $companyA->id, 'name' => 'Suporte', 'slug' => 'suporte', 'is_active' => true]);
        $sectorB = Sector::query()->create(['company_id' => $companyB->id, 'name' => 'Compras', 'slug' => 'compras', 'is_active' => false]);
        Room::query()->create(['sector_id' => $sectorA->id, 'name' => 'Sala Azul', 'is_active' => true]);
        Room::query()->create(['sector_id' => $sectorB->id, 'name' => 'Sala Vermelha', 'is_active' => false]);

        $companies = $this->sheetValues($this->spreadsheetFromResponse(
            $this->actingAs($admin)->get(route('companies.export', ['status' => 'active'])),
        )->getSheet(0));

        $sectors = $this->sheetValues($this->spreadsheetFromResponse(
            $this->actingAs($admin)->get(route('sectors.export', ['company_id' => $companyA->id])),
        )->getSheet(0));

        $rooms = $this->sheetValues($this->spreadsheetFromResponse(
            $this->actingAs($admin)->get(route('rooms.export', ['sector_id' => $sectorA->id, 'status' => 'active'])),
        )->getSheet(0));

        $this->assertStringContainsString('Empresa Alfa', implode("\n", $companies));
        $this->assertStringNotContainsString('Empresa Beta', implode("\n", $companies));
        $this->assertStringContainsString('Suporte', implode("\n", $sectors));
        $this->assertStringNotContainsString('Compras', implode("\n", $sectors));
        $this->assertStringContainsString('Sala Azul', implode("\n", $rooms));
        $this->assertStringNotContainsString('Sala Vermelha', implode("\n", $rooms));
    }

    private function assetScope(): array
    {
        $company = Company::query()->create(['name' => 'Empresa Patrimonio', 'is_active' => true]);
        $sector = Sector::query()->create(['company_id' => $company->id, 'name' => 'Tecnologia', 'slug' => 'tecnologia', 'is_active' => true]);
        $room = Room::query()->create(['sector_id' => $sector->id, 'name' => 'Sala TI', 'is_active' => true]);
        $collaborator = User::factory()->create(['sector_id' => $sector->id, 'room_id' => $room->id]);

        return compact('company', 'sector', 'room', 'collaborator');
    }

    private function knowledgeSector(): Sector
    {
        $company = Company::query()->create(['name' => 'Empresa KB', 'is_active' => true]);

        return Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Tecnologia',
            'slug' => 'tecnologia-kb',
            'is_active' => true,
        ]);
    }

    private function ticketScope(string $sectorName = 'Atendimento', string $companyName = 'Empresa Tickets'): array
    {
        $company = Company::query()->create(['name' => $companyName, 'is_active' => true]);
        $sector = Sector::query()->create(['company_id' => $company->id, 'name' => $sectorName, 'slug' => str($sectorName)->slug(), 'is_active' => true]);
        $room = Room::query()->create(['sector_id' => $sector->id, 'name' => 'Sala '.$sectorName, 'is_active' => true]);
        $board = app(SectorProvisioningService::class)->provision($sector);

        return [
            'company' => $company,
            'sector' => $sector,
            'room' => $room,
            'board' => $board,
            'group' => $board->groups()->firstOrFail(),
            'status' => $board->statuses()->where('is_closed', false)->firstOrFail(),
        ];
    }
}
