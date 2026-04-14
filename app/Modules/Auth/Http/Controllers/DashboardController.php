<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Shared\Support\AccessScope;
use App\Modules\Tickets\Models\Ticket;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $ticketsQuery = Ticket::query()->visibleTo($user);

        $stats = [
            'companies' => $user->isSuperAdmin() ? Company::query()->count() : null,
            'sectors' => $user->isSuperAdmin()
                ? Sector::query()->count()
                : Sector::query()->where('id', $user->sector_id)->count(),
            'rooms' => AccessScope::applySectorScope(Room::query(), $user)->count(),
            'open_tickets' => (clone $ticketsQuery)->whereNull('resolved_at')->count(),
        ];

        $recentTickets = (clone $ticketsQuery)
            ->with(['status', 'group', 'requester', 'assignee'])
            ->latest('updated_at')
            ->limit(8)
            ->get();

        return view('modules.dashboard.index', compact('stats', 'recentTickets'));
    }
}
