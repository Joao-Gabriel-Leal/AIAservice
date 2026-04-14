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
use App\Modules\Tickets\Services\SectorProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_open_a_ticket_from_the_catalog(): void
    {
        ['sector' => $sector, 'room' => $room, 'catalog' => $catalog] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        Livewire::actingAs($requester)
            ->test(CreatePage::class)
            ->set('selectedSectorId', $sector->id)
            ->set('selectedCatalogId', $catalog->id)
            ->set('roomId', $room->id)
            ->set('title', 'Notebook sem rede')
            ->set('description', 'Nao conecta na rede interna.')
            ->set('priority', TicketPriority::HIGH->value)
            ->call('submit')
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'title' => 'Notebook sem rede',
            'requester_id' => $requester->id,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
            'priority' => TicketPriority::HIGH->value,
        ]);
    }

    public function test_board_inline_update_and_chat_work_inside_sector_scope(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        $technician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
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
            'status' => $board->statuses()->firstOrFail(),
            'catalog' => $board->catalogItems()->firstOrFail(),
        ];
    }
}
