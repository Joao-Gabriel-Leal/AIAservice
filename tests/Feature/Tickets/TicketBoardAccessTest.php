<?php

namespace Tests\Feature\Tickets;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Services\SectorProvisioningService;
use App\Enums\TicketFieldType;
use App\Enums\TicketPriority;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketBoardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_route_redirects_to_the_unified_index_when_no_active_sector_exists(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->get(route('tickets.board'))
            ->assertRedirect(route('tickets.index'));

        $this->actingAs($superAdmin)
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertSeeText('Quadros')
            ->assertSeeText('Nenhum quadro disponivel para o seu usuario.');
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

    public function test_board_directory_lists_only_accessible_boards(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Cards',
            'is_active' => true,
        ]);
        $managedSector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'TI Cards',
            'slug' => 'ti-cards',
            'is_active' => true,
        ]);
        $outsideSector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Financeiro Cards',
            'slug' => 'financeiro-cards',
            'is_active' => true,
        ]);

        $supportBoard = app(SectorProvisioningService::class)->provision($managedSector);
        $outsideBoard = app(SectorProvisioningService::class)->provision($outsideSector);
        $operator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $managedSector->id,
        ]);
        $manager = User::factory()->create([
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $managedSector->id,
        ]);
        $devBoard = app(SectorProvisioningService::class)->createAdditionalBoard($managedSector, 'Desenvolvimento Cards', null, [$operator->id]);

        $supportBoard->operators()->detach($operator->id);

        $this->actingAs($manager)
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertSeeText($supportBoard->name)
            ->assertSeeText($devBoard->name)
            ->assertDontSeeText($outsideBoard->name);

        $this->actingAs($operator)
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertSeeText($supportBoard->name)
            ->assertSeeText($devBoard->name)
            ->assertDontSeeText($outsideBoard->name);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertSeeText($supportBoard->name)
            ->assertSeeText($devBoard->name)
            ->assertSeeText($outsideBoard->name);
    }

    public function test_board_card_opens_the_board_detail_route(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $company = Company::query()->create([
            'name' => 'Empresa Abrir Card',
            'is_active' => true,
        ]);
        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'TI Abrir Card',
            'slug' => 'ti-abrir-card',
            'is_active' => true,
        ]);
        $board = app(SectorProvisioningService::class)->provision($sector);

        $this->actingAs($superAdmin)
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertSee(route('tickets.board.show', $board, absolute: false), false)
            ->assertSeeText('Abrir')
            ->assertDontSeeText('Nova demanda')
            ->assertDontSeeText('Configurar');
    }

    public function test_configure_action_is_available_inside_board_detail_in_list_mode(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $company = Company::query()->create([
            'name' => 'Empresa Config Lista',
            'is_active' => true,
        ]);
        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'TI Config Lista',
            'slug' => 'ti-config-lista',
            'is_active' => true,
        ]);
        $board = app(SectorProvisioningService::class)->provision($sector);

        $response = $this->actingAs($superAdmin)
            ->get(route('tickets.board.show', ['board' => $board, 'view' => 'list']));

        $response
            ->assertOk()
            ->assertSeeText('Configurar')
            ->assertSee(route('tickets.settings', $board, absolute: false), false)
            ->assertSeeText('Nova demanda')
            ->assertSeeText('Voltar aos quadros')
            ->assertDontSeeText('Abrir demanda manualmente')
            ->assertDontSeeText('Todos os setores')
            ->assertDontSeeText('Todos os quadros');

        $this->assertGreaterThanOrEqual(1, substr_count($response->getContent(), 'Central de formularios'));
    }

    public function test_operator_with_sector_access_can_open_manual_creation_without_configure_access(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Operador Manual',
            'is_active' => true,
        ]);
        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'TI Operador Manual',
            'slug' => 'ti-operador-manual',
            'is_active' => true,
        ]);
        $board = app(SectorProvisioningService::class)->provision($sector);
        $operator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $board->operators()->detach($operator->id);

        $this->assertFalse($board->operators()->whereKey($operator->id)->exists());
        $this->assertTrue($operator->canOperateBoard($board));

        $this->actingAs($operator)
            ->get(route('tickets.board.show', ['board' => $board, 'view' => 'list']))
            ->assertOk()
            ->assertSeeText('Nova demanda')
            ->assertDontSeeText('Configurar')
            ->assertDontSee(route('tickets.settings', $board, absolute: false), false);
    }

    public function test_user_can_open_manager_and_operator_sector_boards_without_board_operator_row(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Acesso Misto',
            'is_active' => true,
        ]);
        $managerSector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Adesao',
            'slug' => 'adesao',
            'is_active' => true,
        ]);
        $operatorSector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Administrativo',
            'slug' => 'administrativo',
            'is_active' => true,
        ]);
        $outsideSector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'CARC',
            'slug' => 'carc',
            'is_active' => true,
        ]);

        $managerBoard = app(SectorProvisioningService::class)->provision($managerSector);
        $operatorBoard = app(SectorProvisioningService::class)->provision($operatorSector);
        $outsideBoard = app(SectorProvisioningService::class)->provision($outsideSector);

        $user = User::factory()->create([
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $managerSector->id,
        ]);
        $user->sectorAccesses()->updateOrCreate(
            ['sector_id' => $operatorSector->id],
            ['access_level' => 'technician'],
        );
        $operatorBoard->operators()->detach($user->id);

        $this->assertFalse($operatorBoard->operators()->whereKey($user->id)->exists());
        $this->assertTrue($user->canOperateBoard($managerBoard));
        $this->assertTrue($user->canOperateBoard($operatorBoard));
        $this->assertFalse($user->canOperateBoard($outsideBoard));
        $this->assertEqualsCanonicalizing(
            [$managerBoard->id, $operatorBoard->id],
            $user->operationalBoardIds(),
        );

        $this->actingAs($user)
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertSeeText($managerBoard->name)
            ->assertSeeText($operatorBoard->name)
            ->assertDontSeeText($outsideBoard->name);

        $this->actingAs($user)
            ->get(route('tickets.board.show', ['board' => $operatorBoard, 'view' => 'list']))
            ->assertOk()
            ->assertSeeText('Nova demanda')
            ->assertDontSeeText('Configurar')
            ->assertDontSee(route('tickets.settings', $operatorBoard, absolute: false), false);

        $this->actingAs($user)
            ->get(route('tickets.board.show', ['board' => $managerBoard, 'view' => 'list']))
            ->assertOk()
            ->assertSeeText('Configurar')
            ->assertSee(route('tickets.settings', $managerBoard, absolute: false), false);

        $this->actingAs($user)
            ->get(route('tickets.settings', $operatorBoard))
            ->assertForbidden();
    }

    public function test_legacy_board_query_redirects_to_board_detail(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $company = Company::query()->create([
            'name' => 'Empresa Redirect',
            'is_active' => true,
        ]);
        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'TI Redirect',
            'slug' => 'ti-redirect',
            'is_active' => true,
        ]);
        $board = app(SectorProvisioningService::class)->provision($sector);

        $this->actingAs($superAdmin)
            ->get(route('tickets.index', ['board' => $board->id, 'view' => 'kanban']))
            ->assertRedirect(route('tickets.board.show', ['board' => $board, 'view' => 'kanban']));

        $this->actingAs($superAdmin)
            ->get(route('tickets.index', ['sector' => $sector->id, 'view' => 'stages']))
            ->assertRedirect(route('tickets.board.show', ['board' => $board, 'view' => 'stages']));

        $this->actingAs($superAdmin)
            ->get(route('tickets.board', $sector))
            ->assertRedirect(route('tickets.board.show', ['board' => $board, 'view' => 'stages']));
    }

    public function test_manual_board_modal_creates_ticket_without_public_catalog_item(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Manual',
            'is_active' => true,
        ]);
        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'TI Manual',
            'slug' => 'ti-manual',
            'is_active' => true,
        ]);
        $board = app(SectorProvisioningService::class)->provision($sector)->load(['groups', 'statuses']);
        $manager = User::factory()->create([
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $sector->id,
        ]);
        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);
        $assignee = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $field = $board->fields()->create([
            'name' => 'Ambiente',
            'slug' => 'ambiente',
            'type' => TicketFieldType::TEXT,
            'sort_order' => 10,
            'is_required' => false,
            'show_on_board' => true,
            'is_active' => true,
        ]);

        $group = $board->groups->first();

        Livewire::actingAs($manager)
            ->test(\App\Modules\Tickets\Livewire\IndexPage::class)
            ->set('selectedSectorId', $sector->id)
            ->set('selectedBoardId', $board->id)
            ->call('openManualTicketModal')
            ->assertSet('showManualTicketModal', true)
            ->set('manualTicketForm.title', 'Demanda criada no quadro')
            ->set('manualTicketForm.description', 'Criada sem passar pela central.')
            ->set('manualTicketForm.priority', TicketPriority::HIGH->value)
            ->set('manualTicketForm.ticket_group_id', (string) $group->id)
            ->set('manualTicketForm.requester_id', (string) $requester->id)
            ->set('manualTicketForm.assignee_id', (string) $assignee->id)
            ->set("manualTicketForm.dynamic_values.{$field->id}", 'Homolog')
            ->call('createManualTicket')
            ->assertSet('showManualTicketModal', false);

        $ticket = Ticket::query()->where('title', 'Demanda criada no quadro')->firstOrFail();

        $this->assertSame($board->id, $ticket->ticket_board_id);
        $this->assertSame($group->id, $ticket->ticket_group_id);
        $this->assertNull($ticket->service_catalog_item_id);
        $this->assertSame($requester->id, $ticket->requester_id);
        $this->assertSame($assignee->id, $ticket->assignee_id);
        $this->assertSame('Homolog', $ticket->fieldValues()->where('ticket_field_id', $field->id)->first()?->primitive_value);
        $this->assertMatchesRegularExpression('/^TM-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{5}$/', $ticket->reference_code);
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
            ->assertRedirect(route('tickets.index'));

        $this->actingAs($user)
            ->get(route('tickets.index'))
            ->assertRedirect(route('tickets.central'));

        $this->actingAs($user)
            ->get(route('tickets.central'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('tickets.settings'))
            ->assertForbidden();
    }

    public function test_requester_without_company_access_cannot_open_own_ticket_directly(): void
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
            ->assertForbidden();
    }

    public function test_operator_access_is_limited_by_sector_not_board_operator_assignment(): void
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
        $outsideSector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Financeiro',
            'slug' => 'financeiro',
            'is_active' => true,
        ]);

        $room = Room::query()->create([
            'sector_id' => $sector->id,
            'name' => 'Sala TI',
            'is_active' => true,
        ]);
        $outsideRoom = Room::query()->create([
            'sector_id' => $outsideSector->id,
            'name' => 'Sala Financeiro',
            'is_active' => true,
        ]);

        $supportBoard = app(SectorProvisioningService::class)->provision($sector);
        $outsideBoard = app(SectorProvisioningService::class)->provision($outsideSector);
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
        $devBoard->operators()->detach([$devOperator->id, $supportOperator->id]);
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
        $outsideTicket = Ticket::query()->create([
            'sector_id' => $outsideSector->id,
            'ticket_board_id' => $outsideBoard->id,
            'ticket_group_id' => $outsideBoard->groups()->firstOrFail()->id,
            'ticket_status_id' => $outsideBoard->statuses()->firstOrFail()->id,
            'room_id' => $outsideRoom->id,
            'title' => 'Chamado financeiro',
            'description' => 'Fica fora do setor operacional.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        $this->assertTrue($devOperator->canOperateBoard($devBoard));
        $this->assertTrue($devOperator->canOperateBoard($supportBoard));
        $this->assertTrue($supportOperator->canOperateBoard($devBoard));
        $this->assertFalse($devOperator->canOperateBoard($outsideBoard));
        $this->assertTrue($manager->canOperateBoard($devBoard));

        $this->actingAs($devOperator)
            ->get(route('tickets.show', $devTicket))
            ->assertOk()
            ->assertSeeText('Chamado de desenvolvimento');

        $this->actingAs($devOperator)
            ->get(route('tickets.show', $supportTicket))
            ->assertOk()
            ->assertSeeText('Chamado de suporte');

        $this->actingAs($devOperator)
            ->get(route('tickets.show', $outsideTicket))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('tickets.show', $supportTicket))
            ->assertOk()
            ->assertSeeText('Chamado de suporte');
    }
}
