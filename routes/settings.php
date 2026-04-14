<?php

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::view('settings/profile', 'pages.settings.index')->name('profile.edit');
});

Route::middleware(['auth'])->group(function () {
    Route::get('settings/appearance', function (): RedirectResponse {
        return redirect()->to(route('profile.edit'));
    })->name('appearance.edit');

    Route::get('settings/security', function (): RedirectResponse {
        return redirect()->to(route('profile.edit').'#seguranca');
    })
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('security.edit');
});
