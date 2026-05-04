<?php

namespace App\Modules\Licenses\Policies;

use App\Models\User;
use App\Modules\Licenses\Models\License;

class LicensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasOperationalAccess();
    }

    public function view(User $user, License $license): bool
    {
        return $user->isSuperAdmin() || $user->hasOperationalAccess($license->sector_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasOperationalAccess();
    }

    public function update(User $user, License $license): bool
    {
        return $user->isSuperAdmin() || $user->hasOperationalAccess($license->sector_id);
    }
}
