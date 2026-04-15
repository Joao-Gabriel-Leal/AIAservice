<?php

namespace App\Modules\Auth\Support;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\Ticket;

class DashboardDataBuilder
{
    public function build(User $user, int $period): array
    {
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

        $chartDates = collect(range(0, $period - 1))
            ->map(fn (int $offset) => $periodStart->copy()->addDays($offset));

        $createdByDay = (clone $ticketsQuery)
            ->where('created_at', '>=', $periodStart)
            ->selectRaw('DATE(created_at) as chart_date, COUNT(*) as total')
            ->groupBy('chart_date')
            ->pluck('total', 'chart_date');

        $resolvedByDay = (clone $ticketsQuery)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $periodStart)
            ->selectRaw('DATE(resolved_at) as chart_date, COUNT(*) as total')
            ->groupBy('chart_date')
            ->pluck('total', 'chart_date');

        $statusDistribution = (clone $ticketsQuery)
            ->leftJoin('ticket_statuses', 'ticket_statuses.id', '=', 'tickets.ticket_status_id')
            ->selectRaw('COALESCE(ticket_statuses.name, ?) as status_name', ['Sem status'])
            ->selectRaw('COALESCE(ticket_statuses.color, ?) as status_color', ['#94a3b8'])
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status_name', 'status_color')
            ->orderByDesc('total')
            ->get();

        $charts = [
            'volume' => [
                'type' => 'line',
                'data' => [
                    'labels' => $chartDates->map(fn ($date) => $date->format('d/m'))->all(),
                    'datasets' => [
                        [
                            'label' => 'Criados',
                            'data' => $chartDates->map(fn ($date) => (int) ($createdByDay[$date->toDateString()] ?? 0))->all(),
                            'borderColor' => '#0ea5e9',
                            'backgroundColor' => 'rgba(14, 165, 233, 0.16)',
                            'tension' => 0.35,
                            'fill' => true,
                            'borderWidth' => 2,
                            'pointRadius' => 3,
                        ],
                        [
                            'label' => 'Resolvidos',
                            'data' => $chartDates->map(fn ($date) => (int) ($resolvedByDay[$date->toDateString()] ?? 0))->all(),
                            'borderColor' => '#10b981',
                            'backgroundColor' => 'rgba(16, 185, 129, 0.14)',
                            'tension' => 0.35,
                            'fill' => true,
                            'borderWidth' => 2,
                            'pointRadius' => 3,
                        ],
                    ],
                ],
                'options' => [
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'plugins' => [
                        'legend' => [
                            'position' => 'top',
                            'align' => 'start',
                        ],
                    ],
                    'scales' => [
                        'y' => [
                            'beginAtZero' => true,
                            'ticks' => [
                                'precision' => 0,
                            ],
                            'grid' => [
                                'color' => 'rgba(148, 163, 184, 0.14)',
                            ],
                        ],
                        'x' => [
                            'grid' => [
                                'display' => false,
                            ],
                        ],
                    ],
                ],
            ],
            'status' => [
                'type' => 'doughnut',
                'data' => [
                    'labels' => $statusDistribution->pluck('status_name')->all(),
                    'datasets' => [[
                        'data' => $statusDistribution->pluck('total')->map(fn ($value) => (int) $value)->all(),
                        'backgroundColor' => $statusDistribution->pluck('status_color')->all(),
                        'borderWidth' => 0,
                    ]],
                ],
                'options' => [
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'plugins' => [
                        'legend' => [
                            'position' => 'bottom',
                        ],
                    ],
                    'cutout' => '68%',
                ],
            ],
            'health' => [
                'type' => 'bar',
                'data' => [
                    'labels' => ['Abertos', 'Sem responsavel', 'SLA em atraso'],
                    'datasets' => [[
                        'label' => 'Chamados',
                        'data' => [
                            (int) $stats['open_tickets'],
                            (int) $stats['unassigned_open_tickets'],
                            (int) $stats['overdue_sla'],
                        ],
                        'backgroundColor' => ['#0f172a', '#f59e0b', '#ef4444'],
                        'borderRadius' => 14,
                        'maxBarThickness' => 42,
                    ]],
                ],
                'options' => [
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'plugins' => [
                        'legend' => [
                            'display' => false,
                        ],
                    ],
                    'scales' => [
                        'y' => [
                            'beginAtZero' => true,
                            'ticks' => [
                                'precision' => 0,
                            ],
                            'grid' => [
                                'color' => 'rgba(148, 163, 184, 0.14)',
                            ],
                        ],
                        'x' => [
                            'grid' => [
                                'display' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return compact(
            'stats',
            'recentTickets',
            'ratingSummary',
            'period',
            'periodOptions',
            'charts',
            'chartDates',
            'createdByDay',
            'resolvedByDay',
            'statusDistribution',
        );
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
