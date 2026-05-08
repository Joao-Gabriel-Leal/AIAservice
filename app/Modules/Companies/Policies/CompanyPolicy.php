<?php

namespace App\Modules\Companies\Policies;

use App\Models\User;
use App\Modules\Companies\Models\Company;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isGlobalAdmin();
    }

    public function view(User $user, Company $company): bool
    {
        return $user->isGlobalAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isGlobalAdmin();
    }

    public function update(User $user, Company $company): bool
    {
        return $user->isGlobalAdmin();
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->isGlobalAdmin();
    }
}
