<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

trait InteractsWithExcelDownloads
{
    protected function spreadsheetFromResponse(TestResponse $response): Spreadsheet
    {
        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        /** @var \Symfony\Component\HttpFoundation\BinaryFileResponse $binaryResponse */
        $binaryResponse = $response->baseResponse;

        return IOFactory::load($binaryResponse->getFile()->getPathname());
    }

    protected function sheetValues(Worksheet $sheet): array
    {
        $rows = [];

        foreach ($sheet->toArray(null, true, true, false) as $row) {
            $rows[] = implode(' | ', array_map(fn ($value) => (string) $value, $row));
        }

        return $rows;
    }
}
