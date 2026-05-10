<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketBoardWorkflowMode;
use App\Enums\TicketPriority;
use App\Enums\TicketSprintItemResult;
use App\Enums\TicketSprintStatus;
use App\Enums\TicketWorkItemType;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Livewire\CreatePage;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Models\TicketForm;
use App\Modules\Tickets\Services\SectorProvisioningService;
use App\Modules\Tickets\Services\TicketSprintService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DevelopmentWorkManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_dev_form_creates_ticket_on_dev_board_with_default_work_item_type(): void
    {
        ['sector' => $sector, 'devBoard' => $devBoard, 'supportBoard' => $supportBoard] = $this->developmentContext();
        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $devForm = TicketForm::query()->create([
            'ticket_board_id' => $devBoard->id,
            'name' => 'Bug de produto',
            'description' => 'Abertura de bug',
            'default_work_item_type' => TicketWorkItemType::BUG,
            'is_default' => false,
            'is_active' => true,
        ]);
        $supportForm = TicketForm::query()->create([
            'ticket_board_id' => $supportBoard->id,
            'name' => 'Suporte geral',
            'description' => 'Abertura suporte',
            'default_work_item_type' => TicketWorkItemType::REQUEST,
            'is_default' => false,
            'is_active' => true,
        ]);

        Livewire::actingAs($requester)
            ->test(CreatePage::class)
            ->set('selectedSectorId', $sector->id)
            ->set('selectedBoardId', $devBoard->id)
            ->set('selectedFormId', $devForm->id)
            ->set('title', 'Erro no deploy')
            ->set('description', 'Deploy quebra com stack trace.')
            ->set('priority', TicketPriority::HIGH->value)
            ->call('submit')
            ->assertHasNoErrors();

        $devTicket = Ticket::query()->where('title', 'Erro no deploy')->firstOrFail();
        $this->assertSame($devBoard->id, $devTicket->ticket_board_id);
        $this->assertSame(TicketWorkItemType::BUG, $devTicket->work_item_type);

        Livewire::actingAs($requester)
            ->test(CreatePage::class)
            ->set('selectedSectorId', $sector->id)
            ->set('selectedBoardId', $supportBoard->id)
            ->set('selectedFormId', $supportForm->id)
            ->set('title', 'Instalar impressora')
            ->set('description', 'Solicitacao de suporte.')
            ->set('priority', TicketPriority::MEDIUM->value)
            ->call('submit')
            ->assertHasNoErrors();

        $supportTicket = Ticket::query()->where('title', 'Instalar impressora')->firstOrFail();
        $this->assertSame($supportBoard->id, $supportTicket->ticket_board_id);
        $this->assertSame(TicketWorkItemType::REQUEST, $supportTicket->work_item_type);
    }

    public function test_operator_dev_views_filter_backlog_current_sprint_and_bugs(): void
    {
        ['sector' => $sector, 'devBoard' => $devBoard] = $this->developmentContext();
        $operator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);
        $devBoard->operators()->syncWithoutDetaching([$operator->id]);
        $manager = User::factory()->create([
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $sector->id,
        ]);

        $backlogBug = $this->ticketFor($devBoard, 'Bug no login', TicketWorkItemType::BUG);
        $sprintTask = $this->ticketFor($devBoard, 'Ajustar pipeline', TicketWorkItemType::TASK);
        $sprint = app(TicketSprintService::class)->createSprint($manager, $devBoard, ['name' => 'Sprint atual']);
        app(TicketSprintService::class)->startSprint($manager, $sprint);
        app(TicketSprintService::class)->assignTicket($manager, $sprintTask, $sprint);

        $this->actingAs($operator)
            ->get(route('tickets.board.show', ['board' => $devBoard, 'view' => 'list', 'dev_view' => 'backlog']))
            ->assertOk()
            ->assertSeeText($backlogBug->title)
            ->assertDontSeeText($sprintTask->title);

        $this->actingAs($operator)
            ->get(route('tickets.board.show', ['board' => $devBoard, 'view' => 'list', 'dev_view' => 'current_sprint']))
            ->assertOk()
            ->assertSeeText($sprintTask->title)
            ->assertDontSeeText($backlogBug->title);

        $this->actingAs($operator)
            ->get(route('tickets.board.show', ['board' => $devBoard, 'view' => 'list', 'dev_view' => 'bugs']))
            ->assertOk()
            ->assertSeeText($backlogBug->title)
            ->assertDontSeeText($sprintTask->title);
    }

    public function test_sprint_lifecycle_records_history_and_carries_open_items(): void
    {
        ['sector' => $sector, 'devBoard' => $devBoard] = $this->developmentContext();
        $manager = User::factory()->create([
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $sector->id,
        ]);
        $service = app(TicketSprintService::class);
        $currentSprint = $service->createSprint($manager, $devBoard, ['name' => 'Sprint 1']);
        $nextSprint = $service->createSprint($manager, $devBoard, ['name' => 'Sprint 2']);
        $service->startSprint($manager, $currentSprint);

        $openTicket = $this->ticketFor($devBoard, 'Implementar webhook', TicketWorkItemType::STORY);
        $closedTicket = $this->ticketFor($devBoard, 'Corrigir bug fechado', TicketWorkItemType::BUG);
        $service->assignTicket($manager, $openTicket, $currentSprint);
        $service->assignTicket($manager, $closedTicket, $currentSprint);

        $closedGroup = $devBoard->groups()->where('is_closed', true)->firstOrFail();
        $closedTicket->forceFill([
            'ticket_group_id' => $closedGroup->id,
            'resolved_at' => now(),
        ])->save();

        $service->closeSprint($manager, $currentSprint, 'sprint', $nextSprint);

        $this->assertDatabaseHas('ticket_sprints', [
            'id' => $currentSprint->id,
            'status' => TicketSprintStatus::CLOSED->value,
        ]);
        $this->assertDatabaseHas('ticket_sprint_items', [
            'ticket_sprint_id' => $currentSprint->id,
            'ticket_id' => $closedTicket->id,
            'result' => TicketSprintItemResult::COMPLETED->value,
        ]);
        $this->assertDatabaseHas('ticket_sprint_items', [
            'ticket_sprint_id' => $currentSprint->id,
            'ticket_id' => $openTicket->id,
            'result' => TicketSprintItemResult::CARRIED_OVER->value,
            'moved_to_sprint_id' => $nextSprint->id,
        ]);
        $this->assertSame($nextSprint->id, $openTicket->fresh()->ticket_sprint_id);
        $this->assertSame($currentSprint->id, $closedTicket->fresh()->ticket_sprint_id);
    }

    public function test_operator_can_view_dev_board_but_cannot_manage_sprints(): void
    {
        ['sector' => $sector, 'devBoard' => $devBoard] = $this->developmentContext();
        $manager = User::factory()->create([
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $sector->id,
        ]);
        $operator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);
        $devBoard->operators()->syncWithoutDetaching([$operator->id]);
        $sprint = app(TicketSprintService::class)->createSprint($manager, $devBoard, ['name' => 'Sprint restrita']);

        $this->actingAs($operator)
            ->get(route('tickets.board.show', ['board' => $devBoard, 'view' => 'list']))
            ->assertOk()
            ->assertSeeText('Sprint atual')
            ->assertSeeText('Backlog')
            ->assertDontSee('movePlanningTicketToSprint', false);

        $this->expectException(AuthorizationException::class);
        app(TicketSprintService::class)->startSprint($operator, $sprint);
    }

    private function developmentContext(): array
    {
        $company = Company::query()->create([
            'name' => 'Empresa Dev Work',
            'is_active' => true,
        ]);
        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'TI Dev Work',
            'slug' => 'ti-dev-work',
            'is_active' => true,
        ]);
        $supportBoard = app(SectorProvisioningService::class)->provision($sector);
        $devBoard = app(SectorProvisioningService::class)->createAdditionalBoard(
            $sector,
            'Desenvolvimento',
            'Quadro para demandas de produto.',
            [],
            TicketBoardWorkflowMode::DEVELOPMENT,
        );

        return [
            'company' => $company,
            'sector' => $sector,
            'supportBoard' => $supportBoard,
            'devBoard' => $devBoard->fresh(['groups', 'statuses', 'forms']),
        ];
    }

    private function ticketFor(TicketBoard $board, string $title, TicketWorkItemType $type): Ticket
    {
        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $board->sector_id,
        ]);

        return Ticket::query()->create([
            'sector_id' => $board->sector_id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $board->groups()->where('is_closed', false)->firstOrFail()->id,
            'ticket_status_id' => $board->statuses()->where('is_closed', false)->firstOrFail()->id,
            'title' => $title,
            'description' => 'Item de teste Dev Work.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'work_item_type' => $type,
            'last_activity_at' => now(),
        ]);
    }
}
