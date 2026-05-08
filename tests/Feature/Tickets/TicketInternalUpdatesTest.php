<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketPriority;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Livewire\ShowPage;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketAttachment;
use App\Modules\Tickets\Models\TicketMessage;
use App\Modules\Tickets\Notifications\TicketInternalUpdateNotification;
use App\Modules\Tickets\Services\SectorProvisioningService;
use App\Modules\Tickets\Services\TicketWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class TicketInternalUpdatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_does_not_see_internal_updates_but_operational_users_do(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);
        $technician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $ticket = $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'title' => 'Chamado com canal interno',
            'assignee_id' => $technician->id,
        ]);

        app(TicketWorkflowService::class)->addMessage(
            $technician,
            $ticket,
            'Diagnostico interno para alinhamento.',
            isInternal: true,
        );

        Livewire::actingAs($requester)
            ->test(ShowPage::class, ['ticket' => $ticket->fresh()])
            ->assertDontSee('Atualizacoes internas')
            ->assertDontSee('Diagnostico interno para alinhamento.');

        Livewire::actingAs($technician)
            ->test(ShowPage::class, ['ticket' => $ticket->fresh()])
            ->assertSee('Atualizacoes internas')
            ->assertSee('Diagnostico interno para alinhamento.');
    }

    public function test_operator_can_send_internal_update_with_mentions_and_requester_cannot(): void
    {
        Notification::fake();

        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);
        $technician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);
        $manager = User::factory()->create([
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $sector->id,
        ]);

        $ticket = $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'title' => 'Chamado para mencionar time',
            'assignee_id' => $technician->id,
        ]);

        Livewire::actingAs($technician)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->set('internalMessage', 'Consegue validar a regra do firewall, @'.$manager->name.'?')
            ->set('internalMentionedUserIds', [$manager->id])
            ->call('sendInternalUpdate')
            ->assertHasNoErrors()
            ->assertSee('@'.$manager->name)
            ->assertDontSee('Marcado: '.$manager->name);

        $message = TicketMessage::query()
            ->where('ticket_id', $ticket->id)
            ->where('message', 'Consegue validar a regra do firewall, @'.$manager->name.'?')
            ->firstOrFail();

        $this->assertTrue($message->is_internal);
        $this->assertSame([$manager->id], $message->mentioned_user_ids);

        Notification::assertSentTo($manager, TicketInternalUpdateNotification::class);
        Notification::assertNotSentTo($requester, TicketInternalUpdateNotification::class);
        Notification::assertNotSentTo($technician, TicketInternalUpdateNotification::class);

        Livewire::actingAs($requester)
            ->test(ShowPage::class, ['ticket' => $ticket->fresh()])
            ->set('internalMessage', 'Nao deveria conseguir.')
            ->call('sendInternalUpdate')
            ->assertForbidden();
    }

    public function test_internal_update_attachment_is_hidden_from_requester_downloads(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);
        $technician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $ticket = $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'title' => 'Chamado com anexo interno',
            'assignee_id' => $technician->id,
        ]);
        $file = UploadedFile::fake()->create('interno.txt', 10, 'text/plain');

        Livewire::actingAs($technician)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->set('internalMessage', 'Segue arquivo interno')
            ->set('internalChatFiles', [$file])
            ->call('sendInternalUpdate')
            ->assertHasNoErrors()
            ->assertSee('interno.txt');

        $message = TicketMessage::query()
            ->where('ticket_id', $ticket->id)
            ->where('message', 'Segue arquivo interno')
            ->firstOrFail();
        $attachment = TicketAttachment::query()
            ->where('ticket_id', $ticket->id)
            ->where('ticket_message_id', $message->id)
            ->firstOrFail();

        Livewire::actingAs($requester)
            ->test(ShowPage::class, ['ticket' => $ticket->fresh()])
            ->assertDontSee('interno.txt');

        $this->actingAs($requester)
            ->get(route('tickets.attachments.show', $attachment))
            ->assertForbidden();

        $this->actingAs($technician)
            ->get(route('tickets.attachments.show', $attachment))
            ->assertOk();
    }

    private function ticketContext(string $sectorName = 'Suporte Interno'): array
    {
        $company = Company::query()->create([
            'name' => "Empresa {$sectorName}",
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
            'name' => "Sala {$sectorName}",
            'is_active' => true,
        ]);

        $board = app(SectorProvisioningService::class)->provision($sector);

        return [
            'company' => $company,
            'sector' => $sector,
            'room' => $room,
            'board' => $board,
            'group' => $board->groups()->where('is_closed', false)->orderBy('sort_order')->firstOrFail(),
            'status' => $board->statuses()->where('is_closed', false)->orderBy('sort_order')->firstOrFail(),
        ];
    }

    private function ticketFor(User $requester, array $attributes): Ticket
    {
        return Ticket::query()->create([
            'sector_id' => $attributes['sector_id'],
            'ticket_board_id' => $attributes['ticket_board_id'],
            'ticket_group_id' => $attributes['ticket_group_id'],
            'ticket_status_id' => $attributes['ticket_status_id'],
            'service_catalog_item_id' => $attributes['service_catalog_item_id'] ?? null,
            'room_id' => $attributes['room_id'] ?? null,
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? 'Descricao do chamado.',
            'requester_id' => $requester->id,
            'assignee_id' => $attributes['assignee_id'] ?? null,
            'priority' => $attributes['priority'] ?? TicketPriority::MEDIUM,
            'resolved_at' => $attributes['resolved_at'] ?? null,
            'last_activity_at' => $attributes['last_activity_at'] ?? now(),
        ]);
    }
}
