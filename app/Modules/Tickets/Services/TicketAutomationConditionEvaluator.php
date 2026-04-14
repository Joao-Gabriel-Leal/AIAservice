<?php

namespace App\Modules\Tickets\Services;

use App\Enums\TicketAutomationConditionField;
use App\Enums\TicketAutomationConditionOperator;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketAutomationRule;
use App\Modules\Tickets\Models\TicketAutomationRuleCondition;

class TicketAutomationConditionEvaluator
{
    public function matches(TicketAutomationRule $rule, Ticket $ticket): bool
    {
        $ticket->loadMissing('status');

        foreach ($rule->conditions as $condition) {
            if (! $this->matchesCondition($condition, $ticket)) {
                return false;
            }
        }

        return true;
    }

    private function matchesCondition(TicketAutomationRuleCondition $condition, Ticket $ticket): bool
    {
        $actual = $this->resolveActualValue($condition->field, $ticket);
        $expected = $condition->value;

        return match ($condition->operator) {
            TicketAutomationConditionOperator::IN => in_array($actual, $this->arrayValue($expected), true),
            TicketAutomationConditionOperator::NOT_IN => ! in_array($actual, $this->arrayValue($expected), true),
            TicketAutomationConditionOperator::EQUALS => $actual === $this->scalarValue($expected),
            TicketAutomationConditionOperator::NOT_EQUALS => $actual !== $this->scalarValue($expected),
            TicketAutomationConditionOperator::IS_TRUE => $actual === true,
            TicketAutomationConditionOperator::IS_FALSE => $actual === false,
        };
    }

    private function resolveActualValue(TicketAutomationConditionField $field, Ticket $ticket): string|int|bool|null
    {
        return match ($field) {
            TicketAutomationConditionField::PRIORITY => $ticket->priority?->value,
            TicketAutomationConditionField::STATUS_ID => $ticket->ticket_status_id,
            TicketAutomationConditionField::GROUP_ID => $ticket->ticket_group_id,
            TicketAutomationConditionField::HAS_ASSIGNEE => ! is_null($ticket->assignee_id),
            TicketAutomationConditionField::IS_CLOSED => (bool) ($ticket->status?->is_closed || $ticket->isClosed()),
        };
    }

    private function arrayValue(mixed $value): array
    {
        return array_values(is_array($value) ? $value : [$value]);
    }

    private function scalarValue(mixed $value): string|int|bool|null
    {
        if (is_array($value)) {
            return count($value) > 0 ? reset($value) : null;
        }

        return $value;
    }
}
