<?php

namespace App\Modules\Tickets\Services;

use App\Enums\TicketAutomationTrigger;
use App\Enums\TicketTimeEntryApprovalStatus;
use App\Enums\TicketTimeEntrySource;
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
use App\Modules\Tickets\Models\TicketTimeEntry;
use App\Modules\Tickets\Notifications\TicketCreatedNotification;
use App\Modules\Tickets\Notifications\TicketInternalUpdateNotification;
use App\Modules\Tickets\Notifications\TicketManualTimeEntryPendingApprovalNotification;
use App\Modules\Tickets\Notifications\TicketMessageNotification;
use App\Modules\Tickets\Notifications\TicketRatingRequestNotification;
use App\Modules\Tickets\Notifications\TicketTimeEntryReviewedNotification;
use App\Modules\Tickets\Notifications\TicketUpdateNotification;
use App\Modules\Tickets\Support\TicketAttachmentRules;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TicketWorkflowService
{
    public const TRASH_RETENTION_DAYS = 30;

    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly TicketSlaService $ticketSlaService,
        private readonly TicketAutomationEngine $ticketAutomationEngine,
        private readonly TicketBoardOrderService $ticketBoardOrderService,
    ) {}

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
        $this->notifyUsers(
            $ticket,
            new TicketCreatedNotification($ticket, 'Novo chamado criado', 'O chamado '.$ticket->fullReference().' foi criado.'),
        );

        $ticket = $ticket->fresh(['fieldValues', 'attachments', 'group', 'status', 'requester', 'assignee']);

        $this->ticketAutomationEngine->handleEvent($ticket, TicketAutomationTrigger::TICKET_CREATED, [
            ...$context,
            'event' => [
                'ticket_id' => $ticket->id,
            ],
        ]);

        return $ticket->fresh(['fieldValues', 'attachments', 'group', 'status', 'requester', 'assignee']);
    }

    public function createSubelement(User $actor, Ticket $parent, string $title, array $context = []): Ticket
    {
        Gate::forUser($actor)->authorize('update', $parent);

        $parent = $parent->fresh(['board', 'group', 'status', 'requester', 'assignee']) ?? $parent;
        $title = trim($title);

        if ($parent->isSubelement()) {
            throw ValidationException::withMessages([
                'subelementTitle' => 'Subelementos nao podem ter outros subelementos.',
            ]);
        }

        if ($title === '') {
            throw ValidationException::withMessages([
                'subelementTitle' => 'Informe um titulo para o subelemento.',
            ]);
        }

        $attributes = $this->normalizeLifecycleAttributes([
            'parent_ticket_id' => $parent->id,
            'sector_id' => $parent->sector_id,
            'ticket_board_id' => $parent->ticket_board_id,
            'ticket_group_id' => $parent->ticket_group_id,
            'ticket_status_id' => $parent->ticket_status_id,
            'service_catalog_item_id' => $parent->service_catalog_item_id,
            'room_id' => $parent->room_id,
            'title' => $title,
            'description' => null,
            'requester_id' => $parent->requester_id,
            'assignee_id' => null,
            'priority' => $parent->priority,
        ]);

        /** @var Ticket $subelement */
        $subelement = Ticket::query()->create([
            ...$attributes,
            'last_activity_at' => now(),
        ]);

        $subelement = $this->ticketSlaService->applyPolicy($subelement);
        $parent->updateQuietly(['last_activity_at' => now()]);

        $this->activityLogService->log($actor, $parent, 'ticket.subelement.created', 'Subelemento criado no chamado.', [
            'sector_id' => $parent->sector_id,
            'subelement_id' => $subelement->id,
        ]);

        $this->activityLogService->log($actor, $subelement, 'ticket.subelement.created', 'Subelemento criado.', [
            'sector_id' => $subelement->sector_id,
            'parent_ticket_id' => $parent->id,
        ]);

        $subelement = $subelement->fresh(['fieldValues', 'attachments', 'group', 'status', 'requester', 'assignee', 'parentTicket']);

        $this->notifyUsers(
            $subelement,
            new TicketCreatedNotification($subelement, 'Novo subelemento criado', 'O subelemento '.$subelement->fullReference().' foi criado em '.$parent->fullReference().'.'),
            [$actor->id],
        );

        $this->ticketAutomationEngine->handleEvent($subelement, TicketAutomationTrigger::TICKET_CREATED, [
            ...$context,
            'event' => [
                'ticket_id' => $subelement->id,
                'parent_ticket_id' => $parent->id,
                'source' => 'subelement',
            ],
        ]);

        return $subelement->fresh(['fieldValues', 'attachments', 'group', 'status', 'requester', 'assignee', 'parentTicket']);
    }

    public function updateTicket(User $actor, Ticket $ticket, array $attributes, array $context = []): Ticket
    {
        $wasClosed = $ticket->isClosed();
        $original = [
            'assignee_id' => $ticket->assignee_id,
            'ticket_group_id' => $ticket->ticket_group_id,
            'ticket_status_id' => $ticket->ticket_status_id,
            'priority' => $ticket->priority?->value,
        ];

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

        if (! $ticket->isSubelement() && ! $wasClosed && $this->ticketWouldBeClosed($ticket)) {
            $this->ensureNoOpenSubelements($ticket);
        }

        $ticket->last_activity_at = now();
        $ticket->save();

        if (! $ticket->isSubelement() && $this->sameGroupId($original['ticket_group_id'] ?? null, $ticket->ticket_group_id) === false) {
            $this->ticketBoardOrderService->moveTicket(
                $ticket,
                $original['ticket_group_id'] ?? null,
                $ticket->ticket_group_id,
                null,
                'end',
            );
        }

        if (array_key_exists('priority', $attributes) && ! $ticket->first_responded_at && ! $ticket->resolved_at) {
            $ticket = $this->ticketSlaService->applyPolicy($ticket);
        }

        if (! $wasClosed && $ticket->isClosed()) {
            $this->closeOpenTimeEntries($actor, $ticket, $ticket->resolved_at ?? now());
        }

        $this->ticketSlaService->captureFirstResponse($actor, $ticket);
        $this->ticketSlaService->evaluateTicket($ticket);

        $this->activityLogService->log($actor, $ticket, 'ticket.updated', 'Chamado atualizado.', [
            'changes' => $attributes,
            'sector_id' => $ticket->sector_id,
        ]);

        $ticket = $ticket->fresh(['group', 'status', 'requester', 'assignee']);

        $updateNotification = $this->updateNotificationFor($ticket, $original, $wasClosed);

        if ($updateNotification !== null) {
            $this->notifyUsers($ticket, $updateNotification, [$actor->id]);
        }

        if (! $wasClosed && $ticket->isClosed()) {
            $this->notifyRequesterToRate($ticket);
        }

        $this->ticketAutomationEngine->handleEvent($ticket, TicketAutomationTrigger::TICKET_UPDATED, [
            ...$context,
            'event' => [
                'changes' => $attributes,
            ],
        ]);

        return $ticket->fresh(['group', 'status', 'requester', 'assignee']);
    }

    public function startTimeEntry(User $actor, Ticket $ticket): TicketTimeEntry
    {
        Gate::forUser($actor)->authorize('trackTime', $ticket);

        if ($ticket->isClosed()) {
            throw ValidationException::withMessages([
                'timeTracking' => 'Nao e possivel iniciar o cronometro em um chamado encerrado.',
            ]);
        }

        if ($ticket->activeTimeEntryForUser($actor)) {
            throw ValidationException::withMessages([
                'timeTracking' => 'Voce ja possui um cronometro em andamento neste chamado.',
            ]);
        }

        /** @var TicketTimeEntry $timeEntry */
        $timeEntry = DB::transaction(function () use ($actor, $ticket) {
            /** @var TicketTimeEntry $createdTimeEntry */
            $createdTimeEntry = $ticket->timeEntries()->create([
                'user_id' => $actor->id,
                'source' => TicketTimeEntrySource::TIMER,
                'approval_status' => TicketTimeEntryApprovalStatus::APPROVED,
                'started_at' => now(),
                'ended_at' => null,
                'duration_seconds' => null,
            ]);

            $ticket->updateQuietly(['last_activity_at' => now()]);

            $this->activityLogService->log($actor, $ticket, 'ticket.time_entry.started', 'Cronometro iniciado no chamado.', [
                'time_entry_id' => $createdTimeEntry->id,
                'user_id' => $actor->id,
                'source' => $createdTimeEntry->source?->value,
                'started_at' => $createdTimeEntry->started_at?->toIso8601String(),
                'sector_id' => $ticket->sector_id,
            ]);

            return $createdTimeEntry;
        });

        return $timeEntry->load('user');
    }

    public function stopTimeEntry(User $actor, TicketTimeEntry $timeEntry): TicketTimeEntry
    {
        Gate::forUser($actor)->authorize('update', $timeEntry);

        if (! $timeEntry->isRunning()) {
            throw ValidationException::withMessages([
                'timeTracking' => 'Esta sessao ja foi encerrada.',
            ]);
        }

        $this->finalizeTimeEntry($actor, $timeEntry, now(), 'ticket.time_entry.stopped', 'Cronometro encerrado.');

        return $timeEntry->fresh(['user', 'ticket']);
    }

    public function createManualTimeEntry(User $actor, Ticket $ticket, array $data): TicketTimeEntry
    {
        Gate::forUser($actor)->authorize('trackTime', $ticket);

        $timestamps = $this->normalizeTimeEntryTimestamps($data);

        /** @var TicketTimeEntry $timeEntry */
        $timeEntry = DB::transaction(function () use ($actor, $ticket, $timestamps) {
            /** @var TicketTimeEntry $createdTimeEntry */
            $createdTimeEntry = $ticket->timeEntries()->create([
                'user_id' => $actor->id,
                'source' => TicketTimeEntrySource::MANUAL,
                'approval_status' => TicketTimeEntryApprovalStatus::PENDING,
                'started_at' => $timestamps['started_at'],
                'ended_at' => $timestamps['ended_at'],
                'duration_seconds' => $timestamps['duration_seconds'],
            ]);

            $ticket->updateQuietly(['last_activity_at' => now()]);

            $this->activityLogService->log($actor, $ticket, 'ticket.time_entry.manual_created', 'Sessao manual registrada no chamado.', [
                'time_entry_id' => $createdTimeEntry->id,
                'user_id' => $actor->id,
                'source' => $createdTimeEntry->source?->value,
                'started_at' => $createdTimeEntry->started_at?->toIso8601String(),
                'ended_at' => $createdTimeEntry->ended_at?->toIso8601String(),
                'duration_seconds' => $createdTimeEntry->duration_seconds,
                'sector_id' => $ticket->sector_id,
            ]);

            return $createdTimeEntry;
        });

        $timeEntry = $timeEntry->load('user', 'ticket');
        $this->notifyPendingManualTimeEntry($timeEntry, [$actor->id]);

        return $timeEntry;
    }

    public function updateTimeEntry(User $actor, TicketTimeEntry $timeEntry, array $data): TicketTimeEntry
    {
        Gate::forUser($actor)->authorize('update', $timeEntry);

        $timestamps = $this->normalizeTimeEntryTimestamps($data);
        $ticket = $timeEntry->ticket;
        $originalApprovalStatus = $timeEntry->approval_status;
        $shouldReturnToPending = $timeEntry->source === TicketTimeEntrySource::MANUAL
            && in_array($originalApprovalStatus, [
                TicketTimeEntryApprovalStatus::PENDING,
                TicketTimeEntryApprovalStatus::REJECTED,
            ], true);

        DB::transaction(function () use ($actor, $timeEntry, $timestamps, $ticket, $shouldReturnToPending) {
            $originalStartedAt = $timeEntry->started_at?->toIso8601String();
            $originalEndedAt = $timeEntry->ended_at?->toIso8601String();
            $originalDuration = $timeEntry->duration_seconds;
            $originalApprovalStatus = $timeEntry->approval_status?->value;

            $timeEntry->forceFill([
                'started_at' => $timestamps['started_at'],
                'ended_at' => $timestamps['ended_at'],
                'duration_seconds' => $timestamps['duration_seconds'],
                'approval_status' => $shouldReturnToPending
                    ? TicketTimeEntryApprovalStatus::PENDING
                    : $timeEntry->approval_status,
                'reviewed_by_id' => $shouldReturnToPending ? null : $timeEntry->reviewed_by_id,
                'reviewed_at' => $shouldReturnToPending ? null : $timeEntry->reviewed_at,
                'review_note' => $shouldReturnToPending ? null : $timeEntry->review_note,
            ])->save();

            $ticket->updateQuietly(['last_activity_at' => now()]);

            $this->activityLogService->log($actor, $ticket, 'ticket.time_entry.updated', 'Sessao de tempo atualizada.', [
                'time_entry_id' => $timeEntry->id,
                'user_id' => $timeEntry->user_id,
                'source' => $timeEntry->source?->value,
                'before' => [
                    'started_at' => $originalStartedAt,
                    'ended_at' => $originalEndedAt,
                    'duration_seconds' => $originalDuration,
                ],
                'after' => [
                    'started_at' => $timeEntry->started_at?->toIso8601String(),
                    'ended_at' => $timeEntry->ended_at?->toIso8601String(),
                    'duration_seconds' => $timeEntry->duration_seconds,
                    'approval_status' => $timeEntry->approval_status?->value,
                ],
                'before_approval_status' => $originalApprovalStatus,
                'sector_id' => $ticket->sector_id,
            ]);
        });

        $timeEntry = $timeEntry->fresh(['user', 'ticket']);

        if ($shouldReturnToPending && $originalApprovalStatus === TicketTimeEntryApprovalStatus::REJECTED) {
            $this->notifyPendingManualTimeEntry($timeEntry, [$actor->id]);
        }

        return $timeEntry;
    }

    public function approveTimeEntry(User $actor, TicketTimeEntry $timeEntry, ?string $note = null): TicketTimeEntry
    {
        Gate::forUser($actor)->authorize('review', $timeEntry);

        return $this->reviewTimeEntry(
            $actor,
            $timeEntry,
            TicketTimeEntryApprovalStatus::APPROVED,
            'ticket.time_entry.approved',
            'Apontamento manual aprovado.',
            $note,
        );
    }

    public function rejectTimeEntry(User $actor, TicketTimeEntry $timeEntry, ?string $note = null): TicketTimeEntry
    {
        Gate::forUser($actor)->authorize('review', $timeEntry);

        return $this->reviewTimeEntry(
            $actor,
            $timeEntry,
            TicketTimeEntryApprovalStatus::REJECTED,
            'ticket.time_entry.rejected',
            'Apontamento manual rejeitado.',
            $note,
        );
    }

    public function deleteTimeEntry(User $actor, TicketTimeEntry $timeEntry): void
    {
        Gate::forUser($actor)->authorize('delete', $timeEntry);

        DB::transaction(function () use ($actor, $timeEntry) {
            $ticket = $timeEntry->ticket;

            $timeEntry->delete();
            $ticket->updateQuietly(['last_activity_at' => now()]);

            $this->activityLogService->log($actor, $ticket, 'ticket.time_entry.deleted', 'Sessao de tempo removida.', [
                'time_entry_id' => $timeEntry->id,
                'user_id' => $timeEntry->user_id,
                'source' => $timeEntry->source?->value,
                'started_at' => $timeEntry->started_at?->toIso8601String(),
                'ended_at' => $timeEntry->ended_at?->toIso8601String(),
                'duration_seconds' => $timeEntry->duration_seconds,
                'sector_id' => $ticket->sector_id,
            ]);
        });
    }

    public function deleteTicket(User $actor, Ticket $ticket): void
    {
        Gate::forUser($actor)->authorize('delete', $ticket);

        DB::transaction(function () use ($actor, $ticket): void {
            $ticket = $ticket->fresh(['parentTicket', 'subTickets', 'incidentChildren']) ?? $ticket;
            $subelementIds = collect();

            if (! $ticket->isSubelement()) {
                $subelementIds = $ticket->subTickets->pluck('id');

                $ticket->subTickets->each(function (Ticket $subelement) use ($actor, $ticket): void {
                    $this->detachMajorIncidentLinks($subelement);
                    $this->deleteSingleTicket($actor, $subelement, 'ticket.subelement.deleted_with_parent', 'Subelemento movido para a lixeira junto com o chamado pai.', [
                        'parent_ticket_id' => $ticket->id,
                    ]);
                });
            }

            if ($ticket->isSubelement()) {
                $ticket->parentTicket?->updateQuietly(['last_activity_at' => now()]);
            }

            $this->detachMajorIncidentLinks($ticket);
            $this->deleteSingleTicket($actor, $ticket, 'ticket.deleted', $ticket->isSubelement() ? 'Subelemento movido para a lixeira.' : 'Chamado movido para a lixeira.', [
                'parent_ticket_id' => $ticket->parent_ticket_id,
                'subelement_ids' => $subelementIds->all(),
                'retention_days' => self::TRASH_RETENTION_DAYS,
            ]);
        });
    }

    public function restoreTicket(User $actor, Ticket $ticket): Ticket
    {
        Gate::forUser($actor)->authorize('restore', $ticket);

        if (! $ticket->trashed()) {
            return $ticket;
        }

        $this->ensureWithinTrashRetention($ticket);

        return DB::transaction(function () use ($actor, $ticket): Ticket {
            $ticket = $ticket->fresh(['parentTicketWithTrashed', 'board']) ?? $ticket;
            $restoredSubelementIds = [];

            if ($ticket->isSubelement()) {
                $parent = $ticket->parentTicketWithTrashed;

                if ($parent?->trashed()) {
                    Gate::forUser($actor)->authorize('restore', $parent);
                    $this->ensureWithinTrashRetention($parent);
                    $this->restoreSingleTicket($actor, $parent, 'ticket.restored_with_subelement', 'Chamado pai restaurado junto com o subelemento.', [
                        'subelement_id' => $ticket->id,
                    ]);
                }
            }

            $this->restoreSingleTicket($actor, $ticket, 'ticket.restored', $ticket->isSubelement() ? 'Subelemento restaurado da lixeira.' : 'Chamado restaurado da lixeira.', [
                'parent_ticket_id' => $ticket->parent_ticket_id,
            ]);

            if (! $ticket->isSubelement()) {
                $ticket->subTicketsWithTrashed()
                    ->onlyTrashed()
                    ->where('deleted_at', '>=', now()->subDays(self::TRASH_RETENTION_DAYS))
                    ->get()
                    ->each(function (Ticket $subelement) use ($actor, &$restoredSubelementIds): void {
                        $this->restoreSingleTicket($actor, $subelement, 'ticket.subelement.restored_with_parent', 'Subelemento restaurado junto com o chamado pai.', [
                            'parent_ticket_id' => $subelement->parent_ticket_id,
                        ]);

                        $restoredSubelementIds[] = $subelement->id;
                    });

                if ($restoredSubelementIds !== []) {
                    $this->activityLogService->log($actor, $ticket, 'ticket.subelements.restored_with_parent', 'Subelementos restaurados junto com o chamado pai.', [
                        'sector_id' => $ticket->sector_id,
                        'subelement_ids' => $restoredSubelementIds,
                    ]);
                }
            }

            return $ticket->fresh(['parentTicket', 'subTickets', 'group', 'status', 'requester', 'assignee']) ?? $ticket;
        });
    }

    public function updateField(User $actor, Ticket $ticket, TicketField $field, mixed $value, array $context = []): TicketFieldValue
    {
        $fieldValue = TicketFieldValue::query()->firstOrNew([
            'ticket_id' => $ticket->id,
            'ticket_field_id' => $field->id,
        ]);

        $normalizedValue = $field->normalizeMaskedValue($value);

        $fieldValue->storePrimitiveValue($normalizedValue);
        $fieldValue->save();

        $ticket->updateQuietly(['last_activity_at' => now()]);

        $this->activityLogService->log($actor, $ticket, 'ticket.field.updated', "Campo {$field->name} atualizado.", [
            'field_id' => $field->id,
            'value' => $normalizedValue,
            'sector_id' => $ticket->sector_id,
        ]);

        $this->ticketAutomationEngine->handleEvent($ticket->fresh(['group', 'status', 'requester', 'assignee']), TicketAutomationTrigger::TICKET_UPDATED, [
            ...$context,
            'event' => [
                'dynamic_field_id' => $field->id,
                'value' => $normalizedValue,
            ],
        ]);

        return $fieldValue;
    }

    public function addMessage(
        User $actor,
        Ticket $ticket,
        string $message,
        array $attachments = [],
        array $context = [],
        bool $isInternal = false,
        array $mentionedUserIds = [],
    ): TicketMessage {
        Gate::forUser($actor)->authorize($isInternal ? 'commentInternally' : 'comment', $ticket);

        $mentionedUserIds = collect($mentionedUserIds)
            ->map(fn (mixed $userId) => (int) $userId)
            ->filter(fn (int $userId) => $userId > 0 && $userId !== $actor->id)
            ->unique()
            ->values()
            ->all();

        /** @var TicketMessage $ticketMessage */
        $ticketMessage = DB::transaction(function () use ($actor, $ticket, $message, $attachments, $isInternal, $mentionedUserIds) {
            /** @var TicketMessage $createdMessage */
            $createdMessage = $ticket->messages()->create([
                'user_id' => $actor->id,
                'message' => $message,
                'is_system' => false,
                'is_internal' => $isInternal,
                'mentioned_user_ids' => $mentionedUserIds !== [] ? $mentionedUserIds : null,
            ]);

            $this->storeAttachments($ticket, $actor, $attachments, $createdMessage, 'chat');

            return $createdMessage;
        });

        $ticket->updateQuietly(['last_activity_at' => now()]);
        $this->ticketSlaService->captureFirstResponse($actor, $ticket);
        $this->ticketSlaService->evaluateTicket($ticket->fresh());

        $this->activityLogService->log(
            $actor,
            $ticket,
            $isInternal ? 'ticket.internal_message.created' : 'ticket.message.created',
            $isInternal ? 'Atualizacao interna registrada.' : 'Nova mensagem no chat.',
            [
                'message_id' => $ticketMessage->id,
                'mentioned_user_ids' => $mentionedUserIds,
                'sector_id' => $ticket->sector_id,
            ],
        );

        if ($isInternal) {
            $this->notifyInternalUpdateMentions($ticket, $actor, $mentionedUserIds);
        } else {
            $this->notifyUsers(
                $ticket,
                new TicketMessageNotification($ticket, 'Nova mensagem no chamado', 'Ha uma nova mensagem no chamado '.$ticket->fullReference().'.'),
                [$actor->id],
            );
        }

        $ticketMessage->load(['user', 'attachments']);

        $this->broadcastMessageCreated($ticketMessage);

        $this->handleAutomationEventSafely(
            $ticket->fresh(['group', 'status', 'requester', 'assignee']),
            TicketAutomationTrigger::TICKET_MESSAGE_CREATED,
            [
                ...$context,
                'event' => [
                    'message_id' => $ticketMessage->id,
                ],
            ],
        );

        return $ticketMessage;
    }

    public function closeByRequester(User $actor, Ticket $ticket): Ticket
    {
        Gate::forUser($actor)->authorize('closeOwn', $ticket);
        $this->ensureNoOpenSubelements($ticket);

        $sourceGroupId = $ticket->ticket_group_id;

        $closedGroup = $this->targetGroup($ticket, true);
        $closedStatus = $closedGroup
            ? $this->legacyStatusForGroup($ticket->ticket_board_id, $closedGroup) ?? $this->targetStatus($ticket, true)
            : $this->targetStatus($ticket, true);

        if (! $closedGroup || ! $closedStatus) {
            throw ValidationException::withMessages([
                'ticketLifecycle' => 'Este quadro nao possui etapa e status finalizados ativos.',
            ]);
        }

        $ticket = DB::transaction(function () use ($actor, $ticket, $closedGroup, $closedStatus) {
            $ticket->forceFill([
                'ticket_group_id' => $closedGroup?->id ?? $ticket->ticket_group_id,
                'ticket_status_id' => $closedStatus?->id ?? $ticket->ticket_status_id,
                'resolved_at' => now(),
                'last_activity_at' => now(),
            ])->save();

            $this->closeOpenTimeEntries($actor, $ticket, $ticket->resolved_at ?? now());

            $this->activityLogService->log($actor, $ticket, 'ticket.closed_by_requester', 'Chamado finalizado pelo solicitante.', [
                'sector_id' => $ticket->sector_id,
                'ticket_group_id' => $ticket->ticket_group_id,
                'ticket_status_id' => $ticket->ticket_status_id,
            ]);

            $this->createSystemMessage($ticket, 'Chamado finalizado pelo solicitante.');

            return $ticket->fresh(['group', 'status', 'requester', 'assignee']);
        });

        if ($this->sameGroupId($sourceGroupId, $ticket->ticket_group_id) === false) {
            $this->ticketBoardOrderService->moveTicket(
                $ticket,
                $sourceGroupId,
                $ticket->ticket_group_id,
                null,
                'end',
            );
        }

        $this->ticketSlaService->evaluateTicket($ticket);
        $this->notifyUsers(
            $ticket,
            new TicketUpdateNotification($ticket, 'Chamado finalizado pelo solicitante', 'O chamado '.$ticket->fullReference().' foi finalizado pelo solicitante.'),
            [$actor->id],
        );
        $this->notifyRequesterToRate($ticket);
        $this->handleAutomationEventSafely($ticket, TicketAutomationTrigger::TICKET_UPDATED, [
            'event' => [
                'changes' => [
                    'resolved_at' => $ticket->resolved_at?->toIso8601String(),
                    'ticket_group_id' => $ticket->ticket_group_id,
                    'ticket_status_id' => $ticket->ticket_status_id,
                ],
                'source' => 'requester_close',
            ],
        ]);

        return $ticket->fresh(['group', 'status', 'requester', 'assignee']);
    }

    public function reopenByRequester(User $actor, Ticket $ticket): Ticket
    {
        Gate::forUser($actor)->authorize('reopenOwn', $ticket);
        $sourceGroupId = $ticket->ticket_group_id;

        $openGroup = $this->targetGroup($ticket, false);
        $openStatus = $openGroup
            ? $this->legacyStatusForGroup($ticket->ticket_board_id, $openGroup) ?? $this->targetStatus($ticket, false)
            : $this->targetStatus($ticket, false);

        if (! $openGroup || ! $openStatus) {
            throw ValidationException::withMessages([
                'ticketLifecycle' => 'Este quadro nao possui etapa e status abertos ativos.',
            ]);
        }

        $ticket = DB::transaction(function () use ($actor, $ticket, $openGroup, $openStatus) {
            $ticket->forceFill([
                'ticket_group_id' => $openGroup?->id ?? $ticket->ticket_group_id,
                'ticket_status_id' => $openStatus?->id ?? $ticket->ticket_status_id,
                'resolved_at' => null,
                'last_activity_at' => now(),
            ])->save();

            $this->activityLogService->log($actor, $ticket, 'ticket.reopened_by_requester', 'Chamado reaberto pelo solicitante.', [
                'sector_id' => $ticket->sector_id,
                'ticket_group_id' => $ticket->ticket_group_id,
                'ticket_status_id' => $ticket->ticket_status_id,
            ]);

            $this->createSystemMessage($ticket, 'Chamado reaberto pelo solicitante.');

            return $ticket->fresh(['group', 'status', 'requester', 'assignee']);
        });

        if ($this->sameGroupId($sourceGroupId, $ticket->ticket_group_id) === false) {
            $this->ticketBoardOrderService->moveTicket(
                $ticket,
                $sourceGroupId,
                $ticket->ticket_group_id,
                null,
                'end',
            );
        }

        $this->ticketSlaService->evaluateTicket($ticket);
        $this->notifyUsers(
            $ticket,
            new TicketUpdateNotification($ticket, 'Chamado reaberto pelo solicitante', 'O chamado '.$ticket->fullReference().' foi reaberto pelo solicitante.'),
            [$actor->id],
        );
        $this->handleAutomationEventSafely($ticket, TicketAutomationTrigger::TICKET_UPDATED, [
            'event' => [
                'changes' => [
                    'resolved_at' => null,
                    'ticket_group_id' => $ticket->ticket_group_id,
                    'ticket_status_id' => $ticket->ticket_status_id,
                ],
                'source' => 'requester_reopen',
            ],
        ]);

        return $ticket->fresh(['group', 'status', 'requester', 'assignee']);
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
        } catch (QueryException $exception) {
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
            $field = TicketField::query()->find((int) $fieldId);

            $fieldValue = TicketFieldValue::query()->firstOrNew([
                'ticket_id' => $ticket->id,
                'ticket_field_id' => (int) $fieldId,
            ]);

            $fieldValue->storePrimitiveValue($field?->normalizeMaskedValue($value) ?? $value);
            $fieldValue->save();
        }
    }

    public function storeAttachments(
        Ticket $ticket,
        User $actor,
        array $attachments,
        ?TicketMessage $message = null,
        string $source = 'opening',
    ): Collection {
        $field = $message ? 'chatFiles' : 'attachments';
        $validatedAttachments = TicketAttachmentRules::validate($attachments, $field);

        return collect($validatedAttachments)
            ->map(function (UploadedFile $file) use ($ticket, $actor, $message, $source) {
                $content = file_get_contents($file->getRealPath());

                if ($content === false) {
                    throw new \RuntimeException("Nao foi possivel ler o anexo {$file->getClientOriginalName()}.");
                }

                $extension = $file->extension() ?: $file->getClientOriginalExtension() ?: 'bin';
                $path = "tickets/{$ticket->id}/".Str::uuid().".{$extension}";
                $attachment = new TicketAttachment;
                $mimeType = $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream';

                return TicketAttachment::query()->create([
                    'ticket_id' => $ticket->id,
                    'ticket_message_id' => $message?->id,
                    'uploaded_by_id' => $actor->id,
                    'source' => $source,
                    'disk' => 'database',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $mimeType,
                    'size' => $file->getSize() ?: strlen($content),
                    'content' => $attachment->encodeContentForStorage($content),
                ]);
            });
    }

    private function notifyUsers(Ticket $ticket, object $notification, array $exceptUserIds = []): void
    {
        $recipients = $this->ticketRecipients($ticket, $exceptUserIds);

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Notification::send($recipients, $notification);
        } catch (Throwable $throwable) {
            $this->logNotificationFailure($throwable, $ticket, $notification);
        }
    }

    private function deleteSingleTicket(User $actor, Ticket $ticket, string $event, string $description, array $context = []): void
    {
        $this->activityLogService->log($actor, $ticket, $event, $description, [
            ...$context,
            'sector_id' => $ticket->sector_id,
            'ticket_board_id' => $ticket->ticket_board_id,
        ]);

        $ticket->delete();
    }

    private function restoreSingleTicket(User $actor, Ticket $ticket, string $event, string $description, array $context = []): void
    {
        $ticket->restore();
        $ticket->updateQuietly(['last_activity_at' => now()]);

        $this->activityLogService->log($actor, $ticket, $event, $description, [
            ...$context,
            'sector_id' => $ticket->sector_id,
            'ticket_board_id' => $ticket->ticket_board_id,
        ]);
    }

    private function ensureWithinTrashRetention(Ticket $ticket): void
    {
        if (! $ticket->deleted_at || $ticket->deleted_at->lt(now()->subDays(self::TRASH_RETENTION_DAYS))) {
            throw ValidationException::withMessages([
                'trash' => 'Este chamado ultrapassou o prazo de 30 dias da lixeira e nao pode mais ser restaurado.',
            ]);
        }
    }

    private function detachMajorIncidentLinks(Ticket $ticket): void
    {
        if ($ticket->is_major_incident) {
            Ticket::query()
                ->where('major_incident_ticket_id', $ticket->id)
                ->update([
                    'major_incident_ticket_id' => null,
                    'last_activity_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        if ($ticket->major_incident_ticket_id !== null) {
            $ticket->forceFill(['major_incident_ticket_id' => null])->saveQuietly();
        }
    }

    private function notifyPendingManualTimeEntry(TicketTimeEntry $timeEntry, array $exceptUserIds = []): void
    {
        $ticket = $timeEntry->ticket ?? $timeEntry->ticket()->first();

        if (! $ticket) {
            return;
        }

        $recipients = User::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($ticket) {
                $query
                    ->globalAdmins()
                    ->orWhere(fn (Builder $scopedQuery) => $scopedQuery->withSectorAccess($ticket->sector_id, ['sector_admin']));
            })
            ->whereNotIn('id', $exceptUserIds)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $notification = new TicketManualTimeEntryPendingApprovalNotification($ticket);

        try {
            Notification::send($recipients, $notification);
        } catch (Throwable $throwable) {
            $this->logNotificationFailure($throwable, $ticket, $notification);
        }
    }

    private function notifyTimeEntryReviewed(TicketTimeEntry $timeEntry, TicketTimeEntryApprovalStatus $status, array $exceptUserIds = []): void
    {
        $recipient = $timeEntry->user;
        $ticket = $timeEntry->ticket;

        if (! $recipient || ! $ticket || in_array($recipient->id, $exceptUserIds, true)) {
            return;
        }

        $notification = new TicketTimeEntryReviewedNotification($ticket, strtolower($status->label()));

        try {
            $recipient->notify($notification);
        } catch (Throwable $throwable) {
            $this->logNotificationFailure($throwable, $ticket, $notification);
        }
    }

    private function notifyRequesterToRate(Ticket $ticket): void
    {
        if (! $ticket->requester || $ticket->hasRating()) {
            return;
        }

        $notification = new TicketRatingRequestNotification(
            $ticket,
            'Chamado encerrado',
            'O chamado '.$ticket->fullReference().' foi encerrado. Avalie o atendimento quando puder.',
        );

        try {
            $ticket->requester->notify($notification);
        } catch (Throwable $throwable) {
            $this->logNotificationFailure($throwable, $ticket, $notification);
        }
    }

    private function notifyInternalUpdateMentions(Ticket $ticket, User $actor, array $mentionedUserIds): void
    {
        if ($mentionedUserIds === []) {
            return;
        }

        $recipients = User::query()
            ->where('is_active', true)
            ->whereIn('id', $mentionedUserIds)
            ->where(function (Builder $query) use ($ticket) {
                $query
                    ->globalAdmins()
                    ->orWhere(fn (Builder $scopedQuery) => $scopedQuery->withSectorAccess($ticket->sector_id, ['sector_admin', 'technician']));
            })
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $notification = new TicketInternalUpdateNotification($ticket, $actor);

        try {
            Notification::send($recipients, $notification);
        } catch (Throwable $throwable) {
            $this->logNotificationFailure($throwable, $ticket, $notification);
        }
    }

    private function broadcastMessageCreated(TicketMessage $ticketMessage): void
    {
        try {
            $event = new TicketMessageCreated($ticketMessage);
            $socketId = request()->header('X-Socket-ID');

            if (is_string($socketId) && $socketId !== '' && $socketId !== 'undefined') {
                broadcast($event)->toOthers();
            } else {
                event($event);
            }
        } catch (Throwable $throwable) {
            Log::warning('Ticket message broadcast failed.', [
                'ticket_id' => $ticketMessage->ticket_id,
                'message_id' => $ticketMessage->id,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    private function handleAutomationEventSafely(Ticket $ticket, TicketAutomationTrigger $trigger, array $context): void
    {
        try {
            $this->ticketAutomationEngine->handleEvent($ticket, $trigger, $context);
        } catch (Throwable $throwable) {
            Log::error('Ticket automation event failed.', [
                'ticket_id' => $ticket->id,
                'trigger' => $trigger->value,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    private function logNotificationFailure(Throwable $throwable, Ticket $ticket, object $notification): void
    {
        Log::warning('Ticket notification delivery failed.', [
            'ticket_id' => $ticket->id,
            'notification' => $notification::class,
            'exception' => $throwable::class,
            'message' => $throwable->getMessage(),
        ]);
    }

    private function ticketRecipients(Ticket $ticket, array $exceptUserIds = []): Collection
    {
        $directRecipients = $ticket->isSubelement()
            ? [$ticket->assignee]
            : [$ticket->requester, $ticket->assignee];

        return collect([
            ...$directRecipients,
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
        ])
            ->filter()
            ->reject(fn (User $user) => in_array($user->id, $exceptUserIds, true))
            ->unique('id')
            ->values();
    }

    private function ensureNoOpenSubelements(Ticket $ticket): void
    {
        if ($ticket->isSubelement()) {
            return;
        }

        $openCount = $ticket->subTickets()->open()->count();

        if ($openCount === 0) {
            return;
        }

        throw ValidationException::withMessages([
            'ticketLifecycle' => $openCount === 1
                ? 'Finalize o subelemento aberto antes de encerrar a demanda.'
                : "Finalize os {$openCount} subelementos abertos antes de encerrar a demanda.",
        ]);
    }

    private function ticketWouldBeClosed(Ticket $ticket): bool
    {
        if ($ticket->resolved_at !== null) {
            return true;
        }

        if ($ticket->ticket_group_id && TicketGroup::query()->whereKey($ticket->ticket_group_id)->where('is_closed', true)->exists()) {
            return true;
        }

        if ($ticket->ticket_status_id && TicketStatus::query()->whereKey($ticket->ticket_status_id)->where('is_closed', true)->exists()) {
            return true;
        }

        return false;
    }

    private function updateNotificationFor(Ticket $ticket, array $original, bool $wasClosed): ?TicketUpdateNotification
    {
        if (! $wasClosed && $ticket->isClosed()) {
            return null;
        }

        if ($wasClosed && ! $ticket->isClosed()) {
            return new TicketUpdateNotification(
                $ticket,
                'Chamado reaberto',
                'O chamado '.$ticket->fullReference().' foi reaberto e voltou para atendimento.',
            );
        }

        if (($original['assignee_id'] ?? null) !== $ticket->assignee_id) {
            $message = $ticket->assignee
                ? 'O chamado '.$ticket->fullReference().' agora esta atribuido para '.$ticket->assignee->name.'.'
                : 'O chamado '.$ticket->fullReference().' ficou sem responsavel definido.';

            return new TicketUpdateNotification($ticket, 'Responsavel do chamado atualizado', $message);
        }

        if (($original['ticket_group_id'] ?? null) !== $ticket->ticket_group_id || ($original['ticket_status_id'] ?? null) !== $ticket->ticket_status_id) {
            $target = $ticket->group?->name ?? $ticket->status?->name ?? 'nova etapa';

            return new TicketUpdateNotification(
                $ticket,
                'Etapa do chamado atualizada',
                'O chamado '.$ticket->fullReference().' foi movido para '.$target.'.',
            );
        }

        if (($original['priority'] ?? null) !== $ticket->priority?->value) {
            return new TicketUpdateNotification(
                $ticket,
                'Prioridade do chamado atualizada',
                'O chamado '.$ticket->fullReference().' agora esta com prioridade '.$ticket->priority?->label().'.',
            );
        }

        return null;
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

    private function targetGroup(Ticket $ticket, bool $closed): ?TicketGroup
    {
        if (! $ticket->ticket_board_id) {
            return null;
        }

        return TicketGroup::query()
            ->where('ticket_board_id', $ticket->ticket_board_id)
            ->where('is_active', true)
            ->where('is_closed', $closed)
            ->when(
                ! $closed,
                fn (Builder $query) => $query->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id'),
                fn (Builder $query) => $query->orderBy('sort_order')->orderBy('id'),
            )
            ->first();
    }

    private function targetStatus(Ticket $ticket, bool $closed): ?TicketStatus
    {
        if (! $ticket->ticket_board_id) {
            return null;
        }

        return TicketStatus::query()
            ->where('ticket_board_id', $ticket->ticket_board_id)
            ->where('is_active', true)
            ->where('is_closed', $closed)
            ->when(
                ! $closed,
                fn (Builder $query) => $query->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id'),
                fn (Builder $query) => $query->orderBy('sort_order')->orderBy('id'),
            )
            ->first();
    }

    private function sameGroupId(?int $left, ?int $right): bool
    {
        return $left === $right;
    }

    private function createSystemMessage(Ticket $ticket, string $message): TicketMessage
    {
        /** @var TicketMessage $ticketMessage */
        $ticketMessage = $ticket->messages()->create([
            'user_id' => null,
            'message' => $message,
            'is_system' => true,
        ]);

        $ticketMessage->load(['user', 'attachments']);
        $this->broadcastMessageCreated($ticketMessage);

        return $ticketMessage;
    }

    private function normalizeTimeEntryTimestamps(array $data): array
    {
        $startedAt = data_get($data, 'started_at');
        $endedAt = data_get($data, 'ended_at');

        if (! is_string($startedAt) || ! is_string($endedAt)) {
            throw ValidationException::withMessages([
                'timeTracking' => 'Informe inicio e fim validos para a sessao.',
            ]);
        }

        try {
            $startedAt = Carbon::parse($startedAt);
            $endedAt = Carbon::parse($endedAt);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'timeTracking' => 'Informe inicio e fim validos para a sessao.',
            ]);
        }

        if ($endedAt->lessThanOrEqualTo($startedAt)) {
            throw ValidationException::withMessages([
                'timeTracking' => 'O horario final precisa ser maior que o horario inicial.',
            ]);
        }

        return [
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_seconds' => $this->secondsBetween($startedAt, $endedAt),
        ];
    }

    private function reviewTimeEntry(
        User $actor,
        TicketTimeEntry $timeEntry,
        TicketTimeEntryApprovalStatus $status,
        string $event,
        string $description,
        ?string $note = null,
    ): TicketTimeEntry {
        if ($timeEntry->source !== TicketTimeEntrySource::MANUAL || $timeEntry->isRunning()) {
            throw ValidationException::withMessages([
                'timeTracking' => 'Somente apontamentos manuais encerrados podem ser revisados.',
            ]);
        }

        DB::transaction(function () use ($actor, $timeEntry, $status, $event, $description, $note) {
            $ticket = $timeEntry->ticket()->firstOrFail();

            $timeEntry->forceFill([
                'approval_status' => $status,
                'reviewed_by_id' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => filled($note) ? trim((string) $note) : null,
            ])->save();

            $ticket->updateQuietly(['last_activity_at' => now()]);

            $this->activityLogService->log($actor, $ticket, $event, $description, [
                'time_entry_id' => $timeEntry->id,
                'user_id' => $timeEntry->user_id,
                'approval_status' => $timeEntry->approval_status?->value,
                'reviewed_by_id' => $actor->id,
                'review_note' => $timeEntry->review_note,
                'sector_id' => $ticket->sector_id,
            ]);
        });

        $timeEntry = $timeEntry->fresh(['user', 'ticket']);
        $this->notifyTimeEntryReviewed($timeEntry, $status, [$actor->id]);

        return $timeEntry;
    }

    private function closeOpenTimeEntries(User $actor, Ticket $ticket, CarbonInterface $endedAt): void
    {
        $ticket->timeEntries()
            ->whereNull('ended_at')
            ->get()
            ->each(function (TicketTimeEntry $timeEntry) use ($actor, $endedAt) {
                $this->finalizeTimeEntry(
                    $actor,
                    $timeEntry,
                    $endedAt,
                    'ticket.time_entry.auto_closed',
                    'Sessao de tempo encerrada automaticamente com o fechamento do chamado.',
                );
            });
    }

    private function finalizeTimeEntry(
        User $actor,
        TicketTimeEntry $timeEntry,
        CarbonInterface $endedAt,
        string $event,
        string $description,
    ): void {
        DB::transaction(function () use ($actor, $timeEntry, $endedAt, $event, $description) {
            $ticket = $timeEntry->ticket()->firstOrFail();
            $effectiveEndedAt = $endedAt->lessThan($timeEntry->started_at)
                ? $timeEntry->started_at
                : $endedAt;

            $timeEntry->forceFill([
                'ended_at' => $effectiveEndedAt,
                'duration_seconds' => $this->secondsBetween($timeEntry->started_at, $effectiveEndedAt),
            ])->save();

            if ($event !== 'ticket.time_entry.auto_closed') {
                $ticket->updateQuietly(['last_activity_at' => now()]);
            }

            $this->activityLogService->log($actor, $ticket, $event, $description, [
                'time_entry_id' => $timeEntry->id,
                'user_id' => $timeEntry->user_id,
                'source' => $timeEntry->source?->value,
                'started_at' => $timeEntry->started_at?->toIso8601String(),
                'ended_at' => $timeEntry->ended_at?->toIso8601String(),
                'duration_seconds' => $timeEntry->duration_seconds,
                'sector_id' => $ticket->sector_id,
            ]);
        });
    }

    private function secondsBetween(CarbonInterface $startedAt, CarbonInterface $endedAt): int
    {
        return max(0, $endedAt->getTimestamp() - $startedAt->getTimestamp());
    }
}
