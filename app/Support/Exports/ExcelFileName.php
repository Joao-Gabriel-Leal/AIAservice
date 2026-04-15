<?php

namespace App\Support\Exports;

use Illuminate\Support\Str;

class ExcelFileName
{
    public static function make(string $label): string
    {
        $slug = Str::slug($label);

        return sprintf('%s-%s.xlsx', $slug, now()->format('Y-m-d-Hi'));
    }
}
