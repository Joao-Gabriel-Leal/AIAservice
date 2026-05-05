<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Notifications\TicketActivityNotification;
use App\Modules\Tickets\Services\SectorProvisioningService;
use App\Modules\Users\Notifications\AccountCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_only_their_notifications_in_the_inbox(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $user = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        $otherUser = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        $ticket = $this->makeTicket($user, $board->id, $group->id, $status->id, $sector->id, $room->id, 'Impressora com falha');

        $user->notify(new TicketActivityNotification($ticket, 'Atualizacao do ticket', 'Sua fila foi atualizada.'));
        $otherUser->notify(new TicketActivityNotification($ticket, 'Outro usuario', 'Nao deve aparecer.'));

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertOk();
        $response->assertSeeText('Atualizacao do ticket');
        $response->assertDontSeeText('Outro usuario');
    }

    public function test_opening_a_notification_marks_it_as_read_and_redirects_to_the_ticket(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $user = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        $ticket = $this->makeTicket($user, $board->id, $group->id, $status->id, $sector->id, $room->id, 'Notebook sem rede');

        $user->notify(new TicketActivityNotification($ticket, 'Novo evento', 'Abrir deve marcar como lida.'));
        $notification = $user->notifications()->firstOrFail();

        $response = $this->actingAs($user)->get(route('notifications.open', $notification->id));

        $response->assertRedirect(route('tickets.show', $ticket));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_opening_account_created_notification_with_password_change_redirects_to_security_form(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
        ]);

        $user->notify(new AccountCreatedNotification($user->email, true));
        $notification = $user->notifications()->firstOrFail();

        $response = $this->actingAs($user)->get(route('notifications.open', $notification->id));

        $response->assertRedirect(route('profile.edit').'#seguranca');
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_their_notification_as_read_explicitly(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $user = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        $ticket = $this->makeTicket($user, $board->id, $group->id, $status->id, $sector->id, $room->id, 'Mouse sem resposta');

        $user->notify(new TicketActivityNotification($ticket, 'Atualizacao manual', 'Pode marcar pela inbox.'));
        $notification = $user->notifications()->firstOrFail();

        $response = $this->actingAs($user)->post(route('notifications.mark-read', $notification->id));

        $response->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $user = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        $otherUser = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        $ticket = $this->makeTicket($otherUser, $board->id, $group->id, $status->id, $sector->id, $room->id, 'Chamado alheio');

        $otherUser->notify(new TicketActivityNotification($ticket, 'Privada', 'Nao deve ser acessivel.'));
        $notification = $otherUser->notifications()->firstOrFail();

        $response = $this->actingAs($user)->post(route('notifications.mark-read', $notification->id));

        $response->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    private function ticketContext(): array
    {
        $company = Company::query()->create([
            'name' => 'Empresa Notificacoes',
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
            'name' => 'Sala Principal',
            'is_active' => true,
        ]);

        $board = app(SectorProvisioningService::class)->provision($sector);

        return [
            'sector' => $sector,
            'room' => $room,
            'board' => $board,
            'group' => $board->groups()->firstOrFail(),
            'status' => $board->statuses()->firstOrFail(),
        ];
    }

    private function makeTicket(
        User $requester,
        int $boardId,
        int $groupId,
        int $statusId,
        int $sectorId,
        int $roomId,
        string $title,
    ): Ticket {
        return Ticket::query()->create([
            'sector_id' => $sectorId,
            'ticket_board_id' => $boardId,
            'ticket_group_id' => $groupId,
            'ticket_status_id' => $statusId,
            'room_id' => $roomId,
            'title' => $title,
            'description' => 'Descricao do ticket.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);
    }
}
