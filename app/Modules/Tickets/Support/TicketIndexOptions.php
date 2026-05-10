<?php

namespace App\Modules\Tickets\Support;

use App\Models\User;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Shared\Support\AccessScope;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Models\TicketField;
use App\Modules\Tickets\Models\TicketGroup;
use Illuminate\Support\Collection;

class TicketIndexOptions
{
    public function sectorOptions(User $user): Collection
    {
        $sectorIds = collect(AccessScope::currentCompanySectorIds($user))
            ->merge(
                Ticket::query()
                    ->topLevel()
                    ->where('requester_id', $user->id)
                    ->whereHas('sector', fn ($query) => $query->whereIn('id', AccessScope::currentCompanySectorIds($user)))
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
        $boardIds = $this->boardOptions($user, $sectorId)->pluck('id');

        if ($boardIds->isEmpty()) {
            return collect();
        }

        return TicketGroup::query()
            ->whereIn('ticket_board_id', $boardIds->all())
            ->where('is_active', true)
            ->with('board.sector')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function fieldOptions(User $user, ?int $sectorId = null): Collection
    {
        $boardIds = $this->boardOptions($user, $sectorId)->pluck('id');

        if ($boardIds->isEmpty()) {
            return collect();
        }

        return TicketField::query()
            ->where('is_active', true)
            ->where('show_on_board', true)
            ->whereIn('ticket_board_id', $boardIds->all())
            ->with('options')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->values();
    }

    public function boardOptions(User $user, ?int $sectorId = null): Collection
    {
        $boardIds = collect($user->operationalBoardIds())
            ->merge(
                Ticket::query()
                    ->topLevel()
                    ->where('requester_id', $user->id)
                    ->whereHas('sector', fn ($query) => $query->whereIn('id', AccessScope::currentCompanySectorIds($user)))
                    ->pluck('ticket_board_id')
                    ->all(),
            )
            ->filter()
            ->unique()
            ->values();

        if ($boardIds->isEmpty()) {
            return collect();
        }

        return TicketBoard::query()
            ->with('sector.company')
            ->whereIn('id', $boardIds->all())
            ->whereHas('sector', fn ($query) => $query->whereIn('id', AccessScope::currentCompanySectorIds($user)))
            ->when($sectorId, fn ($query) => $query->where('sector_id', $sectorId))
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }
}
