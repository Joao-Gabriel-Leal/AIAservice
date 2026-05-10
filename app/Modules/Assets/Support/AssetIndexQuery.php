<?php

namespace App\Modules\Assets\Support;

use App\Models\User;
use App\Modules\Assets\Models\Asset;
use App\Modules\Shared\Support\AccessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AssetIndexQuery
{
    public function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->string('search')),
            'status' => trim((string) $request->string('status')),
            'allocation_status' => trim((string) $request->string('allocation_status')),
            'sector_id' => $request->integer('sector_id') ?: null,
            'room_id' => $request->integer('room_id') ?: null,
            'user_id' => $request->integer('user_id') ?: null,
        ];
    }

    public function build(array $filters, User $user): Builder
    {
        $query = Asset::query()
            ->with(['currentSector', 'currentRoom', 'currentUser']);

        AccessScope::applyCurrentCompanyScope($query, $user, 'currentSector');

        return $query
            ->when($filters['search'] !== '', function (Builder $query) use ($filters) {
                $query->where(function (Builder $searchQuery) use ($filters) {
                    $searchQuery
                        ->where('asset_code', 'like', '%'.$filters['search'].'%')
                        ->orWhere('name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('serial_number', 'like', '%'.$filters['search'].'%');
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['allocation_status'] !== '', fn (Builder $query) => $query->where('allocation_status', $filters['allocation_status']))
            ->when($filters['sector_id'], fn (Builder $query, int $sectorId) => $query->where('current_sector_id', $sectorId))
            ->when($filters['room_id'], fn (Builder $query, int $roomId) => $query->where('current_room_id', $roomId))
            ->when($filters['user_id'], fn (Builder $query, int $userId) => $query->where('current_user_id', $userId))
            ->latest();
    }
}
