<?php

use App\Http\Controllers\UserProfilePhotoController;
use App\Http\Controllers\CompanyContextController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/users/{user}/profile-photo', [UserProfilePhotoController::class, 'show'])->name('users.profile-photo.show');
    Route::post('/context/company', [CompanyContextController::class, 'store'])->name('context.company.store');
});

require __DIR__.'/settings.php';
