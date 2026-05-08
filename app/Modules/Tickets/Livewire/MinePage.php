<?php

namespace App\Modules\Tickets\Livewire;

use App\Models\User;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Support\TicketReferenceCode;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class MinePage extends Component
{
    use WithPagination;

    #[Url(as: 'title')]
    public string $titleFilter = '';

    #[Url(as: 'sector')]
    public ?int $selectedSectorId = null;

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    #[Url(as: 'sla')]
    public string $slaFilter = 'all';

    #[Url(as: 'updated_from')]
    public string $updatedFrom = '';

    #[Url(as: 'updated_to')]
    public string $updatedTo = '';

    public function mount(): void
    {
        $this->statusFilter = in_array($this->statusFilter, ['all', 'open', 'closed'], true)
            ? $this->statusFilter
            : 'all';
        $this->slaFilter = in_array($this->slaFilter, ['all', 'ok', 'warning', 'breached'], true)
            ? $this->slaFilter
            : 'all';

        if ($this->selectedSectorId && ! $this->sectorOptions()->pluck('id')->contains($this->selectedSectorId)) {
            $this->selectedSectorId = null;
        }
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $ticketsQuery = Ticket::query()
            ->where('requester_id', $user->id)
            ->with([
                'sector.company',
                'group',
                'status',
                'assignee',
                'catalogItem',
                'rating',
            ])
            ->when(trim($this->titleFilter) !== '', function (Builder $query): void {
                $term = trim($this->titleFilter);
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
            ->when($this->selectedSectorId, function (Builder $query): void {
                $query->where('sector_id', $this->selectedSectorId);
            })
            ->when($this->updatedFrom !== '', function (Builder $query): void {
                $query->whereDate('updated_at', '>=', $this->updatedFrom);
            })
            ->when($this->updatedTo !== '', function (Builder $query): void {
                $query->whereDate('updated_at', '<=', $this->updatedTo);
            });

        $this->applyStatusFilter($ticketsQuery);
        $this->applySlaFilter($ticketsQuery);

        return view('livewire.tickets.mine-page', [
            'tickets' => $ticketsQuery
                ->latest('last_activity_at')
                ->latest('updated_at')
                ->paginate(12),
            'sectorOptions' => $this->sectorOptions(),
        ])->layout('layouts.portal', [
            'title' => 'Meus chamados',
            'subtitle' => 'Acompanhe somente as demandas abertas por voce.',
            'headerVariant' => 'none',
        ]);
    }

    public function slaMeta(Ticket $ticket): array
    {
        $state = $ticket->overallSlaState();

        return [
            'state' => $state,
            'color' => match ($state) {
                'breached' => '#ef4444',
                'warning' => '#f59e0b',
                default => '#22c55e',
            },
            'label' => match ($state) {
                'breached' => 'Estourado',
                'warning' => 'A vencer',
                'na' => 'Nao configurado',
                default => 'Em dia',
            },
        ];
    }

    private function sectorOptions(): Collection
    {
        $sectorIds = Ticket::query()
            ->where('requester_id', auth()->id())
            ->whereNotNull('sector_id')
            ->distinct()
            ->pluck('sector_id');

        if ($sectorIds->isEmpty()) {
            return collect();
        }

        return Sector::query()
            ->with('company')
            ->whereIn('id', $sectorIds->all())
            ->orderBy('name')
            ->get();
    }

    private function applyStatusFilter(Builder $query): void
    {
        if ($this->statusFilter === 'open') {
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

        if ($this->statusFilter === 'closed') {
            $query->where(function (Builder $statusQuery): void {
                $statusQuery
                    ->whereNotNull('resolved_at')
                    ->orWhereHas('group', fn (Builder $groupQuery) => $groupQuery->where('is_closed', true))
                    ->orWhereHas('status', fn (Builder $ticketStatusQuery) => $ticketStatusQuery->where('is_closed', true));
            });
        }
    }

    private function applySlaFilter(Builder $query): void
    {
        match ($this->slaFilter) {
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
