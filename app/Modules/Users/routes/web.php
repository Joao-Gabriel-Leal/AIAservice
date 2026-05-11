<?php

use App\Modules\Users\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('users/export', [UserController::class, 'export'])->name('users.export');
    Route::post('users/{user}/reset-default-password', [UserController::class, 'resetDefaultPassword'])->name('users.reset-default-password');
    Route::resource('users', UserController::class);
});
