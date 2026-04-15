<?php

namespace App\Support\Exports;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SpreadsheetExporter
{
    /**
     * @param  array<int, ExcelSheetData>  $sheets
     *
     * @throws FileNotFoundException
     */
    public function download(string $fileName, array $sheets): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ($sheets as $index => $sheetData) {
            $sheet = $spreadsheet->createSheet($index);
            $sheet->setTitle($this->sanitizeTitle($sheetData->title));

            $column = 1;
            foreach ($sheetData->headings as $heading) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($column).'1', $heading);
                $column++;
            }

            $rowNumber = 2;
            foreach ($sheetData->rows as $row) {
                $column = 1;

                foreach ($row as $value) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($column).$rowNumber, $value);
                    $column++;
                }

                $rowNumber++;
            }

            foreach (range(1, max(count($sheetData->headings), 1)) as $columnIndex) {
                $sheet->getColumnDimensionByColumn($columnIndex)->setAutoSize(true);
            }

            $sheet->freezePane('A2');
        }

        $spreadsheet->setActiveSheetIndex(0);

        $directory = storage_path('app/exports');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.'/'.uniqid('export_', true).'.xlsx';

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()->download($path, $fileName)->deleteFileAfterSend(true);
    }

    private function sanitizeTitle(string $title): string
    {
        $normalized = trim(preg_replace('/[\\\\\\/?*:\\[\\]]/', '-', $title) ?? $title);

        return mb_substr($normalized !== '' ? $normalized : 'Planilha', 0, 31);
    }
}
