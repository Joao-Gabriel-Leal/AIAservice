<?php

namespace App\Modules\Users\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class UserIndexQuery
{
    public function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->string('search')),
            'global_role' => trim((string) $request->string('global_role')),
            'status' => trim((string) $request->string('status')),
            'sector_id' => $request->integer('sector_id') ?: null,
        ];
    }

    public function build(User $user, array $filters): Builder
    {
        $query = User::query()
            ->with(['sectorAccesses.sector'])
            ->when($filters['search'] !== '', function (Builder $builder) use ($filters) {
                $builder->where(function (Builder $searchQuery) use ($filters) {
                    $searchQuery
                        ->where('name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('email', 'like', '%'.$filters['search'].'%');
                });
            })
            ->when($filters['global_role'] !== '', fn (Builder $builder) => $builder->where('global_role', $filters['global_role']))
            ->when($filters['status'] !== '', fn (Builder $builder) => $builder->where('is_active', $filters['status'] === 'active'))
            ->when($filters['sector_id'], fn (Builder $builder, int $sectorId) => $builder->withSectorAccess($sectorId))
            ->latest();

        if (! $user->isSuperAdmin()) {
            $managedSectorIds = $user->adminSectorIds();

            $query->where(function (Builder $scopedQuery) use ($managedSectorIds, $user) {
                $scopedQuery
                    ->whereKey($user->id)
                    ->orWhere(fn (Builder $userQuery) => $userQuery->withAnySectorAccess($managedSectorIds));
            });
        }

        return $query;
    }
}
