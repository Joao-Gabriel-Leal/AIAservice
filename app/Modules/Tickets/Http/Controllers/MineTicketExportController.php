<?php

namespace App\Modules\Tickets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Shared\Support\CurrentCompanyContext;
use App\Modules\Tickets\Exports\TicketsExport;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Support\TicketIndexOptions;
use App\Modules\Tickets\Support\TicketReferenceCode;
use App\Support\Exports\SpreadsheetExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MineTicketExportController extends Controller
{
    public function __construct(
        private readonly TicketIndexOptions $ticketIndexOptions,
        private readonly SpreadsheetExporter $spreadsheetExporter,
    ) {
    }

    public function __invoke(Request $request): BinaryFileResponse
    {
        /** @var User $user */
        $user = $request->user();
        $sectorId = $request->integer('sector') ?: null;

        $query = Ticket::query()
            ->topLevel()
            ->where('requester_id', $user->id)
            ->whereHas('sector', fn (Builder $query) => $query->where('company_id', app(CurrentCompanyContext::class)->currentCompanyId($user) ?: 0))
            ->with(['sector.company', 'group', 'requester', 'assignee', 'rating', 'catalogItem', 'parentTicket', 'fieldValues.field.options'])
            ->when($sectorId, fn (Builder $query, int $selectedSectorId) => $query->where('sector_id', $selectedSectorId))
            ->when(trim((string) $request->string('title')) !== '', function (Builder $query) use ($request): void {
                $term = trim((string) $request->string('title'));
                $normalizedReference = TicketReferenceCode::normalizeLookup($term);

                $query->where(function (Builder $searchQuery) use ($term, $normalizedReference): void {
                    $searchQuery->where('title', 'like', '%'.$term.'%');

                    if ($normalizedReference !== '') {
                        $searchQuery->orWhere('reference_lookup', 'like', $normalizedReference.'%');
                    }

                    if (ctype_digit($term)) {
                        $searchQuery->orWhere('id', (int) $term);
                    }
                });
            })
            ->when(trim((string) $request->string('updated_from')) !== '', fn (Builder $query) => $query->whereDate('updated_at', '>=', trim((string) $request->string('updated_from'))))
            ->when(trim((string) $request->string('updated_to')) !== '', fn (Builder $query) => $query->whereDate('updated_at', '<=', trim((string) $request->string('updated_to'))));

        $this->applyStatusFilter($query, trim((string) $request->string('status', 'all')));
        $this->applySlaFilter($query, trim((string) $request->string('sla', 'all')));

        $export = new TicketsExport(
            $query->latest('last_activity_at')->latest('updated_at')->get(),
            $this->ticketIndexOptions->fieldOptions($user, $sectorId),
        );

        return $this->spreadsheetExporter->download($export->fileName(), $export->sheets());
    }

    private function applyStatusFilter(Builder $query, string $status): void
    {
        if ($status === 'open') {
            $query
                ->whereNull('resolved_at')
                ->where(function (Builder $statusQuery): void {
                    $statusQuery
                        ->whereDoesntHave('group')
                        ->orWhereHas('group', fn (Builder $groupQuery) => $groupQuery->where('is_closed', false));
                })
                ->where(function (Builder $statusQuery): void {
                    $statusQuery
                        ->whereDoesntHave('status')
                        ->orWhereHas('status', fn (Builder $ticketStatusQuery) => $ticketStatusQuery->where('is_closed', false));
                });
        }

        if ($status === 'closed') {
            $query->where(function (Builder $statusQuery): void {
                $statusQuery
                    ->whereNotNull('resolved_at')
                    ->orWhereHas('group', fn (Builder $groupQuery) => $groupQuery->where('is_closed', true))
                    ->orWhereHas('status', fn (Builder $ticketStatusQuery) => $ticketStatusQuery->where('is_closed', true));
            });
        }
    }

    private function applySlaFilter(Builder $query, string $sla): void
    {
        match ($sla) {
            'ok' => $this->applyOkSlaFilter($query),
            'warning' => $this->applyWarningSlaFilter($query),
            'breached' => $this->applyBreachedSlaFilter($query),
            default => null,
        };
    }

    private function applyBreachedSlaFilter(Builder $query): void
    {
        $now = now();

        $query->where(function (Builder $slaQuery) use ($now): void {
            $slaQuery
                ->whereNotNull('first_response_breached_at')
                ->orWhereNotNull('resolution_breached_at')
                ->orWhere(function (Builder $pendingQuery) use ($now): void {
                    $pendingQuery
                        ->whereNotNull('first_response_due_at')
                        ->whereNull('first_responded_at')
                        ->where('first_response_due_at', '<', $now);
                })
                ->orWhere(function (Builder $pendingQuery) use ($now): void {
                    $pendingQuery
                        ->whereNotNull('resolution_due_at')
                        ->whereNull('resolved_at')
                        ->where('resolution_due_at', '<', $now);
                })
                ->orWhereColumn('first_responded_at', '>', 'first_response_due_at')
                ->orWhereColumn('resolved_at', '>', 'resolution_due_at');
        });
    }

    private function applyWarningSlaFilter(Builder $query): void
    {
        $this->applyNotBreachedSlaFilter($query);

        $now = now();
        $warningAt = $now->copy()->addMinutes(30);

        $query->where(function (Builder $slaQuery) use ($now, $warningAt): void {
            $slaQuery
                ->where(function (Builder $pendingQuery) use ($now, $warningAt): void {
                    $pendingQuery
                        ->whereNull('first_responded_at')
                        ->whereBetween('first_response_due_at', [$now, $warningAt]);
                })
                ->orWhere(function (Builder $pendingQuery) use ($now, $warningAt): void {
                    $pendingQuery
                        ->whereNull('resolved_at')
                        ->whereBetween('resolution_due_at', [$now, $warningAt]);
                });
        });
    }

    private function applyOkSlaFilter(Builder $query): void
    {
        $this->applyNotBreachedSlaFilter($query);

        $warningAt = now()->copy()->addMinutes(30);

        $query
            ->where(function (Builder $slaQuery) use ($warningAt): void {
                $slaQuery
                    ->whereNull('first_response_due_at')
                    ->orWhereNotNull('first_responded_at')
                    ->orWhere('first_response_due_at', '>', $warningAt);
            })
            ->where(function (Builder $slaQuery) use ($warningAt): void {
                $slaQuery
                    ->whereNull('resolution_due_at')
                    ->orWhereNotNull('resolved_at')
                    ->orWhere('resolution_due_at', '>', $warningAt);
            });
    }

    private function applyNotBreachedSlaFilter(Builder $query): void
    {
        $now = now();

        $query
            ->whereNull('first_response_breached_at')
            ->whereNull('resolution_breached_at')
            ->where(function (Builder $slaQuery) use ($now): void {
                $slaQuery
                    ->whereNull('first_response_due_at')
                    ->orWhere(function (Builder $completedQuery): void {
                        $completedQuery
                            ->whereNotNull('first_responded_at')
                            ->whereColumn('first_responded_at', '<=', 'first_response_due_at');
                    })
                    ->orWhere(function (Builder $pendingQuery) use ($now): void {
                        $pendingQuery
                            ->whereNull('first_responded_at')
                            ->where('first_response_due_at', '>=', $now);
                    });
            })
            ->where(function (Builder $slaQuery) use ($now): void {
                $slaQuery
                    ->whereNull('resolution_due_at')
                    ->orWhere(function (Builder $completedQuery): void {
                        $completedQuery
                            ->whereNotNull('resolved_at')
                            ->whereColumn('resolved_at', '<=', 'resolution_due_at');
                    })
                    ->orWhere(function (Builder $pendingQuery) use ($now): void {
                        $pendingQuery
                            ->whereNull('resolved_at')
                            ->where('resolution_due_at', '>=', $now);
                    });
            });
    }
}
