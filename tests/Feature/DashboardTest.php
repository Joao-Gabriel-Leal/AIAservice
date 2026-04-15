<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Services\SectorProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard', ['period' => 30]));

        $response->assertOk();
        $response->assertSeeText('Volume no periodo');
        $response->assertSeeText('Distribuicao por status');
        $response->assertSeeText('Saude operacional');
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
