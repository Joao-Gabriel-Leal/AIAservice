<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketAutomationTrigger;
use App\Enums\TicketPriority;
use App\Enums\UserRole;
use App\Jobs\RunTicketInactiveAutomationRuleJob;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Livewire\SettingsPage;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketAutomationExecution;
use App\Modules\Tickets\Models\TicketAutomationRule;
use App\Modules\Tickets\Services\SectorProvisioningService;
use App\Modules\Tickets\Services\TicketAutomationEngine;
use App\Modules\Tickets\Services\TicketWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class TicketAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sector_admin_can_create_automation_rule_from_settings_page(): void
    {
        config(['tickets.automations_ui_enabled' => true]);

        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $admin = User::factory()->create([
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class, ['sector' => $sector])
            ->set('automationForm.name', 'Encaminhar urgente sem responsavel')
            ->set('automationForm.trigger', 'ticket_created')
            ->set('automationForm.sort_order', 1)
            ->set('automationConditions', [[
                'field' => 'priority',
                'operator' => 'in',
                'value' => [TicketPriority::URGENT->value],
                'sort_order' => 1,
            ]])
            ->set('automationActions', [[
                'action' => 'change_status',
                'payload' => ['status_id' => $status->id],
                'sort_order' => 1,
            ], [
                'action' => 'change_group',
                'payload' => ['group_id' => $group->id],
                'sort_order' => 2,
            ]])
            ->call('saveAutomation')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ticket_automation_rules', [
            'ticket_board_id' => $board->id,
            'name' => 'Encaminhar urgente sem responsavel',
            'trigger' => 'ticket_created',
        ]);
    }

    public function test_ticket_created_automation_executes_and_is_audited(): void
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

        $rule = $this->createAutomationRule($board->id, [
            'name' => 'Autoatribuir urgentes',
            'trigger' => 'ticket_created',
        ], [
            [
                'field' => 'priority',
                'operator' => 'in',
                'value' => [TicketPriority::URGENT->value],
                'sort_order' => 1,
            ],
            [
                'field' => 'has_assignee',
                'operator' => 'is_false',
                'value' => null,
                'sort_order' => 2,
            ],
        ], [
            [
                'action' => 'assign_fixed_assignee',
                'payload' => ['assignee_id' => $technician->id],
                'sort_order' => 1,
            ],
            [
                'action' => 'change_status',
                'payload' => ['status_id' => $status->id],
                'sort_order' => 2,
            ],
            [
                'action' => 'add_system_message',
                'payload' => ['message' => 'Chamado atribuido automaticamente.'],
                'sort_order' => 3,
            ],
        ]);

        $ticket = app(TicketWorkflowService::class)->createTicket($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => null,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Servidor caiu',
            'description' => 'Sem acesso ao sistema',
            'priority' => TicketPriority::URGENT,
        ]);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'assignee_id' => $technician->id,
        ]);

        $this->assertDatabaseHas('ticket_automation_executions', [
            'ticket_automation_rule_id' => $rule->id,
            'ticket_id' => $ticket->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'message' => 'Chamado atribuido automaticamente.',
            'is_system' => true,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'ticket.automation.executed',
            'subject_id' => $ticket->id,
        ]);
    }

    public function test_message_automation_cannot_bypass_closed_ticket_chat_lock(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group] = $this->ticketContext();
        $openStatus = $board->statuses()->where('is_closed', false)->firstOrFail();
        $closedStatus = $board->statuses()->where('is_closed', true)->firstOrFail();

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
            'ticket_status_id' => $closedStatus->id,
            'room_id' => $room->id,
            'title' => 'Acesso VPN',
            'description' => 'Nao conecta',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::HIGH,
            'resolved_at' => now(),
            'last_activity_at' => now()->subHour(),
        ]);

        $this->createAutomationRule($board->id, [
            'name' => 'Reabrir quando houver nova mensagem',
            'trigger' => 'ticket_message_created',
        ], [
            [
                'field' => 'is_closed',
                'operator' => 'is_true',
                'value' => null,
                'sort_order' => 1,
            ],
        ], [
            [
                'action' => 'reopen_ticket',
                'payload' => [
                    'target_status_id' => $openStatus->id,
                    'message' => 'Chamado reaberto automaticamente por nova mensagem.',
                ],
                'sort_order' => 1,
            ],
        ]);

        try {
            app(TicketWorkflowService::class)->addMessage($technician, $ticket, 'Voltei a ter problema.');
            $this->fail('Closed ticket accepted a human chat message.');
        } catch (AuthorizationException) {
            //
        }

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'ticket_status_id' => $closedStatus->id,
        ]);
        $this->assertNotNull($ticket->fresh()->resolved_at);

        $this->assertDatabaseMissing('ticket_messages', [
            'ticket_id' => $ticket->id,
            'message' => 'Chamado reaberto automaticamente por nova mensagem.',
        ]);
    }

    public function test_inactive_automation_command_dispatches_job_and_job_executes_once(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);

        $rule = $this->createAutomationRule($board->id, [
            'name' => 'Lembrete de inatividade',
            'trigger' => 'ticket_inactive',
            'run_mode' => 'scheduled',
            'trigger_settings' => ['inactive_for_minutes' => 30],
        ], [
            [
                'field' => 'is_closed',
                'operator' => 'is_false',
                'value' => null,
                'sort_order' => 1,
            ],
        ], [
            [
                'action' => 'add_system_message',
                'payload' => ['message' => 'Lembrete automatico de inatividade.'],
                'sort_order' => 1,
            ],
        ]);

        $ticket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Computador lento',
            'description' => 'Muito demorado',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now()->subMinutes(45),
        ]);

        Queue::fake();
        $this->artisan('tickets:run-inactive-automations')->assertOk();
        Queue::assertPushed(RunTicketInactiveAutomationRuleJob::class, fn (RunTicketInactiveAutomationRuleJob $job) => $job->ruleId === $rule->id);

        $job = new RunTicketInactiveAutomationRuleJob($rule->id);
        $job->handle(app(TicketAutomationEngine::class));
        $job->handle(app(TicketAutomationEngine::class));

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'message' => 'Lembrete automatico de inatividade.',
            'is_system' => true,
        ]);

        $this->assertSame(1, TicketAutomationExecution::query()
            ->where('ticket_automation_rule_id', $rule->id)
            ->where('ticket_id', $ticket->id)
            ->count());
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
            'status' => $board->statuses()->where('is_closed', false)->firstOrFail(),
        ];
    }

    private function createAutomationRule(int $boardId, array $attributes, array $conditions, array $actions): TicketAutomationRule
    {
        $rule = TicketAutomationRule::query()->create([
            'ticket_board_id' => $boardId,
            'name' => $attributes['name'],
            'description' => $attributes['description'] ?? null,
            'trigger' => $attributes['trigger'],
            'run_mode' => $attributes['run_mode'] ?? ($attributes['trigger'] === TicketAutomationTrigger::TICKET_INACTIVE->value ? 'scheduled' : 'sync'),
            'trigger_settings' => $attributes['trigger_settings'] ?? null,
            'cooldown_minutes' => $attributes['cooldown_minutes'] ?? null,
            'sort_order' => $attributes['sort_order'] ?? 1,
            'is_active' => $attributes['is_active'] ?? true,
        ]);

        $rule->conditions()->createMany($conditions);
        $rule->actions()->createMany($actions);

        return $rule->fresh(['conditions', 'actions']);
    }
}
