<?php

namespace App\Support\Exports;

class ExcelSheetData
{
    /**
     * @param  array<int, string>  $headings
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public function __construct(
        public readonly string $title,
        public readonly array $headings,
        public readonly iterable $rows,
    ) {
    }
}
