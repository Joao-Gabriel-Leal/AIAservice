<?php

namespace App\Enums;

enum TicketFieldType: string
{
    case TEXT = 'text';
    case NUMBER = 'number';
    case DATE = 'date';
    case STATUS = 'status';
    case USER = 'user';
    case SELECT = 'select';
    case CHECKBOX = 'checkbox';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Texto',
            self::NUMBER => 'Número',
            self::DATE => 'Data',
            self::STATUS => 'Status',
            self::USER => 'Usuário',
            self::SELECT => 'Seleção',
            self::CHECKBOX => 'Checkbox',
        };
    }
}
