<?php

namespace App\Modules\Assets\Exports;

use App\Support\Exports\ExcelFileName;
use App\Support\Exports\ExcelSheetData;
use Illuminate\Support\Collection;

class AssetsExport
{
    public function __construct(
        private readonly Collection $assets,
    ) {
    }

    public function fileName(): string
    {
        return ExcelFileName::make('patrimonios');
    }

    /**
     * @return array<int, ExcelSheetData>
     */
    public function sheets(): array
    {
        return [
            new ExcelSheetData(
                'Patrimonios',
                ['Codigo', 'Nome', 'Serial', 'Status', 'Estado operacional', 'Setor atual', 'Sala atual', 'Colaborador'],
                $this->assets->map(fn ($asset) => [
                    $asset->asset_code,
                    $asset->name,
                    $asset->serial_number ?? '',
                    $asset->statusLabel(),
                    $asset->operationalStateLabel(),
                    $asset->currentSector?->name ?? 'Sem setor',
                    $asset->currentRoom?->name ?? 'Sem sala',
                    $asset->currentUser?->name ?? 'Nao vinculado',
                ]),
            ),
        ];
    }
}
