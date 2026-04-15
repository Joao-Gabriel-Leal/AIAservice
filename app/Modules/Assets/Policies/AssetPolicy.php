<?php

namespace App\Modules\Assets\Policies;

use App\Models\User;
use App\Modules\Assets\Models\Asset;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->isSuperAdmin() || $asset->current_user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->isSuperAdmin();
    }

    public function move(User $user, Asset $asset): bool
    {
        return $user->isSuperAdmin();
    }
}
