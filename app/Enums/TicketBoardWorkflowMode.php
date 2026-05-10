<?php

namespace App\Enums;

enum TicketBoardWorkflowMode: string
{
    case SERVICE = 'service';
    case DEVELOPMENT = 'development';

    public function label(): string
    {
        return match ($this) {
            self::SERVICE => 'Servico/Suporte',
            self::DEVELOPMENT => 'Desenvolvimento',
        };
    }
}
