<?php

use App\Modules\Companies\Http\Controllers\CompanyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:super_admin'])->group(function () {
    Route::get('companies/export', [CompanyController::class, 'export'])->name('companies.export');
    Route::resource('companies', CompanyController::class)->except(['show']);
});
