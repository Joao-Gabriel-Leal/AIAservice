<?php

namespace App\Enums;

enum TicketWorkItemType: string
{
    case REQUEST = 'request';
    case BUG = 'bug';
    case TASK = 'task';
    case STORY = 'story';
    case IMPROVEMENT = 'improvement';

    public function label(): string
    {
        return match ($this) {
            self::REQUEST => 'Solicitacao',
            self::BUG => 'Bug',
            self::TASK => 'Tarefa',
            self::STORY => 'Historia',
            self::IMPROVEMENT => 'Melhoria',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::BUG => 'bg-rose-100 text-rose-700',
            self::TASK => 'bg-sky-100 text-sky-700',
            self::STORY => 'bg-violet-100 text-violet-700',
            self::IMPROVEMENT => 'bg-emerald-100 text-emerald-700',
            self::REQUEST => 'bg-slate-100 text-slate-700',
        };
    }
}
