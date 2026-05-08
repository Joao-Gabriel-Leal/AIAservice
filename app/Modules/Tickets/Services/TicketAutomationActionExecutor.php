<?php

namespace App\Modules\Tickets\Services;

use App\Enums\TicketAutomationActionType;
use App\Models\User;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketAutomationRuleAction;
use App\Modules\Tickets\Models\TicketGroup;
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

        $group = $this->groupForLegacyStatus($ticket, $status);

        $ticket->forceFill([
            'ticket_status_id' => $status->id,
            'ticket_group_id' => $group?->id ?? $ticket->ticket_group_id,
            'resolved_at' => ($group?->is_closed || $status->is_closed) ? now() : null,
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

        $group = $groupId ? TicketGroup::query()->find($groupId) : null;
        $legacyStatus = $group ? $this->legacyStatusForGroup($ticket->ticket_board_id, $group) : null;

        $ticket->forceFill([
            'ticket_group_id' => $groupId,
            'ticket_status_id' => $legacyStatus?->id ?? $ticket->ticket_status_id,
            'resolved_at' => $group?->is_closed ? now() : null,
        ])->save();

        $this->ticketSlaService->evaluateTicket($ticket->fresh());

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
                        ->globalAdmins()
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

        $group = $this->groupForLegacyStatus($ticket, $status);

        $ticket->forceFill([
            'ticket_status_id' => $status->id,
            'ticket_group_id' => $group?->id ?? $ticket->ticket_group_id,
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

    private function groupForLegacyStatus(Ticket $ticket, TicketStatus $status): ?TicketGroup
    {
        $directMatch = TicketGroup::query()
            ->where('ticket_board_id', $ticket->ticket_board_id)
            ->where('sort_order', $status->sort_order)
            ->first();

        if ($directMatch) {
            return $directMatch;
        }

        $fallbackQuery = TicketGroup::query()
            ->where('ticket_board_id', $ticket->ticket_board_id)
            ->where('is_closed', $status->is_closed);

        return $status->is_closed
            ? $fallbackQuery->orderByDesc('sort_order')->first()
            : $fallbackQuery->orderBy('sort_order')->first();
    }

    private function legacyStatusForGroup(int $boardId, TicketGroup $group): ?TicketStatus
    {
        return TicketStatus::query()
            ->where('ticket_board_id', $boardId)
            ->where('sort_order', $group->sort_order)
            ->first()
            ?? TicketStatus::query()
                ->where('ticket_board_id', $boardId)
                ->where('is_closed', $group->is_closed)
                ->when($group->is_closed, fn ($query) => $query->orderByDesc('sort_order'), fn ($query) => $query->orderBy('sort_order'))
                ->first();
    }
}
