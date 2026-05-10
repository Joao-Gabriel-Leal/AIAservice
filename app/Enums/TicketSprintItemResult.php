<?php

namespace App\Enums;

enum TicketSprintItemResult: string
{
    case COMPLETED = 'completed';
    case CARRIED_OVER = 'carried_over';
    case REMOVED = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::COMPLETED => 'Concluido',
            self::CARRIED_OVER => 'Carregado',
            self::REMOVED => 'Removido',
        };
    }
}
