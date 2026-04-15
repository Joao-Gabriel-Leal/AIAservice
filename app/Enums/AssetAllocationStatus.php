<?php

namespace App\Enums;

enum AssetAllocationStatus: string
{
    case ALLOCATED = 'allocated';
    case PENDING_REVIEW = 'pending_review';

    public function label(): string
    {
        return match ($this) {
            self::ALLOCATED => 'Alocado',
            self::PENDING_REVIEW => 'Pendente de saneamento',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::ALLOCATED => 'bg-emerald-100 text-emerald-700',
            self::PENDING_REVIEW => 'bg-amber-100 text-amber-800',
        };
    }
}
