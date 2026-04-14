<?php

namespace App\Modules\Tickets\Services;

use App\Modules\Shared\Services\ActivityLogService;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketAutomationExecution;
use App\Modules\Tickets\Models\TicketAutomationRule;

class TicketAutomationAuditService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    public function logExecuted(
        TicketAutomationRule $rule,
        Ticket $ticket,
        TicketAutomationExecution $execution,
        array $actions,
    ): void {
        $this->activityLogService->log(null, $ticket, 'ticket.automation.executed', "Automacao {$rule->name} executada.", [
            'sector_id' => $ticket->sector_id,
            'automation_rule_id' => $rule->id,
            'automation_rule_name' => $rule->name,
            'execution_id' => $execution->id,
            'trigger' => $execution->trigger,
            'matched_conditions' => true,
            'actions' => $actions,
            'idempotency_key' => $execution->idempotency_key,
        ]);
    }

    public function logSkipped(
        TicketAutomationRule $rule,
        Ticket $ticket,
        TicketAutomationExecution $execution,
        string $reason,
    ): void {
        $this->activityLogService->log(null, $ticket, 'ticket.automation.skipped', "Automacao {$rule->name} ignorada.", [
            'sector_id' => $ticket->sector_id,
            'automation_rule_id' => $rule->id,
            'automation_rule_name' => $rule->name,
            'execution_id' => $execution->id,
            'trigger' => $execution->trigger,
            'matched_conditions' => $execution->matched_conditions,
            'reason' => $reason,
            'idempotency_key' => $execution->idempotency_key,
        ]);
    }

    public function logFailed(
        TicketAutomationRule $rule,
        Ticket $ticket,
        TicketAutomationExecution $execution,
    ): void {
        $this->activityLogService->log(null, $ticket, 'ticket.automation.failed', "Automacao {$rule->name} falhou.", [
            'sector_id' => $ticket->sector_id,
            'automation_rule_id' => $rule->id,
            'automation_rule_name' => $rule->name,
            'execution_id' => $execution->id,
            'trigger' => $execution->trigger,
            'error' => $execution->error_message,
            'idempotency_key' => $execution->idempotency_key,
        ]);
    }
}
