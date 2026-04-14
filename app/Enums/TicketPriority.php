<?php

namespace App\Enums;

enum TicketPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Baixa',
            self::MEDIUM => 'Média',
            self::HIGH => 'Alta',
            self::URGENT => 'Urgente',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::LOW => 'bg-emerald-100 text-emerald-700',
            self::MEDIUM => 'bg-amber-100 text-amber-700',
            self::HIGH => 'bg-orange-100 text-orange-700',
            self::URGENT => 'bg-rose-100 text-rose-700',
        };
    }
}
