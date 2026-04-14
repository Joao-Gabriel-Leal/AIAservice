<?php

namespace App\Modules\Tickets\Services;

use App\Enums\TicketAutomationActionType;
use App\Models\User;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketAutomationRuleAction;
use App\Modules\Tickets\Models\TicketStatus;
use App\Modules\Tickets\Notifications\TicketActivityNotification;
use Illuminate\Support\Facades\Notification;

class TicketAutomationActionExecutor
{
    public function __construct(
        private readonly TicketSlaService $ticketSlaService,
    ) {
    }

    public function execute(Ticket $ticket, TicketAutomationRuleAction $action): ?array
    {
        return match ($action->action) {
            TicketAutomationActionType::ASSIGN_FIXED_ASSIGNEE => $this->assignFixedAssignee($ticket, $action->payload ?? []),
            TicketAutomationActionType::CHANGE_STATUS => $this->changeStatus($ticket, $action->payload ?? []),
            TicketAutomationActionType::CHANGE_GROUP => $this->changeGroup($ticket, $action->payload ?? []),
            TicketAutomationActionType::CHANGE_PRIORITY => $this->changePriority($ticket, $action->payload ?? []),
            TicketAutomationActionType::ADD_SYSTEM_MESSAGE => $this->addSystemMessage($ticket, $action->payload ?? []),
            TicketAutomationActionType::SEND_NOTIFICATION => $this->sendNotification($ticket, $action->payload ?? []),
            TicketAutomationActionType::REOPEN_TICKET => $this->reopenTicket($ticket, $action->payload ?? []),
        };
    }

    private function assignFixedAssignee(Ticket $ticket, array $payload): ?array
    {
        $assigneeId = isset($payload['assignee_id']) ? (int) $payload['assignee_id'] : null;

        if (! $assigneeId || $ticket->assignee_id === $assigneeId) {
            return null;
        }

        $ticket->forceFill(['assignee_id' => $assigneeId])->save();

        return [
            'type' => TicketAutomationActionType::ASSIGN_FIXED_ASSIGNEE->value,
            'assignee_id' => $assigneeId,
        ];
    }

    private function changeStatus(Ticket $ticket, array $payload): ?array
    {
        $statusId = isset($payload['status_id']) ? (int) $payload['status_id'] : null;

        if (! $statusId || $ticket->ticket_status_id === $statusId) {
            return null;
        }

        $status = TicketStatus::query()->find($statusId);

        if (! $status) {
            return null;
        }

        $ticket->forceFill([
            'ticket_status_id' => $status->id,
            'resolved_at' => $status->is_closed ? now() : null,
        ])->save();

        $this->ticketSlaService->evaluateTicket($ticket->fresh());

        return [
            'type' => TicketAutomationActionType::CHANGE_STATUS->value,
            'status_id' => $status->id,
        ];
    }

    private function changeGroup(Ticket $ticket, array $payload): ?array
    {
        $groupId = array_key_exists('group_id', $payload) && ! is_null($payload['group_id'])
            ? (int) $payload['group_id']
            : null;

        if ($ticket->ticket_group_id === $groupId) {
            return null;
        }

        $ticket->forceFill(['ticket_group_id' => $groupId])->save();

        return [
            'type' => TicketAutomationActionType::CHANGE_GROUP->value,
            'group_id' => $groupId,
        ];
    }

    private function changePriority(Ticket $ticket, array $payload): ?array
    {
        $priority = $payload['priority'] ?? null;

        if (! is_string($priority) || $ticket->priority?->value === $priority) {
            return null;
        }

        $ticket->forceFill(['priority' => $priority])->save();

        if (! $ticket->first_responded_at && ! $ticket->resolved_at) {
            $this->ticketSlaService->applyPolicy($ticket->fresh());
        }

        return [
            'type' => TicketAutomationActionType::CHANGE_PRIORITY->value,
            'priority' => $priority,
        ];
    }

    private function addSystemMessage(Ticket $ticket, array $payload): ?array
    {
        $message = trim((string) ($payload['message'] ?? ''));

        if ($message === '') {
            return null;
        }

        $ticket->messages()->create([
            'user_id' => null,
            'message' => $message,
            'is_system' => true,
        ]);

        return [
            'type' => TicketAutomationActionType::ADD_SYSTEM_MESSAGE->value,
            'message' => $message,
        ];
    }

    private function sendNotification(Ticket $ticket, array $payload): ?array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $message = trim((string) ($payload['message'] ?? ''));

        if ($title === '' || $message === '') {
            return null;
        }

        $recipients = collect([
            $ticket->requester,
            $ticket->assignee,
            ...User::query()
                ->where(function ($query) use ($ticket) {
                    $query
                        ->where('global_role', 'super_admin')
                        ->orWhere(function ($scopedQuery) use ($ticket) {
                            $scopedQuery->withSectorAccess($ticket->sector_id, ['sector_admin', 'technician']);
                        });
                })
                ->get()
                ->all(),
        ])->filter()->unique('id');

        if ($recipients->isEmpty()) {
            return null;
        }

        Notification::send($recipients, new TicketActivityNotification($ticket, $title, $message));

        return [
            'type' => TicketAutomationActionType::SEND_NOTIFICATION->value,
            'title' => $title,
            'message' => $message,
        ];
    }

    private function reopenTicket(Ticket $ticket, array $payload): ?array
    {
        $targetStatusId = isset($payload['target_status_id']) ? (int) $payload['target_status_id'] : null;

        if (! $ticket->isClosed() || ! $targetStatusId) {
            return null;
        }

        $status = TicketStatus::query()->find($targetStatusId);

        if (! $status || $status->is_closed) {
            return null;
        }

        $ticket->forceFill([
            'ticket_status_id' => $status->id,
            'resolved_at' => null,
        ])->save();

        $message = trim((string) ($payload['message'] ?? ''));

        if ($message !== '') {
            $ticket->messages()->create([
                'user_id' => null,
                'message' => $message,
                'is_system' => true,
            ]);
        }

        return [
            'type' => TicketAutomationActionType::REOPEN_TICKET->value,
            'status_id' => $status->id,
            'message' => $message !== '' ? $message : null,
        ];
    }
}
