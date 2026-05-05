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
use App\Modules\Tickets\Notifications\TicketManualTimeEntryPendingApprovalNotification;
use App\Modules\Tickets\Notifications\TicketMessageNotification;
use App\Modules\Tickets\Notifications\TicketRatingRequestNotification;
use App\Modules\Tickets\Notifications\TicketTimeEntryReviewedNotification;
use App\Modules\Tickets\Notifications\TicketUpdateNotification;
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
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly TicketSlaService $ticketSlaService,
        private readonly TicketAutomationEngine $ticketAutomationEngine,
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
            new TicketCreatedNotification($ticket, 'Novo chamado criado', "O chamado #{$ticket->id} foi criado."),
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

        $ticket->last_activity_at = now();
        $ticket->save();

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
        $this->notifyUsers(
            $ticket,
            new TicketMessageNotification($ticket, 'Nova mensagem no chamado', "Ha uma nova mensagem no chamado #{$ticket->id}."),
            [$actor->id],
        );

        $ticketMessage->load('user');

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

    public function storeAttachments(Ticket $ticket, User $actor, array $attachments): Collection
    {
        return collect($attachments)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->map(function (UploadedFile $file) use ($ticket, $actor) {
                $content = file_get_contents($file->getRealPath());

                if ($content === false) {
                    throw new \RuntimeException("Nao foi possivel ler o anexo {$file->getClientOriginalName()}.");
                }

                $extension = $file->extension() ?: $file->getClientOriginalExtension() ?: 'bin';
                $path = "tickets/{$ticket->id}/".Str::uuid().".{$extension}";
                $attachment = new TicketAttachment;

                return TicketAttachment::query()->create([
                    'ticket_id' => $ticket->id,
                    'uploaded_by_id' => $actor->id,
                    'disk' => 'database',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
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
                    ->where('global_role', 'super_admin')
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
        if (! $ticket->requester) {
            return;
        }

        $notification = new TicketRatingRequestNotification(
            $ticket,
            'Chamado encerrado',
            "O chamado #{$ticket->id} foi encerrado. Avalie o atendimento quando puder.",
        );

        try {
            $ticket->requester->notify($notification);
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
        return collect([
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
        ])
            ->filter()
            ->reject(fn (User $user) => in_array($user->id, $exceptUserIds, true))
            ->unique('id')
            ->values();
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
                "O chamado #{$ticket->id} foi reaberto e voltou para atendimento.",
            );
        }

        if (($original['assignee_id'] ?? null) !== $ticket->assignee_id) {
            $message = $ticket->assignee
                ? "O chamado #{$ticket->id} agora esta atribuido para {$ticket->assignee->name}."
                : "O chamado #{$ticket->id} ficou sem responsavel definido.";

            return new TicketUpdateNotification($ticket, 'Responsavel do chamado atualizado', $message);
        }

        if (($original['ticket_group_id'] ?? null) !== $ticket->ticket_group_id || ($original['ticket_status_id'] ?? null) !== $ticket->ticket_status_id) {
            $target = $ticket->group?->name ?? $ticket->status?->name ?? 'nova etapa';

            return new TicketUpdateNotification(
                $ticket,
                'Etapa do chamado atualizada',
                "O chamado #{$ticket->id} foi movido para {$target}.",
            );
        }

        if (($original['priority'] ?? null) !== $ticket->priority?->value) {
            return new TicketUpdateNotification(
                $ticket,
                'Prioridade do chamado atualizada',
                "O chamado #{$ticket->id} agora esta com prioridade {$ticket->priority?->label()}.",
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
