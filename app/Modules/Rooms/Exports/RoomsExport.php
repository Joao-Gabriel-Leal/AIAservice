<?php

namespace App\Modules\Rooms\Exports;

use App\Support\Exports\ExcelFileName;
use App\Support\Exports\ExcelSheetData;
use Illuminate\Support\Collection;

class RoomsExport
{
    public function __construct(
        private readonly Collection $rooms,
    ) {
    }

    public function fileName(): string
    {
        return ExcelFileName::make('salas');
    }

    /**
     * @return array<int, ExcelSheetData>
     */
    public function sheets(): array
    {
        return [
            new ExcelSheetData(
                'Salas',
                ['Sala', 'Descricao', 'Setor', 'Empresa', 'Status'],
                $this->rooms->map(fn ($room) => [
                    $room->name,
                    $room->description ?? '',
                    $room->sector?->name ?? '',
                    $room->sector?->company?->name ?? '',
                    $room->is_active ? 'Ativa' : 'Inativa',
                ]),
            ),
        ];
    }
}
