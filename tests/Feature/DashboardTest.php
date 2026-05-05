<?php

namespace Tests\Feature;

use App\Enums\AssetAllocationStatus;
use App\Enums\AssetStatus;
use App\Enums\KnowledgeBaseArticleStatus;
use App\Enums\KnowledgeBaseVisibility;
use App\Enums\LicenseAssignmentStatus;
use App\Enums\LicenseStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketTimeEntryApprovalStatus;
use App\Enums\TicketTimeEntrySource;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Assets\Models\Asset;
use App\Modules\Companies\Models\Company;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use App\Modules\Licenses\Models\License;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Services\SectorProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_dashboard_renders_visual_sections_and_chart_payloads(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('dashboard', ['period' => 30]));

        $response->assertOk();
        $response->assertSeeText('Volume no periodo');
        $response->assertSeeText('Distribuicao por status');
        $response->assertSeeText('Saude operacional');
        $response->assertSeeText('Fila de atencao');
        $response->assertSeeText('Base de conhecimento');
        $response->assertSeeText('Ativos pendentes');
        $response->assertSeeText('Proximas renovacoes');
        $response->assertDontSeeText('Abrir quadro');
        $response->assertDontSeeText('Novo chamado');
        $response->assertDontSeeText('Licencas vencendo');
        $response->assertDontSeeText('Revisar base');
        $response->assertSee('data-chart=', false);
    }

    public function test_dashboard_shows_rooms_menu_only_for_administrative_profiles(): void
    {
        ['sector' => $sector, 'room' => $room] = $this->ticketContext();

        $superAdmin = User::factory()->superAdmin()->create();
        $sectorAdmin = User::factory()->create([
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);
        $collaborator = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('rooms.index', absolute: false), false);

        $this->actingAs($sectorAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('rooms.index', absolute: false), false);

        $this->actingAs($collaborator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('rooms.index', absolute: false), false);
    }

    public function test_dashboard_shows_global_search_menu_only_for_super_admin(): void
    {
        ['sector' => $sector, 'room' => $room] = $this->ticketContext();

        $superAdmin = User::factory()->superAdmin()->create();
        $sectorAdmin = User::factory()->create([
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);
        $collaborator = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('search', absolute: false), false);

        $this->actingAs($sectorAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('search', absolute: false), false);

        $this->actingAs($collaborator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('search', absolute: false), false);
    }

    public function test_dashboard_shows_rating_summary_for_technicians(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status, 'closedStatus' => $closedStatus] = $this->ticketContext();

        $technician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        $firstRequester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        $secondRequester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        $ratedTicket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $closedStatus->id,
            'room_id' => $room->id,
            'title' => 'Chamado avaliado',
            'description' => 'Fechado com nota',
            'requester_id' => $firstRequester->id,
            'assignee_id' => $technician->id,
            'priority' => TicketPriority::MEDIUM,
            'resolved_at' => now()->subDay(),
            'last_activity_at' => now()->subDay(),
        ]);

        $ratedTicket->rating()->create([
            'user_id' => $firstRequester->id,
            'rating' => 4,
            'comment' => 'Tudo certo.',
        ]);

        Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $closedStatus->id,
            'room_id' => $room->id,
            'title' => 'Chamado sem avaliacao',
            'description' => 'Fechado sem nota',
            'requester_id' => $secondRequester->id,
            'assignee_id' => $technician->id,
            'priority' => TicketPriority::HIGH,
            'resolved_at' => now()->subHours(12),
            'last_activity_at' => now()->subHours(12),
        ]);

        Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Chamado em aberto',
            'description' => 'Ainda em atendimento',
            'requester_id' => $secondRequester->id,
            'priority' => TicketPriority::LOW,
            'last_activity_at' => now(),
        ]);

        $response = $this->actingAs($technician)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSeeText('Media de avaliacao');
        $response->assertSeeText('4.0/5');
        $response->assertSeeText('Chamados avaliados');
        $response->assertSeeText('1');
        $response->assertSeeText('Encerrados sem avaliacao');
        $response->assertSeeText('Colaboradores vinculados no seu escopo');
        $response->assertSeeText('Chamado avaliado');
        $response->assertSeeText('Chamado sem avaliacao');
        $response->assertSeeText('Sem avaliacao');
    }

    public function test_dashboard_counts_operational_modules_and_respects_sector_filter(): void
    {
        ['sector' => $sectorA, 'room' => $roomA, 'board' => $boardA, 'group' => $groupA, 'status' => $statusA] = $this->ticketContext();
        ['sector' => $sectorB, 'room' => $roomB, 'board' => $boardB, 'group' => $groupB, 'status' => $statusB] = $this->ticketContext();

        $superAdmin = User::factory()->superAdmin()->create();
        $technician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sectorA->id,
            'room_id' => $roomA->id,
        ]);
        $requesterA = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sectorA->id,
            'room_id' => $roomA->id,
        ]);
        $requesterB = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sectorB->id,
            'room_id' => $roomB->id,
        ]);

        $criticalTicket = Ticket::query()->create([
            'sector_id' => $sectorA->id,
            'ticket_board_id' => $boardA->id,
            'ticket_group_id' => $groupA->id,
            'ticket_status_id' => $statusA->id,
            'room_id' => $roomA->id,
            'title' => 'SLA critico setor A',
            'description' => 'Chamado em atraso para o dashboard completo.',
            'requester_id' => $requesterA->id,
            'priority' => TicketPriority::URGENT,
            'first_response_due_at' => now()->subHour(),
            'last_activity_at' => now()->subDays(8),
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subHours(2),
        ]);

        Ticket::query()->create([
            'sector_id' => $sectorB->id,
            'ticket_board_id' => $boardB->id,
            'ticket_group_id' => $groupB->id,
            'ticket_status_id' => $statusB->id,
            'room_id' => $roomB->id,
            'title' => 'Chamado setor B fora do filtro',
            'description' => 'Nao deve aparecer quando setor A estiver filtrado.',
            'requester_id' => $requesterB->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        $criticalTicket->timeEntries()->create([
            'user_id' => $technician->id,
            'source' => TicketTimeEntrySource::TIMER,
            'approval_status' => TicketTimeEntryApprovalStatus::APPROVED,
            'started_at' => now()->subDay(),
            'ended_at' => now()->subDay()->addHours(2),
            'duration_seconds' => 7200,
        ]);

        $criticalTicket->timeEntries()->create([
            'user_id' => $technician->id,
            'source' => TicketTimeEntrySource::MANUAL,
            'approval_status' => TicketTimeEntryApprovalStatus::PENDING,
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHours(2),
            'duration_seconds' => 3600,
        ]);

        $license = License::query()->create([
            'sector_id' => $sectorA->id,
            'vendor_name' => 'SAP',
            'product_name' => 'Suite Dashboard',
            'plan_name' => 'Operacional',
            'seats_total' => 1,
            'status' => LicenseStatus::ACTIVE,
            'renewal_date' => now()->addDays(10)->toDateString(),
            'created_by' => $superAdmin->id,
        ]);

        $license->assignments()->create([
            'user_id' => $requesterA->id,
            'status' => LicenseAssignmentStatus::ACTIVE,
            'assigned_at' => now(),
            'created_by' => $superAdmin->id,
        ]);

        Asset::query()->create([
            'uuid' => (string) Str::uuid(),
            'asset_code' => 'PAT-DASH-001',
            'name' => 'Notebook Dashboard',
            'status' => AssetStatus::MANUTENCAO,
            'allocation_status' => AssetAllocationStatus::PENDING_REVIEW,
            'current_sector_id' => $sectorA->id,
            'current_room_id' => $roomA->id,
            'current_user_id' => $requesterA->id,
            'created_by' => $superAdmin->id,
        ]);

        $article = KnowledgeBaseArticle::query()->create([
            'sector_id' => $sectorA->id,
            'created_by' => $superAdmin->id,
            'generated_from_ticket_id' => $criticalTicket->id,
            'title' => 'Artigo Dashboard publicado',
            'summary' => 'Resumo do artigo.',
            'content' => 'Conteudo do artigo.',
            'visibility' => KnowledgeBaseVisibility::PUBLIC,
            'editorial_status' => KnowledgeBaseArticleStatus::PUBLISHED,
            'is_active' => true,
        ]);

        $article->feedback()->create([
            'user_id' => $requesterA->id,
            'is_helpful' => true,
        ]);

        KnowledgeBaseArticle::query()->create([
            'sector_id' => $sectorA->id,
            'created_by' => $superAdmin->id,
            'title' => 'Rascunho Dashboard',
            'summary' => 'Resumo pendente.',
            'content' => 'Conteudo pendente.',
            'visibility' => KnowledgeBaseVisibility::PRIVATE,
            'editorial_status' => KnowledgeBaseArticleStatus::DRAFT,
            'is_active' => false,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('dashboard', [
            'period' => 30,
            'sector_id' => $sectorA->id,
        ]));

        $response->assertOk();
        $response->assertSeeText('SLA critico setor A');
        $response->assertDontSeeText('Chamado setor B fora do filtro');
        $response->assertSeeText('Suite Dashboard');
        $response->assertSeeText('Notebook Dashboard');
        $response->assertSeeText('Rascunho Dashboard');
        $response->assertSeeText('2h 0min');
        $response->assertSeeText('SLA em atraso');
        $response->assertSeeText('Sem responsavel');
        $response->assertSeeText('Alta ou urgente');
        $response->assertSee('dashboard/export?period=30&amp;sector_id='.$sectorA->id, false);
    }

    public function test_operational_dashboard_ignores_requested_tickets_outside_operator_or_manager_sectors(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Carol',
            'is_active' => true,
        ]);

        $managedSector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Setor Gerenciado Carol',
            'slug' => 'setor-gerenciado-carol',
            'is_active' => true,
        ]);
        $operatedSector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Setor Operado Carol',
            'slug' => 'setor-operado-carol',
            'is_active' => true,
        ]);
        $outsideSector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Setor Fora Carol',
            'slug' => 'setor-fora-carol',
            'is_active' => true,
        ]);

        $managedRoom = Room::query()->create(['sector_id' => $managedSector->id, 'name' => 'Sala Gestao Carol', 'is_active' => true]);
        $operatedRoom = Room::query()->create(['sector_id' => $operatedSector->id, 'name' => 'Sala Operacao Carol', 'is_active' => true]);
        $outsideRoom = Room::query()->create(['sector_id' => $outsideSector->id, 'name' => 'Sala Fora Carol', 'is_active' => true]);

        $managedBoard = app(SectorProvisioningService::class)->provision($managedSector);
        $operatedBoard = app(SectorProvisioningService::class)->provision($operatedSector);
        $outsideBoard = app(SectorProvisioningService::class)->provision($outsideSector);

        $carol = User::factory()->create([
            'name' => 'Carol',
            'email' => 'carol@teste.com',
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $managedSector->id,
            'room_id' => $managedRoom->id,
        ]);
        $carol->sectorAccesses()->updateOrCreate(
            ['sector_id' => $operatedSector->id],
            ['access_level' => 'technician'],
        );
        $carol->sectorAccesses()->updateOrCreate(
            ['sector_id' => $outsideSector->id],
            ['access_level' => 'requester'],
        );

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $managedSector->id,
            'room_id' => $managedRoom->id,
        ]);

        Ticket::query()->create([
            'sector_id' => $managedSector->id,
            'ticket_board_id' => $managedBoard->id,
            'ticket_group_id' => $managedBoard->groups()->firstOrFail()->id,
            'ticket_status_id' => $managedBoard->statuses()->where('is_closed', false)->firstOrFail()->id,
            'room_id' => $managedRoom->id,
            'title' => 'Chamado permitido gestor',
            'description' => 'Dentro do setor gerenciado.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        Ticket::query()->create([
            'sector_id' => $operatedSector->id,
            'ticket_board_id' => $operatedBoard->id,
            'ticket_group_id' => $operatedBoard->groups()->firstOrFail()->id,
            'ticket_status_id' => $operatedBoard->statuses()->where('is_closed', false)->firstOrFail()->id,
            'room_id' => $operatedRoom->id,
            'title' => 'Chamado permitido operador',
            'description' => 'Dentro do setor operado.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::HIGH,
            'last_activity_at' => now(),
        ]);

        Ticket::query()->create([
            'sector_id' => $outsideSector->id,
            'ticket_board_id' => $outsideBoard->id,
            'ticket_group_id' => $outsideBoard->groups()->firstOrFail()->id,
            'ticket_status_id' => $outsideBoard->statuses()->where('is_closed', false)->firstOrFail()->id,
            'room_id' => $outsideRoom->id,
            'title' => 'Chamado fora dos acessos operacionais',
            'description' => 'Carol e solicitante, mas nao operadora nem gestora deste setor.',
            'requester_id' => $carol->id,
            'priority' => TicketPriority::URGENT,
            'last_activity_at' => now(),
        ]);

        $response = $this->actingAs($carol)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('selectedSectorId', null);
        $response->assertViewHas('stats', fn (array $stats) => $stats['tickets_total'] === 2 && $stats['sectors'] === 2);
        $response->assertViewHas('availableSectors', function ($sectors) use ($managedSector, $operatedSector, $outsideSector) {
            return $sectors->pluck('id')->sort()->values()->all() === collect([$managedSector->id, $operatedSector->id])->sort()->values()->all()
                && ! $sectors->pluck('id')->contains($outsideSector->id);
        });
        $response->assertSeeText('Chamado permitido gestor');
        $response->assertSeeText('Chamado permitido operador');
        $response->assertDontSeeText('Chamado fora dos acessos operacionais');

        $this->actingAs($carol)
            ->get(route('dashboard', ['sector_id' => $outsideSector->id]))
            ->assertOk()
            ->assertViewHas('selectedSectorId', null)
            ->assertDontSeeText('Chamado fora dos acessos operacionais');
    }

    public function test_dashboard_hides_restricted_module_actions_for_requesters(): void
    {
        ['sector' => $sector, 'room' => $room] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        $response = $this->actingAs($requester)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('href="'.route('assets.index').'"', false);
        $response->assertDontSee('href="'.route('licenses.index').'"', false);
        $response->assertSee(route('tickets.central', absolute: false), false);
    }

    private function ticketContext(): array
    {
        $company = Company::query()->create([
            'name' => 'Empresa Dashboard',
            'is_active' => true,
        ]);

        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Atendimento',
            'slug' => 'atendimento',
            'is_active' => true,
        ]);

        $room = Room::query()->create([
            'sector_id' => $sector->id,
            'name' => 'Sala Dashboard',
            'is_active' => true,
        ]);

        $board = app(SectorProvisioningService::class)->provision($sector);

        return [
            'company' => $company,
            'sector' => $sector,
            'room' => $room,
            'board' => $board,
            'group' => $board->groups()->firstOrFail(),
            'status' => $board->statuses()->where('is_closed', false)->firstOrFail(),
            'closedStatus' => $board->statuses()->where('is_closed', true)->firstOrFail(),
        ];
    }
}
