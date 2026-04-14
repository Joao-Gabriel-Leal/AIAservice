<?php

namespace App\Modules\Tickets\Services;

use App\Models\User;
use App\Modules\Shared\Services\ActivityLogService;
use App\Modules\Tickets\Events\TicketMessageCreated;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketAttachment;
use App\Modules\Tickets\Models\TicketField;
use App\Modules\Tickets\Models\TicketFieldValue;
use App\Modules\Tickets\Models\TicketMessage;
use App\Modules\Tickets\Models\TicketStatus;
use App\Modules\Tickets\Notifications\TicketActivityNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class TicketWorkflowService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    public function createTicket(User $actor, array $attributes, array $dynamicValues = [], array $attachments = []): Ticket
    {
        /** @var Ticket $ticket */
        $ticket = Ticket::query()->create([
            ...$attributes,
            'requester_id' => $attributes['requester_id'] ?? $actor->id,
            'last_activity_at' => now(),
        ]);

        $this->syncDynamicFields($ticket, $dynamicValues);
        $this->storeAttachments($ticket, $actor, $attachments);
        $this->activityLogService->log($actor, $ticket, 'ticket.created', 'Chamado criado.', [
            'sector_id' => $ticket->sector_id,
        ]);
        $this->notifyUsers($ticket, 'Novo chamado criado', "O chamado #{$ticket->id} foi criado.");

        return $ticket->fresh(['fieldValues', 'attachments']);
    }

    public function updateTicket(User $actor, Ticket $ticket, array $attributes): Ticket
    {
        $ticket->fill($attributes);

        if (array_key_exists('ticket_status_id', $attributes)) {
            $status = TicketStatus::query()->find($attributes['ticket_status_id']);
            $ticket->resolved_at = $status?->is_closed ? now() : null;
        }

        $ticket->last_activity_at = now();
        $ticket->save();

        $this->activityLogService->log($actor, $ticket, 'ticket.updated', 'Chamado atualizado.', [
            'changes' => $attributes,
            'sector_id' => $ticket->sector_id,
        ]);
        $this->notifyUsers($ticket, 'Chamado atualizado', "O chamado #{$ticket->id} recebeu uma atualização.");

        return $ticket->fresh();
    }

    public function updateField(User $actor, Ticket $ticket, TicketField $field, mixed $value): TicketFieldValue
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

        return $fieldValue;
    }

    public function addMessage(User $actor, Ticket $ticket, string $message): TicketMessage
    {
        $ticketMessage = $ticket->messages()->create([
            'user_id' => $actor->id,
            'message' => $message,
            'is_system' => false,
        ]);

        $ticket->updateQuietly(['last_activity_at' => now()]);

        $this->activityLogService->log($actor, $ticket, 'ticket.message.created', 'Nova mensagem no chat.', [
            'message_id' => $ticketMessage->id,
            'sector_id' => $ticket->sector_id,
        ]);
        $this->notifyUsers($ticket, 'Nova mensagem no chamado', "Há uma nova mensagem no chamado #{$ticket->id}.");

        $ticketMessage->load('user');
        broadcast(new TicketMessageCreated($ticketMessage))->toOthers();

        return $ticketMessage;
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
                ->where('sector_id', $ticket->sector_id)
                ->whereIn('role', ['super_admin', 'sector_admin'])
                ->get()
                ->all(),
        ])->filter()->unique('id');

        Notification::send($recipients, new TicketActivityNotification($ticket, $title, $message));
    }
}
