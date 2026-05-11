<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Modules\Shared\Services\ActivityLogService;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        app(ActivityLogService::class)->logChanges(
            null,
            $user,
            'user.created',
            'Usuario criado.',
            [],
            [
                'name' => $user->name,
                'email' => $user->email,
                'global_role' => $user->global_role?->value,
                'role' => $user->role?->value,
                'must_change_password' => (bool) $user->must_change_password,
                'is_active' => (bool) $user->is_active,
            ],
            [
                'target_user_id' => $user->id,
            ],
        );

        return $user;
    }
}
