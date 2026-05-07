<?php

namespace App\Enums;

enum GlobalUserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case DEV = 'dev';
    case COLLABORATOR = 'collaborator';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::DEV => 'Dev',
            self::COLLABORATOR => 'Colaborador',
        };
    }
}
