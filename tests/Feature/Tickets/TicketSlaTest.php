<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketPriority;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Notifications\TicketActivityNotification;
use App\Modules\Tickets\Services\SectorProvisioningService;
use App\Modules\Tickets\Services\TicketWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketSlaTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_deadlines_and_marks_first_response_on_time(): void
    {
        Carbon::setTestNow('2026-04-14 09:00:00');

        ['sector' => $sector, 'room' => $room, 'board' => $board, 'status' => $status] = $this->ticketContext();

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

        $ticket = app(TicketWorkflowService::class)->createTicket($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $board->groups()->firstOrFail()->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Notebook sem acesso',
            'description' => 'Erro ao abrir o sistema.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::HIGH,
        ]);

        $this->assertSame(60, $ticket->first_response_sla_minutes);
        $this->assertSame('2026-04-14 10:00:00', $ticket->first_response_due_at?->format('Y-m-d H:i:s'));
        $this->assertSame(480, $ticket->resolution_sla_minutes);
        $this->assertSame('2026-04-14 17:00:00', $ticket->resolution_due_at?->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-04-14 09:20:00');
        app(TicketWorkflowService::class)->addMessage($technician, $ticket->fresh(), 'Assumindo o atendimento agora.');

        $ticket->refresh();

        $this->assertSame('2026-04-14 09:20:00', $ticket->first_responded_at?->format('Y-m-d H:i:s'));
        $this->assertNull($ticket->first_response_breached_at);
    }

    public function test_it_marks_first_response_breach_and_notifies_when_monitor_runs(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-04-14 09:00:00');

        ['sector' => $sector, 'room' => $room, 'board' => $board, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        $sectorAdmin = User::factory()->create([
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        $technician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        $ticket = app(TicketWorkflowService::class)->createTicket($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $board->groups()->firstOrFail()->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'assignee_id' => $technician->id,
            'title' => 'Servidor parado',
            'description' => 'Servico indisponivel.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::URGENT,
        ]);

        Carbon::setTestNow('2026-04-14 09:16:00');
        $this->artisan('tickets:sla-monitor')->assertSuccessful();

        $ticket->refresh();

        $this->assertNotNull($ticket->first_response_breached_at);
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => Ticket::class,
            'subject_id' => $ticket->id,
            'event' => 'ticket.sla.first_response_breached',
        ]);

        Notification::assertSentTo(
            [$sectorAdmin, $technician],
            TicketActivityNotification::class,
            fn (TicketActivityNotification $notification) => str_contains((string) data_get($notification->toArray($technician), 'title'), 'primeira resposta estourado'),
        );
    }

    public function test_it_registers_resolution_breach_when_ticket_is_closed_late(): void
    {
        Carbon::setTestNow('2026-04-14 09:00:00');

        ['sector' => $sector, 'room' => $room, 'board' => $board, 'status' => $status] = $this->ticketContext();

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

        $ticket = app(TicketWorkflowService::class)->createTicket($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $board->groups()->firstOrFail()->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Switch indisponivel',
            'description' => 'Sem conectividade.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::HIGH,
        ]);

        Carbon::setTestNow('2026-04-14 09:05:00');
        app(TicketWorkflowService::class)->addMessage($technician, $ticket->fresh(), 'Primeiro retorno enviado.');

        Carbon::setTestNow('2026-04-14 17:30:00');
        $resolvedStatusId = $board->statuses()->where('is_closed', true)->value('id');

        app(TicketWorkflowService::class)->updateTicket($technician, $ticket->fresh(), [
            'ticket_status_id' => $resolvedStatusId,
        ]);

        $ticket->refresh();

        $this->assertNotNull($ticket->resolved_at);
        $this->assertNotNull($ticket->resolution_breached_at);
        $this->assertNull($ticket->first_response_breached_at);
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => Ticket::class,
            'subject_id' => $ticket->id,
            'event' => 'ticket.sla.resolution_breached',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function ticketContext(): array
    {
        $company = Company::query()->create([
            'name' => 'Empresa SLA',
            'is_active' => true,
        ]);

        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Operacoes',
            'slug' => 'operacoes',
            'is_active' => true,
        ]);

        $room = Room::query()->create([
            'sector_id' => $sector->id,
            'name' => 'NOC',
            'is_active' => true,
        ]);

        $board = app(SectorProvisioningService::class)->provision($sector);

        return [
            'company' => $company,
            'sector' => $sector,
            'room' => $room,
            'board' => $board,
            'status' => $board->statuses()->firstOrFail(),
        ];
    }
}
