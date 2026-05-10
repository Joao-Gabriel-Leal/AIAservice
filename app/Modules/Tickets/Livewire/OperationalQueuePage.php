<?php

namespace App\Modules\Tickets\Livewire;

use App\Models\User;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Shared\Support\AccessScope;
use App\Modules\Shared\Support\CurrentCompanyContext;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Services\TicketWorkflowService;
use App\Modules\Tickets\Support\TicketIndexQuery;
use App\Modules\Tickets\Support\TicketReferenceCode;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class OperationalQueuePage extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'bucket')]
    public string $bucket = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'sector')]
    public ?int $selectedSectorId = null;

    public function mount(): void
    {
        if (! auth()->user()->hasOperationalAccess()) {
            session()->flash('status', 'A fila operacional fica disponivel para operadores e gestores.');
            $this->redirectRoute('tickets.mine');

            return;
        }

        $this->bucket = $this->normalizeBucket($this->bucket);

        if ($this->selectedSectorId && ! $this->sectorOptions()->pluck('id')->contains($this->selectedSectorId)) {
            $this->selectedSectorId = null;
        }
    }

    public function updated(): void
    {
        $this->bucket = $this->normalizeBucket($this->bucket);
        $this->resetPage();
    }

    public function setBucket(string $bucket): void
    {
        $this->bucket = $this->normalizeBucket($bucket);
        $this->resetPage();
    }

    public function deleteTicket(TicketWorkflowService $workflowService, int $ticketId): void
    {
        $ticket = Ticket::query()->findOrFail($ticketId);
        $this->authorize('delete', $ticket);

        $reference = $ticket->fullReference();
        $isSubelement = $ticket->isSubelement();

        $workflowService->deleteTicket(auth()->user(), $ticket);
        $this->resetPage();

        session()->flash('status', ($isSubelement ? 'Subelemento ' : 'Chamado ').$reference.' movido para a lixeira por 30 dias.');
    }

    public function render(TicketIndexQuery $ticketIndexQuery): View
    {
        $baseQuery = $this->baseQuery();
        $ticketsQuery = clone $baseQuery;

        $this->applyBucket($ticketsQuery, $ticketIndexQuery);

        return view('livewire.tickets.operational-queue-page', [
            'tickets' => $ticketsQuery
                ->latest('last_activity_at')
                ->latest('updated_at')
                ->paginate(15),
            'stats' => $this->stats($baseQuery, $ticketIndexQuery),
            'sectorOptions' => $this->sectorOptions(),
            'buckets' => $this->buckets(),
        ])->layout('layouts.portal', [
            'title' => 'Minha fila operacional',
            'subtitle' => 'Um cockpit rapido para chamados atribuidos, sem responsavel, SLA critico e tempos abertos.',
            'headerVariant' => 'none',
        ]);
    }

    public function slaMeta(Ticket $ticket): array
    {
        $state = $ticket->overallSlaState();

        return [
            'color' => match ($state) {
                'breached' => '#ef4444',
                'warning' => '#f59e0b',
                'na' => '#94a3b8',
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

    private function baseQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();
        $sectorIds = AccessScope::currentCompanySectorIds($user, true);
        $boardIds = $sectorIds === []
            ? []
            : \App\Modules\Tickets\Models\TicketBoard::query()
                ->whereIn('id', $user->operationalBoardIds())
                ->whereIn('sector_id', $sectorIds)
                ->pluck('id')
                ->all();

        $query = Ticket::query()
            ->visibleTo($user)
            ->with(['sector.company', 'board', 'group', 'status', 'requester', 'assignee', 'parentTicket', 'catalogItem', 'timeEntries'])
            ->whereIn('ticket_board_id', $boardIds)
            ->whereNull('resolved_at')
            ->where(function (Builder $statusQuery): void {
                $statusQuery
                    ->whereDoesntHave('group')
                    ->orWhereHas('group', fn (Builder $groupQuery) => $groupQuery->where('is_closed', false));
            });

        if ($this->selectedSectorId) {
            $query->where('sector_id', $this->selectedSectorId);
        }

        $search = trim($this->search);

        if ($search !== '') {
            $normalizedReference = TicketReferenceCode::normalizeLookup($search);

            $query->where(function (Builder $searchQuery) use ($search, $normalizedReference): void {
                $searchQuery
                    ->where('title', 'like', "%{$search}%")
                    ->orWhereHas('requester', fn (Builder $userQuery) => $userQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('assignee', fn (Builder $userQuery) => $userQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));

                if ($normalizedReference !== '') {
                    $searchQuery->orWhere('reference_lookup', 'like', $normalizedReference.'%');
                }

                if (ctype_digit($search)) {
                    $searchQuery->orWhere('id', (int) $search);
                }
            });
        }

        return $query;
    }

    private function applyBucket(Builder $query, TicketIndexQuery $ticketIndexQuery): void
    {
        match ($this->bucket) {
            'assigned_to_me' => $query->where('assignee_id', auth()->id()),
            'unassigned' => $query->whereNull('assignee_id'),
            'sla_critical' => $ticketIndexQuery->applySlaStateFilter($query, 'critical'),
            'timers_open' => $query->whereHas('timeEntries', fn (Builder $timeQuery) => $timeQuery
                ->where('user_id', auth()->id())
                ->whereNull('ended_at')),
            default => null,
        };
    }

    private function stats(Builder $baseQuery, TicketIndexQuery $ticketIndexQuery): array
    {
        $slaCriticalQuery = clone $baseQuery;
        $ticketIndexQuery->applySlaStateFilter($slaCriticalQuery, 'critical');

        return [
            'all' => (clone $baseQuery)->count(),
            'assigned_to_me' => (clone $baseQuery)->where('assignee_id', auth()->id())->count(),
            'unassigned' => (clone $baseQuery)->whereNull('assignee_id')->count(),
            'sla_critical' => $slaCriticalQuery->count(),
            'timers_open' => (clone $baseQuery)->whereHas('timeEntries', fn (Builder $timeQuery) => $timeQuery
                ->where('user_id', auth()->id())
                ->whereNull('ended_at'))->count(),
        ];
    }

    private function sectorOptions(): Collection
    {
        $sectorIds = AccessScope::currentCompanySectorIds(auth()->user(), true);

        if ($sectorIds === []) {
            return collect();
        }

        return Sector::query()
            ->with('company')
            ->whereIn('id', $sectorIds)
            ->where('company_id', app(CurrentCompanyContext::class)->currentCompanyId(auth()->user()) ?: 0)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function buckets(): array
    {
        return [
            'all' => 'Toda fila',
            'assigned_to_me' => 'Atribuidos a mim',
            'unassigned' => 'Sem responsavel',
            'sla_critical' => 'SLA critico',
            'timers_open' => 'Tempo aberto',
        ];
    }

    private function normalizeBucket(string $bucket): string
    {
        return array_key_exists($bucket, $this->buckets()) ? $bucket : 'all';
    }
}
