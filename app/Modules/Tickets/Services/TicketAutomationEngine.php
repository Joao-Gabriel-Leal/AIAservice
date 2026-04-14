<?php

namespace App\Modules\Tickets\Services;

use App\Enums\TicketAutomationExecutionStatus;
use App\Enums\TicketAutomationRunMode;
use App\Enums\TicketAutomationTrigger;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketAutomationExecution;
use App\Modules\Tickets\Models\TicketAutomationRule;
use Throwable;

class TicketAutomationEngine
{
    public function __construct(
        private readonly TicketAutomationConditionEvaluator $conditionEvaluator,
        private readonly TicketAutomationActionExecutor $actionExecutor,
        private readonly TicketAutomationIdempotencyService $idempotencyService,
        private readonly TicketAutomationAuditService $auditService,
    ) {
    }

    public function handleEvent(Ticket $ticket, TicketAutomationTrigger $trigger, array $context = []): void
    {
        if (($context['trigger_automations'] ?? true) === false || (($context['automation_depth'] ?? 0) >= 1)) {
            return;
        }

        $ticket->loadMissing('board');

        if (! $ticket->board) {
            return;
        }

        $rules = $ticket->board->automationRules()
            ->with(['conditions', 'actions'])
            ->where('trigger', $trigger->value)
            ->where('is_active', true)
            ->whereIn('run_mode', [TicketAutomationRunMode::SYNC->value, TicketAutomationRunMode::ASYNC->value])
            ->get();

        foreach ($rules as $rule) {
            $this->runRule($rule, $ticket, $trigger, $context);
        }
    }

    public function runRule(
        TicketAutomationRule $rule,
        Ticket $ticket,
        TicketAutomationTrigger $trigger,
        array $context = [],
    ): TicketAutomationExecution {
        $rule->loadMissing(['conditions', 'actions']);
        $ticket->loadMissing(['group', 'status', 'requester', 'assignee']);

        $baseContext = $this->baseContext($ticket, $trigger, $context);
        $idempotencyKey = $this->idempotencyService->generateKey($rule, $ticket, $trigger->value, $baseContext);
        $existingExecution = $this->idempotencyService->findExistingExecution($idempotencyKey);

        if ($existingExecution) {
            return $existingExecution;
        }

        if ($this->isCoolingDown($rule, $ticket)) {
            $execution = $this->createExecution($rule, $ticket, $trigger, $baseContext, $idempotencyKey, false);
            $execution->forceFill([
                'status' => TicketAutomationExecutionStatus::SKIPPED,
                'finished_at' => now(),
                'error_message' => 'Rule is inside cooldown window.',
            ])->save();

            $this->auditService->logSkipped($rule, $ticket, $execution, 'cooldown');

            return $execution;
        }

        $matchedConditions = $this->conditionEvaluator->matches($rule, $ticket);
        $execution = $this->createExecution($rule, $ticket, $trigger, $baseContext, $idempotencyKey, $matchedConditions);

        if (! $matchedConditions) {
            $execution->forceFill([
                'status' => TicketAutomationExecutionStatus::SKIPPED,
                'finished_at' => now(),
            ])->save();

            $this->auditService->logSkipped($rule, $ticket, $execution, 'conditions_not_matched');

            return $execution;
        }

        try {
            $appliedActions = [];

            foreach ($rule->actions as $action) {
                $appliedAction = $this->actionExecutor->execute($ticket, $action);

                if ($appliedAction) {
                    $appliedActions[] = $appliedAction;
                    $ticket->refresh()->loadMissing(['group', 'status', 'requester', 'assignee']);
                }
            }

            $execution->forceFill([
                'status' => TicketAutomationExecutionStatus::COMPLETED,
                'actions_count' => count($appliedActions),
                'finished_at' => now(),
                'context' => [
                    ...($execution->context ?? []),
                    'applied_actions' => $appliedActions,
                ],
            ])->save();

            $this->auditService->logExecuted($rule, $ticket->fresh(), $execution, $appliedActions);

            return $execution;
        } catch (Throwable $throwable) {
            $execution->forceFill([
                'status' => TicketAutomationExecutionStatus::FAILED,
                'finished_at' => now(),
                'error_message' => $throwable->getMessage(),
            ])->save();

            $this->auditService->logFailed($rule, $ticket, $execution);

            return $execution;
        }
    }

    private function baseContext(Ticket $ticket, TicketAutomationTrigger $trigger, array $context): array
    {
        return [
            'trigger' => $trigger->value,
            'ticket_updated_at' => $ticket->updated_at?->toIso8601String(),
            'last_activity_at' => $ticket->last_activity_at?->toIso8601String(),
            'event' => $context['event'] ?? [],
        ];
    }

    private function createExecution(
        TicketAutomationRule $rule,
        Ticket $ticket,
        TicketAutomationTrigger $trigger,
        array $context,
        string $idempotencyKey,
        bool $matchedConditions,
    ): TicketAutomationExecution {
        return TicketAutomationExecution::query()->create([
            'ticket_automation_rule_id' => $rule->id,
            'ticket_id' => $ticket->id,
            'trigger' => $trigger->value,
            'trigger_context_hash' => $this->idempotencyService->generateContextHash($context),
            'status' => TicketAutomationExecutionStatus::SKIPPED,
            'matched_conditions' => $matchedConditions,
            'actions_count' => 0,
            'idempotency_key' => $idempotencyKey,
            'started_at' => now(),
            'context' => $context,
        ]);
    }

    private function isCoolingDown(TicketAutomationRule $rule, Ticket $ticket): bool
    {
        if (! $rule->cooldown_minutes) {
            return false;
        }

        return $rule->executions()
            ->where('ticket_id', $ticket->id)
            ->where('status', TicketAutomationExecutionStatus::COMPLETED->value)
            ->where('created_at', '>=', now()->subMinutes($rule->cooldown_minutes))
            ->exists();
    }
}
