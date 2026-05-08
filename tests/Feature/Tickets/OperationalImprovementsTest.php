<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketTimeEntrySource;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Livewire\IndexPage;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Models\TicketBoardSavedView;
use App\Modules\Tickets\Models\TicketBoardUserPreference;
use App\Modules\Tickets\Models\TicketGroup;
use App\Modules\Tickets\Models\TicketStatus;
use App\Modules\Tickets\Services\SectorProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalImprovementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_directory_supports_search_favorites_and_recent_ordering(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $defaultBoard] = $this->ticketContext('Operacoes Favoritas');

        $defaultBoard->update(['name' => 'Quadro Alfa']);
        $favoriteBoard = app(SectorProvisioningService::class)->createAdditionalBoard($sector, 'Quadro Bravo', null, []);
        $recentBoard = app(SectorProvisioningService::class)->createAdditionalBoard($sector, 'Quadro Charlie', null, []);

        $operator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        TicketBoardUserPreference::query()->create([
            'user_id' => $operator->id,
            'ticket_board_id' => $favoriteBoard->id,
            'is_favorite' => true,
        ]);
        TicketBoardUserPreference::query()->create([
            'user_id' => $operator->id,
            'ticket_board_id' => $recentBoard->id,
            'last_opened_at' => now(),
        ]);

        $this->actingAs($operator)
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertSeeInOrder(['Quadro Bravo', 'Quadro Charlie', 'Quadro Alfa'])
            ->assertSeeText('Favoritos')
            ->assertSeeText('Recentes');

        $this->actingAs($operator)
            ->get(route('tickets.index', ['q' => 'Charlie']))
            ->assertOk()
            ->assertSeeText('Quadro Charlie')
            ->assertDontSeeText('Quadro Alfa')
            ->assertDontSeeText('Quadro Bravo');
    }

    public function test_saved_views_and_quick_views_store_and_restore_filters(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board] = $this->ticketContext('Operacoes Views');

        $operator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        Livewire::actingAs($operator)
            ->test(IndexPage::class, ['board' => $board])
            ->set('assigneeStateFilter', 'unassigned')
            ->set('priorityFilter', 'high_or_urgent')
            ->set('slaFilter', 'critical')
            ->set('viewMode', 'kanban')
            ->set('savedViewName', 'SLA critico sem dono')
            ->call('saveCurrentView')
            ->assertHasNoErrors();

        $savedView = TicketBoardSavedView::query()->where('name', 'SLA critico sem dono')->firstOrFail();

        Livewire::actingAs($operator)
            ->test(IndexPage::class, ['board' => $board])
            ->call('applySavedView', $savedView->id)
            ->assertSet('assigneeStateFilter', 'unassigned')
            ->assertSet('priorityFilter', 'high_or_urgent')
            ->assertSet('slaFilter', 'critical')
            ->assertSet('viewMode', 'kanban')
            ->call('applyQuickView', 'mine')
            ->assertSet('assigneeStateFilter', 'me')
            ->call('applyQuickView', 'high_priority')
            ->assertSet('priorityFilter', 'high_or_urgent');
    }

    public function test_operational_queue_respects_operator_sectors_and_buckets(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext('Operacoes Fila');
        ['board' => $outsideBoard, 'group' => $outsideGroup, 'status' => $outsideStatus, 'sector' => $outsideSector, 'room' => $outsideRoom] = $this->ticketContext('Financeiro Fila');

        $operator = User::factory()->create([
            'name' => 'Quincy Zebra',
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);
        $requester = User::factory()->create([
            'name' => 'Carol Foto',
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);
        $requester->forceFill([
            'profile_photo_path' => "profile-photos/{$requester->id}/avatar.png",
            'profile_photo_original_name' => 'avatar.png',
            'profile_photo_mime_type' => 'image/png',
            'profile_photo_size' => 14,
            'profile_photo_content' => 'avatar-content',
        ])->save();
        $outsideRequester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $outsideSector->id,
            'room_id' => $outsideRoom->id,
        ]);

        $assignedTicket = $this->ticketFor($board, $group, $status, $requester, 'Fila atribuida', $operator);
        $unassignedTicket = $this->ticketFor($board, $group, $status, $requester, 'Fila sem responsavel');
        $outsideTicket = $this->ticketFor($outsideBoard, $outsideGroup, $outsideStatus, $outsideRequester, 'Fila de fora');
        $assignedTicket->timeEntries()->create([
            'user_id' => $operator->id,
            'source' => TicketTimeEntrySource::TIMER,
            'started_at' => now()->subMinutes(15),
        ]);

        $this->actingAs($operator)
            ->get(route('tickets.queue'))
            ->assertOk()
            ->assertSeeText('Minha fila operacional')
            ->assertSeeText($assignedTicket->title)
            ->assertSeeText($unassignedTicket->title)
            ->assertSee($requester->profilePhotoUrl(), false)
            ->assertSee('title="'.$requester->name.'"', false)
            ->assertSee('title="'.$operator->name.'"', false)
            ->assertSee('QZ', false)
            ->assertSee('Nao atribuido')
            ->assertDontSee('<td class="px-6 py-4 text-slate-600">'.$requester->name.'</td>', false)
            ->assertDontSee('<td class="px-6 py-4 text-slate-600">'.$operator->name.'</td>', false)
            ->assertDontSeeText($outsideTicket->title);

        $this->actingAs($operator)
            ->get(route('tickets.queue', ['bucket' => 'timers_open']))
            ->assertOk()
            ->assertSeeText($assignedTicket->title)
            ->assertDontSeeText($unassignedTicket->title);
    }

    public function test_kanban_loads_the_first_page_per_column_and_shows_more_action(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext('Operacoes Kanban');

        $operator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);
        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        foreach (range(1, 30) as $number) {
            $this->ticketFor($board, $group, $status, $requester, 'Carga '.str_pad((string) $number, 2, '0', STR_PAD_LEFT));
        }

        $this->actingAs($operator)
            ->get(route('tickets.board.show', ['board' => $board, 'view' => 'kanban']))
            ->assertOk()
            ->assertSeeText('25 de 30 chamado(s)')
            ->assertSee('Carga 01')
            ->assertDontSee('Carga 30')
            ->assertSeeText('Carregar mais');
    }

    private function ticketContext(string $sectorName): array
    {
        $company = Company::query()->firstOrCreate([
            'name' => 'Empresa '.$sectorName,
        ], [
            'is_active' => true,
        ]);

        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => $sectorName,
            'slug' => str($sectorName)->slug()->toString(),
            'is_active' => true,
        ]);

        $room = Room::query()->create([
            'sector_id' => $sector->id,
            'name' => 'Sala '.$sectorName,
            'is_active' => true,
        ]);

        $board = app(SectorProvisioningService::class)->provision($sector);

        return [
            'sector' => $sector,
            'room' => $room,
            'board' => $board,
            'group' => $board->groups()->where('is_closed', false)->firstOrFail(),
            'status' => $board->statuses()->where('is_default', true)->firstOrFail(),
        ];
    }

    private function ticketFor(
        TicketBoard $board,
        TicketGroup $group,
        TicketStatus $status,
        User $requester,
        string $title,
        ?User $assignee = null,
    ): Ticket {
        return Ticket::query()->create([
            'sector_id' => $board->sector_id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $requester->room_id,
            'title' => $title,
            'description' => 'Chamado operacional de teste.',
            'requester_id' => $requester->id,
            'assignee_id' => $assignee?->id,
            'priority' => TicketPriority::HIGH,
            'last_activity_at' => now(),
        ]);
    }
}
