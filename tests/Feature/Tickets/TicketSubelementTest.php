<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketPriority;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Search\Services\GlobalSearchService;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Livewire\IndexPage;
use App\Modules\Tickets\Livewire\ShowPage;
use App\Modules\Tickets\Livewire\TrashPage;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Models\TicketGroup;
use App\Modules\Tickets\Models\TicketStatus;
use App\Modules\Tickets\Services\SectorProvisioningService;
use App\Modules\Tickets\Services\TicketWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TicketSubelementTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_creates_one_level_subelement_with_parent_defaults(): void
    {
        Notification::fake();

        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();
        $operator = User::factory()->create(['role' => UserRole::TECHNICIAN, 'sector_id' => $sector->id, 'room_id' => $room->id]);
        $requester = User::factory()->create(['role' => UserRole::REQUESTER, 'sector_id' => $sector->id, 'room_id' => $room->id]);
        $parent = $this->ticketFor($board, $group, $status, $requester, 'Demanda mae', $operator);

        $subelement = app(TicketWorkflowService::class)->createSubelement($operator, $parent, 'Separar instalacao');

        $this->assertTrue($subelement->isSubelement());
        $this->assertSame($parent->id, $subelement->parent_ticket_id);
        $this->assertSame($parent->sector_id, $subelement->sector_id);
        $this->assertSame($parent->ticket_board_id, $subelement->ticket_board_id);
        $this->assertSame($parent->ticket_group_id, $subelement->ticket_group_id);
        $this->assertSame($parent->requester_id, $subelement->requester_id);
        $this->assertNull($subelement->assignee_id);
        $this->assertSame(1, $subelement->subticket_sort_order);
        $this->assertNull($subelement->board_sort_order);

        $this->expectException(ValidationException::class);
        app(TicketWorkflowService::class)->createSubelement($operator, $subelement, 'Nivel proibido');
    }

    public function test_subelements_are_internal_to_operations(): void
    {
        Notification::fake();

        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();
        $operator = User::factory()->create(['role' => UserRole::TECHNICIAN, 'sector_id' => $sector->id, 'room_id' => $room->id]);
        $requester = User::factory()->create(['role' => UserRole::REQUESTER, 'sector_id' => $sector->id, 'room_id' => $room->id]);
        $parent = $this->ticketFor($board, $group, $status, $requester, 'Demanda interna', $operator);
        $subelement = app(TicketWorkflowService::class)->createSubelement($operator, $parent, 'Parte interna');

        $this->actingAs($requester)
            ->get(route('tickets.show', $subelement))
            ->assertForbidden();

        $this->actingAs($operator)
            ->get(route('tickets.show', $subelement))
            ->assertOk()
            ->assertSee('Subelemento de '.$parent->publicReference());
    }

    public function test_parent_cannot_close_while_subelements_are_open(): void
    {
        Notification::fake();

        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status, 'closedGroup' => $closedGroup] = $this->ticketContext();
        $operator = User::factory()->create(['role' => UserRole::TECHNICIAN, 'sector_id' => $sector->id, 'room_id' => $room->id]);
        $requester = User::factory()->create(['role' => UserRole::REQUESTER, 'sector_id' => $sector->id, 'room_id' => $room->id]);
        $parent = $this->ticketFor($board, $group, $status, $requester, 'Demanda bloqueada', $operator);
        $subelement = app(TicketWorkflowService::class)->createSubelement($operator, $parent, 'Pendencia aberta');
        $workflow = app(TicketWorkflowService::class);

        try {
            $workflow->updateTicket($operator, $parent, ['ticket_group_id' => $closedGroup->id]);
            $this->fail('Parent ticket closed with an open subelement.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ticketLifecycle', $exception->errors());
        }

        $workflow->updateTicket($operator, $subelement, ['ticket_group_id' => $closedGroup->id]);
        $closedParent = $workflow->updateTicket($operator, $parent->fresh(), ['ticket_group_id' => $closedGroup->id]);

        $this->assertTrue($closedParent->isClosed());
    }

    public function test_board_inline_creation_and_operational_search_include_subelements(): void
    {
        Notification::fake();

        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();
        $operator = User::factory()->create(['role' => UserRole::TECHNICIAN, 'sector_id' => $sector->id, 'room_id' => $room->id]);
        $requester = User::factory()->create(['role' => UserRole::REQUESTER, 'sector_id' => $sector->id, 'room_id' => $room->id]);
        $parent = $this->ticketFor($board, $group, $status, $requester, 'Demanda com filhos', $operator);

        Livewire::actingAs($operator)
            ->test(IndexPage::class, ['board' => $board])
            ->call('setViewMode', 'stages')
            ->call('toggleSubelements', $parent->id)
            ->set("newSubelementTitles.{$parent->id}", 'Montagem eletrica')
            ->call('createSubelement', $parent->id)
            ->assertHasNoErrors()
            ->assertSee('Montagem eletrica');

        $subelement = Ticket::query()->where('title', 'Montagem eletrica')->firstOrFail();
        $subelement->update(['assignee_id' => $operator->id]);

        $results = app(GlobalSearchService::class)->search($operator, [
            'query' => 'Montagem eletrica',
            'types' => ['tickets'],
        ]);

        $ticketItems = collect($results['groups']['tickets']['items']);
        $this->assertTrue($ticketItems->contains(fn (array $item) => $item['title'] === $subelement->publicReference().' - Montagem eletrica'));
        $this->assertSame('Subelemento', $ticketItems->firstWhere('title', $subelement->publicReference().' - Montagem eletrica')['meta']['kind'] ?? null);

        $requesterResults = app(GlobalSearchService::class)->search($requester, [
            'query' => 'Montagem eletrica',
            'types' => ['tickets'],
        ]);

        $this->assertCount(0, $requesterResults['groups']['tickets']['items']);
    }

    public function test_operational_users_can_delete_tickets_and_subelements(): void
    {
        Notification::fake();

        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();
        $operator = User::factory()->create(['role' => UserRole::TECHNICIAN, 'sector_id' => $sector->id, 'room_id' => $room->id]);
        $manager = User::factory()->create(['role' => UserRole::SECTOR_ADMIN, 'sector_id' => $sector->id, 'room_id' => $room->id]);
        $requester = User::factory()->create(['role' => UserRole::REQUESTER, 'sector_id' => $sector->id, 'room_id' => $room->id]);
        $parent = $this->ticketFor($board, $group, $status, $requester, 'Demanda removivel', $operator);
        $workflow = app(TicketWorkflowService::class);
        $subelement = $workflow->createSubelement($operator, $parent, 'Parte removivel');

        $this->assertTrue($operator->can('delete', $parent));
        $this->assertTrue($manager->can('delete', $subelement));
        $this->assertFalse($requester->can('delete', $parent));

        try {
            $workflow->deleteTicket($requester, $parent);
            $this->fail('Requester deleted an operational ticket.');
        } catch (AuthorizationException) {
            $this->assertNotSoftDeleted('tickets', ['id' => $parent->id]);
        }

        Livewire::actingAs($operator)
            ->test(IndexPage::class, ['board' => $board])
            ->call('deleteTicket', $subelement->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('tickets', ['id' => $subelement->id]);
        $this->assertNotSoftDeleted('tickets', ['id' => $parent->id]);

        Livewire::actingAs($operator)
            ->test(TrashPage::class)
            ->assertSee('Parte removivel')
            ->call('restoreTicket', $subelement->id)
            ->assertHasNoErrors();

        $this->assertNotSoftDeleted('tickets', ['id' => $subelement->id]);

        $secondSubelement = $workflow->createSubelement($manager, $parent->fresh(), 'Parte removida com pai');

        Livewire::actingAs($manager)
            ->test(ShowPage::class, ['ticket' => $parent->fresh()])
            ->assertSee('Excluir chamado')
            ->call('deleteCurrentTicket')
            ->assertRedirect();

        $this->assertSoftDeleted('tickets', ['id' => $parent->id]);
        $this->assertSoftDeleted('tickets', ['id' => $subelement->id]);
        $this->assertSoftDeleted('tickets', ['id' => $secondSubelement->id]);

        Livewire::actingAs($manager)
            ->test(TrashPage::class)
            ->set('search', 'Demanda removivel')
            ->assertSee('Demanda removivel')
            ->call('restoreTicket', $parent->id)
            ->assertHasNoErrors();

        $this->assertNotSoftDeleted('tickets', ['id' => $parent->id]);
        $this->assertNotSoftDeleted('tickets', ['id' => $subelement->id]);
        $this->assertNotSoftDeleted('tickets', ['id' => $secondSubelement->id]);
    }

    private function ticketContext(): array
    {
        $company = Company::query()->create([
            'name' => 'Empresa Subelementos',
            'is_active' => true,
        ]);

        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Suporte Subelementos',
            'slug' => 'suporte-subelementos',
            'is_active' => true,
        ]);

        $room = Room::query()->create([
            'sector_id' => $sector->id,
            'name' => 'Sala Subelementos',
            'is_active' => true,
        ]);

        $board = app(SectorProvisioningService::class)->provision($sector);

        return [
            'sector' => $sector,
            'room' => $room,
            'board' => $board,
            'group' => $board->groups()->where('is_closed', false)->firstOrFail(),
            'closedGroup' => $board->groups()->where('is_closed', true)->firstOrFail(),
            'status' => $board->statuses()->where('is_closed', false)->firstOrFail(),
            'closedStatus' => $board->statuses()->where('is_closed', true)->firstOrFail(),
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
            'description' => 'Demanda de teste com subelementos.',
            'requester_id' => $requester->id,
            'assignee_id' => $assignee?->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);
    }
}
