<?php

namespace App\Enums;

enum KnowledgeBaseArticleStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Rascunho',
            self::PUBLISHED => 'Publicado',
        };
    }
}
