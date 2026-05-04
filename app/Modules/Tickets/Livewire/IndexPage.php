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
use App\Modules\Tickets\Support\TicketIndexQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class IndexPage extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'sector')]
    public ?int $selectedSectorId = null;

    #[Url(as: 'title')]
    public string $titleFilter = '';

    #[Url(as: 'group')]
    public ?int $selectedGroupId = null;

    #[Url(as: 'requester')]
    public string $requesterFilter = '';

    #[Url(as: 'assignee')]
    public string $assigneeFilter = '';

    #[Url(as: 'updated_from')]
    public string $updatedFrom = '';

    #[Url(as: 'updated_to')]
    public string $updatedTo = '';

    #[Url(as: 'view')]
    public string $viewMode = 'list';

    public array $fieldFilters = [];

    public array $collapsedGroups = [];

    public function mount(): void
    {
        if (! auth()->user()->hasOperationalAccess()) {
            session()->flash('status', 'A central de formularios fica disponivel para abrir chamados. O quadro e exclusivo para operadores e gestores.');
            $this->redirectRoute('tickets.central');

            return;
        }

        $this->viewMode = $this->normalizeViewMode($this->viewMode);

        if ($this->isBoardView()) {
            $this->ensureBoardSectorSelected();
        }

        $this->syncCollapsedGroups();
    }

    public function updatedSelectedSectorId(): void
    {
        if (! $this->selectedSectorId) {
            $this->selectedGroupId = null;
            $this->fieldFilters = [];

            if ($this->isBoardView()) {
                $this->ensureBoardSectorSelected();
            }

            $this->syncCollapsedGroups();
            $this->resetPage();

            return;
        }

        abort_unless($this->sectorOptions()->pluck('id')->contains($this->selectedSectorId), 403);

        $this->selectedGroupId = null;
        $this->fieldFilters = [];

        if ($this->isBoardView() && ! $this->availableBoardSectors()->pluck('id')->contains($this->selectedSectorId)) {
            $this->viewMode = 'list';
        }

        $this->syncCollapsedGroups();
        $this->resetPage();
    }

    public function updated($name): void
    {
        if ($name === 'selectedSectorId') {
            return;
        }

        $this->resetPage();
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $this->normalizeViewMode($mode);

        if ($this->isBoardView()) {
            $this->ensureBoardSectorSelected();
            $this->syncCollapsedGroups();
        }
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

    public function toggleGroup(int $groupId): void
    {
        $this->collapsedGroups[$groupId] = ! ($this->collapsedGroups[$groupId] ?? false);
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->hasOperationalAccess()) {
            session()->flash('status', 'A central de formularios fica disponivel para abrir chamados. O quadro e exclusivo para operadores e gestores.');
            $this->redirectRoute('tickets.central');
        }

        if ($this->isBoardView()) {
            $this->ensureBoardSectorSelected();
        }

        $sectorOptions = $this->availableBoardSectors();
        $groupOptions = $this->groupOptions();
        $fieldOptions = $this->fieldOptions();

        $normalizedFieldFilters = collect($this->fieldFilters)
            ->mapWithKeys(fn ($value, $fieldId) => [(int) $fieldId => is_string($value) ? trim($value) : $value])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        $filterPayload = [
            'sector_id' => $this->selectedSectorId,
            'title' => trim($this->titleFilter),
            'group_id' => $this->selectedGroupId,
            'requester' => trim($this->requesterFilter),
            'assignee' => trim($this->assigneeFilter),
            'updated_from' => $this->updatedFrom,
            'updated_to' => $this->updatedTo,
            'field_filters' => $normalizedFieldFilters,
        ];

        $ticketQuery = app(TicketIndexQuery::class)->build($user, $filterPayload);
        $allowedSectorIds = $sectorOptions->pluck('id')->all();

        if ($allowedSectorIds !== []) {
            $ticketQuery->whereIn('sector_id', $allowedSectorIds);
        }

        $tickets = $this->viewMode === 'list'
            ? (clone $ticketQuery)->paginate(12)
            : null;

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

        if ($board && $this->isBoardView()) {
            $boardTickets = (clone $ticketQuery)
                ->where('ticket_board_id', $board->id)
                ->with([
                    'requester',
                    'assignee',
                    'group',
                    'catalogItem',
                    'fieldValues.field.options',
                ])
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            $ticketsByGroup = $groups->mapWithKeys(fn ($group) => [
                $group->id => $boardTickets->where('ticket_group_id', $group->id)->values(),
            ]);
            $ungroupedTickets = $boardTickets->whereNull('ticket_group_id')->values();

            $assignees = User::query()
                ->withSectorAccess($board->sector_id, ['sector_admin', 'technician'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $kanbanColumns = $groups->map(fn ($group) => [
                'key' => "group-{$group->id}",
                'group' => $group,
                'tickets' => $ticketsByGroup->get($group->id, collect()),
                'targetGroupId' => $group->id,
            ])->values();

            if ($ungroupedTickets->isNotEmpty()) {
                $kanbanColumns->push([
                    'key' => 'group-none',
                    'group' => null,
                    'tickets' => $ungroupedTickets,
                    'targetGroupId' => null,
                ]);
            }
        }

        return view('livewire.tickets.index-page', [
            'tickets' => $tickets,
            'board' => $board,
            'groups' => $groups,
            'fields' => $fields,
            'ticketsByGroup' => $ticketsByGroup,
            'ungroupedTickets' => $ungroupedTickets,
            'kanbanColumns' => $kanbanColumns,
            'assignees' => $assignees,
            'sectorUsers' => $assignees,
            'sectorOptions' => $sectorOptions,
            'groupOptions' => $groupOptions,
            'fieldOptions' => $fieldOptions,
            'priorities' => TicketPriority::cases(),
            'canUpdate' => true,
            'manualCreateUrl' => $this->manualCreateUrl(),
            'exportParams' => array_filter([
                'sector' => $this->selectedSectorId,
                'title' => trim($this->titleFilter),
                'group' => $this->selectedGroupId,
                'requester' => trim($this->requesterFilter),
                'assignee' => trim($this->assigneeFilter),
                'updated_from' => $this->updatedFrom,
                'updated_to' => $this->updatedTo,
            ], fn ($value) => ! is_null($value) && $value !== ''),
            'fieldFiltersForExport' => $normalizedFieldFilters,
        ])->layout('layouts.portal', [
            'title' => 'Quadro',
            'subtitle' => 'Acompanhe chamados em lista, etapas ou kanban no mesmo fluxo operacional.',
        ]);
    }

    public function fieldValue(Ticket $ticket, TicketField $field): mixed
    {
        return $ticket->fieldValues->firstWhere('ticket_field_id', $field->id)?->primitive_value;
    }

    public function displayFieldValue(Ticket $ticket, TicketField $field): mixed
    {
        $value = $this->fieldValue($ticket, $field);
        return is_bool($value) ? ($value ? 'Sim' : 'Nao') : $value;
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

    private function sectorOptions(): Collection
    {
        return $this->availableBoardSectors();
    }

    private function board(): ?TicketBoard
    {
        if (! $this->selectedSectorId || ! $this->availableBoardSectors()->pluck('id')->contains($this->selectedSectorId)) {
            return null;
        }

        $board = TicketBoard::query()
            ->with(['sector.company', 'groups', 'fields.options'])
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
            ->with(['sector.company', 'groups', 'fields.options'])
            ->where('sector_id', $this->selectedSectorId)
            ->first();
    }

    private function availableBoardSectors(): Collection
    {
        $query = Sector::query()->with('company')->orderBy('name');

        if (! auth()->user()->isSuperAdmin()) {
            $query->whereIn('id', auth()->user()->operationalSectorIds());
        }

        return $query->where('is_active', true)->get();
    }

    private function ensureBoardSectorSelected(): void
    {
        $availableBoardSectors = $this->availableBoardSectors();

        if ($availableBoardSectors->isEmpty()) {
            $this->viewMode = 'list';

            return;
        }

        if (! $this->selectedSectorId || ! $availableBoardSectors->pluck('id')->contains($this->selectedSectorId)) {
            $this->selectedSectorId = $availableBoardSectors->first()?->id;
            $this->selectedGroupId = null;
            $this->fieldFilters = [];
        }
    }

    private function normalizeViewMode(string $mode): string
    {
        return in_array($mode, ['list', 'stages', 'kanban'], true) ? $mode : 'list';
    }

    private function isBoardView(): bool
    {
        return in_array($this->viewMode, ['stages', 'kanban'], true);
    }

    private function groupOptions(): Collection
    {
        $sectorIds = $this->availableBoardSectors()->pluck('id');

        if ($this->selectedSectorId) {
            $sectorIds = $sectorIds->filter(fn (int $id) => $id === $this->selectedSectorId)->values();
        }

        if ($sectorIds->isEmpty()) {
            return collect();
        }

        return \App\Modules\Tickets\Models\TicketGroup::query()
            ->whereHas('board', fn (Builder $query) => $query->whereIn('sector_id', $sectorIds->all()))
            ->where('is_active', true)
            ->with('board.sector')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function fieldOptions(): Collection
    {
        $sectorIds = $this->availableBoardSectors()->pluck('id');

        if ($this->selectedSectorId) {
            $sectorIds = $sectorIds->filter(fn (int $id) => $id === $this->selectedSectorId)->values();
        }

        if ($sectorIds->isEmpty()) {
            return collect();
        }

        return TicketField::query()
            ->where('is_active', true)
            ->where('show_on_board', true)
            ->whereHas('board', fn (Builder $query) => $query->whereIn('sector_id', $sectorIds->all()))
            ->with('options')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->values();
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

    private function manualCreateUrl(): string
    {
        $sectorId = $this->selectedSectorId;

        if (! $sectorId || ! $this->availableBoardSectors()->pluck('id')->contains($sectorId)) {
            $sectorId = $this->availableBoardSectors()->first()?->id;
        }

        return route('tickets.create', array_filter([
            'sector' => $sectorId,
        ]));
    }
}
