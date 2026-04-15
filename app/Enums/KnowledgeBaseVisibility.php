<?php

namespace App\Enums;

enum KnowledgeBaseVisibility: string
{
    case PUBLIC = 'public';
    case PRIVATE = 'private';

    public function label(): string
    {
        return match ($this) {
            self::PUBLIC => 'Publico',
            self::PRIVATE => 'Privado',
        };
    }
}
