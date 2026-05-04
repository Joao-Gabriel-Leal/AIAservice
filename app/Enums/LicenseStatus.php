<?php

namespace App\Enums;

enum LicenseStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case CANCELED = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Ativa',
            self::SUSPENDED => 'Suspensa',
            self::CANCELED => 'Cancelada',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::ACTIVE => 'bg-emerald-100 text-emerald-700',
            self::SUSPENDED => 'bg-amber-100 text-amber-700',
            self::CANCELED => 'bg-slate-200 text-slate-700',
        };
    }
}
