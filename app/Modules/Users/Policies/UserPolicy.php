<?php

namespace App\Modules\Users\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isSectorAdmin();
    }

    public function view(User $user, User $model): bool
    {
        if ($user->isSuperAdmin() || $user->id === $model->id) {
            return true;
        }

        return $user->isSectorAdmin() && $user->sector_id === $model->sector_id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isSectorAdmin();
    }

    public function update(User $user, User $model): bool
    {
        if ($user->isSuperAdmin() || $user->id === $model->id) {
            return true;
        }

        if (! $user->isSectorAdmin() || $user->sector_id !== $model->sector_id) {
            return false;
        }

        return $model->role !== UserRole::SUPER_ADMIN;
    }

    public function delete(User $user, User $model): bool
    {
        if ($model->id === $user->id) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isSectorAdmin()
            && $user->sector_id === $model->sector_id
            && $model->role !== UserRole::SUPER_ADMIN;
    }
}
