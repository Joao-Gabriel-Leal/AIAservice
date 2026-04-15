<?php

namespace App\Modules\Sectors\Exports;

use App\Support\Exports\ExcelFileName;
use App\Support\Exports\ExcelSheetData;
use Illuminate\Support\Collection;

class SectorsExport
{
    public function __construct(
        private readonly Collection $sectors,
    ) {
    }

    public function fileName(): string
    {
        return ExcelFileName::make('setores');
    }

    /**
     * @return array<int, ExcelSheetData>
     */
    public function sheets(): array
    {
        return [
            new ExcelSheetData(
                'Setores',
                ['Setor', 'Descricao', 'Empresa', 'Status'],
                $this->sectors->map(fn ($sector) => [
                    $sector->name,
                    $sector->description ?? '',
                    $sector->company?->name ?? '',
                    $sector->is_active ? 'Ativo' : 'Inativo',
                ]),
            ),
        ];
    }
}
