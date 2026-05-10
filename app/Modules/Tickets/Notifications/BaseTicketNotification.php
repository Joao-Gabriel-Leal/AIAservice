<?php

namespace App\Modules\Tickets\Notifications;

use App\Modules\Emails\Services\EmailTemplateService;
use App\Modules\Emails\Support\EmailTemplateCatalog;
use App\Modules\Tickets\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

abstract class BaseTicketNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected readonly Ticket $ticket,
        protected readonly string $title,
        protected readonly string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return app(EmailTemplateService::class)->channelsFor($this->emailType());
    }

    public function toMail(object $notifiable): MailMessage
    {
        return app(EmailTemplateService::class)->mailMessage(
            $this->emailType(),
            $notifiable,
            $this->emailContext($notifiable),
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_reference_code' => $this->ticket->publicReference(),
            'title' => $this->title,
            'message' => $this->message,
            'url' => route('tickets.show', $this->ticket, absolute: false),
            'type' => static::class,
        ];
    }

    protected function actionLabel(): string
    {
        return 'Abrir chamado';
    }

    protected function emailType(): string
    {
        return match (static::class) {
            TicketCreatedNotification::class => EmailTemplateCatalog::TICKET_CREATED,
            TicketUpdateNotification::class => EmailTemplateCatalog::TICKET_UPDATED,
            TicketMessageNotification::class => EmailTemplateCatalog::TICKET_MESSAGE_CREATED,
            TicketInternalUpdateNotification::class => EmailTemplateCatalog::TICKET_INTERNAL_MENTION,
            TicketSlaNotification::class => EmailTemplateCatalog::TICKET_SLA_ALERT,
            TicketRatingRequestNotification::class => EmailTemplateCatalog::TICKET_RATING_REQUEST,
            TicketManualTimeEntryPendingApprovalNotification::class => EmailTemplateCatalog::TICKET_MANUAL_TIME_ENTRY_PENDING,
            TicketTimeEntryReviewedNotification::class => EmailTemplateCatalog::TICKET_TIME_ENTRY_REVIEWED,
            default => EmailTemplateCatalog::TICKET_ACTIVITY,
        };
    }

    protected function emailContext(object $notifiable): array
    {
        $ticket = $this->ticket->loadMissing(['assignee', 'group', 'requester', 'sector', 'status']);

        return [
            'action_url' => route('tickets.show', $ticket),
            'action_label' => $this->actionLabel(),
            'notification_title' => $this->title,
            'notification_message' => $this->message,
            'ticket_reference' => $ticket->publicReference(),
            'ticket_title' => $ticket->title,
            'ticket_priority' => $ticket->priority?->label() ?? 'Sem prioridade',
            'ticket_status' => $ticket->group?->name ?? $ticket->status?->name ?? 'Sem status',
            'ticket_sector' => $ticket->sector?->name ?? 'Sem setor',
            'requester_name' => $ticket->requester?->name ?? 'Sem solicitante',
            'assignee_name' => $ticket->assignee?->name ?? 'Nao atribuido',
        ];
    }
}
