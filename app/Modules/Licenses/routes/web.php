<?php

use App\Modules\Licenses\Http\Controllers\LicenseAssignmentController;
use App\Modules\Licenses\Http\Controllers\LicenseController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('licenses', [LicenseController::class, 'index'])->name('licenses.index');
    Route::get('licenses/export', [LicenseController::class, 'export'])->name('licenses.export');
    Route::get('licenses/create', [LicenseController::class, 'create'])->name('licenses.create');
    Route::post('licenses', [LicenseController::class, 'store'])->name('licenses.store');
    Route::get('licenses/{license}', [LicenseController::class, 'show'])->name('licenses.show');
    Route::get('licenses/{license}/edit', [LicenseController::class, 'edit'])->name('licenses.edit');
    Route::put('licenses/{license}', [LicenseController::class, 'update'])->name('licenses.update');

    Route::post('licenses/{license}/assignments', [LicenseAssignmentController::class, 'store'])->name('licenses.assignments.store');
    Route::put('licenses/{license}/assignments/{assignment}', [LicenseAssignmentController::class, 'update'])->name('licenses.assignments.update');
    Route::post('licenses/{license}/assignments/{assignment}/transfer', [LicenseAssignmentController::class, 'transfer'])->name('licenses.assignments.transfer');
    Route::post('licenses/{license}/assignments/{assignment}/release', [LicenseAssignmentController::class, 'release'])->name('licenses.assignments.release');
});
