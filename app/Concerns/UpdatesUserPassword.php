<?php

namespace App\Concerns;

use App\Modules\Shared\Services\ActivityLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

trait UpdatesUserPassword
{
    /**
     * Update the password for the currently authenticated user.
     */
    protected function updateAuthenticatedUserPassword(): void
    {
        $user = Auth::user();
        $rules = [
            'password' => $this->passwordRules(),
        ];

        if (! $user?->must_change_password) {
            $rules['current_password'] = $this->currentPasswordRules();
        }

        try {
            $validated = $this->validate($rules);
        } catch (ValidationException $exception) {
            $this->resetPasswordFormFields();

            throw $exception;
        }

        $mustChangePasswordBefore = (bool) $user->must_change_password;

        $user->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        app(ActivityLogService::class)->log(
            $user,
            $user,
            'user.password.changed',
            'Senha alterada pelo usuario.',
            [
                'target_user_id' => $user->id,
                'changes' => [
                    'password' => app(ActivityLogService::class)->protectedChange(),
                    'must_change_password' => [
                        'before' => $mustChangePasswordBefore,
                        'after' => false,
                    ],
                ],
            ],
        );

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
