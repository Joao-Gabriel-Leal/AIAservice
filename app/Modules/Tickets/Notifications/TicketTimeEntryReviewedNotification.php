<?php

namespace App\Modules\Tickets\Notifications;

use App\Modules\Tickets\Models\Ticket;

class TicketTimeEntryReviewedNotification extends BaseTicketNotification
{
    public function __construct(Ticket $ticket, string $statusLabel)
    {
        parent::__construct(
            $ticket,
            'Apontamento manual revisado',
            "O seu apontamento manual no chamado #{$ticket->id} foi {$statusLabel}.",
        );
    }

    protected function actionLabel(): string
    {
        return 'Ver chamado';
    }
}
