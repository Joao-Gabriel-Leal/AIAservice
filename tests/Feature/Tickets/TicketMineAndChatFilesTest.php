<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketFieldType;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Livewire\MinePage;
use App\Modules\Tickets\Livewire\ShowPage;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketAttachment;
use App\Modules\Tickets\Models\TicketField;
use App\Modules\Tickets\Models\TicketFieldValue;
use App\Modules\Tickets\Models\TicketMessage;
use App\Modules\Tickets\Notifications\TicketRatingRequestNotification;
use App\Modules\Tickets\Notifications\TicketUpdateNotification;
use App\Modules\Tickets\Services\SectorProvisioningService;
use App\Modules\Tickets\Services\TicketWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class TicketMineAndChatFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_tickets_is_available_to_all_profiles_and_only_lists_own_tickets(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);
        $operator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);
        $manager = User::factory()->create([
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $sector->id,
        ]);

        $ownTicket = $this->ticketFor($operator, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'title' => 'Chamado aberto pelo operador',
        ]);
        $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'title' => 'Chamado de outra pessoa no mesmo setor',
        ]);

        foreach ([$requester, $operator, $manager] as $user) {
            $this->actingAs($user)
                ->get(route('tickets.mine'))
                ->assertOk()
                ->assertSee('Meus chamados')
                ->assertSee('Somente solicitacoes do seu usuario')
                ->assertSee('Filtros pessoais');
        }

        Livewire::actingAs($operator)
            ->test(MinePage::class)
            ->assertSee($ownTicket->title)
            ->assertDontSee('Chamado de outra pessoa no mesmo setor');
    }

    public function test_my_tickets_filters_by_status_sector_and_sla(): void
    {
        Carbon::setTestNow('2026-05-06 10:00:00');

        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status, 'closedGroup' => $closedGroup, 'closedStatus' => $closedStatus] = $this->ticketContext();
        ['sector' => $otherSector, 'board' => $otherBoard, 'group' => $otherGroup, 'status' => $otherStatus] = $this->ticketContext('Financeiro');

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $openOk = $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'title' => 'Notebook em dia',
            'first_response_due_at' => now()->addHours(2),
            'resolution_due_at' => now()->addHours(4),
        ]);
        $closed = $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $closedGroup->id,
            'ticket_status_id' => $closedStatus->id,
            'title' => 'VPN finalizada',
            'resolved_at' => now()->subMinutes(10),
        ]);
        $warning = $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'title' => 'Impressora a vencer',
            'first_response_due_at' => now()->addMinutes(15),
            'resolution_due_at' => now()->addHours(2),
        ]);
        $breached = $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'title' => 'Acesso estourado',
            'first_response_due_at' => now()->subMinutes(15),
            'resolution_due_at' => now()->addHour(),
        ]);
        $otherSectorTicket = $this->ticketFor($requester, [
            'sector_id' => $otherSector->id,
            'ticket_board_id' => $otherBoard->id,
            'ticket_group_id' => $otherGroup->id,
            'ticket_status_id' => $otherStatus->id,
            'title' => 'Demanda financeira',
        ]);

        Livewire::actingAs($requester)
            ->test(MinePage::class)
            ->set('statusFilter', 'closed')
            ->assertSee($closed->title)
            ->assertDontSee($openOk->title)
            ->set('statusFilter', 'open')
            ->assertSee($openOk->title)
            ->assertDontSee($closed->title)
            ->set('statusFilter', 'all')
            ->set('selectedSectorId', $sector->id)
            ->assertSee($breached->title)
            ->assertDontSee($otherSectorTicket->title)
            ->set('selectedSectorId', null)
            ->set('slaFilter', 'breached')
            ->assertSee($breached->title)
            ->assertDontSee($openOk->title)
            ->set('slaFilter', 'warning')
            ->assertSee($warning->title)
            ->assertDontSee($breached->title)
            ->set('slaFilter', 'ok')
            ->assertSee($openOk->title)
            ->assertDontSee($warning->title);
    }

    public function test_requester_can_close_and_reopen_own_ticket(): void
    {
        Notification::fake();

        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status, 'closedGroup' => $closedGroup, 'closedStatus' => $closedStatus] = $this->ticketContext();

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
            'title' => 'Encerrar pelo solicitante',
        ]);

        Livewire::actingAs($requester)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->assertSee('Finalizar chamado')
            ->assertDontSee('Reabrir chamado')
            ->assertSee('wire:click="closeOwnTicket"', false)
            ->call('closeOwnTicket')
            ->assertHasNoErrors()
            ->assertSee('Chamado finalizado com sucesso.')
            ->assertSee('Reabrir chamado')
            ->assertDontSee('Finalizar chamado')
            ->assertSee('wire:click="reopenOwnTicket"', false);

        $ticket->refresh();

        $this->assertSame($closedGroup->id, $ticket->ticket_group_id);
        $this->assertSame($closedStatus->id, $ticket->ticket_status_id);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertTrue($ticket->fresh(['group', 'status'])->isClosed());
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'user_id' => null,
            'message' => 'Chamado finalizado pelo solicitante.',
            'is_system' => true,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => Ticket::class,
            'subject_id' => $ticket->id,
            'event' => 'ticket.closed_by_requester',
        ]);
        Notification::assertSentTo($technician, TicketUpdateNotification::class);
        Notification::assertSentTo($requester, TicketRatingRequestNotification::class);

        Notification::fake();

        Livewire::actingAs($requester)
            ->test(ShowPage::class, ['ticket' => $ticket->fresh()])
            ->call('reopenOwnTicket')
            ->assertHasNoErrors()
            ->assertSee('Chamado reaberto com sucesso.')
            ->assertSee('Finalizar chamado')
            ->assertDontSee('Reabrir chamado');

        $ticket->refresh();

        $this->assertSame($group->id, $ticket->ticket_group_id);
        $this->assertSame($status->id, $ticket->ticket_status_id);
        $this->assertNull($ticket->resolved_at);
        $this->assertFalse($ticket->fresh(['group', 'status'])->isClosed());
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'user_id' => null,
            'message' => 'Chamado reaberto pelo solicitante.',
            'is_system' => true,
        ]);
        Notification::assertSentTo($technician, TicketUpdateNotification::class);
    }

    public function test_requester_detail_hides_operational_history_and_shows_only_filled_request_information(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();
        $catalog = $board->catalogItems()->with('form')->firstOrFail();
        $form = $catalog->form;

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
            'service_catalog_item_id' => $catalog->id,
            'title' => 'Chamado com dados extras',
        ]);

        $filledField = TicketField::query()->create([
            'ticket_board_id' => $board->id,
            'name' => 'Patrimonio',
            'slug' => 'patrimonio',
            'type' => TicketFieldType::TEXT,
            'settings' => [],
            'sort_order' => 1,
            'is_required' => false,
            'show_on_board' => false,
            'is_active' => true,
        ]);
        $form->fields()->attach($filledField->id, [
            'is_required' => false,
            'sort_order' => 1,
        ]);
        $emptyField = TicketField::query()->create([
            'ticket_board_id' => $board->id,
            'name' => 'Campo vazio',
            'slug' => 'campo-vazio',
            'type' => TicketFieldType::TEXT,
            'settings' => [],
            'sort_order' => 2,
            'is_required' => false,
            'show_on_board' => false,
            'is_active' => true,
        ]);
        $form->fields()->attach($emptyField->id, [
            'is_required' => false,
            'sort_order' => 2,
        ]);
        $internalField = TicketField::query()->create([
            'ticket_board_id' => $board->id,
            'name' => 'Campo interno',
            'slug' => 'campo-interno',
            'type' => TicketFieldType::TEXT,
            'settings' => [],
            'sort_order' => 3,
            'is_required' => false,
            'show_on_board' => false,
            'is_active' => true,
        ]);
        TicketFieldValue::query()->create([
            'ticket_id' => $ticket->id,
            'ticket_field_id' => $filledField->id,
            'value' => ['value' => 'ABC123'],
        ]);
        TicketFieldValue::query()->create([
            'ticket_id' => $ticket->id,
            'ticket_field_id' => $internalField->id,
            'value' => ['value' => 'Segredo operacional'],
        ]);
        $ticket->activityLogs()->create([
            'sector_id' => $sector->id,
            'causer_id' => $technician->id,
            'event' => 'ticket.updated',
            'description' => 'Campo interno alterado.',
            'properties' => [],
        ]);

        Livewire::actingAs($requester)
            ->test(ShowPage::class, ['ticket' => $ticket->fresh()])
            ->assertSee('Informacoes da solicitacao')
            ->assertSee('Patrimonio')
            ->assertSee('ABC123')
            ->assertSee('Resumo do atendimento')
            ->assertDontSee('Campos do chamado')
            ->assertDontSee('Campo vazio')
            ->assertDontSee('Campo interno')
            ->assertDontSee('Segredo operacional')
            ->assertDontSee('Historico')
            ->assertDontSee('Auditoria')
            ->assertDontSee('Campo interno alterado')
            ->assertDontSee('1 evento')
            ->assertDontSee('Atualizacoes operacionais')
            ->assertDontSee('Prazos em blocos menores');
    }

    public function test_operational_user_sees_history_and_editable_ticket_fields(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);
        $operator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);
        $ticket = $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'title' => 'Chamado operacional',
        ]);

        TicketField::query()->create([
            'ticket_board_id' => $board->id,
            'name' => 'Campo vazio operacional',
            'slug' => 'campo-vazio-operacional',
            'type' => TicketFieldType::TEXT,
            'settings' => [],
            'sort_order' => 1,
            'is_required' => false,
            'show_on_board' => false,
            'is_active' => true,
        ]);
        $ticket->activityLogs()->create([
            'sector_id' => $sector->id,
            'causer_id' => $operator->id,
            'event' => 'ticket.updated',
            'description' => 'Campo operacional atualizado.',
            'properties' => [],
        ]);

        Livewire::actingAs($operator)
            ->test(ShowPage::class, ['ticket' => $ticket->fresh()])
            ->assertSee('Historico')
            ->assertSee('Auditoria')
            ->assertSee('1 evento')
            ->assertSee('Campo operacional atualizado.')
            ->assertSee('Atualizacoes operacionais')
            ->assertSee('SLA')
            ->assertSee('Campos do chamado')
            ->assertSee('Campo vazio operacional')
            ->assertSee('wire:change="updateDynamicField', false);
    }

    public function test_my_tickets_highlights_closed_tickets_pending_rating(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status, 'closedGroup' => $closedGroup, 'closedStatus' => $closedStatus] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $pendingRating = $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $closedGroup->id,
            'ticket_status_id' => $closedStatus->id,
            'title' => 'Chamado finalizado sem avaliacao',
            'resolved_at' => now()->subMinutes(20),
        ]);

        $ratedTicket = $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $closedGroup->id,
            'ticket_status_id' => $closedStatus->id,
            'title' => 'Chamado finalizado ja avaliado',
            'resolved_at' => now()->subMinutes(30),
        ]);
        $ratedTicket->rating()->create([
            'user_id' => $requester->id,
            'rating' => 5,
            'comment' => 'Resolvido.',
        ]);

        $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'title' => 'Chamado aberto sem avaliacao',
        ]);

        Livewire::actingAs($requester)
            ->test(MinePage::class)
            ->set('titleFilter', $pendingRating->title)
            ->assertSee('Avalie o atendimento')
            ->set('titleFilter', $ratedTicket->title)
            ->assertDontSee('Avalie o atendimento');
    }

    public function test_non_requester_cannot_close_or_reopen_someone_elses_ticket(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status, 'closedGroup' => $closedGroup, 'closedStatus' => $closedStatus] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);
        $technician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $openTicket = $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'title' => 'Nao pode fechar',
        ]);
        $closedTicket = $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $closedGroup->id,
            'ticket_status_id' => $closedStatus->id,
            'title' => 'Nao pode reabrir',
            'resolved_at' => now(),
        ]);

        try {
            app(TicketWorkflowService::class)->closeByRequester($technician, $openTicket);
            $this->fail('Non requester closed another user ticket.');
        } catch (AuthorizationException) {
            $this->assertNull($openTicket->fresh()->resolved_at);
        }

        try {
            app(TicketWorkflowService::class)->reopenByRequester($technician, $closedTicket);
            $this->fail('Non requester reopened another user ticket.');
        } catch (AuthorizationException) {
            $this->assertNotNull($closedTicket->fresh()->resolved_at);
        }
    }

    public function test_closed_ticket_blocks_chat_for_requester_operator_and_manager(): void
    {
        ['sector' => $sector, 'board' => $board, 'closedGroup' => $closedGroup, 'closedStatus' => $closedStatus] = $this->ticketContext();

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
            'ticket_group_id' => $closedGroup->id,
            'ticket_status_id' => $closedStatus->id,
            'title' => 'Chat bloqueado em chamado fechado',
            'assignee_id' => $technician->id,
            'resolved_at' => now()->subHour(),
        ]);

        foreach ([$requester, $technician, $manager] as $user) {
            Livewire::actingAs($user)
                ->test(ShowPage::class, ['ticket' => $ticket])
                ->assertSee('A conversa fica bloqueada')
                ->assertDontSee('Enviar mensagem')
                ->set('message', "Tentativa de {$user->id}")
                ->call('sendMessage')
                ->assertForbidden();
        }

        $this->assertSame(0, TicketMessage::query()->where('ticket_id', $ticket->id)->where('is_system', false)->count());
    }

    public function test_chat_accepts_text_files_and_file_only_messages(): void
    {
        Notification::fake();

        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);
        $ticket = $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'title' => 'Chat com evidencia',
        ]);
        $image = UploadedFile::fake()->create('print.png', 512, 'image/png');

        Livewire::actingAs($requester)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->set('message', 'Segue print')
            ->set('chatFiles', [$image])
            ->call('sendMessage')
            ->assertHasNoErrors()
            ->assertSee('Segue print')
            ->assertSee('print.png')
            ->assertSee('Chat');

        $message = TicketMessage::query()
            ->where('ticket_id', $ticket->id)
            ->where('message', 'Segue print')
            ->firstOrFail();
        $attachment = TicketAttachment::query()
            ->where('ticket_id', $ticket->id)
            ->where('ticket_message_id', $message->id)
            ->firstOrFail();

        $this->assertSame('chat', $attachment->source);
        $this->assertTrue($attachment->isImage());

        $this->actingAs($requester)
            ->get(route('tickets.attachments.inline', $attachment))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename="print.png"')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $video = UploadedFile::fake()->create('evidencia.mp4', 100, 'video/mp4');

        Livewire::actingAs($requester)
            ->test(ShowPage::class, ['ticket' => $ticket->fresh()])
            ->set('chatFiles', [$video])
            ->call('sendMessage')
            ->assertHasNoErrors()
            ->assertSee('evidencia.mp4');

        $fileOnlyMessage = TicketMessage::query()
            ->where('ticket_id', $ticket->id)
            ->where('message', '')
            ->latest()
            ->firstOrFail();

        $this->assertDatabaseHas('ticket_attachments', [
            'ticket_id' => $ticket->id,
            'ticket_message_id' => $fileOnlyMessage->id,
            'source' => 'chat',
            'original_name' => 'evidencia.mp4',
        ]);
    }

    public function test_chat_file_validation_limits_count_size_and_requires_text_without_files(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);
        $ticket = $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'title' => 'Validar arquivos',
        ]);

        Livewire::actingAs($requester)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->set('message', '')
            ->call('sendMessage')
            ->assertHasErrors(['message' => 'required']);

        $files = collect(range(1, 6))
            ->map(fn (int $index) => UploadedFile::fake()->create("arquivo-{$index}.txt", 1, 'text/plain'))
            ->all();

        Livewire::actingAs($requester)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->set('chatFiles', $files)
            ->call('sendMessage')
            ->assertHasErrors(['chatFiles' => 'max']);

        $largeFile = UploadedFile::fake()->create('arquivo-grande.zip', 10241, 'application/zip');

        Livewire::actingAs($requester)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->set('chatFiles', [$largeFile])
            ->call('sendMessage')
            ->assertHasErrors(['chatFiles.0']);

        $unsupportedFile = UploadedFile::fake()->create('script.html', 10, 'text/html');

        Livewire::actingAs($requester)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->set('chatFiles', [$unsupportedFile])
            ->call('sendMessage')
            ->assertHasErrors(['chatFiles.0']);

        $tooLargeBatch = [
            UploadedFile::fake()->create('evidencia-1.zip', 9000, 'application/zip'),
            UploadedFile::fake()->create('evidencia-2.zip', 9000, 'application/zip'),
            UploadedFile::fake()->create('evidencia-3.zip', 9000, 'application/zip'),
        ];

        Livewire::actingAs($requester)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->set('chatFiles', $tooLargeBatch)
            ->call('sendMessage')
            ->assertHasErrors(['chatFiles']);
    }

    public function test_inline_preview_is_blocked_for_non_safe_attachment_types(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);
        $ticket = $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'title' => 'Download seguro',
        ]);

        $attachment = TicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'ticket_message_id' => null,
            'uploaded_by_id' => $requester->id,
            'source' => 'chat',
            'disk' => 'database',
            'path' => 'tickets/'.$ticket->id.'/manual.pdf',
            'original_name' => 'manual.pdf',
            'mime_type' => 'application/pdf',
            'size' => 12,
            'content' => (new TicketAttachment)->encodeContentForStorage('%PDF-sample%'),
        ]);

        $this->actingAs($requester)
            ->get(route('tickets.attachments.inline', $attachment))
            ->assertNotFound();

        $this->actingAs($requester)
            ->get(route('tickets.attachments.show', $attachment))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    private function ticketContext(string $sectorName = 'Suporte'): array
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
            'closedGroup' => $board->groups()->where('is_closed', true)->orderBy('sort_order')->firstOrFail(),
            'status' => $board->statuses()->where('is_closed', false)->orderBy('sort_order')->firstOrFail(),
            'closedStatus' => $board->statuses()->where('is_closed', true)->orderBy('sort_order')->firstOrFail(),
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
            'first_response_due_at' => $attributes['first_response_due_at'] ?? null,
            'first_responded_at' => $attributes['first_responded_at'] ?? null,
            'first_response_breached_at' => $attributes['first_response_breached_at'] ?? null,
            'resolution_due_at' => $attributes['resolution_due_at'] ?? null,
            'resolution_breached_at' => $attributes['resolution_breached_at'] ?? null,
            'resolved_at' => $attributes['resolved_at'] ?? null,
            'last_activity_at' => $attributes['last_activity_at'] ?? now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
