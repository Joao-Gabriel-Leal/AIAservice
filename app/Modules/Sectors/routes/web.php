<?php

use App\Modules\Sectors\Http\Controllers\SectorController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('sectors/export', [SectorController::class, 'export'])->name('sectors.export');
    Route::resource('sectors', SectorController::class)->except(['show']);
});
