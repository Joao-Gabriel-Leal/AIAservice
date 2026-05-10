<?php

use App\Modules\Emails\Http\Controllers\EmailTemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:dev'])
    ->prefix('admin/emails')
    ->name('admin.emails.')
    ->group(function (): void {
        Route::get('/', [EmailTemplateController::class, 'index'])->name('index');
        Route::patch('/{type}', [EmailTemplateController::class, 'update'])->name('update');
        Route::delete('/{type}', [EmailTemplateController::class, 'restore'])->name('restore');
        Route::post('/{type}/preview', [EmailTemplateController::class, 'preview'])->name('preview');
        Route::post('/{type}/test', [EmailTemplateController::class, 'sendTest'])->name('test');
    });
