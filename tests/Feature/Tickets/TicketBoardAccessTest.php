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
            ->assertSeeText('Quadros')
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
            ->assertSee('>Quadros</a>', false)
            ->assertDontSee('>Chamados</a>', false);
    }

    public function test_ticket_index_hides_global_search_action_for_non_super_admins(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Busca Operacional',
            'is_active' => true,
        ]);

        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Suporte Busca',
            'slug' => 'suporte-busca',
            'is_active' => true,
        ]);

        $operator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        app(SectorProvisioningService::class)->provision($sector);

        $this->actingAs($operator)
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertDontSeeText('Buscar em tudo')
            ->assertDontSee(route('search', absolute: false), false);
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

    public function test_operator_access_is_limited_by_selected_board(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Multi Quadro',
            'is_active' => true,
        ]);

        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'TI',
            'slug' => 'ti',
            'is_active' => true,
        ]);

        $room = Room::query()->create([
            'sector_id' => $sector->id,
            'name' => 'Sala TI',
            'is_active' => true,
        ]);

        $supportBoard = app(SectorProvisioningService::class)->provision($sector);
        $devOperator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);
        $supportOperator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);
        $manager = User::factory()->create([
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $sector->id,
        ]);

        $supportBoard->operators()->detach([$devOperator->id, $supportOperator->id]);

        $devBoard = app(SectorProvisioningService::class)->createAdditionalBoard($sector, 'Desenvolvimento', null, [$devOperator->id]);
        $requester = User::factory()->create();

        $supportTicket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $supportBoard->id,
            'ticket_group_id' => $supportBoard->groups()->firstOrFail()->id,
            'ticket_status_id' => $supportBoard->statuses()->firstOrFail()->id,
            'room_id' => $room->id,
            'title' => 'Chamado de suporte',
            'description' => 'Fica no quadro de suporte.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        $devTicket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $devBoard->id,
            'ticket_group_id' => $devBoard->groups()->firstOrFail()->id,
            'ticket_status_id' => $devBoard->statuses()->firstOrFail()->id,
            'room_id' => $room->id,
            'title' => 'Chamado de desenvolvimento',
            'description' => 'Fica no quadro de desenvolvimento.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        $this->assertTrue($devOperator->canOperateBoard($devBoard));
        $this->assertFalse($supportOperator->canOperateBoard($devBoard));
        $this->assertTrue($manager->canOperateBoard($devBoard));

        $this->actingAs($devOperator)
            ->get(route('tickets.show', $devTicket))
            ->assertOk()
            ->assertSeeText('Chamado de desenvolvimento');

        $this->actingAs($devOperator)
            ->get(route('tickets.show', $supportTicket))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('tickets.show', $supportTicket))
            ->assertOk()
            ->assertSeeText('Chamado de suporte');
    }
}
