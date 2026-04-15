<?php

namespace App\Modules\Tickets\Livewire;

use App\Enums\TicketPriority;
use App\Models\User;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Models\TicketField;
use App\Modules\Tickets\Models\TicketFieldOption;
use App\Modules\Tickets\Services\SectorProvisioningService;
use App\Modules\Tickets\Services\TicketWorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Component;

class BoardPage extends Component
{
    use AuthorizesRequests;

    public ?int $selectedSectorId = null;

    public string $viewMode = 'list';

    public array $collapsedGroups = [];

    public function mount(?Sector $sector = null): void
    {
        $this->selectedSectorId = $this->resolveSectorId($sector);

        if (! $this->selectedSectorId && $this->hasAnyActiveSector()) {
            abort(403);
        }

        $this->syncCollapsedGroups();
    }

    public function updatedSelectedSectorId(): void
    {
        abort_unless($this->availableSectors()->pluck('id')->contains($this->selectedSectorId), 403);
        $this->syncCollapsedGroups();
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $this->normalizeViewMode($mode);
    }

    public function toggleGroup(int $groupId): void
    {
        $this->collapsedGroups[$groupId] = ! ($this->collapsedGroups[$groupId] ?? false);
    }

    public function updateFixedField(TicketWorkflowService $workflowService, int $ticketId, string $field, mixed $value): void
    {
        if ($field === 'ticket_group_id') {
            $this->moveTicketToGroup($workflowService, $ticketId, $value);
            return;
        }

        $ticket = Ticket::query()->findOrFail($ticketId);
        $this->authorize('update', $ticket);

        if (! in_array($field, ['title', 'priority', 'assignee_id'], true)) {
            return;
        }

        $workflowService->updateTicket(auth()->user(), $ticket, [
            $field => $value === '' ? null : $value,
        ]);
    }

    public function moveTicketToGroup(TicketWorkflowService $workflowService, int $ticketId, mixed $groupId): void
    {
        $ticket = Ticket::query()->findOrFail($ticketId);
        $this->authorize('update', $ticket);

        $board = $this->board();
        $normalizedGroupId = $this->normalizeGroupId($groupId);

        abort_unless($board && $ticket->ticket_board_id === $board->id, 404);

        if ($normalizedGroupId !== null) {
            abort_unless($board->groups->pluck('id')->contains($normalizedGroupId), 404);
        }

        $workflowService->updateTicket(auth()->user(), $ticket, [
            'ticket_group_id' => $normalizedGroupId,
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

    public function fieldOption(TicketField $field, mixed $value): ?TicketFieldOption
    {
        return $field->options->first(
            fn (TicketFieldOption $option) => (string) $option->value === (string) $value
        );
    }

    public function priorityColor(?TicketPriority $priority): string
    {
        return match ($priority) {
            TicketPriority::LOW => '#22c55e',
            TicketPriority::MEDIUM => '#f59e0b',
            TicketPriority::HIGH => '#8b5cf6',
            TicketPriority::URGENT => '#f97316',
            default => '#94a3b8',
        };
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
                default => 'Em dia',
            },
        ];
    }

    public function render(): View
    {
        $this->viewMode = $this->normalizeViewMode($this->viewMode);

        $board = $this->board();
        $groups = $board?->groups ?? collect();
        $fields = $board?->fields
            ->where('is_active', true)
            ->where('show_on_board', true)
            ->values() ?? collect();

        $ticketsByGroup = collect();
        $ungroupedTickets = collect();
        $kanbanColumns = collect();
        $assignees = collect();

        if ($board) {
            $ticketQuery = $this->ticketQuery($board);

            $ticketsByGroup = $groups->mapWithKeys(fn ($group) => [
                $group->id => (clone $ticketQuery)->where('ticket_group_id', $group->id)->get(),
            ]);

            $ungroupedTickets = (clone $ticketQuery)->whereNull('ticket_group_id')->get();

            $kanbanColumns = $groups->map(fn ($group) => [
                'key' => "group-{$group->id}",
                'group' => $group,
                'tickets' => $ticketsByGroup->get($group->id, collect()),
                'targetGroupId' => $group->id,
            ]);

            if ($ungroupedTickets->isNotEmpty()) {
                $kanbanColumns->push([
                    'key' => 'group-none',
                    'group' => null,
                    'tickets' => $ungroupedTickets,
                    'targetGroupId' => null,
                ]);
            }

            $assignees = User::query()
                ->withSectorAccess($board->sector_id, ['sector_admin', 'technician'])
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
            'kanbanColumns' => $kanbanColumns,
            'assignees' => $assignees,
            'sectorUsers' => $assignees,
            'priorities' => TicketPriority::cases(),
            'sectorOptions' => $this->availableSectors(),
            'canUpdate' => true,
        ])->layout('layouts.portal', [
            'title' => 'Quadro de chamados',
            'subtitle' => 'Visualizacao operacional dos chamados por etapa.',
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
                'fields.options',
            ])
            ->where('sector_id', $this->selectedSectorId)
            ->first();
    }

    private function availableSectors(): Collection
    {
        $query = Sector::query()->with('company')->orderBy('name');

        if (! auth()->user()->isSuperAdmin()) {
            $query->whereIn('id', auth()->user()->operationalSectorIds());
        }

        return $query->where('is_active', true)->get();
    }

    private function resolveSectorId(?Sector $sector = null): ?int
    {
        if ($sector?->exists) {
            abort_unless(auth()->user()->isSuperAdmin() || auth()->user()->hasOperationalAccess($sector->id), 403);

            return $sector->id;
        }

        return $this->availableSectors()->first()?->id;
    }

    private function hasAnyActiveSector(): bool
    {
        return Sector::query()->where('is_active', true)->exists();
    }

    private function ticketQuery(TicketBoard $board): Builder
    {
        return Ticket::query()
            ->visibleTo(auth()->user())
            ->where('ticket_board_id', $board->id)
            ->with([
                'requester',
                'assignee',
                'group',
                'catalogItem',
                'fieldValues',
                'fieldValues.field.options',
            ])
            ->orderBy('created_at')
            ->orderBy('id');
    }

    private function syncCollapsedGroups(): void
    {
        $board = $this->board();

        if (! $board) {
            $this->collapsedGroups = [];

            return;
        }

        $this->collapsedGroups = $board->groups
            ->filter(fn ($group) => $group->is_collapsed_by_default)
            ->mapWithKeys(fn ($group) => [$group->id => true])
            ->all();
    }

    private function normalizeViewMode(string $mode): string
    {
        return in_array($mode, ['list', 'kanban'], true) ? $mode : 'list';
    }

    private function normalizeGroupId(mixed $groupId): ?int
    {
        if ($groupId === null || $groupId === '') {
            return null;
        }

        if (is_int($groupId)) {
            return $groupId;
        }

        if (is_string($groupId) && ctype_digit($groupId)) {
            return (int) $groupId;
        }

        if (is_float($groupId) && floor($groupId) === $groupId) {
            return (int) $groupId;
        }

        abort(404);
    }
}
