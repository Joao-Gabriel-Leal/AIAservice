<?php

namespace App\Modules\Tickets\Livewire;

use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Services\SectorProvisioningService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class BoardDirectoryPage extends Component
{
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
        ])->layout('layouts.portal', [
            'title' => 'Quadros',
            'subtitle' => 'Escolha um quadro para acompanhar demandas em lista, etapas ou kanban.',
        ]);
    }

    private function boards()
    {
        $this->provisionMissingManagerBoards();

        $query = TicketBoard::query()
            ->with('sector.company')
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

        if (! auth()->user()->isSuperAdmin()) {
            $query->whereIn('id', auth()->user()->operationalBoardIds());
        }

        return $query
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    private function provisionMissingManagerBoards(): void
    {
        $user = auth()->user();

        if (! $user->isSectorAdmin() || $user->isSuperAdmin()) {
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

        if (! auth()->user()->isSuperAdmin()) {
            $query->whereIn('id', auth()->user()->operationalBoardIds());
        }

        return $query->first();
    }

    private function redirectToBoard(TicketBoard $board): void
    {
        $this->redirectRoute('tickets.board.show', [
            'board' => $board,
            'view' => request()->query('view', 'list'),
        ]);
    }
}
