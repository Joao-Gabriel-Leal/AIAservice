<?php

namespace App\Enums;

enum TicketAutomationConditionOperator: string
{
    case IN = 'in';
    case NOT_IN = 'not_in';
    case EQUALS = 'equals';
    case NOT_EQUALS = 'not_equals';
    case IS_TRUE = 'is_true';
    case IS_FALSE = 'is_false';

    public function label(): string
    {
        return match ($this) {
            self::IN => 'Esta em',
            self::NOT_IN => 'Nao esta em',
            self::EQUALS => 'Igual a',
            self::NOT_EQUALS => 'Diferente de',
            self::IS_TRUE => 'E verdadeiro',
            self::IS_FALSE => 'E falso',
        };
    }
}
