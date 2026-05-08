<?php

namespace App\Modules\Users\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isGlobalAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isGlobalAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isGlobalAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isGlobalAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        if ($model->id === $user->id) {
            return false;
        }

        return $user->isGlobalAdmin();
    }
}
