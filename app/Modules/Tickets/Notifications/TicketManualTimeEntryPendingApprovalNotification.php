<?php

namespace App\Modules\Tickets\Notifications;

use App\Modules\Tickets\Models\Ticket;

class TicketManualTimeEntryPendingApprovalNotification extends BaseTicketNotification
{
    public function __construct(Ticket $ticket)
    {
        parent::__construct(
            $ticket,
            'Apontamento manual pendente',
            'O chamado '.$ticket->fullReference().' recebeu um apontamento manual aguardando aprovacao.',
        );
    }

    protected function actionLabel(): string
    {
        return 'Revisar apontamento';
    }
}
