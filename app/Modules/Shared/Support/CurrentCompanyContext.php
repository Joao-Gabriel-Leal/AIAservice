<?php

namespace App\Modules\Shared\Support;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Sectors\Models\Sector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CurrentCompanyContext
{
    public function availableCompanies(?User $user = null): Collection
    {
        if (! $user) {
            return collect();
        }

        $query = Company::query()
            ->where('is_active', true)
            ->orderBy('name');

        if (! $user->isGlobalAdmin()) {
            $sectorIds = $user->allSectorIds();

            if ($sectorIds === []) {
                return collect();
            }

            $query->whereHas('sectors', function (Builder $sectorQuery) use ($sectorIds): void {
                $sectorQuery
                    ->where('is_active', true)
                    ->whereIn('id', $sectorIds);
            });
        }

        return $query->get();
    }

    public function current(?User $user = null): ?Company
    {
        if (! $user) {
            return null;
        }

        $companies = $this->availableCompanies($user);

        if ($companies->isEmpty()) {
            if ($user->current_company_id !== null) {
                $user->forceFill(['current_company_id' => null])->saveQuietly();
            }

            return null;
        }

        $current = $companies->firstWhere('id', (int) $user->current_company_id)
            ?? $companies->first();

        if ((int) $user->current_company_id !== (int) $current->id) {
            $user->forceFill(['current_company_id' => $current->id])->saveQuietly();
        }

        return $current;
    }

    public function currentCompanyId(?User $user = null): ?int
    {
        return $this->current($user)?->id;
    }

    public function switch(User $user, int $companyId): bool
    {
        $company = $this->availableCompanies($user)->firstWhere('id', $companyId);

        if (! $company) {
            return false;
        }

        $user->forceFill(['current_company_id' => $company->id])->saveQuietly();
        $user->setRelation('currentCompany', $company);

        return true;
    }

    public function ensureForCompany(?User $user, int $companyId): bool
    {
        if (! $user) {
            return false;
        }

        if ((int) $this->currentCompanyId($user) === $companyId) {
            return true;
        }

        return $this->switch($user, $companyId);
    }

    public function scopedSectorIds(User $user, bool $operational = false): array
    {
        $companyId = $this->currentCompanyId($user);

        if (! $companyId) {
            return [];
        }

        $sectorIds = $user->isGlobalAdmin()
            ? Sector::query()->where('company_id', $companyId)->pluck('id')
            : collect($operational ? $user->operationalSectorIds() : $user->allSectorIds());

        return $sectorIds
            ->map(fn ($sectorId) => (int) $sectorId)
            ->when(! $user->isGlobalAdmin(), function (Collection $ids) use ($companyId) {
                if ($ids->isEmpty()) {
                    return $ids;
                }

                return Sector::query()
                    ->where('company_id', $companyId)
                    ->whereIn('id', $ids->all())
                    ->pluck('id');
            })
            ->unique()
            ->values()
            ->all();
    }
}
