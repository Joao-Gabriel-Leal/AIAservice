<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

trait UpdatesUserPassword
{
    /**
     * Update the password for the currently authenticated user.
     */
    protected function updateAuthenticatedUserPassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $exception) {
            $this->resetPasswordFormFields();

            throw $exception;
        }

        Auth::user()->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        $this->resetPasswordFormFields();
    }

    /**
     * Reset password form fields for the current component.
     */
    protected function resetPasswordFormFields(): void
    {
        $this->reset('current_password', 'password', 'password_confirmation');
    }
}
