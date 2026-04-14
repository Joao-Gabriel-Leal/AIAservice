<?php

namespace App\Modules\Tickets\Services;

use App\Enums\TicketAutomationTrigger;
use App\Models\User;
use App\Modules\Shared\Services\ActivityLogService;
use App\Modules\Tickets\Events\TicketMessageCreated;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketAttachment;
use App\Modules\Tickets\Models\TicketField;
use App\Modules\Tickets\Models\TicketFieldValue;
use App\Modules\Tickets\Models\TicketGroup;
use App\Modules\Tickets\Models\TicketMessage;
use App\Modules\Tickets\Models\TicketRating;
use App\Modules\Tickets\Models\TicketStatus;
use App\Modules\Tickets\Notifications\TicketActivityNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class TicketWorkflowService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly TicketSlaService $ticketSlaService,
        private readonly TicketAutomationEngine $ticketAutomationEngine,
    ) {
    }

    public function createTicket(User $actor, array $attributes, array $dynamicValues = [], array $attachments = [], array $context = []): Ticket
    {
        $attributes = $this->normalizeLifecycleAttributes($attributes);

        /** @var Ticket $ticket */
        $ticket = Ticket::query()->create([
            ...$attributes,
            'requester_id' => $attributes['requester_id'] ?? $actor->id,
            'last_activity_at' => now(),
        ]);

        $ticket = $this->ticketSlaService->applyPolicy($ticket);

        $this->syncDynamicFields($ticket, $dynamicValues);
        $this->storeAttachments($ticket, $actor, $attachments);
        $this->activityLogService->log($actor, $ticket, 'ticket.created', 'Chamado criado.', [
            'sector_id' => $ticket->sector_id,
        ]);
        $this->notifyUsers($ticket, 'Novo chamado criado', "O chamado #{$ticket->id} foi criado.");

        $ticket = $ticket->fresh(['fieldValues', 'attachments', 'group', 'status', 'requester', 'assignee']);

        $this->ticketAutomationEngine->handleEvent($ticket, TicketAutomationTrigger::TICKET_CREATED, [
            ...$context,
            'event' => [
                'ticket_id' => $ticket->id,
            ],
        ]);

        return $ticket->fresh(['fieldValues', 'attachments', 'group', 'status', 'requester', 'assignee']);
    }

    public function updateTicket(User $actor, Ticket $ticket, array $attributes, array $context = []): Ticket
    {
        $wasClosed = $ticket->isClosed();

        $ticket->fill($attributes);

        if (array_key_exists('ticket_group_id', $attributes)) {
            $group = isset($attributes['ticket_group_id'])
                ? TicketGroup::query()->find($attributes['ticket_group_id'])
                : null;

            $legacyStatus = $group ? $this->legacyStatusForGroup($ticket->ticket_board_id, $group) : null;
            $ticket->ticket_status_id = $legacyStatus?->id ?? $ticket->ticket_status_id;
            $ticket->resolved_at = $group?->is_closed ? now() : null;
        } elseif (array_key_exists('ticket_status_id', $attributes)) {
            $status = TicketStatus::query()->find($attributes['ticket_status_id']);
            $group = $status ? $this->groupForLegacyStatus($ticket->ticket_board_id, $status) : null;
            $ticket->ticket_group_id = $group?->id ?? $ticket->ticket_group_id;
            $ticket->resolved_at = ($group?->is_closed || $status?->is_closed) ? now() : null;
        }

        $ticket->last_activity_at = now();
        $ticket->save();

        if (array_key_exists('priority', $attributes) && ! $ticket->first_responded_at && ! $ticket->resolved_at) {
            $ticket = $this->ticketSlaService->applyPolicy($ticket);
        }

        $this->ticketSlaService->captureFirstResponse($actor, $ticket);
        $this->ticketSlaService->evaluateTicket($ticket);

        $this->activityLogService->log($actor, $ticket, 'ticket.updated', 'Chamado atualizado.', [
            'changes' => $attributes,
            'sector_id' => $ticket->sector_id,
        ]);
        $this->notifyUsers($ticket, 'Chamado atualizado', "O chamado #{$ticket->id} recebeu uma atualizacao.");

        if (! $wasClosed && $ticket->isClosed()) {
            $this->notifyRequesterToRate($ticket);
        }

        $ticket = $ticket->fresh(['group', 'status', 'requester', 'assignee']);

        $this->ticketAutomationEngine->handleEvent($ticket, TicketAutomationTrigger::TICKET_UPDATED, [
            ...$context,
            'event' => [
                'changes' => $attributes,
            ],
        ]);

        return $ticket->fresh(['group', 'status', 'requester', 'assignee']);
    }

    public function updateField(User $actor, Ticket $ticket, TicketField $field, mixed $value, array $context = []): TicketFieldValue
    {
        $fieldValue = TicketFieldValue::query()->firstOrNew([
            'ticket_id' => $ticket->id,
            'ticket_field_id' => $field->id,
        ]);

        $fieldValue->storePrimitiveValue($value);
        $fieldValue->save();

        $ticket->updateQuietly(['last_activity_at' => now()]);

        $this->activityLogService->log($actor, $ticket, 'ticket.field.updated', "Campo {$field->name} atualizado.", [
            'field_id' => $field->id,
            'value' => $value,
            'sector_id' => $ticket->sector_id,
        ]);

        $this->ticketAutomationEngine->handleEvent($ticket->fresh(['group', 'status', 'requester', 'assignee']), TicketAutomationTrigger::TICKET_UPDATED, [
            ...$context,
            'event' => [
                'dynamic_field_id' => $field->id,
                'value' => $value,
            ],
        ]);

        return $fieldValue;
    }

    public function addMessage(User $actor, Ticket $ticket, string $message, array $context = []): TicketMessage
    {
        $ticketMessage = $ticket->messages()->create([
            'user_id' => $actor->id,
            'message' => $message,
            'is_system' => false,
        ]);

        $ticket->updateQuietly(['last_activity_at' => now()]);
        $this->ticketSlaService->captureFirstResponse($actor, $ticket);
        $this->ticketSlaService->evaluateTicket($ticket->fresh());

        $this->activityLogService->log($actor, $ticket, 'ticket.message.created', 'Nova mensagem no chat.', [
            'message_id' => $ticketMessage->id,
            'sector_id' => $ticket->sector_id,
        ]);
        $this->notifyUsers($ticket, 'Nova mensagem no chamado', "Ha uma nova mensagem no chamado #{$ticket->id}.");

        $ticketMessage->load('user');

        $event = new TicketMessageCreated($ticketMessage);
        $socketId = request()->header('X-Socket-ID');

        if (is_string($socketId) && $socketId !== '' && $socketId !== 'undefined') {
            broadcast($event)->toOthers();
        } else {
            event($event);
        }

        $this->ticketAutomationEngine->handleEvent($ticket->fresh(['group', 'status', 'requester', 'assignee']), TicketAutomationTrigger::TICKET_MESSAGE_CREATED, [
            ...$context,
            'event' => [
                'message_id' => $ticketMessage->id,
            ],
        ]);

        return $ticketMessage;
    }

    public function submitRating(User $actor, Ticket $ticket, array $data): TicketRating
    {
        if ($ticket->requester_id !== $actor->id) {
            throw ValidationException::withMessages([
                'ratingValue' => 'Somente o solicitante pode avaliar este chamado.',
            ]);
        }

        if (! $ticket->isClosed()) {
            throw ValidationException::withMessages([
                'ratingValue' => 'A avaliacao so pode ser enviada apos o encerramento do chamado.',
            ]);
        }

        if ($ticket->rating()->exists()) {
            throw ValidationException::withMessages([
                'ratingValue' => 'Este chamado ja foi avaliado.',
            ]);
        }

        try {
            return DB::transaction(function () use ($actor, $ticket, $data) {
                /** @var TicketRating $rating */
                $rating = $ticket->rating()->create([
                    'user_id' => $actor->id,
                    'rating' => $data['rating'],
                    'comment' => $data['comment'] ?: null,
                ]);

                $ticket->updateQuietly(['last_activity_at' => now()]);

                $this->activityLogService->log($actor, $ticket, 'ticket.rating.created', 'Avaliacao registrada pelo solicitante.', [
                    'rating' => $rating->rating,
                    'has_comment' => ! empty($rating->comment),
                    'sector_id' => $ticket->sector_id,
                ]);

                return $rating->load('user');
            });
        } catch (\Illuminate\Database\QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw ValidationException::withMessages([
                    'ratingValue' => 'Este chamado ja foi avaliado.',
                ]);
            }

            throw $exception;
        }
    }

    public function syncDynamicFields(Ticket $ticket, array $dynamicValues): void
    {
        foreach ($dynamicValues as $fieldId => $value) {
            $fieldValue = TicketFieldValue::query()->firstOrNew([
                'ticket_id' => $ticket->id,
                'ticket_field_id' => (int) $fieldId,
            ]);

            $fieldValue->storePrimitiveValue($value);
            $fieldValue->save();
        }
    }

    public function storeAttachments(Ticket $ticket, User $actor, array $attachments): Collection
    {
        return collect($attachments)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->map(function (UploadedFile $file) use ($ticket, $actor) {
                $path = $file->store("tickets/{$ticket->id}", 'local');

                return TicketAttachment::query()->create([
                    'ticket_id' => $ticket->id,
                    'uploaded_by_id' => $actor->id,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            });
    }

    private function notifyUsers(Ticket $ticket, string $title, string $message): void
    {
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

        Notification::send($recipients, new TicketActivityNotification($ticket, $title, $message));
    }

    private function notifyRequesterToRate(Ticket $ticket): void
    {
        if (! $ticket->requester) {
            return;
        }

        $ticket->requester->notify(new TicketActivityNotification(
            $ticket,
            'Chamado encerrado',
            "O chamado #{$ticket->id} foi encerrado. Avalie o atendimento quando puder.",
        ));
    }

    private function normalizeLifecycleAttributes(array $attributes): array
    {
        if (! array_key_exists('ticket_group_id', $attributes)) {
            return $attributes;
        }

        $group = isset($attributes['ticket_group_id'])
            ? TicketGroup::query()->find($attributes['ticket_group_id'])
            : null;

        if (! $group) {
            return [
                ...$attributes,
                'resolved_at' => $attributes['resolved_at'] ?? null,
            ];
        }

        return [
            ...$attributes,
            'ticket_status_id' => $attributes['ticket_status_id'] ?? $this->legacyStatusForGroup((int) $attributes['ticket_board_id'], $group)?->id,
            'resolved_at' => $group->is_closed
                ? ($attributes['resolved_at'] ?? now())
                : null,
        ];
    }

    private function groupForLegacyStatus(int $boardId, TicketStatus $status): ?TicketGroup
    {
        $directMatch = TicketGroup::query()
            ->where('ticket_board_id', $boardId)
            ->where('sort_order', $status->sort_order)
            ->first();

        if ($directMatch) {
            return $directMatch;
        }

        $fallbackQuery = TicketGroup::query()
            ->where('ticket_board_id', $boardId)
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
