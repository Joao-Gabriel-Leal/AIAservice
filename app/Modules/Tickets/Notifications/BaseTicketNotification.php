<?php

namespace App\Modules\Tickets\Notifications;

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
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line($this->message)
            ->action($this->actionLabel(), route('tickets.show', $this->ticket));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
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
}
