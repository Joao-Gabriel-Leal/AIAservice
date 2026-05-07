<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case DEV = 'dev';
    case SECTOR_ADMIN = 'sector_admin';
    case TECHNICIAN = 'technician';
    case REQUESTER = 'requester';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::DEV => 'Dev',
            self::SECTOR_ADMIN => 'Gestor',
            self::TECHNICIAN => 'Operador',
            self::REQUESTER => 'Solicitante',
        };
    }
}
