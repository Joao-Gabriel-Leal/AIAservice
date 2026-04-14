<?php

namespace App\Enums;

enum TicketAutomationActionType: string
{
    case ASSIGN_FIXED_ASSIGNEE = 'assign_fixed_assignee';
    case CHANGE_STATUS = 'change_status';
    case CHANGE_GROUP = 'change_group';
    case CHANGE_PRIORITY = 'change_priority';
    case ADD_SYSTEM_MESSAGE = 'add_system_message';
    case SEND_NOTIFICATION = 'send_notification';
    case REOPEN_TICKET = 'reopen_ticket';

    public function label(): string
    {
        return match ($this) {
            self::ASSIGN_FIXED_ASSIGNEE => 'Atribuir responsavel fixo',
            self::CHANGE_STATUS => 'Mudar status',
            self::CHANGE_GROUP => 'Mudar grupo',
            self::CHANGE_PRIORITY => 'Mudar prioridade',
            self::ADD_SYSTEM_MESSAGE => 'Registrar mensagem automatica',
            self::SEND_NOTIFICATION => 'Enviar notificacao',
            self::REOPEN_TICKET => 'Reabrir chamado',
        };
    }
}
