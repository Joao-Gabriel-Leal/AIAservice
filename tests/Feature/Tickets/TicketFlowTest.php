<?php

namespace Tests\Feature\Tickets;

use App\Enums\KnowledgeBaseArticleStatus;
use App\Enums\KnowledgeBaseVisibility;
use App\Enums\TicketFieldType;
use App\Enums\TicketPriority;
use App\Enums\TicketTimeEntryApprovalStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Livewire\CreatePage;
use App\Modules\Tickets\Livewire\IndexPage;
use App\Modules\Tickets\Livewire\ShowPage;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketAttachment;
use App\Modules\Tickets\Models\TicketField;
use App\Modules\Tickets\Models\TicketFieldOption;
use App\Modules\Tickets\Models\TicketFieldValue;
use App\Modules\Tickets\Models\TicketForm;
use App\Modules\Tickets\Models\TicketMessage;
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
            'name' => 'Requester Avatar Board',
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

        $ticket = Ticket::query()->where('title', 'Notebook sem rede')->firstOrFail();

        $this->assertMatchesRegularExpression('/^SUP-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{5}$/', $ticket->reference_code);
        $this->assertSame(str_replace('-', '', $ticket->reference_code), $ticket->reference_lookup);

        Notification::assertSentTo(
            $requester,
            TicketCreatedNotification::class,
            fn (TicketCreatedNotification $notification, array $channels) => in_array('database', $channels, true)
                && in_array('mail', $channels, true)
                && data_get($notification->toArray($requester), 'title') === 'Novo chamado criado'
                && data_get($notification->toArray($requester), 'ticket_reference_code') === $ticket->reference_code
        );
    }

    public function test_ticket_public_reference_remains_stable_after_sector_slug_changes(): void
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
            'title' => 'Referencia estavel',
            'description' => 'Teste de estabilidade do codigo publico.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        $originalReferenceCode = $ticket->reference_code;
        $originalReferenceLookup = $ticket->reference_lookup;

        $sector->update([
            'slug' => 'tecnologia-infraestrutura',
        ]);

        $ticket->refresh();

        $this->assertSame($originalReferenceCode, $ticket->reference_code);
        $this->assertSame($originalReferenceLookup, $ticket->reference_lookup);
    }

    public function test_create_page_suggests_articles_similar_tickets_and_previous_solutions(): void
    {
        ['sector' => $sector, 'board' => $board, 'catalog' => $catalog, 'group' => $group, 'status' => $status, 'closedGroup' => $closedGroup, 'closedStatus' => $closedStatus] = $this->ticketContext();

        $otherSector = Sector::query()->create([
            'company_id' => $sector->company_id,
            'name' => 'Financeiro',
            'slug' => 'financeiro',
            'is_active' => true,
        ]);

        $otherBoard = app(SectorProvisioningService::class)->provision($otherSector);
        $otherGroup = $otherBoard->groups()->firstOrFail();
        $otherStatus = $otherBoard->statuses()->where('is_closed', false)->firstOrFail();

        $technician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        KnowledgeBaseArticle::query()->create([
            'sector_id' => $sector->id,
            'created_by' => $technician->id,
            'title' => 'Reset de senha VPN',
            'summary' => 'Procedimento para credenciais expiradas',
            'content' => 'Abra o portal de identidade e atualize a senha da VPN.',
            'visibility' => KnowledgeBaseVisibility::PRIVATE,
            'is_active' => true,
        ]);

        KnowledgeBaseArticle::query()->create([
            'sector_id' => $otherSector->id,
            'created_by' => User::factory()->create([
                'role' => UserRole::TECHNICIAN,
                'sector_id' => $otherSector->id,
            ])->id,
            'title' => 'Guia financeiro oculto',
            'summary' => 'Nao deve aparecer',
            'content' => 'Conteudo privado de outro setor.',
            'visibility' => KnowledgeBaseVisibility::PRIVATE,
            'is_active' => true,
        ]);

        $visibleTicket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'title' => 'Erro de senha na VPN',
            'description' => 'Colaborador nao consegue autenticar na VPN.',
            'requester_id' => User::factory()->create([
                'role' => UserRole::REQUESTER,
                'sector_id' => $sector->id,
            ])->id,
            'assignee_id' => $technician->id,
            'priority' => TicketPriority::HIGH,
            'last_activity_at' => now()->subMinutes(5),
        ]);

        $hiddenTicket = Ticket::query()->create([
            'sector_id' => $otherSector->id,
            'ticket_board_id' => $otherBoard->id,
            'ticket_group_id' => $otherGroup->id,
            'ticket_status_id' => $otherStatus->id,
            'title' => 'Erro de senha na VPN do financeiro',
            'description' => 'Nao deveria aparecer para outro setor.',
            'requester_id' => User::factory()->create([
                'role' => UserRole::REQUESTER,
                'sector_id' => $otherSector->id,
            ])->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now()->subMinutes(3),
        ]);

        $closedSolvedTicket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $closedGroup->id,
            'ticket_status_id' => $closedStatus->id,
            'title' => 'VPN bloqueada por senha expirada',
            'description' => 'A senha expirou e o acesso remoto parou.',
            'requester_id' => User::factory()->create([
                'role' => UserRole::REQUESTER,
                'sector_id' => $sector->id,
            ])->id,
            'assignee_id' => $technician->id,
            'priority' => TicketPriority::HIGH,
            'resolved_at' => now()->subHour(),
            'last_activity_at' => now()->subHour(),
        ]);

        TicketMessage::query()->create([
            'ticket_id' => $closedSolvedTicket->id,
            'user_id' => $technician->id,
            'message' => 'Solucao aplicada: reset da senha da VPN e sincronizacao das credenciais.',
            'is_system' => false,
        ]);

        $closedWithoutMessage = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $closedGroup->id,
            'ticket_status_id' => $closedStatus->id,
            'title' => 'Senha VPN sem retorno final',
            'description' => 'Chamado parecido encerrado sem anotacao final.',
            'requester_id' => User::factory()->create([
                'role' => UserRole::REQUESTER,
                'sector_id' => $sector->id,
            ])->id,
            'assignee_id' => $technician->id,
            'priority' => TicketPriority::MEDIUM,
            'resolved_at' => now()->subMinutes(90),
            'last_activity_at' => now()->subMinutes(90),
        ]);

        Livewire::actingAs($technician)
            ->test(CreatePage::class)
            ->set('selectedSectorId', $sector->id)
            ->set('selectedCatalogId', $catalog->id)
            ->set('title', 'Erro senha VPN')
            ->assertSee('Reset de senha VPN')
            ->assertDontSee('Guia financeiro oculto')
            ->assertSee($visibleTicket->title)
            ->assertDontSee($hiddenTicket->title)
            ->assertSee($closedSolvedTicket->title)
            ->assertSee('Solucao aplicada: reset da senha da VPN')
            ->assertSee($closedWithoutMessage->title);
    }

    public function test_create_page_clears_suggestions_when_input_becomes_too_short(): void
    {
        ['sector' => $sector, 'catalog' => $catalog] = $this->ticketContext();

        $technician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        KnowledgeBaseArticle::query()->create([
            'sector_id' => $sector->id,
            'created_by' => $technician->id,
            'title' => 'Reset de senha VPN',
            'summary' => 'Procedimento relacionado',
            'content' => 'Atualize a senha do acesso remoto.',
            'visibility' => KnowledgeBaseVisibility::PRIVATE,
            'is_active' => true,
        ]);

        Livewire::actingAs($technician)
            ->test(CreatePage::class)
            ->set('selectedSectorId', $sector->id)
            ->set('selectedCatalogId', $catalog->id)
            ->set('title', 'Senha VPN')
            ->assertSee('Reset de senha VPN')
            ->set('title', 'abc')
            ->assertDontSee('Reset de senha VPN')
            ->assertSet('suggestions.articles', [])
            ->assertSet('suggestions.similar_tickets', [])
            ->assertSet('suggestions.previous_solutions', []);
    }

    public function test_requester_can_submit_ticket_even_when_suggestions_are_visible(): void
    {
        Notification::fake();

        ['sector' => $sector, 'catalog' => $catalog] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        KnowledgeBaseArticle::query()->create([
            'sector_id' => $sector->id,
            'created_by' => User::factory()->superAdmin()->create()->id,
            'title' => 'Erro no acesso financeiro',
            'summary' => 'Artigo para reaproveitar antes de abrir chamado',
            'content' => 'Confira as credenciais e tente novamente.',
            'visibility' => KnowledgeBaseVisibility::PUBLIC,
            'is_active' => true,
        ]);

        Livewire::actingAs($requester)
            ->test(CreatePage::class)
            ->set('selectedSectorId', $sector->id)
            ->set('selectedCatalogId', $catalog->id)
            ->set('title', 'Erro no acesso financeiro')
            ->assertSee('Erro no acesso financeiro')
            ->set('description', 'Nao consigo entrar no sistema financeiro.')
            ->call('submit')
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'title' => 'Erro no acesso financeiro',
            'requester_id' => $requester->id,
            'sector_id' => $sector->id,
        ]);
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

    public function test_operator_uses_list_stages_and_kanban_modes_from_unified_board(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $operator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Validar tres modos',
            'description' => 'Chamado aparece nas etapas e no kanban.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($operator)
            ->test(IndexPage::class)
            ->assertSee('Lista')
            ->assertSee('Etapas')
            ->assertSee('Kanban')
            ->assertSee('Validar tres modos')
            ->assertSee('title="'.$requester->name.'"', false)
            ->assertDontSee('>'.$requester->name.'</td>', false)
            ->set('selectedSectorId', $sector->id)
            ->call('setViewMode', 'stages')
            ->assertSet('viewMode', 'stages')
            ->assertSee($group->name)
            ->assertSee('Validar tres modos')
            ->assertSee('Formulario padrao')
            ->assertSee('title="'.$requester->name.'"', false)
            ->assertDontSee('>'.$requester->name.'</p>', false)
            ->call('setViewMode', 'kanban')
            ->assertSet('viewMode', 'kanban')
            ->assertSee('ui-kanban-grid', false)
            ->assertSee('data-kanban-column', false)
            ->assertSee('Arraste o card para mover de coluna');
    }

    public function test_board_filters_render_collapsible_summary_without_counting_default_states(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $operator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Filtro recolhivel do quadro',
            'description' => 'Chamado para validar o cabecalho compacto dos filtros.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($operator)
            ->test(IndexPage::class, ['board' => $board])
            ->assertSee('Operacao do quadro')
            ->assertSee('Recolher filtros')
            ->assertSee('Sem filtros ativos')
            ->assertSee('Views rapidas')
            ->assertSee('Views salvas')
            ->set('titleFilter', 'recolhivel')
            ->assertSee('1 filtro ativo')
            ->call('resetTicketFilters')
            ->assertSee('Sem filtros ativos');
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

    public function test_create_page_skips_context_selection_when_form_was_chosen_from_central(): void
    {
        ['sector' => $sector, 'board' => $board, 'catalog' => $catalog] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $this->actingAs($requester)
            ->get(route('tickets.create', [
                'sector' => $sector->id,
                'board' => $board->id,
                'form' => $catalog->ticket_form_id,
            ]))
            ->assertOk()
            ->assertSee('Setor: '.$sector->name)
            ->assertSee('Formulario: '.$catalog->form->name)
            ->assertSee('Explique o chamado')
            ->assertSee('Pronto para enviar?')
            ->assertDontSee('Escolha o setor e o formulario')
            ->assertDontSee('Selecione um setor')
            ->assertDontSee('Selecione um formulario')
            ->assertDontSee('Preencha as informacoes abaixo para abrir o chamado com mais rapidez e menos retrabalho na triagem.')
            ->assertDontSee('Use um titulo curto e uma descricao clara para facilitar o atendimento.')
            ->assertDontSee('Revise os dados e envie. Depois voce acompanha tudo em');
    }

    public function test_create_page_uses_concise_copy_when_opened_without_context(): void
    {
        ['sector' => $sector] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $this->actingAs($requester)
            ->get(route('tickets.create'))
            ->assertOk()
            ->assertSee('Abertura de chamado')
            ->assertSee('Setor, quadro e formulario')
            ->assertSee('Explique o chamado')
            ->assertSee('Pronto para enviar?')
            ->assertDontSee('Preencha as informacoes abaixo para abrir o chamado com mais rapidez e menos retrabalho na triagem.')
            ->assertDontSee('O formulario define os campos da abertura. Se houver catalogo vinculado, ele complementa a triagem automaticamente.')
            ->assertDontSee('Use um titulo curto e uma descricao clara para facilitar o atendimento.')
            ->assertDontSee('Adicione prints, documentos ou qualquer arquivo que ajude no atendimento.')
            ->assertDontSee('Revise os dados e envie. Depois voce acompanha tudo em');
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
            ->test(IndexPage::class)
            ->set('selectedSectorId', $sector->id)
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

    public function test_show_page_renders_the_redesigned_chat_copy(): void
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
            'title' => 'Tela de conversa',
            'description' => 'Validando o novo bloco do chat.',
            'requester_id' => $requester->id,
            'assignee_id' => $technician->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($technician)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->assertSee('Chat do chamado')
            ->assertSee('Conversa')
            ->assertDontSee('Conversa com solicitante')
            ->assertDontSee('Canal visivel para quem abriu o chamado.')
            ->assertDontSee('Visivel para o solicitante')
            ->assertSee('Responder')
            ->assertSee('Escreva sua mensagem')
            ->assertSee('Nenhuma mensagem por aqui ainda.')
            ->assertDontSee('Chat interno')
            ->assertDontSee('Visibilidade')
            ->assertDontSee('Responder ao time');
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
            ->test(IndexPage::class)
            ->set('selectedSectorId', $sector->id)
            ->call('setViewMode', 'stages')
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

    public function test_board_drag_reorders_tickets_within_the_same_group_and_reflects_in_kanban_and_stages(): void
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

        $firstTicket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Primeiro da fila',
            'description' => 'Primeiro chamado',
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
            'title' => 'Segundo da fila',
            'description' => 'Segundo chamado',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        $thirdTicket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Terceiro da fila',
            'description' => 'Terceiro chamado',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($technician)
            ->test(IndexPage::class, ['board' => $board])
            ->call('setViewMode', 'kanban')
            ->assertSeeInOrder(['Primeiro da fila', 'Segundo da fila', 'Terceiro da fila'])
            ->call('moveTicketByDrag', $thirdTicket->id, $group->id, $firstTicket->id, 'top')
            ->assertSeeInOrder(['Terceiro da fila', 'Primeiro da fila', 'Segundo da fila'])
            ->call('setViewMode', 'stages')
            ->assertSeeInOrder(['Terceiro da fila', 'Primeiro da fila', 'Segundo da fila']);

        $this->assertSame(
            ['Terceiro da fila', 'Primeiro da fila', 'Segundo da fila'],
            Ticket::query()
                ->where('ticket_board_id', $board->id)
                ->where('ticket_group_id', $group->id)
                ->orderedForBoardDisplay()
                ->pluck('title')
                ->all()
        );

        $this->assertSame(1, $thirdTicket->fresh()->board_sort_order);
        $this->assertSame(2, $firstTicket->fresh()->board_sort_order);
        $this->assertSame(3, $secondTicket->fresh()->board_sort_order);
    }

    public function test_board_drag_to_top_keeps_ticket_metadata_untouched_when_reordering_in_the_same_group(): void
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

        $firstTicket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Fila original',
            'description' => 'Primeiro chamado',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => Carbon::parse('2026-05-08 10:00:00'),
        ]);

        $reorderedTicket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Vai para o topo',
            'description' => 'Segundo chamado',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => Carbon::parse('2026-05-08 11:30:00'),
        ]);

        $originalStatusId = $reorderedTicket->ticket_status_id;
        $originalActivityAt = $reorderedTicket->last_activity_at?->toIso8601String();

        Livewire::actingAs($technician)
            ->test(IndexPage::class, ['board' => $board])
            ->call('setViewMode', 'kanban')
            ->call('moveTicketByDrag', $reorderedTicket->id, $group->id, null, 'top')
            ->assertSeeInOrder(['Vai para o topo', 'Fila original']);

        $reorderedTicket->refresh();
        $firstTicket->refresh();

        $this->assertSame($group->id, $reorderedTicket->ticket_group_id);
        $this->assertSame($originalStatusId, $reorderedTicket->ticket_status_id);
        $this->assertNull($reorderedTicket->resolved_at);
        $this->assertSame($originalActivityAt, $reorderedTicket->last_activity_at?->toIso8601String());
        $this->assertSame(1, $reorderedTicket->board_sort_order);
        $this->assertSame(2, $firstTicket->board_sort_order);
    }

    public function test_board_drag_can_insert_ticket_before_another_ticket_in_a_different_group(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $targetGroup = $board->groups()
            ->where('is_closed', false)
            ->whereKeyNot($group->id)
            ->orderBy('sort_order')
            ->firstOrFail();
        $targetStatus = $board->statuses()->where('sort_order', $targetGroup->sort_order)->firstOrFail();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $technician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $sourceTicket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Mover entre etapas',
            'description' => 'Chamado de origem',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        $targetFirst = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $targetGroup->id,
            'ticket_status_id' => $targetStatus->id,
            'room_id' => $room->id,
            'title' => 'Ja na etapa 1',
            'description' => 'Primeiro da etapa de destino',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        $targetSecond = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $targetGroup->id,
            'ticket_status_id' => $targetStatus->id,
            'room_id' => $room->id,
            'title' => 'Ja na etapa 2',
            'description' => 'Segundo da etapa de destino',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($technician)
            ->test(IndexPage::class, ['board' => $board])
            ->call('setViewMode', 'kanban')
            ->call('moveTicketByDrag', $sourceTicket->id, $targetGroup->id, $targetSecond->id, 'top')
            ->call('setViewMode', 'stages')
            ->assertSeeInOrder(['Ja na etapa 1', 'Mover entre etapas', 'Ja na etapa 2']);

        $sourceTicket->refresh();

        $this->assertSame($targetGroup->id, $sourceTicket->ticket_group_id);
        $this->assertSame($targetStatus->id, $sourceTicket->ticket_status_id);
        $this->assertNull($sourceTicket->resolved_at);
        $this->assertSame(
            ['Ja na etapa 1', 'Mover entre etapas', 'Ja na etapa 2'],
            Ticket::query()
                ->where('ticket_board_id', $board->id)
                ->where('ticket_group_id', $targetGroup->id)
                ->orderedForBoardDisplay()
                ->pluck('title')
                ->all()
        );
    }

    public function test_board_select_move_places_ticket_at_the_end_of_the_destination_group(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $targetGroup = $board->groups()
            ->where('is_closed', false)
            ->whereKeyNot($group->id)
            ->orderBy('sort_order')
            ->firstOrFail();
        $targetStatus = $board->statuses()->where('sort_order', $targetGroup->sort_order)->firstOrFail();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $technician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $sourceTicket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Vai para o fim',
            'description' => 'Chamado movido pelo select',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $targetGroup->id,
            'ticket_status_id' => $targetStatus->id,
            'room_id' => $room->id,
            'title' => 'Primeiro destino',
            'description' => 'Primeiro na etapa destino',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $targetGroup->id,
            'ticket_status_id' => $targetStatus->id,
            'room_id' => $room->id,
            'title' => 'Segundo destino',
            'description' => 'Segundo na etapa destino',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($technician)
            ->test(IndexPage::class, ['board' => $board])
            ->call('setViewMode', 'stages')
            ->call('moveTicketToGroup', $sourceTicket->id, $targetGroup->id)
            ->assertSeeInOrder(['Primeiro destino', 'Segundo destino', 'Vai para o fim']);

        $sourceTicket->refresh();

        $this->assertSame($targetGroup->id, $sourceTicket->ticket_group_id);
        $this->assertSame($targetStatus->id, $sourceTicket->ticket_status_id);
        $this->assertSame(
            ['Primeiro destino', 'Segundo destino', 'Vai para o fim'],
            Ticket::query()
                ->where('ticket_board_id', $board->id)
                ->where('ticket_group_id', $targetGroup->id)
                ->orderedForBoardDisplay()
                ->pluck('title')
                ->all()
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
            ->test(IndexPage::class)
            ->set('selectedSectorId', $sector->id)
            ->call('setViewMode', 'kanban')
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
            ->test(IndexPage::class)
            ->set('selectedSectorId', $sector->id)
            ->call('setViewMode', 'kanban')
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
            ->test(IndexPage::class)
            ->set('selectedSectorId', $sector->id)
            ->call('setViewMode', 'kanban')
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
            ->test(IndexPage::class)
            ->set('selectedSectorId', $sector->id)
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
            ->test(IndexPage::class)
            ->set('selectedSectorId', $sector->id)
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

    public function test_conditional_field_stays_hidden_until_parent_value_matches(): void
    {
        ['sector' => $sector, 'board' => $board] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        ['form' => $form, 'triggerField' => $triggerField, 'conditionalField' => $conditionalField] = $this->conditionalFormContext($board);

        Livewire::actingAs($requester)
            ->test(CreatePage::class)
            ->set('selectedSectorId', $sector->id)
            ->set('selectedFormId', $form->id)
            ->assertSee($triggerField->name)
            ->assertDontSee($conditionalField->name)
            ->set("dynamicValues.{$triggerField->id}", 'hardware')
            ->assertSee($conditionalField->name);
    }

    public function test_conditional_required_field_is_only_validated_when_visible(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        ['form' => $form, 'triggerField' => $triggerField, 'conditionalField' => $conditionalField] = $this->conditionalFormContext($board);

        Livewire::actingAs($requester)
            ->test(CreatePage::class)
            ->set('selectedSectorId', $sector->id)
            ->set('selectedFormId', $form->id)
            ->set('title', 'Chamado sem patrimonio')
            ->set('description', 'Campo condicional segue oculto.')
            ->set('priority', TicketPriority::MEDIUM->value)
            ->set("dynamicValues.{$triggerField->id}", 'software')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'title' => 'Chamado sem patrimonio',
        ]);

        Livewire::actingAs($requester)
            ->test(CreatePage::class)
            ->set('selectedSectorId', $sector->id)
            ->set('selectedFormId', $form->id)
            ->set('title', 'Chamado com patrimonio faltando')
            ->set('description', 'Campo agora fica visivel.')
            ->set('priority', TicketPriority::MEDIUM->value)
            ->set("dynamicValues.{$triggerField->id}", 'hardware')
            ->call('submit')
            ->assertHasErrors(["dynamicValues.{$conditionalField->id}"]);
    }

    public function test_hidden_conditional_field_value_is_not_persisted(): void
    {
        ['sector' => $sector, 'board' => $board] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        ['form' => $form, 'triggerField' => $triggerField, 'conditionalField' => $conditionalField] = $this->conditionalFormContext($board);

        Livewire::actingAs($requester)
            ->test(CreatePage::class)
            ->set('selectedSectorId', $sector->id)
            ->set('selectedFormId', $form->id)
            ->set('title', 'Chamado com valor oculto')
            ->set('description', 'O valor escondido nao deve ser salvo.')
            ->set('priority', TicketPriority::MEDIUM->value)
            ->set("dynamicValues.{$triggerField->id}", 'software')
            ->set("dynamicValues.{$conditionalField->id}", 'P-001')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect();

        $ticket = Ticket::query()->where('title', 'Chamado com valor oculto')->firstOrFail();
        $triggerValue = TicketFieldValue::query()
            ->where('ticket_id', $ticket->id)
            ->where('ticket_field_id', $triggerField->id)
            ->firstOrFail();

        $this->assertSame('software', $triggerValue->primitive_value);
        $this->assertDatabaseMissing('ticket_field_values', [
            'ticket_id' => $ticket->id,
            'ticket_field_id' => $conditionalField->id,
        ]);
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

        $manager = User::factory()->create([
            'role' => UserRole::SECTOR_ADMIN,
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

        $firstEntry = TicketTimeEntry::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $firstTechnician->id)
            ->firstOrFail();

        $secondEntry = TicketTimeEntry::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $secondTechnician->id)
            ->firstOrFail();

        $this->assertSame(TicketTimeEntryApprovalStatus::PENDING, $firstEntry->approval_status);
        $this->assertSame(TicketTimeEntryApprovalStatus::PENDING, $secondEntry->approval_status);

        $ticket = $ticket->fresh(['timeEntries.user']);
        $timeSummary = $ticket->timeEntriesTotalByUser()->keyBy('user_id');

        $this->assertSame(0, $ticket->timeEntriesTotalSeconds());
        $this->assertCount(0, $timeSummary);

        Livewire::actingAs($firstTechnician)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->call('approveTimeEntry', $secondEntry->id)
            ->assertForbidden();

        Livewire::actingAs($manager)
            ->test(ShowPage::class, ['ticket' => $ticket->fresh()])
            ->call('approveTimeEntry', $firstEntry->id)
            ->assertHasNoErrors()
            ->call('approveTimeEntry', $secondEntry->id)
            ->assertHasNoErrors();

        $ticket = $ticket->fresh(['timeEntries.user']);
        $timeSummary = $ticket->timeEntriesTotalByUser()->keyBy('user_id');

        $this->assertSame(12600, $ticket->timeEntriesTotalSeconds());
        $this->assertSame(5400, $timeSummary[$firstTechnician->id]['total_seconds']);
        $this->assertSame(7200, $timeSummary[$secondTechnician->id]['total_seconds']);
        $this->assertDatabaseHas('ticket_time_entries', [
            'id' => $firstEntry->id,
            'approval_status' => TicketTimeEntryApprovalStatus::APPROVED->value,
            'reviewed_by_id' => $manager->id,
        ]);
        $this->assertDatabaseHas('ticket_time_entries', [
            'id' => $secondEntry->id,
            'approval_status' => TicketTimeEntryApprovalStatus::APPROVED->value,
            'reviewed_by_id' => $manager->id,
        ]);

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

    public function test_rating_invitation_is_not_sent_when_ticket_already_has_rating(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'closedGroup' => $closedGroup, 'closedStatus' => $closedStatus] = $this->ticketContext();
        $openGroup = $board->groups()->where('is_closed', false)->orderBy('sort_order')->firstOrFail();
        $openStatus = $board->statuses()->where('is_closed', false)->orderBy('sort_order')->firstOrFail();

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
            'ticket_group_id' => $closedGroup->id,
            'ticket_status_id' => $closedStatus->id,
            'room_id' => $room->id,
            'title' => 'Chamado ja avaliado',
            'description' => 'Nao deve pedir nova avaliacao.',
            'requester_id' => $requester->id,
            'assignee_id' => $technician->id,
            'priority' => TicketPriority::MEDIUM,
            'resolved_at' => now()->subMinutes(30),
            'last_activity_at' => now()->subMinutes(30),
        ]);
        $ticket->rating()->create([
            'user_id' => $requester->id,
            'rating' => 5,
            'comment' => 'Ja avaliado.',
        ]);

        $ticket->forceFill([
            'ticket_group_id' => $openGroup->id,
            'ticket_status_id' => $openStatus->id,
            'resolved_at' => null,
            'last_activity_at' => now(),
        ])->save();

        Notification::fake();

        Livewire::actingAs($technician)
            ->test(ShowPage::class, ['ticket' => $ticket->fresh(['group', 'status'])])
            ->call('updateFixedField', 'ticket_group_id', (string) $closedGroup->id)
            ->assertHasNoErrors();

        Notification::assertNotSentTo($requester, TicketRatingRequestNotification::class);
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

    public function test_new_message_stays_saved_when_notification_delivery_fails(): void
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
            'title' => 'Mensagem com notificacao indisponivel',
            'description' => 'O chat precisa continuar mesmo se um canal externo falhar.',
            'requester_id' => $requester->id,
            'assignee_id' => $technician->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);

        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('mail transport unavailable'));

        Livewire::actingAs($technician)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->set('message', 'A mensagem nao pode virar erro 500.')
            ->call('sendMessage')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'user_id' => $technician->id,
            'message' => 'A mensagem nao pode virar erro 500.',
        ]);
    }

    public function test_closed_ticket_suggests_creating_article_when_none_exists(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'closedGroup' => $closedGroup, 'closedStatus' => $closedStatus] = $this->ticketContext();

        $technician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $ticket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $closedGroup->id,
            'ticket_status_id' => $closedStatus->id,
            'room_id' => $room->id,
            'title' => 'Encerrar com artigo',
            'description' => 'Chamado pronto para virar artigo.',
            'requester_id' => $technician->id,
            'assignee_id' => $technician->id,
            'priority' => TicketPriority::MEDIUM,
            'resolved_at' => now(),
            'last_activity_at' => now(),
        ]);

        Livewire::actingAs($technician)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->assertSee('Transformar em artigo')
            ->assertDontSee('Este chamado ja originou um artigo');
    }

    public function test_closed_ticket_hides_creation_cta_when_article_already_exists(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'closedGroup' => $closedGroup, 'closedStatus' => $closedStatus] = $this->ticketContext();

        $technician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $ticket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $closedGroup->id,
            'ticket_status_id' => $closedStatus->id,
            'room_id' => $room->id,
            'title' => 'Chamado com artigo',
            'description' => 'Chamado encerrado com artigo existente.',
            'requester_id' => $technician->id,
            'assignee_id' => $technician->id,
            'priority' => TicketPriority::MEDIUM,
            'resolved_at' => now(),
            'last_activity_at' => now(),
        ]);

        KnowledgeBaseArticle::query()->create([
            'sector_id' => $sector->id,
            'created_by' => $technician->id,
            'generated_from_ticket_id' => $ticket->id,
            'title' => 'Artigo ja gerado',
            'summary' => 'Resumo do artigo',
            'content' => 'Conteudo do artigo',
            'visibility' => KnowledgeBaseVisibility::PRIVATE,
            'editorial_status' => KnowledgeBaseArticleStatus::DRAFT,
            'is_active' => false,
        ]);

        Livewire::actingAs($technician)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->assertDontSee('Transformar em artigo')
            ->assertSee('Este chamado ja originou um artigo');
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

    private function conditionalFormContext($board): array
    {
        $triggerField = TicketField::query()->create([
            'ticket_board_id' => $board->id,
            'name' => 'Categoria',
            'slug' => 'categoria',
            'type' => TicketFieldType::SELECT,
            'placeholder' => null,
            'help_text' => null,
            'settings' => [],
            'sort_order' => 1,
            'is_required' => true,
            'show_on_board' => false,
            'is_active' => true,
        ]);

        TicketFieldOption::query()->create([
            'ticket_field_id' => $triggerField->id,
            'label' => 'Hardware',
            'value' => 'hardware',
            'color' => null,
            'sort_order' => 1,
            'is_default' => false,
        ]);
        TicketFieldOption::query()->create([
            'ticket_field_id' => $triggerField->id,
            'label' => 'Software',
            'value' => 'software',
            'color' => null,
            'sort_order' => 2,
            'is_default' => false,
        ]);

        $conditionalField = TicketField::query()->create([
            'ticket_board_id' => $board->id,
            'name' => 'Patrimonio',
            'slug' => 'patrimonio',
            'type' => TicketFieldType::TEXT,
            'placeholder' => 'Numero do patrimonio',
            'help_text' => null,
            'settings' => [],
            'sort_order' => 2,
            'is_required' => false,
            'show_on_board' => false,
            'is_active' => true,
        ]);

        $form = TicketForm::query()->create([
            'ticket_board_id' => $board->id,
            'name' => 'Abertura condicional',
            'description' => 'Formulario com visibilidade condicional.',
            'is_default' => false,
            'is_active' => true,
        ]);

        $form->fields()->sync([
            $triggerField->id => [
                'is_required' => true,
                'sort_order' => 1,
                'visibility_parent_field_id' => null,
                'visibility_operator' => null,
                'visibility_expected_value' => null,
            ],
            $conditionalField->id => [
                'is_required' => true,
                'sort_order' => 2,
                'visibility_parent_field_id' => $triggerField->id,
                'visibility_operator' => 'equals',
                'visibility_expected_value' => 'hardware',
            ],
        ]);

        return [
            'form' => $form,
            'triggerField' => $triggerField,
            'conditionalField' => $conditionalField,
        ];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
