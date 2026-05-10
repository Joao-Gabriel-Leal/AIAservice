<?php

namespace App\Modules\Tickets\Livewire;

use App\Models\User;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Services\TicketWorkflowService;
use App\Modules\Tickets\Support\TicketReferenceCode;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TrashPage extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'type')]
    public string $type = 'all';

    public function mount(): void
    {
        if (! auth()->user()->hasOperationalAccess()) {
            session()->flash('status', 'A lixeira de chamados fica disponivel para operadores e gestores.');
            $this->redirectRoute('tickets.mine');
        }

        $this->type = $this->normalizeType($this->type);
    }

    public function updated(): void
    {
        $this->type = $this->normalizeType($this->type);
        $this->resetPage();
    }

    public function restoreTicket(TicketWorkflowService $workflowService, int $ticketId): void
    {
        $ticket = $this->trashQuery()
            ->with(['parentTicketWithTrashed', 'board'])
            ->findOrFail($ticketId);

        $reference = $ticket->fullReference();
        $isSubelement = $ticket->isSubelement();

        $workflowService->restoreTicket(auth()->user(), $ticket);

        session()->flash('status', ($isSubelement ? 'Subelemento ' : 'Chamado ').$reference.' restaurado com sucesso.');
    }

    public function render(): View
    {
        return view('livewire.tickets.trash-page', [
            'tickets' => $this->filteredTrashQuery()
                ->latest('deleted_at')
                ->paginate(15),
            'retentionDays' => TicketWorkflowService::TRASH_RETENTION_DAYS,
            'typeOptions' => $this->typeOptions(),
        ])->layout('layouts.portal', [
            'title' => 'Lixeira de chamados',
            'subtitle' => 'Chamados e subelementos excluidos ficam disponiveis por 30 dias para restauracao.',
            'headerVariant' => 'none',
        ]);
    }

    private function filteredTrashQuery(): Builder
    {
        $query = $this->trashQuery()
            ->with([
                'sector.company',
                'board',
                'group',
                'status',
                'requester',
                'assignee',
                'parentTicketWithTrashed',
            ])
            ->withCount([
                'subTicketsWithTrashed as trashed_sub_tickets_count' => fn (Builder $query) => $query
                    ->onlyTrashed()
                    ->where('deleted_at', '>=', now()->subDays(TicketWorkflowService::TRASH_RETENTION_DAYS)),
            ]);

        if ($this->type === 'tickets') {
            $query->topLevel();
        } elseif ($this->type === 'subelements') {
            $query->subelements();
        }

        $term = trim($this->search);

        if ($term !== '') {
            $lookup = TicketReferenceCode::normalizeLookup($term);

            $query->where(function (Builder $searchQuery) use ($term, $lookup): void {
                $searchQuery
                    ->where('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('reference_code', 'like', "%{$term}%")
                    ->orWhere('reference_lookup', 'like', "%{$lookup}%")
                    ->orWhereHas('requester', fn (Builder $userQuery) => $userQuery->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('assignee', fn (Builder $userQuery) => $userQuery->where('name', 'like', "%{$term}%"));

                if (ctype_digit($term)) {
                    $searchQuery->orWhereKey((int) $term);
                }
            });
        }

        return $query;
    }

    private function trashQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();
        $boardIds = $user->operationalBoardIds();

        return Ticket::onlyTrashed()
            ->whereIn('ticket_board_id', $boardIds)
            ->where('deleted_at', '>=', now()->subDays(TicketWorkflowService::TRASH_RETENTION_DAYS));
    }

    private function normalizeType(string $type): string
    {
        return in_array($type, ['all', 'tickets', 'subelements'], true) ? $type : 'all';
    }

    private function typeOptions(): array
    {
        return [
            'all' => 'Todos',
            'tickets' => 'Chamados',
            'subelements' => 'Subelementos',
        ];
    }
}
