<?php

namespace App\Modules\Tickets\Notifications;

use App\Models\User;
use App\Modules\Tickets\Models\Ticket;

class TicketInternalUpdateNotification extends BaseTicketNotification
{
    public function __construct(Ticket $ticket, User $actor)
    {
        parent::__construct(
            $ticket,
            'Atualizacao interna no chamado',
            $actor->name.' marcou voce em uma atualizacao interna do chamado '.$ticket->fullReference().'.',
        );
    }
}
