<?php

use App\Modules\Audit\Http\Controllers\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:dev'])->group(function () {
    Route::get('/admin/logs', ActivityLogController::class)->name('admin.logs.index');
});
