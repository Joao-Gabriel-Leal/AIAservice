<?php

namespace App\Modules\Shared\Support;

use App\Enums\SectorAccessLevel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AccessScope
{
    public static function applySectorScope(Builder $query, User $user, string $column = 'sector_id', array $levels = []): Builder
    {
        if ($user->isGlobalAdmin()) {
            return $query;
        }

        $normalizedLevels = collect($levels)
            ->map(fn (SectorAccessLevel|string $level) => $level instanceof SectorAccessLevel ? $level->value : $level)
            ->all();

        $sectorIds = $normalizedLevels === []
            ? $user->allSectorIds()
            : collect($normalizedLevels)->flatMap(function (string $level) use ($user) {
                return match ($level) {
                    SectorAccessLevel::SECTOR_ADMIN->value => $user->adminSectorIds(),
                    SectorAccessLevel::TECHNICIAN->value => $user->sectorIdsForLevel(SectorAccessLevel::TECHNICIAN),
                    SectorAccessLevel::REQUESTER->value => $user->sectorIdsForLevel(SectorAccessLevel::REQUESTER),
                    default => [],
                };
            })->unique()->values()->all();

        if ($sectorIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $sectorIds);
    }
}
