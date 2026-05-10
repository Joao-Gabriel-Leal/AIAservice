<?php

namespace App\Enums;

enum TicketSprintStatus: string
{
    case PLANNED = 'planned';
    case ACTIVE = 'active';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'Planejada',
            self::ACTIVE => 'Ativa',
            self::CLOSED => 'Fechada',
        };
    }
}
