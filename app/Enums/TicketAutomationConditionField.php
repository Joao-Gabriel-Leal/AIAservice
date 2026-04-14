<?php

namespace App\Enums;

enum TicketAutomationConditionField: string
{
    case PRIORITY = 'priority';
    case STATUS_ID = 'status_id';
    case GROUP_ID = 'group_id';
    case HAS_ASSIGNEE = 'has_assignee';
    case IS_CLOSED = 'is_closed';

    public function label(): string
    {
        return match ($this) {
            self::PRIORITY => 'Prioridade',
            self::STATUS_ID => 'Status atual (legado)',
            self::GROUP_ID => 'Etapa atual',
            self::HAS_ASSIGNEE => 'Tem responsavel',
            self::IS_CLOSED => 'Chamado fechado',
        };
    }
}
