<?php

namespace App\Modules\SectorTemplates\Policies;

use App\Models\User;
use App\Modules\SectorTemplates\Models\SectorTemplate;

class SectorTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isGlobalAdmin();
    }

    public function view(User $user, SectorTemplate $sectorTemplate): bool
    {
        return $user->isGlobalAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isGlobalAdmin();
    }

    public function update(User $user, SectorTemplate $sectorTemplate): bool
    {
        return $user->isGlobalAdmin();
    }

    public function delete(User $user, SectorTemplate $sectorTemplate): bool
    {
        return $user->isGlobalAdmin();
    }
}
