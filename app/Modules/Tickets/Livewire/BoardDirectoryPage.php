<?php

namespace App\Modules\Tickets\Livewire;

use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Models\TicketBoardUserPreference;
use App\Modules\Tickets\Services\SectorProvisioningService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class BoardDirectoryPage extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'scope')]
    public string $scope = 'all';

    public function mount(): void
    {
        if (! auth()->user()->hasOperationalAccess()) {
            session()->flash('status', 'A central de formularios fica disponivel para abrir chamados. O quadro e exclusivo para operadores e gestores.');
            $this->redirectRoute('tickets.central');

            return;
        }

        if ($requestedBoardId = request()->integer('board')) {
            $board = TicketBoard::query()
                ->where('is_active', true)
                ->find($requestedBoardId);

            abort_unless($board, 404);
            abort_unless(auth()->user()->canOperateBoard($board), 403);

            $this->redirectToBoard($board);

            return;
        }

        if ($requestedSectorId = request()->integer('sector')) {
            $board = $this->firstAccessibleBoardForSector($requestedSectorId);

            if (! $board) {
                return;
            }

            $this->redirectToBoard($board);
        }
    }

    public function render(): View
    {
        $boards = $this->boards();

        return view('livewire.tickets.board-directory-page', [
            'boards' => $boards,
            'favoriteCount' => $boards->filter(fn (TicketBoard $board) => $this->boardPreference($board)?->is_favorite)->count(),
            'recentCount' => $boards->filter(fn (TicketBoard $board) => ! is_null($this->boardPreference($board)?->last_opened_at))->count(),
        ])->layout('layouts.portal', [
            'title' => 'Quadros',
            'subtitle' => 'Escolha um quadro para acompanhar demandas em lista, etapas ou kanban.',
            'headerVariant' => 'none',
        ]);
    }

    public function updatedSearch(): void
    {
        $this->search = trim($this->search);
    }

    public function setScope(string $scope): void
    {
        $this->scope = in_array($scope, ['all', 'favorites', 'recent'], true) ? $scope : 'all';
    }

    public function toggleFavorite(int $boardId): void
    {
        $board = TicketBoard::query()->where('is_active', true)->findOrFail($boardId);

        abort_unless(auth()->user()->canOperateBoard($board), 403);

        $preference = TicketBoardUserPreference::query()->firstOrCreate([
            'user_id' => auth()->id(),
            'ticket_board_id' => $board->id,
        ]);

        $preference->update(['is_favorite' => ! $preference->is_favorite]);
    }

    private function boards(): Collection
    {
        $this->provisionMissingManagerBoards();

        $query = TicketBoard::query()
            ->with([
                'sector.company',
                'userPreferences' => fn ($preferenceQuery) => $preferenceQuery->where('user_id', auth()->id()),
            ])
            ->withCount([
                'tickets as open_tickets_count' => function (Builder $ticketQuery): void {
                    $ticketQuery
                        ->whereNull('resolved_at')
                        ->where(function (Builder $openQuery): void {
                            $openQuery
                                ->whereNull('ticket_group_id')
                                ->orWhereHas('group', fn (Builder $groupQuery) => $groupQuery->where('is_closed', false));
                        });
                },
            ])
            ->where('is_active', true);

        if (! auth()->user()->isGlobalAdmin()) {
            $query->whereIn('id', auth()->user()->operationalBoardIds());
        }

        $search = trim($this->search);

        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhereHas('sector', fn (Builder $sectorQuery) => $sectorQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('sector.company', fn (Builder $companyQuery) => $companyQuery->where('name', 'like', "%{$search}%"));
            });
        }

        $boards = $query->get();

        if ($this->scope === 'favorites') {
            $boards = $boards->filter(fn (TicketBoard $board) => $this->boardPreference($board)?->is_favorite)->values();
        }

        if ($this->scope === 'recent') {
            $boards = $boards->filter(fn (TicketBoard $board) => ! is_null($this->boardPreference($board)?->last_opened_at))->values();
        }

        return $boards
            ->sort(function (TicketBoard $first, TicketBoard $second): int {
                $firstPreference = $this->boardPreference($first);
                $secondPreference = $this->boardPreference($second);

                return [
                    $secondPreference?->is_favorite ? 1 : 0,
                    $secondPreference?->last_opened_at?->getTimestamp() ?? 0,
                    $second->is_default ? 1 : 0,
                    mb_strtolower($first->name),
                ] <=> [
                    $firstPreference?->is_favorite ? 1 : 0,
                    $firstPreference?->last_opened_at?->getTimestamp() ?? 0,
                    $first->is_default ? 1 : 0,
                    mb_strtolower($second->name),
                ];
            })
            ->values();
    }

    private function boardPreference(TicketBoard $board): ?TicketBoardUserPreference
    {
        return $board->userPreferences->firstWhere('user_id', auth()->id());
    }

    private function provisionMissingManagerBoards(): void
    {
        $user = auth()->user();

        if (! $user->isSectorAdmin() || $user->isGlobalAdmin()) {
            return;
        }

        Sector::query()
            ->whereIn('id', $user->adminSectorIds())
            ->where('is_active', true)
            ->whereDoesntHave('boards')
            ->get()
            ->each(fn (Sector $sector) => app(SectorProvisioningService::class)->provision($sector));
    }

    private function firstAccessibleBoardForSector(int $sectorId): ?TicketBoard
    {
        $query = TicketBoard::query()
            ->where('sector_id', $sectorId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name');

        if (! auth()->user()->isGlobalAdmin()) {
            $query->whereIn('id', auth()->user()->operationalBoardIds());
        }

        return $query->first();
    }

    private function redirectToBoard(TicketBoard $board): void
    {
        TicketBoardUserPreference::query()->updateOrCreate([
            'user_id' => auth()->id(),
            'ticket_board_id' => $board->id,
        ], [
            'last_opened_at' => now(),
        ]);

        $this->redirectRoute('tickets.board.show', [
            'board' => $board,
            'view' => request()->query('view', 'list'),
        ]);
    }
}
