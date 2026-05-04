<?php

namespace App\Enums;

enum LicenseAssignmentStatus: string
{
    case ACTIVE = 'active';
    case RELEASED = 'released';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Em uso',
            self::RELEASED => 'Desatribuida',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::ACTIVE => 'bg-emerald-100 text-emerald-700',
            self::RELEASED => 'bg-slate-200 text-slate-700',
        };
    }
}
