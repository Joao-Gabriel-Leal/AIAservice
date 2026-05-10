<?php

namespace App\Modules\Sectors\Support;

use App\Modules\Shared\Support\AccessScope;
use App\Models\User;
use App\Modules\Sectors\Models\Sector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SectorIndexQuery
{
    public function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->string('search')),
            'status' => trim((string) $request->string('status')),
            'company_id' => $request->integer('company_id') ?: null,
        ];
    }

    public function build(User $user, array $filters): Builder
    {
        $query = Sector::query()
            ->with('company');

        AccessScope::applyCurrentCompanyScope($query, $user, '');

        return $query
            ->when($user->isSectorAdmin() && ! $user->isGlobalAdmin(), fn (Builder $query) => $query->whereIn('id', $user->adminSectorIds()))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters) {
                $query->where(function (Builder $searchQuery) use ($filters) {
                    $searchQuery
                        ->where('name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('description', 'like', '%'.$filters['search'].'%');
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active'))
            ->when($filters['company_id'], fn (Builder $query, int $companyId) => $query->where('company_id', $companyId))
            ->latest();
    }
}
