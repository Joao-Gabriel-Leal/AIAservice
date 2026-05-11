<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Modules\Shared\Services\ActivityLogService;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $mustChangePasswordBefore = (bool) $user->must_change_password;

        $user->forceFill([
            'password' => $input['password'],
            'must_change_password' => false,
        ])->save();

        app(ActivityLogService::class)->log(
            null,
            $user,
            'user.password.reset',
            'Senha redefinida pelo fluxo de recuperacao.',
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
    }
}
