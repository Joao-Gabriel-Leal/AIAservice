<?php

namespace Tests\Feature\Tickets;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Services\SectorProvisioningService;
use App\Enums\TicketPriority;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketBoardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_route_redirects_to_the_unified_index_when_no_active_sector_exists(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->get(route('tickets.board'))
            ->assertRedirect(route('tickets.index', ['view' => 'stages']));

        $this->actingAs($superAdmin)
            ->get(route('tickets.index', ['view' => 'stages']))
            ->assertOk()
            ->assertSeeText('Quadro')
            ->assertSeeText('Nenhum chamado encontrado. Use a central para abrir a primeira solicitacao.');
    }

    public function test_super_admin_sees_empty_settings_state_when_no_active_sector_exists(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->get(route('tickets.settings'))
            ->assertOk()
            ->assertSeeText('Nenhum quadro disponivel para configuracao.');
    }

    public function test_sidebar_shows_only_quadro_for_operational_users(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Operacional',
            'is_active' => true,
        ]);

        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Suporte',
            'slug' => 'suporte',
            'is_active' => true,
        ]);

        $operator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $this->actingAs($operator)
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertSee('>Quadro</a>', false)
            ->assertDontSee('>Chamados</a>', false);
    }

    public function test_user_without_operational_access_is_redirected_to_central(): void
    {
        $user = User::factory()->create();

        $company = Company::query()->create([
            'name' => 'Empresa Sem Acesso',
            'is_active' => true,
        ]);

        Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Operacoes',
            'slug' => 'operacoes',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('tickets.board'))
            ->assertRedirect(route('tickets.index', ['view' => 'stages']));

        $this->actingAs($user)
            ->get(route('tickets.index', ['view' => 'stages']))
            ->assertRedirect(route('tickets.central'));

        $this->actingAs($user)
            ->get(route('tickets.central'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('tickets.settings'))
            ->assertForbidden();
    }

    public function test_requester_can_still_open_own_ticket_directly_without_board_access(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Solicitante',
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
            'name' => 'Sala A',
            'is_active' => true,
        ]);

        $board = app(SectorProvisioningService::class)->provision($sector);
        $group = $board->groups()->firstOrFail();
        $status = $board->statuses()->where('is_closed', false)->firstOrFail();
        $requester = User::factory()->create();

        $ticket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Chamado proprio',
            'description' => 'Solicitante deve acompanhar pelo link.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        $this->actingAs($requester)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSeeText('Chamado proprio');
    }
}
