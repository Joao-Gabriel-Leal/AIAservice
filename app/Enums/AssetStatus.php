<?php

namespace App\Enums;

enum AssetStatus: string
{
    case DISPONIVEL = 'disponivel';
    case EM_USO = 'em_uso';
    case MANUTENCAO = 'manutencao';
    case EXTRAVIADO = 'extraviado';
    case BAIXADO = 'baixado';

    public function label(): string
    {
        return match ($this) {
            self::DISPONIVEL => 'Disponivel',
            self::EM_USO => 'Em uso',
            self::MANUTENCAO => 'Manutencao',
            self::EXTRAVIADO => 'Extraviado',
            self::BAIXADO => 'Baixado',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::DISPONIVEL => 'bg-emerald-100 text-emerald-700',
            self::EM_USO => 'bg-sky-100 text-sky-700',
            self::MANUTENCAO => 'bg-amber-100 text-amber-700',
            self::EXTRAVIADO => 'bg-rose-100 text-rose-700',
            self::BAIXADO => 'bg-slate-200 text-slate-700',
        };
    }
}
