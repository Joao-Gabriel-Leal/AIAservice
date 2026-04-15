<?php

namespace App\Enums;

enum AssetMovementType: string
{
    case CADASTRO_INICIAL = 'cadastro_inicial';
    case TRANSFERENCIA_LOCAL = 'transferencia_local';
    case ATRIBUICAO_COLABORADOR = 'atribuicao_colaborador';
    case DEVOLUCAO_COLABORADOR = 'devolucao_colaborador';
    case MUDANCA_STATUS = 'mudanca_status';

    public function label(): string
    {
        return match ($this) {
            self::CADASTRO_INICIAL => 'Cadastro inicial',
            self::TRANSFERENCIA_LOCAL => 'Transferencia local',
            self::ATRIBUICAO_COLABORADOR => 'Atribuicao a colaborador',
            self::DEVOLUCAO_COLABORADOR => 'Devolucao de colaborador',
            self::MUDANCA_STATUS => 'Mudanca de status',
        };
    }
}
