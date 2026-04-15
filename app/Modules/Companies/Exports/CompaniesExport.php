<?php

namespace App\Modules\Companies\Exports;

use App\Support\Exports\ExcelFileName;
use App\Support\Exports\ExcelSheetData;
use Illuminate\Support\Collection;

class CompaniesExport
{
    public function __construct(
        private readonly Collection $companies,
    ) {
    }

    public function fileName(): string
    {
        return ExcelFileName::make('empresas');
    }

    /**
     * @return array<int, ExcelSheetData>
     */
    public function sheets(): array
    {
        return [
            new ExcelSheetData(
                'Empresas',
                ['Nome', 'Razao social', 'Documento', 'Email', 'Telefone', 'Status'],
                $this->companies->map(fn ($company) => [
                    $company->name,
                    $company->legal_name ?? '',
                    $company->document ?? '',
                    $company->email ?? '',
                    $company->phone ?? '',
                    $company->is_active ? 'Ativa' : 'Inativa',
                ]),
            ),
        ];
    }
}
