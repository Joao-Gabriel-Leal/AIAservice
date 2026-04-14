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
            self::SECTOR_ADMIN => 'Admin de setor',
            self::TECHNICIAN => 'Tecnico',
            self::REQUESTER => 'Solicitante',
        };
    }
}
