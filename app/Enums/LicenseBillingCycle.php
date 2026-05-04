<?php

namespace App\Enums;

enum LicenseBillingCycle: string
{
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case ANNUAL = 'annual';
    case ONE_TIME = 'one_time';

    public function label(): string
    {
        return match ($this) {
            self::MONTHLY => 'Mensal',
            self::QUARTERLY => 'Trimestral',
            self::ANNUAL => 'Anual',
            self::ONE_TIME => 'Pontual',
        };
    }
}
