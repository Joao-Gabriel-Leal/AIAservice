<?php

namespace App\Modules\Tickets\Livewire;

use App\Enums\TicketPriority;
use App\Models\User;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Models\TicketField;
use App\Modules\Tickets\Services\SectorProvisioningService;
use App\Modules\Tickets\Services\TicketWorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Component;

class BoardPage extends Component
{
    use AuthorizesRequests;

    public ?int $selectedSectorId = null;

    public array $collapsedGroups = [];

    public function mount(?Sector $sector = null): void
    {
        $this->selectedSectorId = $this->resolveSectorId($sector);

        $board = $this->board();

        if ($board) {
            $this->collapsedGroups = $board->groups
                ->filter(fn ($group) => $group->is_collapsed_by_default)
                ->mapWithKeys(fn ($group) => [$group->id => true])
                ->all();
        }
    }

    public function updatedSelectedSectorId(): void
    {
        abort_unless($this->availableSectors()->pluck('id')->contains($this->selectedSectorId), 403);
    }

    public function toggleGroup(int $groupId): void
    {
        $this->collapsedGroups[$groupId] = ! ($this->collapsedGroups[$groupId] ?? false);
    }

    public function updateFixedField(TicketWorkflowService $workflowService, int $ticketId, string $field, mixed $value): void
    {
        $ticket = Ticket::query()->findOrFail($ticketId);
        $this->authorize('update', $ticket);

        if (! in_array($field, ['title', 'priority', 'ticket_status_id', 'ticket_group_id', 'assignee_id'], true)) {
            return;
        }

        $workflowService->updateTicket(auth()->user(), $ticket, [
            $field => $value === '' ? null : $value,
        ]);
    }

    public function updateDynamicField(TicketWorkflowService $workflowService, int $ticketId, int $fieldId, mixed $value): void
    {
        $ticket = Ticket::query()->findOrFail($ticketId);
        $field = TicketField::query()->findOrFail($fieldId);

        $this->authorize('update', $ticket);
        $workflowService->updateField(auth()->user(), $ticket, $field, $value);
    }

    public function fieldValue(Ticket $ticket, TicketField $field): mixed
    {
        return $ticket->fieldValues->firstWhere('ticket_field_id', $field->id)?->primitive_value;
    }

    public function render(): View
    {
        $board = $this->board();
        $groups = $board?->groups ?? collect();
        $fields = $board?->fields->where('is_active', true)->values() ?? collect();

        $ticketsByGroup = collect();
        $ungroupedTickets = collect();
        $assignees = collect();

        if ($board) {
            $ticketQuery = Ticket::query()
                ->visibleTo(auth()->user())
                ->where('ticket_board_id', $board->id)
                ->with([
                    'requester',
                    'assignee',
                    'status',
                    'group',
                    'fieldValues',
                    'fieldValues.field.options',
                ]);

            $ticketsByGroup = $groups->mapWithKeys(fn ($group) => [
                $group->id => (clone $ticketQuery)->where('ticket_group_id', $group->id)->get(),
            ]);

            $ungroupedTickets = (clone $ticketQuery)->whereNull('ticket_group_id')->get();

            $assignees = User::query()
                ->where('sector_id', $board->sector_id)
                ->whereIn('role', ['sector_admin', 'technician'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        return view('livewire.tickets.board-page', [
            'board' => $board,
            'groups' => $groups,
            'fields' => $fields,
            'ticketsByGroup' => $ticketsByGroup,
            'ungroupedTickets' => $ungroupedTickets,
            'assignees' => $assignees,
            'sectorUsers' => $assignees,
            'priorities' => TicketPriority::cases(),
            'sectorOptions' => $this->availableSectors(),
            'canUpdate' => ! auth()->user()->isRequester(),
        ])->layout('layouts.portal', [
            'title' => 'Quadro de chamados',
            'subtitle' => 'Visualizacao operacional dos chamados por grupo.',
        ]);
    }

    private function board(): ?TicketBoard
    {
        if (! $this->selectedSectorId) {
            return null;
        }

        $board = TicketBoard::query()
            ->with([
                'sector.company',
                'groups',
                'statuses',
                'fields.options',
            ])
            ->where('sector_id', $this->selectedSectorId)
            ->first();

        if ($board) {
            return $board;
        }

        $sector = Sector::query()->find($this->selectedSectorId);

        if (! $sector) {
            return null;
        }

        app(SectorProvisioningService::class)->provision($sector);

        return TicketBoard::query()
            ->with([
                'sector.company',
                'groups',
                'statuses',
                'fields.options',
            ])
            ->where('sector_id', $this->selectedSectorId)
            ->first();
    }

    private function availableSectors(): Collection
    {
        $query = Sector::query()->with('company')->orderBy('name');

        if (! auth()->user()->isSuperAdmin()) {
            $query->where('id', auth()->user()->sector_id);
        }

        return $query->get();
    }

    private function resolveSectorId(?Sector $sector = null): ?int
    {
        if ($sector) {
            return $sector->id;
        }

        if (! auth()->user()->isSuperAdmin()) {
            return auth()->user()->sector_id;
        }

        return $this->availableSectors()->first()?->id;
    }
}
