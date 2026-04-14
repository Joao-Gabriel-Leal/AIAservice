<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\Ticket;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = auth()->user();
        $period = $this->resolvePeriod($request->string('period')->toString());
        $periodStart = now()->subDays($period);

        $ticketsQuery = Ticket::query()->visibleTo($user);

        $stats = [
            'companies' => $user->isSuperAdmin() ? Company::query()->count() : null,
            'sectors' => $user->isSuperAdmin()
                ? Sector::query()->count()
                : count($user->allSectorIds()),
            'collaborators' => $this->collaboratorCount($user),
            'tickets_total' => (clone $ticketsQuery)->count(),
            'open_tickets' => (clone $ticketsQuery)->whereNull('resolved_at')->count(),
            'created_in_period' => (clone $ticketsQuery)->where('created_at', '>=', $periodStart)->count(),
            'resolved_in_period' => (clone $ticketsQuery)->where('resolved_at', '>=', $periodStart)->count(),
            'active_in_period' => (clone $ticketsQuery)->where('last_activity_at', '>=', $periodStart)->count(),
            'unassigned_open_tickets' => (clone $ticketsQuery)
                ->whereNull('resolved_at')
                ->whereNull('assignee_id')
                ->count(),
            'overdue_sla' => (clone $ticketsQuery)
                ->whereNull('resolved_at')
                ->where(function ($query) {
                    $query
                        ->whereNotNull('first_response_breached_at')
                        ->orWhereNotNull('resolution_breached_at');
                })
                ->count(),
        ];

        $recentTickets = (clone $ticketsQuery)
            ->with(['status', 'group', 'requester', 'assignee', 'rating'])
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $ratingSummary = null;

        if ($user->hasOperationalAccess()) {
            $closedTicketsQuery = (clone $ticketsQuery)->whereNotNull('resolved_at');

            $ratingSummary = [
                'average' => number_format(
                    (float) (clone $closedTicketsQuery)
                        ->join('ticket_ratings', 'ticket_ratings.ticket_id', '=', 'tickets.id')
                        ->avg('ticket_ratings.rating'),
                    1,
                ),
                'rated_count' => (clone $closedTicketsQuery)->whereHas('rating')->count(),
                'pending_count' => (clone $closedTicketsQuery)->whereDoesntHave('rating')->count(),
            ];
        }

        $periodOptions = [
            7 => '7 dias',
            30 => '30 dias',
            90 => '90 dias',
        ];

        return view('modules.dashboard.index', compact('stats', 'recentTickets', 'ratingSummary', 'period', 'periodOptions'));
    }

    private function resolvePeriod(string $period): int
    {
        return in_array((int) $period, [7, 30, 90], true) ? (int) $period : 7;
    }

    private function collaboratorCount(User $user): int
    {
        if ($user->isSuperAdmin()) {
            return User::query()
                ->where('global_role', 'collaborator')
                ->count();
        }

        $sectorIds = $user->allSectorIds();

        if ($sectorIds === []) {
            return 0;
        }

        return User::query()
            ->where('global_role', 'collaborator')
            ->withAnySectorAccess($sectorIds)
            ->distinct('users.id')
            ->count('users.id');
    }
}
