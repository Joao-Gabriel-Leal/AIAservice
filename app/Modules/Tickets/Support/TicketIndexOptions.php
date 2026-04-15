<?php

namespace App\Modules\Tickets\Support;

use App\Models\User;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketField;
use App\Modules\Tickets\Models\TicketGroup;
use Illuminate\Support\Collection;

class TicketIndexOptions
{
    public function sectorOptions(User $user): Collection
    {
        if ($user->isSuperAdmin()) {
            return Sector::query()
                ->with('company')
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        $sectorIds = collect($user->allSectorIds())
            ->merge(
                Ticket::query()
                    ->where('requester_id', $user->id)
                    ->pluck('sector_id')
                    ->all(),
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($sectorIds === []) {
            return collect();
        }

        return Sector::query()
            ->with('company')
            ->whereIn('id', $sectorIds)
            ->orderBy('name')
            ->get();
    }

    public function groupOptions(User $user, ?int $sectorId = null): Collection
    {
        $sectorIds = $this->sectorOptions($user)->pluck('id');

        if ($sectorId) {
            $sectorIds = $sectorIds->filter(fn (int $id) => $id === $sectorId)->values();
        }

        if ($sectorIds->isEmpty()) {
            return collect();
        }

        return TicketGroup::query()
            ->whereHas('board', fn ($query) => $query->whereIn('sector_id', $sectorIds->all()))
            ->where('is_active', true)
            ->with('board.sector')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function fieldOptions(User $user, ?int $sectorId = null): Collection
    {
        $sectorIds = $this->sectorOptions($user)->pluck('id');

        if ($sectorId) {
            $sectorIds = $sectorIds->filter(fn (int $id) => $id === $sectorId)->values();
        }

        if ($sectorIds->isEmpty()) {
            return collect();
        }

        return TicketField::query()
            ->where('is_active', true)
            ->where('show_on_board', true)
            ->whereHas('board', fn ($query) => $query->whereIn('sector_id', $sectorIds->all()))
            ->with('options')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->values();
    }
}
