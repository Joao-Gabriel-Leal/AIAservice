<?php

namespace App\Modules\Sectors\Policies;

use App\Models\User;
use App\Modules\Sectors\Models\Sector;

class SectorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isGlobalAdmin() || $user->isSectorAdmin();
    }

    public function view(User $user, Sector $sector): bool
    {
        return $user->isGlobalAdmin() || $user->isSectorAdmin($sector->id);
    }

    public function create(User $user): bool
    {
        return $user->isGlobalAdmin();
    }

    public function update(User $user, Sector $sector): bool
    {
        return $user->isGlobalAdmin() || $user->isSectorAdmin($sector->id);
    }

    public function delete(User $user, Sector $sector): bool
    {
        return $user->isGlobalAdmin();
    }
}
