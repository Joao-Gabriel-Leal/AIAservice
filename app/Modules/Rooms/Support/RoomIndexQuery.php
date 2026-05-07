<?php

namespace App\Modules\Rooms\Support;

use App\Models\User;
use App\Modules\Rooms\Models\Room;
use App\Modules\Shared\Support\AccessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class RoomIndexQuery
{
    public function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->string('search')),
            'status' => trim((string) $request->string('status')),
            'sector_id' => $request->integer('sector_id') ?: null,
        ];
    }

    public function build(User $user, array $filters): Builder
    {
        $query = $user->canManageRooms()
            ? Room::query()->with('sector.company')
            : AccessScope::applySectorScope(Room::query()->with('sector.company'), $user);

        return $query
            ->when($filters['search'] !== '', function (Builder $query) use ($filters) {
                $query->where(function (Builder $searchQuery) use ($filters) {
                    $searchQuery
                        ->where('name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('description', 'like', '%'.$filters['search'].'%');
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active'))
            ->when($filters['sector_id'], fn (Builder $query, int $sectorId) => $query->where('sector_id', $sectorId))
            ->latest();
    }
}
