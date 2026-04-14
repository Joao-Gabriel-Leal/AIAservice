<?php

use App\Modules\Auth\Http\Controllers\DashboardController;
use App\Modules\Auth\Http\Controllers\NotificationsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{notification}/open', [NotificationsController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/{notification}/read', [NotificationsController::class, 'markAsRead'])->name('notifications.mark-read');
});
