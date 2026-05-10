<?php

namespace App\Modules\Tickets\Notifications;

use App\Modules\Tickets\Models\Ticket;

class TicketTimeEntryReviewedNotification extends BaseTicketNotification
{
    private readonly string $statusLabel;

    public function __construct(Ticket $ticket, string $statusLabel)
    {
        $this->statusLabel = $statusLabel;

        parent::__construct(
            $ticket,
            'Apontamento manual revisado',
            'O seu apontamento manual no chamado '.$ticket->fullReference().' foi '.$statusLabel.'.',
        );
    }

    protected function actionLabel(): string
    {
        return 'Ver chamado';
    }

    protected function emailContext(object $notifiable): array
    {
        return [
            ...parent::emailContext($notifiable),
            'time_entry_status' => $this->statusLabel,
        ];
    }
}
