<?php

namespace App\Modules\Users\Exports;

use App\Support\Exports\ExcelFileName;
use App\Support\Exports\ExcelSheetData;
use Illuminate\Support\Collection;

class UsersExport
{
    public function __construct(
        private readonly Collection $users,
    ) {
    }

    public function fileName(): string
    {
        return ExcelFileName::make('usuarios');
    }

    /**
     * @return array<int, ExcelSheetData>
     */
    public function sheets(): array
    {
        return [
            new ExcelSheetData(
                'Usuarios',
                ['Nome', 'Email', 'Perfil global', 'Acessos por setor', 'Status', 'Troca de senha pendente'],
                $this->users->map(fn ($user) => [
                    $user->name,
                    $user->email,
                    $user->global_role->label(),
                    $user->sectorAccesses->map(
                        fn ($access) => ($access->sector?->name ?? 'Setor removido').': '.$access->access_level->label(),
                    )->implode(' | '),
                    $user->is_active ? 'Ativo' : 'Inativo',
                    $user->must_change_password ? 'Sim' : 'Nao',
                ]),
            ),
        ];
    }
}
