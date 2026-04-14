<?php

namespace App\Jobs;

use App\Enums\TicketAutomationTrigger;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketAutomationRule;
use App\Modules\Tickets\Services\TicketAutomationEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunTicketInactiveAutomationRuleJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $ruleId,
    ) {
    }

    public function handle(TicketAutomationEngine $engine): void
    {
        $rule = TicketAutomationRule::query()
            ->with(['conditions', 'actions'])
            ->find($this->ruleId);

        if (! $rule || ! $rule->is_active || $rule->trigger !== TicketAutomationTrigger::TICKET_INACTIVE) {
            return;
        }

        $inactiveForMinutes = (int) data_get($rule->trigger_settings, 'inactive_for_minutes', 0);

        if ($inactiveForMinutes <= 0) {
            return;
        }

        Ticket::query()
            ->with(['status', 'requester', 'assignee'])
            ->where('ticket_board_id', $rule->ticket_board_id)
            ->whereNotNull('last_activity_at')
            ->where('last_activity_at', '<=', now()->subMinutes($inactiveForMinutes))
            ->chunkById(100, function ($tickets) use ($engine, $rule, $inactiveForMinutes) {
                foreach ($tickets as $ticket) {
                    $engine->runRule($rule, $ticket, TicketAutomationTrigger::TICKET_INACTIVE, [
                        'event' => [
                            'inactive_for_minutes' => $inactiveForMinutes,
                            'last_activity_at' => $ticket->last_activity_at?->toIso8601String(),
                        ],
                    ]);
                }
            });
    }
}
