<?php

namespace App\Modules\Shared\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AccessScope
{
    public static function applySectorScope(Builder $query, User $user, string $column = 'sector_id'): Builder
    {
        if ($user->role === UserRole::SUPER_ADMIN) {
            return $query;
        }

        return $query->where($column, $user->sector_id);
    }
}
