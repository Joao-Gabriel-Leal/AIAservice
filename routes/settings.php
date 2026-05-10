<?php

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('password/force-change', fn () => view('pages.auth.force-password-change'))->name('password.force-change');
    Route::post('password/force-change', function (Request $request): RedirectResponse {
        $requiresCurrentPassword = ! $request->user()?->must_change_password;

        $validated = $request->validate([
            'current_password' => [
                Rule::requiredIf($requiresCurrentPassword),
                'current_password',
            ],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        return redirect()->route('dashboard')->with('status', 'Senha atualizada com sucesso.');
    })->name('password.force-change.update');

    Route::view('settings/profile', 'pages.settings.index')->name('profile.edit');
});

Route::middleware(['auth'])->group(function () {
    Route::get('settings/appearance', fn (): RedirectResponse => redirect()->route('profile.edit'))
        ->name('appearance.edit');

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
