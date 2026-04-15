<?php

namespace App\Modules\SectorTemplates\Policies;

use App\Models\User;
use App\Modules\SectorTemplates\Models\SectorTemplate;

class SectorTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, SectorTemplate $sectorTemplate): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, SectorTemplate $sectorTemplate): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, SectorTemplate $sectorTemplate): bool
    {
        return $user->isSuperAdmin();
    }
}
