<?php

namespace App\Enums;

use App\Models\User;

enum TicketFormOpeningAccessLevel: string
{
    case PUBLIC = 'public';
    case OPERATOR = 'operator';
    case MANAGER = 'manager';

    public function label(): string
    {
        return match ($this) {
            self::PUBLIC => 'Publico',
            self::OPERATOR => 'Operador',
            self::MANAGER => 'Gestor',
        };
    }

    public function allows(User $user, int $sectorId): bool
    {
        return match ($this) {
            self::PUBLIC => true,
            self::OPERATOR => $user->hasOperationalAccess($sectorId),
            self::MANAGER => $user->isSectorAdmin($sectorId),
        };
    }
}
