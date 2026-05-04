<?php

namespace App\Enums;

enum SectorAccessLevel: string
{
    case SECTOR_ADMIN = 'sector_admin';
    case TECHNICIAN = 'technician';
    case REQUESTER = 'requester';

    public function label(): string
    {
        return match ($this) {
            self::SECTOR_ADMIN => 'Gestor',
            self::TECHNICIAN => 'Operador',
            self::REQUESTER => 'Solicitante',
        };
    }
}
