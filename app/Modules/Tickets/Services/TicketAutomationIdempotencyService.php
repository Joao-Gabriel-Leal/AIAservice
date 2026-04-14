<?php

namespace App\Modules\Tickets\Services;

use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketAutomationExecution;
use App\Modules\Tickets\Models\TicketAutomationRule;

class TicketAutomationIdempotencyService
{
    public function generateContextHash(array $context): string
    {
        return sha1(json_encode($this->normalize($context), JSON_THROW_ON_ERROR));
    }

    public function generateKey(TicketAutomationRule $rule, Ticket $ticket, string $trigger, array $context): string
    {
        return sha1(implode('|', [
            $rule->id,
            $ticket->id,
            $trigger,
            $this->generateContextHash($context),
        ]));
    }

    public function findExistingExecution(string $idempotencyKey): ?TicketAutomationExecution
    {
        return TicketAutomationExecution::query()->where('idempotency_key', $idempotencyKey)->first();
    }

    private function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = [];
            ksort($value);

            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalize($item);
            }

            return $normalized;
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return $value;
    }
}
