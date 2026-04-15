<?php

namespace App\Enums;

enum TicketTimeEntrySource: string
{
    case TIMER = 'timer';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::TIMER => 'Cronometro',
            self::MANUAL => 'Manual',
        };
    }
}
