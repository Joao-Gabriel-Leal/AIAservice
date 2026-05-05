<?php

namespace App\Modules\Auth\Support;

use App\Enums\AssetAllocationStatus;
use App\Enums\AssetStatus;
use App\Enums\GlobalUserRole;
use App\Enums\KnowledgeBaseArticleStatus;
use App\Enums\KnowledgeBaseVisibility;
use App\Enums\LicenseAssignmentStatus;
use App\Enums\LicenseStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketTimeEntryApprovalStatus;
use App\Enums\TicketTimeEntrySource;
use App\Models\User;
use App\Modules\Assets\Models\Asset;
use App\Modules\Companies\Models\Company;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticleFeedback;
use App\Modules\Licenses\Models\License;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketTimeEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardDataBuilder
{
    public function build(User $user, int $period, ?int $sectorId = null): array
    {
        $periodStart = now()->subDays($period - 1)->startOfDay();
        $slaWarningLimit = now()->addMinutes(30);
        $staleCutoff = now()->subDays(7);
        $availableSectors = $this->availableSectors($user);
        $selectedSectorId = $this->resolveSelectedSectorId($sectorId, $availableSectors);
        $ticketsQuery = $this->ticketQuery($user, $selectedSectorId);

        $stats = [
            'companies' => $user->isSuperAdmin() ? Company::query()->count() : null,
            'sectors' => $selectedSectorId ? 1 : $availableSectors->count(),
            'collaborators' => $this->collaboratorCount($user, $selectedSectorId),
            'tickets_total' => (clone $ticketsQuery)->count(),
            'open_tickets' => (clone $ticketsQuery)->whereNull('resolved_at')->count(),
            'closed_tickets' => (clone $ticketsQuery)->whereNotNull('resolved_at')->count(),
            'created_in_period' => (clone $ticketsQuery)->where('created_at', '>=', $periodStart)->count(),
            'resolved_in_period' => (clone $ticketsQuery)->where('resolved_at', '>=', $periodStart)->count(),
            'active_in_period' => (clone $ticketsQuery)->where('last_activity_at', '>=', $periodStart)->count(),
            'unassigned_open_tickets' => (clone $ticketsQuery)
                ->whereNull('resolved_at')
                ->whereNull('assignee_id')
                ->count(),
            'overdue_sla' => (clone $ticketsQuery)
                ->whereNull('resolved_at')
                ->where(fn (Builder $query) => $this->applyOverdueSlaFilter($query))
                ->count(),
            'warning_sla' => (clone $ticketsQuery)
                ->whereNull('resolved_at')
                ->where(fn (Builder $query) => $this->applyWarningSlaFilter($query, $slaWarningLimit))
                ->count(),
            'high_priority_open_tickets' => (clone $ticketsQuery)
                ->whereNull('resolved_at')
                ->whereIn('priority', [TicketPriority::HIGH->value, TicketPriority::URGENT->value])
                ->count(),
            'stale_open_tickets' => (clone $ticketsQuery)
                ->whereNull('resolved_at')
                ->where(fn (Builder $query) => $this->applyStaleFilter($query, $staleCutoff))
                ->count(),
        ];

        $stats['resolution_rate'] = $stats['created_in_period'] > 0
            ? round(($stats['resolved_in_period'] / $stats['created_in_period']) * 100)
            : 0;

        $recentTickets = (clone $ticketsQuery)
            ->with(['status', 'group', 'requester', 'assignee', 'rating', 'sector'])
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $attentionQueue = (clone $ticketsQuery)
            ->with(['status', 'group', 'requester', 'assignee', 'sector', 'rating'])
            ->whereNull('resolved_at')
            ->where(function (Builder $query) use ($staleCutoff) {
                $query
                    ->where(fn (Builder $slaQuery) => $this->applyOverdueSlaFilter($slaQuery))
                    ->orWhereNull('assignee_id')
                    ->orWhereIn('priority', [TicketPriority::HIGH->value, TicketPriority::URGENT->value])
                    ->orWhere(fn (Builder $staleQuery) => $this->applyStaleFilter($staleQuery, $staleCutoff));
            })
            ->latest('updated_at')
            ->limit(10)
            ->get()
            ->map(fn (Ticket $ticket) => [
                'ticket' => $ticket,
                'reason' => $this->attentionReason($ticket, $staleCutoff),
                'tone' => $this->attentionTone($ticket, $staleCutoff),
            ]);

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
                'resolution_rate' => $stats['resolution_rate'],
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

        $statusDistribution = $this->statusDistribution($ticketsQuery);
        $priorityDistribution = $this->priorityDistribution($ticketsQuery);
        $sectorBacklog = $this->sectorBacklog($ticketsQuery);
        $assigneeBacklog = $this->assigneeBacklog($ticketsQuery);
        $timeTrackingSummary = $this->timeTrackingSummary($user, $periodStart, $selectedSectorId);
        $licenseSummary = $this->licenseSummary($user, $selectedSectorId);
        $assetSummary = $this->assetSummary($user, $selectedSectorId);
        $knowledgeBaseSummary = $this->knowledgeBaseSummary($user, $selectedSectorId);

        $alerts = $this->alerts($stats, $licenseSummary, $assetSummary);

        $workloadSummary = [
            'by_assignee' => $assigneeBacklog,
            'by_sector' => $sectorBacklog,
        ];

        $charts = $this->charts(
            $chartDates,
            $createdByDay,
            $resolvedByDay,
            $statusDistribution,
            $priorityDistribution,
            $assigneeBacklog,
            $licenseSummary,
            $assetSummary,
            $stats,
        );

        return compact(
            'stats',
            'alerts',
            'charts',
            'recentTickets',
            'attentionQueue',
            'workloadSummary',
            'licenseSummary',
            'assetSummary',
            'knowledgeBaseSummary',
            'timeTrackingSummary',
            'availableSectors',
            'selectedSectorId',
            'ratingSummary',
            'period',
            'periodOptions',
            'chartDates',
            'createdByDay',
            'resolvedByDay',
            'statusDistribution',
            'priorityDistribution',
        );
    }

    private function availableSectors(User $user): Collection
    {
        if ($user->isSuperAdmin()) {
            return Sector::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        $sectorIds = $this->usesOperationalDashboardScope($user)
            ? collect($user->operationalSectorIds())
            : collect($user->allSectorIds())
                ->merge(Ticket::query()->visibleTo($user)->pluck('sector_id'));

        $sectorIds = $sectorIds
            ->filter()
            ->map(fn ($sectorId) => (int) $sectorId)
            ->unique()
            ->values();

        if ($sectorIds->isEmpty()) {
            return collect();
        }

        return Sector::query()
            ->whereIn('id', $sectorIds->all())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function resolveSelectedSectorId(?int $sectorId, Collection $availableSectors): ?int
    {
        if (! $sectorId) {
            return null;
        }

        return $availableSectors->pluck('id')->contains($sectorId) ? $sectorId : null;
    }

    private function ticketQuery(User $user, ?int $sectorId = null): Builder
    {
        $query = Ticket::query();

        if ($this->usesOperationalDashboardScope($user)) {
            $this->applyDashboardSectorScope($query, $user);
        } else {
            $query->visibleTo($user);
        }

        return $query
            ->when($sectorId, fn (Builder $query, int $selectedSectorId) => $query->where('sector_id', $selectedSectorId));
    }

    private function usesOperationalDashboardScope(User $user): bool
    {
        return ! $user->isSuperAdmin() && $user->hasOperationalAccess();
    }

    private function applyDashboardSectorScope(Builder $query, User $user, string $column = 'sector_id'): Builder
    {
        $sectorIds = $user->operationalSectorIds();

        if ($sectorIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $sectorIds);
    }

    private function applyOverdueSlaFilter(Builder $query): Builder
    {
        return $query->where(function (Builder $slaQuery) {
            $slaQuery
                ->whereNotNull('first_response_breached_at')
                ->orWhereNotNull('resolution_breached_at')
                ->orWhere(function (Builder $firstResponseQuery) {
                    $firstResponseQuery
                        ->whereNull('first_responded_at')
                        ->whereNotNull('first_response_due_at')
                        ->where('first_response_due_at', '<', now());
                })
                ->orWhere(function (Builder $resolutionQuery) {
                    $resolutionQuery
                        ->whereNull('resolved_at')
                        ->whereNotNull('resolution_due_at')
                        ->where('resolution_due_at', '<', now());
                });
        });
    }

    private function applyWarningSlaFilter(Builder $query, mixed $slaWarningLimit): Builder
    {
        return $query->where(function (Builder $slaQuery) use ($slaWarningLimit) {
            $slaQuery
                ->where(function (Builder $firstResponseQuery) use ($slaWarningLimit) {
                    $firstResponseQuery
                        ->whereNull('first_responded_at')
                        ->whereNull('first_response_breached_at')
                        ->whereBetween('first_response_due_at', [now(), $slaWarningLimit]);
                })
                ->orWhere(function (Builder $resolutionQuery) use ($slaWarningLimit) {
                    $resolutionQuery
                        ->whereNull('resolved_at')
                        ->whereNull('resolution_breached_at')
                        ->whereBetween('resolution_due_at', [now(), $slaWarningLimit]);
                });
        });
    }

    private function applyStaleFilter(Builder $query, mixed $staleCutoff): Builder
    {
        return $query->where(function (Builder $staleQuery) use ($staleCutoff) {
            $staleQuery
                ->where('last_activity_at', '<=', $staleCutoff)
                ->orWhere(function (Builder $emptyActivityQuery) use ($staleCutoff) {
                    $emptyActivityQuery
                        ->whereNull('last_activity_at')
                        ->where('created_at', '<=', $staleCutoff);
                });
        });
    }

    private function statusDistribution(Builder $ticketsQuery): Collection
    {
        return (clone $ticketsQuery)
            ->leftJoin('ticket_statuses', 'ticket_statuses.id', '=', 'tickets.ticket_status_id')
            ->selectRaw('COALESCE(ticket_statuses.name, ?) as status_name', ['Sem status'])
            ->selectRaw('COALESCE(ticket_statuses.color, ?) as status_color', ['#94a3b8'])
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status_name', 'status_color')
            ->orderByDesc('total')
            ->get();
    }

    private function priorityDistribution(Builder $ticketsQuery): Collection
    {
        $colors = [
            TicketPriority::LOW->value => '#22c55e',
            TicketPriority::MEDIUM->value => '#f59e0b',
            TicketPriority::HIGH->value => '#f97316',
            TicketPriority::URGENT->value => '#ef4444',
        ];

        return (clone $ticketsQuery)
            ->selectRaw('priority as priority_key, COUNT(*) as total')
            ->groupBy('priority_key')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) use ($colors) {
                $priority = TicketPriority::tryFrom((string) $row->priority_key);

                return [
                    'key' => (string) ($row->priority_key ?? 'none'),
                    'label' => $priority?->label() ?? 'Sem prioridade',
                    'color' => $colors[$priority?->value] ?? '#94a3b8',
                    'total' => (int) $row->total,
                ];
            });
    }

    private function sectorBacklog(Builder $ticketsQuery): Collection
    {
        return (clone $ticketsQuery)
            ->whereNull('resolved_at')
            ->leftJoin('sectors', 'sectors.id', '=', 'tickets.sector_id')
            ->selectRaw('COALESCE(sectors.name, ?) as sector_name', ['Sem setor'])
            ->selectRaw('COUNT(*) as total')
            ->groupBy('sector_name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();
    }

    private function assigneeBacklog(Builder $ticketsQuery): Collection
    {
        return (clone $ticketsQuery)
            ->whereNull('resolved_at')
            ->with('assignee')
            ->get()
            ->groupBy(fn (Ticket $ticket) => $ticket->assignee?->name ?? 'Nao atribuido')
            ->map(fn (Collection $tickets, string $assigneeName) => (object) [
                'assignee_name' => $assigneeName,
                'total' => $tickets->count(),
            ])
            ->sortByDesc('total')
            ->take(8)
            ->values();
    }

    private function timeTrackingSummary(User $user, mixed $periodStart, ?int $sectorId): array
    {
        $timeEntryQuery = $this->timeEntryQuery($user, $sectorId);

        $approvedSeconds = (int) (clone $timeEntryQuery)
            ->where('approval_status', TicketTimeEntryApprovalStatus::APPROVED->value)
            ->where('started_at', '>=', $periodStart)
            ->sum('duration_seconds');

        $topUsers = (clone $timeEntryQuery)
            ->join('users', 'users.id', '=', 'ticket_time_entries.user_id')
            ->where('approval_status', TicketTimeEntryApprovalStatus::APPROVED->value)
            ->where('started_at', '>=', $periodStart)
            ->selectRaw('users.name as user_name, COUNT(*) as entries_count, SUM(ticket_time_entries.duration_seconds) as total_seconds')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_seconds')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'user_name' => $row->user_name,
                'entries_count' => (int) $row->entries_count,
                'total_seconds' => (int) $row->total_seconds,
                'total_human' => $this->formatSeconds((int) $row->total_seconds),
            ]);

        return [
            'approved_seconds' => $approvedSeconds,
            'approved_human' => $this->formatSeconds($approvedSeconds),
            'running_count' => (clone $timeEntryQuery)->whereNull('ended_at')->count(),
            'pending_manual_count' => (clone $timeEntryQuery)
                ->where('source', TicketTimeEntrySource::MANUAL->value)
                ->where('approval_status', TicketTimeEntryApprovalStatus::PENDING->value)
                ->count(),
            'top_users' => $topUsers,
        ];
    }

    private function timeEntryQuery(User $user, ?int $sectorId): Builder
    {
        return TicketTimeEntry::query()
            ->whereHas('ticket', function (Builder $query) use ($user, $sectorId) {
                if ($this->usesOperationalDashboardScope($user)) {
                    $this->applyDashboardSectorScope($query, $user);
                } else {
                    $query->visibleTo($user);
                }

                $query->when($sectorId, fn (Builder $ticketQuery, int $selectedSectorId) => $ticketQuery->where('sector_id', $selectedSectorId));
            });
    }

    private function licenseSummary(User $user, ?int $sectorId): array
    {
        $canView = $user->isSuperAdmin() || $user->hasOperationalAccess();

        if (! $canView) {
            return [
                'can_view' => false,
                'active' => 0,
                'expired' => 0,
                'expiring_soon' => 0,
                'seats_total' => 0,
                'seats_used' => 0,
                'seats_available' => 0,
                'full_count' => 0,
                'upcoming' => collect(),
            ];
        }

        $today = now()->startOfDay()->toDateString();
        $limit = now()->addDays(30)->endOfDay()->toDateString();
        $licenseQuery = $this->licenseQuery($user, $sectorId);

        $licensesForSeats = (clone $licenseQuery)
            ->withCount([
                'assignments as active_assignments_count' => fn (Builder $query) => $query
                    ->where('status', LicenseAssignmentStatus::ACTIVE->value),
            ])
            ->get();

        $seatsTotal = (int) $licensesForSeats->sum('seats_total');
        $seatsUsed = (int) $licensesForSeats->sum('active_assignments_count');

        return [
            'can_view' => true,
            'active' => (clone $licenseQuery)->where('status', LicenseStatus::ACTIVE->value)->count(),
            'expired' => (clone $licenseQuery)->where(fn (Builder $query) => $this->applyExpiredLicenseFilter($query, $today))->count(),
            'expiring_soon' => (clone $licenseQuery)->where(fn (Builder $query) => $this->applyExpiringLicenseFilter($query, $today, $limit))->count(),
            'seats_total' => $seatsTotal,
            'seats_used' => $seatsUsed,
            'seats_available' => max(0, $seatsTotal - $seatsUsed),
            'full_count' => $licensesForSeats
                ->filter(fn (License $license) => (int) $license->seats_total > 0 && (int) $license->active_assignments_count >= (int) $license->seats_total)
                ->count(),
            'upcoming' => (clone $licenseQuery)
                ->with(['sector'])
                ->withCount([
                    'assignments as active_assignments_count' => fn (Builder $query) => $query
                        ->where('status', LicenseAssignmentStatus::ACTIVE->value),
                ])
                ->where(fn (Builder $query) => $this->applyUpcomingLicenseFilter($query, $limit))
                ->orderByRaw('COALESCE(expires_at, renewal_date) asc')
                ->limit(6)
                ->get(),
        ];
    }

    private function licenseQuery(User $user, ?int $sectorId): Builder
    {
        return License::query()
            ->when(! $user->isSuperAdmin(), function (Builder $query) use ($user) {
                $sectorIds = $user->operationalSectorIds();

                if ($sectorIds === []) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->whereIn('sector_id', $sectorIds);
            })
            ->when($sectorId, fn (Builder $query, int $selectedSectorId) => $query->where('sector_id', $selectedSectorId));
    }

    private function applyExpiredLicenseFilter(Builder $query, string $today): Builder
    {
        return $query->where(function (Builder $dateQuery) use ($today) {
            $dateQuery
                ->whereDate('expires_at', '<', $today)
                ->orWhere(function (Builder $renewalQuery) use ($today) {
                    $renewalQuery
                        ->whereNull('expires_at')
                        ->whereDate('renewal_date', '<', $today);
                });
        });
    }

    private function applyExpiringLicenseFilter(Builder $query, string $today, string $limit): Builder
    {
        return $query->where(function (Builder $dateQuery) use ($today, $limit) {
            $dateQuery
                ->whereBetween('expires_at', [$today, $limit])
                ->orWhere(function (Builder $renewalQuery) use ($today, $limit) {
                    $renewalQuery
                        ->whereNull('expires_at')
                        ->whereBetween('renewal_date', [$today, $limit]);
                });
        });
    }

    private function applyUpcomingLicenseFilter(Builder $query, string $limit): Builder
    {
        return $query->where(function (Builder $dateQuery) use ($limit) {
            $dateQuery
                ->whereNotNull('expires_at')
                ->whereDate('expires_at', '<=', $limit)
                ->orWhere(function (Builder $renewalQuery) use ($limit) {
                    $renewalQuery
                        ->whereNull('expires_at')
                        ->whereNotNull('renewal_date')
                        ->whereDate('renewal_date', '<=', $limit);
                });
        });
    }

    private function assetSummary(User $user, ?int $sectorId): array
    {
        $canViewIndex = $user->isSuperAdmin();
        $assetQuery = $this->assetQuery($user, $sectorId);

        $statusDistribution = (clone $assetQuery)
            ->selectRaw('status as status_key, COUNT(*) as total')
            ->groupBy('status_key')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) {
                $status = AssetStatus::tryFrom((string) $row->status_key);

                return [
                    'key' => (string) $row->status_key,
                    'label' => $status?->label() ?? 'Sem status',
                    'color' => $this->assetStatusColor($status),
                    'total' => (int) $row->total,
                ];
            });

        $allocationDistribution = (clone $assetQuery)
            ->selectRaw('allocation_status as allocation_key, COUNT(*) as total')
            ->groupBy('allocation_key')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) {
                $allocation = AssetAllocationStatus::tryFrom((string) $row->allocation_key);

                return [
                    'key' => (string) $row->allocation_key,
                    'label' => $allocation?->label() ?? 'Alocacao indefinida',
                    'color' => $allocation === AssetAllocationStatus::PENDING_REVIEW ? '#f59e0b' : '#10b981',
                    'total' => (int) $row->total,
                ];
            });

        return [
            'can_view_index' => $canViewIndex,
            'scope_label' => $canViewIndex ? 'Parque patrimonial' : 'Meus ativos',
            'total' => (clone $assetQuery)->count(),
            'in_maintenance' => (clone $assetQuery)->where('status', AssetStatus::MANUTENCAO->value)->count(),
            'lost' => (clone $assetQuery)->where('status', AssetStatus::EXTRAVIADO->value)->count(),
            'pending_review' => (clone $assetQuery)->where('allocation_status', AssetAllocationStatus::PENDING_REVIEW->value)->count(),
            'status_distribution' => $statusDistribution,
            'allocation_distribution' => $allocationDistribution,
            'attention_assets' => (clone $assetQuery)
                ->with(['currentSector', 'currentRoom', 'currentUser'])
                ->where(function (Builder $query) {
                    $query
                        ->whereIn('status', [AssetStatus::MANUTENCAO->value, AssetStatus::EXTRAVIADO->value])
                        ->orWhere('allocation_status', AssetAllocationStatus::PENDING_REVIEW->value);
                })
                ->latest()
                ->limit(6)
                ->get(),
        ];
    }

    private function assetQuery(User $user, ?int $sectorId): Builder
    {
        $query = Asset::query();

        if (! $user->isSuperAdmin()) {
            $query->where('current_user_id', $user->id);

            if ($this->usesOperationalDashboardScope($user)) {
                $this->applyDashboardSectorScope($query, $user, 'current_sector_id');
            }
        }

        return $query->when($sectorId, fn (Builder $query, int $selectedSectorId) => $query->where('current_sector_id', $selectedSectorId));
    }

    private function knowledgeBaseSummary(User $user, ?int $sectorId): array
    {
        $canManage = $user->isSuperAdmin() || $user->isSectorAdmin();
        $visibleArticleQuery = $this->knowledgeBaseVisibleQuery($user, $sectorId);
        $adminArticleQuery = $this->knowledgeBaseAdminQuery($user, $sectorId);

        return [
            'can_manage' => $canManage,
            'published' => (clone $visibleArticleQuery)->count(),
            'drafts' => $canManage
                ? (clone $adminArticleQuery)->where('editorial_status', KnowledgeBaseArticleStatus::DRAFT->value)->count()
                : 0,
            'generated_from_tickets' => (clone $visibleArticleQuery)->whereNotNull('generated_from_ticket_id')->count(),
            'helpful_feedback' => KnowledgeBaseArticleFeedback::query()
                ->where('is_helpful', true)
                ->whereHas('article', fn (Builder $query) => $this->applyKnowledgeBaseVisibility($query, $user, $sectorId))
                ->count(),
            'not_helpful_feedback' => KnowledgeBaseArticleFeedback::query()
                ->where('is_helpful', false)
                ->whereHas('article', fn (Builder $query) => $this->applyKnowledgeBaseVisibility($query, $user, $sectorId))
                ->count(),
            'top_articles' => (clone $visibleArticleQuery)
                ->with(['sector'])
                ->withCount('ticketUsages')
                ->orderByDesc('ticket_usages_count')
                ->latest('updated_at')
                ->limit(5)
                ->get(),
            'review_articles' => $canManage
                ? (clone $adminArticleQuery)
                    ->with(['sector', 'author'])
                    ->where(function (Builder $query) {
                        $query
                            ->where('editorial_status', KnowledgeBaseArticleStatus::DRAFT->value)
                            ->orWhere('is_active', false);
                    })
                    ->latest('updated_at')
                    ->limit(5)
                    ->get()
                : collect(),
        ];
    }

    private function knowledgeBaseVisibleQuery(User $user, ?int $sectorId): Builder
    {
        return KnowledgeBaseArticle::query()
            ->where(fn (Builder $query) => $this->applyKnowledgeBaseVisibility($query, $user, $sectorId));
    }

    private function knowledgeBaseAdminQuery(User $user, ?int $sectorId): Builder
    {
        return KnowledgeBaseArticle::query()
            ->when(! $user->isSuperAdmin(), function (Builder $query) use ($user) {
                $sectorIds = $user->adminSectorIds();

                if ($sectorIds === []) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->whereIn('sector_id', $sectorIds);
            })
            ->when($sectorId, fn (Builder $query, int $selectedSectorId) => $query->where('sector_id', $selectedSectorId));
    }

    private function applyKnowledgeBaseVisibility(Builder $query, User $user, ?int $sectorId): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('editorial_status', KnowledgeBaseArticleStatus::PUBLISHED->value)
            ->where(function (Builder $visibilityQuery) use ($user) {
                $visibilityQuery->where('visibility', KnowledgeBaseVisibility::PUBLIC->value);

                $operationalSectorIds = $user->operationalSectorIds();

                if ($operationalSectorIds !== []) {
                    $visibilityQuery->orWhere(function (Builder $privateQuery) use ($operationalSectorIds) {
                        $privateQuery
                            ->where('visibility', KnowledgeBaseVisibility::PRIVATE->value)
                            ->whereIn('sector_id', $operationalSectorIds);
                    });
                }
            })
            ->when(
                $this->usesOperationalDashboardScope($user) && ! $sectorId,
                fn (Builder $sectorQuery) => $this->applyDashboardSectorScope($sectorQuery, $user),
            )
            ->when($sectorId, fn (Builder $sectorQuery, int $selectedSectorId) => $sectorQuery->where('sector_id', $selectedSectorId));
    }

    private function alerts(array $stats, array $licenseSummary, array $assetSummary): array
    {
        return [
            [
                'key' => 'overdue_sla',
                'label' => 'SLA em atraso',
                'value' => $stats['overdue_sla'],
                'hint' => 'Chamados que ja romperam prazo.',
                'tone' => 'rose',
            ],
            [
                'key' => 'warning_sla',
                'label' => 'SLA perto do prazo',
                'value' => $stats['warning_sla'],
                'hint' => 'Demandas dentro dos proximos 30 minutos.',
                'tone' => 'amber',
            ],
            [
                'key' => 'unassigned',
                'label' => 'Sem responsavel',
                'value' => $stats['unassigned_open_tickets'],
                'hint' => 'Fila aberta para triagem.',
                'tone' => 'amber',
            ],
            [
                'key' => 'priority',
                'label' => 'Alta ou urgente',
                'value' => $stats['high_priority_open_tickets'],
                'hint' => 'Abertos com prioridade elevada.',
                'tone' => 'orange',
            ],
            [
                'key' => 'licenses',
                'label' => 'Licencas criticas',
                'value' => $licenseSummary['can_view']
                    ? $licenseSummary['expired'] + $licenseSummary['expiring_soon']
                    : 0,
                'hint' => 'Vencidas ou vencendo em ate 30 dias.',
                'tone' => 'sky',
                'hidden' => ! $licenseSummary['can_view'],
            ],
            [
                'key' => 'assets',
                'label' => 'Ativos em atencao',
                'value' => $assetSummary['in_maintenance'] + $assetSummary['lost'] + $assetSummary['pending_review'],
                'hint' => 'Manutencao, extravio ou saneamento.',
                'tone' => 'slate',
            ],
        ];
    }

    private function charts(
        Collection $chartDates,
        Collection $createdByDay,
        Collection $resolvedByDay,
        Collection $statusDistribution,
        Collection $priorityDistribution,
        Collection $assigneeBacklog,
        array $licenseSummary,
        array $assetSummary,
        array $stats,
    ): array {
        return [
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
                'options' => $this->cartesianOptions(true),
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
                'options' => $this->doughnutOptions(),
            ],
            'health' => [
                'type' => 'bar',
                'data' => [
                    'labels' => ['Abertos', 'Sem responsavel', 'SLA atrasado', 'Alta/urgente', 'Sem atividade'],
                    'datasets' => [[
                        'label' => 'Chamados',
                        'data' => [
                            (int) $stats['open_tickets'],
                            (int) $stats['unassigned_open_tickets'],
                            (int) $stats['overdue_sla'],
                            (int) $stats['high_priority_open_tickets'],
                            (int) $stats['stale_open_tickets'],
                        ],
                        'backgroundColor' => ['#0f172a', '#f59e0b', '#ef4444', '#f97316', '#64748b'],
                        'borderRadius' => 12,
                        'maxBarThickness' => 42,
                    ]],
                ],
                'options' => $this->cartesianOptions(false),
            ],
            'priority' => [
                'type' => 'bar',
                'data' => [
                    'labels' => $priorityDistribution->pluck('label')->all(),
                    'datasets' => [[
                        'label' => 'Chamados',
                        'data' => $priorityDistribution->pluck('total')->all(),
                        'backgroundColor' => $priorityDistribution->pluck('color')->all(),
                        'borderRadius' => 12,
                        'maxBarThickness' => 42,
                    ]],
                ],
                'options' => $this->cartesianOptions(false),
            ],
            'workload' => [
                'type' => 'bar',
                'data' => [
                    'labels' => $assigneeBacklog->pluck('assignee_name')->all(),
                    'datasets' => [[
                        'label' => 'Abertos',
                        'data' => $assigneeBacklog->pluck('total')->map(fn ($value) => (int) $value)->all(),
                        'backgroundColor' => '#2563eb',
                        'borderRadius' => 12,
                        'maxBarThickness' => 42,
                    ]],
                ],
                'options' => $this->cartesianOptions(false),
            ],
            'licenses' => [
                'type' => 'doughnut',
                'data' => [
                    'labels' => ['Em uso', 'Disponiveis'],
                    'datasets' => [[
                        'data' => [
                            (int) $licenseSummary['seats_used'],
                            (int) $licenseSummary['seats_available'],
                        ],
                        'backgroundColor' => ['#2563eb', '#10b981'],
                        'borderWidth' => 0,
                    ]],
                ],
                'options' => $this->doughnutOptions(),
            ],
            'assets' => [
                'type' => 'doughnut',
                'data' => [
                    'labels' => $assetSummary['status_distribution']->pluck('label')->all(),
                    'datasets' => [[
                        'data' => $assetSummary['status_distribution']->pluck('total')->all(),
                        'backgroundColor' => $assetSummary['status_distribution']->pluck('color')->all(),
                        'borderWidth' => 0,
                    ]],
                ],
                'options' => $this->doughnutOptions(),
            ],
        ];
    }

    private function cartesianOptions(bool $legend): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => $legend,
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
        ];
    }

    private function doughnutOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
            'cutout' => '66%',
        ];
    }

    private function collaboratorCount(User $user, ?int $sectorId): int
    {
        $query = User::query()
            ->where('global_role', GlobalUserRole::COLLABORATOR->value);

        if ($user->isSuperAdmin()) {
            if ($sectorId) {
                $query->withAnySectorAccess([$sectorId]);
            }

            return $query->count();
        }

        $sectorIds = $sectorId
            ? [$sectorId]
            : ($this->usesOperationalDashboardScope($user) ? $user->operationalSectorIds() : $user->allSectorIds());

        if ($sectorIds === []) {
            return 0;
        }

        return $query
            ->withAnySectorAccess($sectorIds)
            ->distinct('users.id')
            ->count('users.id');
    }

    private function attentionReason(Ticket $ticket, mixed $staleCutoff): string
    {
        if ($ticket->overallSlaState() === 'breached') {
            return 'SLA em atraso';
        }

        if (! $ticket->assignee_id) {
            return 'Sem responsavel';
        }

        if (in_array($ticket->priority, [TicketPriority::HIGH, TicketPriority::URGENT], true)) {
            return 'Prioridade elevada';
        }

        $lastActivity = $ticket->last_activity_at ?? $ticket->created_at;

        if ($lastActivity && $lastActivity->lessThanOrEqualTo($staleCutoff)) {
            return 'Sem atividade recente';
        }

        return 'Acompanhar';
    }

    private function attentionTone(Ticket $ticket, mixed $staleCutoff): string
    {
        return match ($this->attentionReason($ticket, $staleCutoff)) {
            'SLA em atraso' => 'bg-rose-100 text-rose-700',
            'Sem responsavel', 'Sem atividade recente' => 'bg-amber-100 text-amber-700',
            'Prioridade elevada' => 'bg-orange-100 text-orange-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    private function assetStatusColor(?AssetStatus $status): string
    {
        return match ($status) {
            AssetStatus::DISPONIVEL => '#10b981',
            AssetStatus::EM_USO => '#0ea5e9',
            AssetStatus::MANUTENCAO => '#f59e0b',
            AssetStatus::EXTRAVIADO => '#ef4444',
            AssetStatus::BAIXADO => '#64748b',
            default => '#94a3b8',
        };
    }

    private function formatSeconds(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return "{$hours}h {$minutes}min";
        }

        return "{$minutes}min";
    }
}
