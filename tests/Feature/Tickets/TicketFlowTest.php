<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketPriority;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Livewire\BoardPage;
use App\Modules\Tickets\Livewire\CreatePage;
use App\Modules\Tickets\Livewire\ShowPage;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Notifications\TicketActivityNotification;
use App\Modules\Tickets\Services\SectorProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class TicketFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_open_a_ticket_from_the_catalog(): void
    {
        ['sector' => $sector, 'catalog' => $catalog] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        Livewire::actingAs($requester)
            ->test(CreatePage::class)
            ->set('selectedSectorId', $sector->id)
            ->set('selectedCatalogId', $catalog->id)
            ->set('title', 'Notebook sem rede')
            ->set('description', 'Nao conecta na rede interna.')
            ->set('priority', TicketPriority::HIGH->value)
            ->call('submit')
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'title' => 'Notebook sem rede',
            'requester_id' => $requester->id,
            'sector_id' => $sector->id,
            'room_id' => null,
            'priority' => TicketPriority::HIGH->value,
        ]);
    }

    public function test_collaborator_without_sector_access_can_open_ticket_for_any_active_sector(): void
    {
        ['sector' => $sector, 'catalog' => $catalog] = $this->ticketContext();

        $collaborator = User::factory()->create();

        Livewire::actingAs($collaborator)
            ->test(CreatePage::class)
            ->set('selectedSectorId', $sector->id)
            ->set('selectedCatalogId', $catalog->id)
            ->set('title', 'Preciso de ajuda no setor')
            ->set('description', 'Abrindo pela central sem vinculo previo.')
            ->set('priority', TicketPriority::MEDIUM->value)
            ->call('submit')
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'requester_id' => $collaborator->id,
            'sector_id' => $sector->id,
            'room_id' => null,
            'title' => 'Preciso de ajuda no setor',
        ]);
    }

    public function test_create_page_requires_explicit_sector_and_catalog_selection_when_opened_without_a_catalog(): void
    {
        ['sector' => $sector] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        Livewire::actingAs($requester)
            ->test(CreatePage::class)
            ->assertSet('selectedSectorId', null)
            ->assertSet('selectedCatalogId', null)
            ->set('selectedSectorId', $sector->id)
            ->assertSet('selectedCatalogId', null);
    }

    public function test_board_inline_update_and_chat_work_inside_sector_scope(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $technician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $ticket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Impressora parada',
            'description' => 'Fila travada',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($technician)
            ->test(BoardPage::class)
            ->call('updateFixedField', $ticket->id, 'priority', TicketPriority::URGENT->value);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'priority' => TicketPriority::URGENT->value,
        ]);

        Livewire::actingAs($technician)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->set('message', 'Estou assumindo este atendimento.')
            ->call('sendMessage');

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'user_id' => $technician->id,
            'message' => 'Estou assumindo este atendimento.',
        ]);
    }

    public function test_requester_can_rate_a_closed_ticket_once(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'closedStatus' => $closedStatus] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $ticket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $closedStatus->id,
            'room_id' => $room->id,
            'title' => 'Rede estabilizada',
            'description' => 'Chamado resolvido',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'resolved_at' => now()->subHour(),
            'last_activity_at' => now()->subHour(),
        ]);

        Livewire::actingAs($requester)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->set('ratingValue', 5)
            ->set('ratingComment', 'Atendimento rapido e claro.')
            ->call('submitRating')
            ->assertHasNoErrors();

        Livewire::actingAs($requester)
            ->test(ShowPage::class, ['ticket' => $ticket->fresh()])
            ->set('ratingValue', 4)
            ->call('submitRating')
            ->assertForbidden();

        $this->assertDatabaseHas('ticket_ratings', [
            'ticket_id' => $ticket->id,
            'user_id' => $requester->id,
            'rating' => 5,
            'comment' => 'Atendimento rapido e claro.',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => Ticket::class,
            'subject_id' => $ticket->id,
            'event' => 'ticket.rating.created',
        ]);
    }

    public function test_requester_cannot_rate_an_open_ticket(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $ticket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Chamado em aberto',
            'description' => 'Ainda sem resolucao',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::LOW,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($requester)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->set('ratingValue', 3)
            ->call('submitRating')
            ->assertForbidden();

        $this->assertDatabaseMissing('ticket_ratings', [
            'ticket_id' => $ticket->id,
        ]);
    }

    public function test_non_requester_cannot_rate_a_ticket(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'closedStatus' => $closedStatus] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $technician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $ticket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $closedStatus->id,
            'room_id' => $room->id,
            'title' => 'Chamado encerrado',
            'description' => 'Pronto para avaliar',
            'requester_id' => $requester->id,
            'assignee_id' => $technician->id,
            'priority' => TicketPriority::MEDIUM,
            'resolved_at' => now()->subMinutes(30),
            'last_activity_at' => now()->subMinutes(30),
        ]);

        Livewire::actingAs($technician)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->set('ratingValue', 4)
            ->call('submitRating')
            ->assertForbidden();

        $this->assertDatabaseMissing('ticket_ratings', [
            'ticket_id' => $ticket->id,
        ]);
    }

    public function test_requester_receives_rating_invitation_when_ticket_is_closed(): void
    {
        Notification::fake();

        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status, 'closedGroup' => $closedGroup] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $technician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $ticket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Equipamento entregue',
            'description' => 'Aguardando encerramento',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($technician)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->call('updateFixedField', 'ticket_group_id', (string) $closedGroup->id);

        Notification::assertSentTo(
            $requester,
            TicketActivityNotification::class,
            fn (TicketActivityNotification $notification, array $channels) => in_array('database', $channels, true)
                && in_array('mail', $channels, true)
                && data_get($notification->toArray($requester), 'title') === 'Chamado encerrado'
        );
    }

    private function ticketContext(): array
    {
        $company = Company::query()->create([
            'name' => 'Empresa Tickets',
            'is_active' => true,
        ]);

        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Suporte',
            'slug' => 'suporte',
            'is_active' => true,
        ]);

        $room = Room::query()->create([
            'sector_id' => $sector->id,
            'name' => 'Sala Tickets',
            'is_active' => true,
        ]);

        $board = app(SectorProvisioningService::class)->provision($sector);

        return [
            'company' => $company,
            'sector' => $sector,
            'room' => $room,
            'board' => $board,
            'group' => $board->groups()->firstOrFail(),
            'closedGroup' => $board->groups()->where('is_closed', true)->firstOrFail(),
            'status' => $board->statuses()->where('is_closed', false)->firstOrFail(),
            'closedStatus' => $board->statuses()->where('is_closed', true)->firstOrFail(),
            'catalog' => $board->catalogItems()->firstOrFail(),
        ];
    }
}
