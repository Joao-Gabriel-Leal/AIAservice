<?php

namespace App\Enums;

enum TicketAutomationTrigger: string
{
    case TICKET_CREATED = 'ticket_created';
    case TICKET_UPDATED = 'ticket_updated';
    case TICKET_MESSAGE_CREATED = 'ticket_message_created';
    case TICKET_INACTIVE = 'ticket_inactive';

    public function label(): string
    {
        return match ($this) {
            self::TICKET_CREATED => 'Ticket criado',
            self::TICKET_UPDATED => 'Ticket atualizado',
            self::TICKET_MESSAGE_CREATED => 'Nova mensagem no chamado',
            self::TICKET_INACTIVE => 'Ticket inativo por tempo',
        };
    }
}
