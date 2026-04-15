<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketFieldType;
use App\Enums\TicketPriority;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Livewire\BoardPage;
use App\Modules\Tickets\Livewire\CreatePage;
use App\Modules\Tickets\Livewire\IndexPage;
use App\Modules\Tickets\Livewire\ShowPage;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketAttachment;
use App\Modules\Tickets\Models\TicketField;
use App\Modules\Tickets\Models\TicketFieldOption;
use App\Modules\Tickets\Models\TicketFieldValue;
use App\Modules\Tickets\Models\TicketTimeEntry;
use App\Modules\Tickets\Notifications\TicketCreatedNotification;
use App\Modules\Tickets\Notifications\TicketMessageNotification;
use App\Modules\Tickets\Notifications\TicketRatingRequestNotification;
use App\Modules\Tickets\Notifications\TicketUpdateNotification;
use App\Modules\Tickets\Services\SectorProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class TicketFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_open_a_ticket_from_the_catalog(): void
    {
        Notification::fake();

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

        Notification::assertSentTo(
            $requester,
            TicketCreatedNotification::class,
            fn (TicketCreatedNotification $notification, array $channels) => in_array('database', $channels, true)
                && in_array('mail', $channels, true)
                && data_get($notification->toArray($requester), 'title') === 'Novo chamado criado'
        );
    }

    public function test_ticket_index_can_filter_by_fixed_columns_and_dynamic_fields(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $requesterA = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $requesterB = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $technicianA = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $technicianB = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $systemField = TicketField::query()->create([
            'ticket_board_id' => $board->id,
            'name' => 'Sistema',
            'slug' => 'sistema',
            'type' => TicketFieldType::TEXT,
            'placeholder' => 'Informe o sistema',
            'help_text' => 'Sistema relacionado ao chamado',
            'settings' => [],
            'sort_order' => 1,
            'is_required' => false,
            'show_on_board' => true,
            'is_active' => true,
        ]);

        $matchingTicket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Rede ERP indisponivel',
            'description' => 'Problema no ERP',
            'requester_id' => $requesterA->id,
            'assignee_id' => $technicianA->id,
            'priority' => TicketPriority::HIGH,
            'last_activity_at' => now(),
        ]);

        $otherTicket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Impressora sem toner',
            'description' => 'Problema local',
            'requester_id' => $requesterB->id,
            'assignee_id' => $technicianB->id,
            'priority' => TicketPriority::LOW,
            'last_activity_at' => now()->subDay(),
        ]);

        foreach ([
            [$matchingTicket, 'ERP'],
            [$otherTicket, 'IMPRESSORA'],
        ] as [$ticket, $value]) {
            $fieldValue = new TicketFieldValue([
                'ticket_id' => $ticket->id,
                'ticket_field_id' => $systemField->id,
            ]);

            $fieldValue->storePrimitiveValue($value);
            $fieldValue->save();
        }

        Livewire::actingAs($technicianA)
            ->test(IndexPage::class)
            ->assertSee('Sistema')
            ->set('titleFilter', 'Rede ERP')
            ->assertSee('Rede ERP indisponivel')
            ->assertDontSee('Impressora sem toner')
            ->set('titleFilter', '')
            ->set('requesterFilter', $requesterA->name)
            ->assertSee('Rede ERP indisponivel')
            ->assertDontSee('Impressora sem toner')
            ->set('requesterFilter', '')
            ->set('assigneeFilter', $technicianA->name)
            ->assertSee('Rede ERP indisponivel')
            ->assertDontSee('Impressora sem toner')
            ->set('assigneeFilter', '')
            ->set("fieldFilters.{$systemField->id}", 'ERP')
            ->assertSee('Rede ERP indisponivel')
            ->assertDontSee('Impressora sem toner');
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
            ->assertSet('selectedFormId', null)
            ->set('selectedSectorId', $sector->id)
            ->assertSet('selectedCatalogId', null)
            ->assertSet('selectedFormId', null);
    }

    public function test_requester_can_open_a_ticket_from_an_active_form_without_catalog_item(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $form = $board->forms()->create([
            'name' => 'Criar conta',
            'description' => 'Formulario sem item de catalogo.',
            'is_default' => false,
            'is_active' => true,
        ]);

        Livewire::actingAs($requester)
            ->test(CreatePage::class)
            ->set('selectedSectorId', $sector->id)
            ->set('selectedFormId', $form->id)
            ->set('title', 'Novo acesso')
            ->set('description', 'Preciso criar uma conta para colaborador.')
            ->set('priority', TicketPriority::MEDIUM->value)
            ->call('submit')
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'requester_id' => $requester->id,
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'service_catalog_item_id' => null,
            'title' => 'Novo acesso',
        ]);
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

    public function test_board_keeps_ticket_order_stable_when_updating_a_dynamic_field(): void
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

        $systemField = TicketField::query()->create([
            'ticket_board_id' => $board->id,
            'name' => 'Sistema',
            'slug' => 'sistema',
            'type' => TicketFieldType::SELECT,
            'placeholder' => 'Selecione o sistema',
            'help_text' => 'Sistema afetado',
            'settings' => [],
            'sort_order' => 1,
            'is_required' => false,
            'show_on_board' => true,
            'is_active' => true,
        ]);

        foreach ([
            ['label' => 'RD', 'value' => 'RD', 'color' => '#94a3b8', 'sort_order' => 1],
            ['label' => 'SIS', 'value' => 'SIS', 'color' => '#10b981', 'sort_order' => 2],
            ['label' => 'TESTE', 'value' => 'TESTE', 'color' => '#3b82f6', 'sort_order' => 3],
        ] as $option) {
            TicketFieldOption::query()->create([
                'ticket_field_id' => $systemField->id,
                ...$option,
                'is_default' => false,
            ]);
        }

        $olderTicket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Erro sisanadem',
            'description' => 'Chamado mais antigo',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        $newerTicket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Erro RD',
            'description' => 'Chamado mais novo',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        foreach ([
            [$olderTicket->id, 'RD'],
            [$newerTicket->id, 'TESTE'],
        ] as [$ticketId, $value]) {
            $fieldValue = new TicketFieldValue([
                'ticket_id' => $ticketId,
                'ticket_field_id' => $systemField->id,
            ]);

            $fieldValue->storePrimitiveValue($value);
            $fieldValue->save();
        }

        Livewire::actingAs($technician)
            ->test(BoardPage::class, ['sector' => $sector])
            ->assertSeeInOrder(['Erro sisanadem', 'Erro RD'])
            ->call('updateDynamicField', $olderTicket->id, $systemField->id, 'SIS')
            ->assertSeeInOrder(['Erro sisanadem', 'Erro RD']);

        $this->assertSame(
            'SIS',
            TicketFieldValue::query()
                ->where('ticket_id', $olderTicket->id)
                ->where('ticket_field_id', $systemField->id)
                ->firstOrFail()
                ->primitive_value
        );

        $this->assertSame(
            'TESTE',
            TicketFieldValue::query()
                ->where('ticket_id', $newerTicket->id)
                ->where('ticket_field_id', $systemField->id)
                ->firstOrFail()
                ->primitive_value
        );
    }

    public function test_board_can_move_ticket_to_another_group_and_close_it(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status, 'closedGroup' => $closedGroup, 'closedStatus' => $closedStatus] = $this->ticketContext();

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
            'title' => 'Mover para encerrado',
            'description' => 'Fluxo de grupo',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($technician)
            ->test(BoardPage::class, ['sector' => $sector])
            ->call('moveTicketToGroup', $ticket->id, $closedGroup->id);

        $ticket->refresh();

        $this->assertSame($closedGroup->id, $ticket->ticket_group_id);
        $this->assertSame($closedStatus->id, $ticket->ticket_status_id);
        $this->assertNotNull($ticket->resolved_at);
    }

    public function test_board_can_move_ticket_to_another_group_when_group_id_is_a_numeric_string(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status, 'closedGroup' => $closedGroup, 'closedStatus' => $closedStatus] = $this->ticketContext();

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
            'title' => 'Mover com string numerica',
            'description' => 'Fluxo com valor vindo do select',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($technician)
            ->test(BoardPage::class, ['sector' => $sector])
            ->call('moveTicketToGroup', $ticket->id, (string) $closedGroup->id);

        $ticket->refresh();

        $this->assertSame($closedGroup->id, $ticket->ticket_group_id);
        $this->assertSame($closedStatus->id, $ticket->ticket_status_id);
        $this->assertNotNull($ticket->resolved_at);
    }

    public function test_board_can_move_ticket_to_no_group_when_receiving_null(): void
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
            'title' => 'Mover para sem etapa',
            'description' => 'Fluxo com grupo nulo',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($technician)
            ->test(BoardPage::class, ['sector' => $sector])
            ->call('moveTicketToGroup', $ticket->id, null);

        $ticket->refresh();

        $this->assertNull($ticket->ticket_group_id);
        $this->assertSame($status->id, $ticket->ticket_status_id);
        $this->assertNull($ticket->resolved_at);
    }

    public function test_board_renders_kanban_mode_with_ungrouped_column(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $technician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => null,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Chamado sem etapa',
            'description' => 'Aguardando triagem',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::LOW,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($technician)
            ->test(BoardPage::class, ['sector' => $sector])
            ->call('setViewMode', 'kanban')
            ->assertSet('viewMode', 'kanban')
            ->assertSee('Sem etapa')
            ->assertSee('Chamado sem etapa')
            ->assertSee('Arraste o card para mover de coluna');
    }

    public function test_board_markup_does_not_render_inline_number_calls_for_group_changes(): void
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

        Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Markup do quadro',
            'description' => 'Sem Number inline',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::LOW,
            'last_activity_at' => now(),
        ]);

        $component = Livewire::actingAs($technician)
            ->test(BoardPage::class, ['sector' => $sector])
            ->call('setViewMode', 'kanban');

        $this->assertStringNotContainsString('Number(', $component->html());
    }

    public function test_requester_can_open_a_ticket_with_binary_attachment(): void
    {
        ['sector' => $sector, 'catalog' => $catalog] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $attachmentContent = hex2bin('255044462d312e370aff0042494e');

        $response = Livewire::actingAs($requester)
            ->test(CreatePage::class)
            ->set('selectedSectorId', $sector->id)
            ->set('selectedCatalogId', $catalog->id)
            ->set('title', 'Chamado com anexo')
            ->set('description', 'Enviando PDF binario.')
            ->set('priority', TicketPriority::HIGH->value)
            ->set('attachments', [
                UploadedFile::fake()->createWithContent('NF.pdf', $attachmentContent),
            ])
            ->call('submit');

        $response->assertRedirect();

        $ticket = Ticket::query()->where('title', 'Chamado com anexo')->firstOrFail();
        $attachment = TicketAttachment::query()->where('ticket_id', $ticket->id)->firstOrFail();

        $this->assertSame('NF.pdf', $attachment->original_name);
        $this->assertSame(strlen($attachmentContent), $attachment->size);
        $this->assertSame($attachmentContent, $attachment->binaryContent());
    }

    public function test_operational_user_can_track_time_without_being_the_assignee(): void
    {
        Carbon::setTestNow('2026-04-14 10:00:00');

        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $assignee = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $helper = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $ticket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Atendimento com apoio tecnico',
            'description' => 'Outro colaborador ajudou no chamado.',
            'requester_id' => $requester->id,
            'assignee_id' => $assignee->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($helper)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->call('startTimeEntry')
            ->assertHasNoErrors();

        $timeEntry = TicketTimeEntry::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $helper->id)
            ->firstOrFail();

        Carbon::setTestNow('2026-04-14 10:45:30');

        Livewire::actingAs($helper)
            ->test(ShowPage::class, ['ticket' => $ticket->fresh()])
            ->call('stopTimeEntry', $timeEntry->id)
            ->assertHasNoErrors();

        $timeEntry->refresh();

        $this->assertSame(2730, $timeEntry->duration_seconds);
        $this->assertNotNull($timeEntry->ended_at);
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => Ticket::class,
            'subject_id' => $ticket->id,
            'event' => 'ticket.time_entry.started',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => Ticket::class,
            'subject_id' => $ticket->id,
            'event' => 'ticket.time_entry.stopped',
        ]);
    }

    public function test_time_tracking_buttons_toggle_after_starting_and_stopping_a_session(): void
    {
        Carbon::setTestNow('2026-04-14 11:00:00');

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
            'title' => 'Troca visual do cronometro',
            'description' => 'Os botoes devem refletir o estado atual da sessao.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        $component = Livewire::actingAs($technician)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->assertSee('Iniciar cronometro')
            ->assertDontSee('Parar cronometro')
            ->call('startTimeEntry')
            ->assertHasNoErrors()
            ->assertSee('Parar cronometro')
            ->assertDontSee('Iniciar cronometro');

        $timeEntry = TicketTimeEntry::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $technician->id)
            ->firstOrFail();

        Carbon::setTestNow('2026-04-14 11:12:00');

        $component
            ->call('stopTimeEntry', $timeEntry->id)
            ->assertHasNoErrors()
            ->assertSee('Iniciar cronometro')
            ->assertDontSee('Parar cronometro');
    }

    public function test_stopping_time_entry_with_microseconds_persists_integer_duration(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-14 10:00:00.123456'));

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
            'title' => 'Cronometro com microssegundos',
            'description' => 'Nao deve salvar duracao decimal.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($technician)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->call('startTimeEntry')
            ->assertHasNoErrors();

        $timeEntry = TicketTimeEntry::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $technician->id)
            ->firstOrFail();

        Carbon::setTestNow(Carbon::parse('2026-04-14 10:00:45.688536'));

        Livewire::actingAs($technician)
            ->test(ShowPage::class, ['ticket' => $ticket->fresh()])
            ->call('stopTimeEntry', $timeEntry->id)
            ->assertHasNoErrors();

        $timeEntry->refresh();

        $this->assertSame(45, $timeEntry->duration_seconds);
        $this->assertIsInt($timeEntry->duration_seconds);
    }

    public function test_two_collaborators_can_register_time_and_ticket_totals_are_aggregated(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $firstTechnician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $secondTechnician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $ticket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Chamado colaborativo',
            'description' => 'Duas pessoas atuaram na mesma demanda.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::HIGH,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($firstTechnician)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->set('timeEntryForm.started_at', '2026-04-14T08:00')
            ->set('timeEntryForm.ended_at', '2026-04-14T09:30')
            ->call('saveTimeEntry')
            ->assertHasNoErrors();

        Livewire::actingAs($secondTechnician)
            ->test(ShowPage::class, ['ticket' => $ticket->fresh()])
            ->set('timeEntryForm.started_at', '2026-04-14T10:00')
            ->set('timeEntryForm.ended_at', '2026-04-14T12:00')
            ->call('saveTimeEntry')
            ->assertHasNoErrors();

        $ticket = $ticket->fresh(['timeEntries.user']);
        $timeSummary = $ticket->timeEntriesTotalByUser()->keyBy('user_id');

        $this->assertSame(12600, $ticket->timeEntriesTotalSeconds());
        $this->assertSame(5400, $timeSummary[$firstTechnician->id]['total_seconds']);
        $this->assertSame(7200, $timeSummary[$secondTechnician->id]['total_seconds']);

        Livewire::actingAs($firstTechnician)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->assertSee('03:30:00')
            ->assertSee($firstTechnician->name)
            ->assertSee($secondTechnician->name);
    }

    public function test_same_technician_cannot_open_two_sessions_on_same_ticket_but_can_on_another_ticket(): void
    {
        Carbon::setTestNow('2026-04-14 09:00:00');

        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $technician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $firstTicket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Primeiro chamado',
            'description' => 'Primeiro atendimento do dia.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        $secondTicket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Segundo chamado',
            'description' => 'Outro chamado em paralelo.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::HIGH,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($technician)
            ->test(ShowPage::class, ['ticket' => $firstTicket])
            ->call('startTimeEntry')
            ->assertHasNoErrors()
            ->call('startTimeEntry')
            ->assertHasErrors(['timeTracking']);

        Livewire::actingAs($technician)
            ->test(ShowPage::class, ['ticket' => $secondTicket])
            ->call('startTimeEntry')
            ->assertHasNoErrors();

        $this->assertSame(
            2,
            TicketTimeEntry::query()
                ->where('user_id', $technician->id)
                ->whereNull('ended_at')
                ->count()
        );
    }

    public function test_manual_time_entry_can_cross_midnight(): void
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
            'title' => 'Turno noturno',
            'description' => 'Sessao atravessando a meia-noite.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($technician)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->set('timeEntryForm.started_at', '2026-04-14T23:30')
            ->set('timeEntryForm.ended_at', '2026-04-15T01:15')
            ->call('saveTimeEntry')
            ->assertHasNoErrors();

        $timeEntry = TicketTimeEntry::query()->where('ticket_id', $ticket->id)->firstOrFail();

        $this->assertSame(6300, $timeEntry->duration_seconds);
    }

    public function test_requester_cannot_see_or_manage_time_tracking(): void
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
            'title' => 'Chamado sem tempo visivel',
            'description' => 'O solicitante nao pode ver o controle interno.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::LOW,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($requester)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->assertDontSee('Controle de tempo')
            ->call('startTimeEntry')
            ->assertForbidden();
    }

    public function test_sector_admin_can_update_and_delete_another_users_time_entry(): void
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

        $sectorAdmin = User::factory()->create([
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $sector->id,
        ]);

        $ticket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Ajuste administrativo',
            'description' => 'Admin precisa corrigir apontamento de outro tecnico.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::HIGH,
            'last_activity_at' => now(),
        ]);

        $timeEntry = TicketTimeEntry::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $technician->id,
            'source' => 'manual',
            'started_at' => Carbon::parse('2026-04-14 08:00:00'),
            'ended_at' => Carbon::parse('2026-04-14 09:00:00'),
            'duration_seconds' => 3600,
        ]);

        Livewire::actingAs($sectorAdmin)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->call('editTimeEntry', $timeEntry->id)
            ->set('timeEntryForm.started_at', '2026-04-14T08:15')
            ->set('timeEntryForm.ended_at', '2026-04-14T09:45')
            ->call('saveTimeEntry')
            ->assertHasNoErrors();

        $timeEntry->refresh();

        $this->assertSame(5400, $timeEntry->duration_seconds);

        Livewire::actingAs($sectorAdmin)
            ->test(ShowPage::class, ['ticket' => $ticket->fresh()])
            ->call('deleteTimeEntry', $timeEntry->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('ticket_time_entries', [
            'id' => $timeEntry->id,
        ]);
    }

    public function test_technician_cannot_manage_another_users_time_entry(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $owner = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $helper = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $ticket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Sessao de outro tecnico',
            'description' => 'Tecnico comum nao pode alterar apontamento alheio.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::LOW,
            'last_activity_at' => now(),
        ]);

        $timeEntry = TicketTimeEntry::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'source' => 'manual',
            'started_at' => Carbon::parse('2026-04-14 13:00:00'),
            'ended_at' => Carbon::parse('2026-04-14 14:00:00'),
            'duration_seconds' => 3600,
        ]);

        Livewire::actingAs($helper)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->call('editTimeEntry', $timeEntry->id)
            ->assertForbidden();
    }

    public function test_closing_ticket_auto_closes_open_time_entries_and_logs_it(): void
    {
        Carbon::setTestNow('2026-04-14 09:00:00');

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
            'title' => 'Fechamento automatico de tempo',
            'description' => 'Ao fechar o chamado, a sessao aberta deve ser encerrada.',
            'requester_id' => $requester->id,
            'assignee_id' => $technician->id,
            'priority' => TicketPriority::HIGH,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($technician)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->call('startTimeEntry')
            ->assertHasNoErrors();

        $timeEntry = TicketTimeEntry::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $technician->id)
            ->firstOrFail();

        Carbon::setTestNow('2026-04-14 10:15:00');

        Livewire::actingAs($technician)
            ->test(ShowPage::class, ['ticket' => $ticket->fresh()])
            ->call('updateFixedField', 'ticket_group_id', (string) $closedGroup->id)
            ->assertHasNoErrors();

        $timeEntry->refresh();

        $this->assertSame(4500, $timeEntry->duration_seconds);
        $this->assertNotNull($timeEntry->ended_at);
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => Ticket::class,
            'subject_id' => $ticket->id,
            'event' => 'ticket.time_entry.auto_closed',
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
            TicketRatingRequestNotification::class,
            fn (TicketRatingRequestNotification $notification, array $channels) => in_array('database', $channels, true)
                && in_array('mail', $channels, true)
                && data_get($notification->toArray($requester), 'title') === 'Chamado encerrado'
        );
    }

    public function test_relevant_ticket_update_notifies_other_recipients_but_not_the_actor(): void
    {
        Notification::fake();

        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $actor = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $newAssignee = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $ticket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Troca de responsavel',
            'description' => 'Chamado com reatribuicao.',
            'requester_id' => $requester->id,
            'assignee_id' => $actor->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($actor)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->call('updateFixedField', 'assignee_id', (string) $newAssignee->id)
            ->assertHasNoErrors();

        Notification::assertSentTo($requester, TicketUpdateNotification::class);
        Notification::assertSentTo($newAssignee, TicketUpdateNotification::class);
        Notification::assertNotSentTo($actor, TicketUpdateNotification::class);
    }

    public function test_new_message_notifies_other_recipients_but_not_the_author(): void
    {
        Notification::fake();

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
            'title' => 'Mensagem no chamado',
            'description' => 'Conversa em andamento.',
            'requester_id' => $requester->id,
            'assignee_id' => $technician->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($technician)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->set('message', 'Atualizei o atendimento agora.')
            ->call('sendMessage')
            ->assertHasNoErrors();

        Notification::assertSentTo($requester, TicketMessageNotification::class);
        Notification::assertNotSentTo($technician, TicketMessageNotification::class);
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
