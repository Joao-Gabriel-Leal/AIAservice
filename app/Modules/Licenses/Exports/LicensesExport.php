<?php

namespace App\Modules\Licenses\Exports;

use App\Support\Exports\ExcelFileName;
use App\Support\Exports\ExcelSheetData;
use Illuminate\Support\Collection;

class LicensesExport
{
    public function __construct(
        private readonly Collection $licenses,
    ) {}

    public function fileName(): string
    {
        return ExcelFileName::make('licencas');
    }

    /**
     * @return array<int, ExcelSheetData>
     */
    public function sheets(): array
    {
        return [
            new ExcelSheetData(
                'Licencas',
                [
                    'Fornecedor',
                    'Produto',
                    'Plano',
                    'Setor',
                    'Status',
                    'Licencas totais',
                    'Licencas em uso',
                    'Licencas disponiveis',
                    'Renovacao',
                    'Expiracao',
                    'Custo',
                    'Moeda',
                    'Referencia',
                    'Fornecedor da compra',
                ],
                $this->licenses->map(fn ($license) => [
                    $license->vendor_name,
                    $license->product_name,
                    $license->plan_name ?? '',
                    $license->sector?->name ?? 'Sem setor',
                    $license->status?->label() ?? '',
                    $license->seats_total,
                    $license->seatsInUse(),
                    $license->seatsAvailable(),
                    $license->renewal_date?->format('d/m/Y') ?? '',
                    $license->expires_at?->format('d/m/Y') ?? '',
                    $license->cost_amount !== null ? number_format((float) $license->cost_amount, 2, ',', '.') : '',
                    $license->cost_currency ?? '',
                    $license->license_reference ?? '',
                    $license->supplier_name ?? '',
                ]),
            ),
        ];
    }
}
