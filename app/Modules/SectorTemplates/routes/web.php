<?php

use App\Modules\SectorTemplates\Http\Controllers\SectorTemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::resource('sector-templates', SectorTemplateController::class)->except(['show']);
});
