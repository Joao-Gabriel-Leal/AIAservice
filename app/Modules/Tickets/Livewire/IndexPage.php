<?php

namespace App\Modules\Tickets\Livewire;

use App\Enums\TicketPriority;
use App\Models\User;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Models\TicketField;
use App\Modules\Tickets\Models\TicketFieldOption;
use App\Modules\Tickets\Models\TicketGroup;
use App\Modules\Tickets\Services\SectorProvisioningService;
use App\Modules\Tickets\Services\TicketWorkflowService;
use App\Modules\Tickets\Support\TicketIndexQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class IndexPage extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public ?int $selectedSectorId = null;

    public ?int $selectedBoardId = null;

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

    public bool $showManualTicketModal = false;

    public ?int $lastManualTicketId = null;

    public array $manualTicketForm = [
        'title' => '',
        'description' => '',
        'priority' => 'medium',
        'ticket_group_id' => '',
        'assignee_id' => '',
        'requester_id' => '',
        'dynamic_values' => [],
    ];

    public function mount(?TicketBoard $board = null): void
    {
        if (! auth()->user()->hasOperationalAccess()) {
            session()->flash('status', 'A central de formularios fica disponivel para abrir chamados. O quadro e exclusivo para operadores e gestores.');
            $this->redirectRoute('tickets.central');

            return;
        }

        if ($board?->exists) {
            abort_if(! $board->is_active, 404);
            abort_unless(auth()->user()->canOperateBoard($board), 403);

            $this->selectedSectorId = $board->sector_id;
            $this->selectedBoardId = $board->id;
        }

        $this->viewMode = $this->normalizeViewMode($this->viewMode);

        if (! $this->selectedBoardId) {
            $this->ensureBoardSelected();
        }

        $this->syncCollapsedGroups();
    }

    public function updatedSelectedSectorId(): void
    {
        if (! $this->selectedSectorId) {
            $this->selectedGroupId = null;
            $this->fieldFilters = [];

            $this->selectedBoardId = null;

            if ($this->isBoardView()) {
                $this->ensureBoardSelected();
            }

            $this->syncCollapsedGroups();
            $this->resetPage();

            return;
        }

        abort_unless($this->sectorOptions()->pluck('id')->contains($this->selectedSectorId), 403);

        $this->selectedGroupId = null;
        $this->fieldFilters = [];

        if ($this->selectedBoardId && ! $this->boardOptions()->pluck('id')->contains($this->selectedBoardId)) {
            $this->selectedBoardId = null;
        }

        if ($this->isBoardView() && ! $this->availableBoardSectors()->pluck('id')->contains($this->selectedSectorId)) {
            $this->viewMode = 'list';
        }

        if ($this->isBoardView()) {
            $this->ensureBoardSelected();
        }

        $this->syncCollapsedGroups();
        $this->resetPage();
    }

    public function updatedSelectedBoardId(): void
    {
        if ($this->selectedBoardId) {
            abort_unless($this->boardOptions()->pluck('id')->contains($this->selectedBoardId), 403);
            $this->selectedSectorId = $this->boardOptions()->firstWhere('id', $this->selectedBoardId)?->sector_id;
        }

        $this->selectedGroupId = null;
        $this->fieldFilters = [];
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
            $this->ensureBoardSelected();
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

    public function openManualTicketModal(): void
    {
        $board = $this->manualBoard();

        abort_unless($board && auth()->user()->canOperateBoard($board), 403);

        $this->selectedSectorId = $board->sector_id;
        $this->selectedBoardId = $board->id;
        $this->lastManualTicketId = null;

        $this->manualTicketForm = [
            'title' => '',
            'description' => '',
            'priority' => TicketPriority::MEDIUM->value,
            'ticket_group_id' => (string) ($board->defaultGroup()?->id ?? ''),
            'assignee_id' => '',
            'requester_id' => (string) auth()->id(),
            'dynamic_values' => $this->manualFields($board)
                ->mapWithKeys(fn (TicketField $field) => [$field->id => $field->type->value === 'checkbox' ? false : ''])
                ->all(),
        ];

        $this->resetValidation();
        $this->showManualTicketModal = true;
    }

    public function closeManualTicketModal(): void
    {
        $this->showManualTicketModal = false;
        $this->resetValidation();
    }

    public function createManualTicket(TicketWorkflowService $workflowService): void
    {
        $board = $this->manualBoard();

        abort_unless($board && auth()->user()->canOperateBoard($board), 403);

        $manualFields = $this->manualFields($board);
        $validated = $this->validate($this->manualTicketRules($board, $manualFields));
        $form = $validated['manualTicketForm'];
        $groupId = $this->normalizeGroupId($form['ticket_group_id'] ?? null)
            ?? $board->defaultGroup()?->id;

        if ($groupId !== null) {
            abort_unless($board->groups->pluck('id')->contains($groupId), 404);
        }

        $assigneeId = $this->normalizeNullableId($form['assignee_id'] ?? null);

        if ($assigneeId !== null) {
            $allowedAssigneeIds = $this->boardAssignees($board)->pluck('id')->all();

            if (! in_array($assigneeId, $allowedAssigneeIds, true)) {
                $this->addError('manualTicketForm.assignee_id', 'Selecione um responsavel que tenha acesso a este quadro.');

                return;
            }
        }

        $requesterId = $this->normalizeNullableId($form['requester_id'] ?? null) ?? auth()->id();
        $dynamicValues = collect($form['dynamic_values'] ?? [])
            ->only($manualFields->pluck('id')->map(fn (int $fieldId) => (string) $fieldId)->all())
            ->mapWithKeys(function (mixed $value, string|int $fieldId) use ($manualFields): array {
                $field = $manualFields->firstWhere('id', (int) $fieldId);
                $normalized = $field ? $field->normalizeMaskedValue($value) : $value;

                return [(int) $fieldId => $normalized];
            })
            ->reject(fn (mixed $value) => $value === null || $value === '')
            ->all();

        $ticket = $workflowService->createTicket(auth()->user(), [
            'sector_id' => $board->sector_id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $groupId,
            'ticket_status_id' => $this->legacyStatusIdForGroup($board, $groupId),
            'service_catalog_item_id' => null,
            'room_id' => null,
            'title' => $form['title'],
            'description' => $form['description'] ?? null,
            'requester_id' => $requesterId,
            'assignee_id' => $assigneeId,
            'priority' => $form['priority'],
        ], $dynamicValues, [], [
            'source' => 'manual_board',
        ]);

        $this->lastManualTicketId = $ticket->id;
        $this->showManualTicketModal = false;
        $this->resetPage();
        $this->syncCollapsedGroups();

        session()->flash('status', "Chamado #{$ticket->id} criado manualmente no quadro {$board->name}.");
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->hasOperationalAccess()) {
            session()->flash('status', 'A central de formularios fica disponivel para abrir chamados. O quadro e exclusivo para operadores e gestores.');
            $this->redirectRoute('tickets.central');
        }

        $sectorOptions = $this->availableBoardSectors();
        $boardOptions = $this->boardOptions();
        $groupOptions = $this->groupOptions();
        $fieldOptions = $this->fieldOptions();
        $configBoard = $this->configBoard();
        $manualBoard = $this->manualBoard();
        $manualFields = $manualBoard ? $this->manualFields($manualBoard) : collect();
        $manualAssignees = $manualBoard ? $this->boardAssignees($manualBoard) : collect();
        $manualRequesters = $manualBoard ? $this->manualRequesterOptions($manualBoard) : collect();

        $normalizedFieldFilters = collect($this->fieldFilters)
            ->mapWithKeys(fn ($value, $fieldId) => [(int) $fieldId => is_string($value) ? trim($value) : $value])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        $filterPayload = [
            'sector_id' => $this->selectedSectorId,
            'board_id' => $this->selectedBoardId,
            'title' => trim($this->titleFilter),
            'group_id' => $this->selectedGroupId,
            'requester' => trim($this->requesterFilter),
            'assignee' => trim($this->assigneeFilter),
            'updated_from' => $this->updatedFrom,
            'updated_to' => $this->updatedTo,
            'field_filters' => $normalizedFieldFilters,
        ];

        $ticketQuery = app(TicketIndexQuery::class)->build($user, $filterPayload);
        $allowedBoardIds = $boardOptions->pluck('id')->all();

        if ($allowedBoardIds !== []) {
            $ticketQuery->whereIn('ticket_board_id', $allowedBoardIds);
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

            $assignees = $this->boardAssignees($board);

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
            'boardOptions' => $boardOptions,
            'groupOptions' => $groupOptions,
            'fieldOptions' => $fieldOptions,
            'configBoard' => $configBoard,
            'manualBoard' => $manualBoard,
            'manualFields' => $manualFields,
            'manualAssignees' => $manualAssignees,
            'manualRequesters' => $manualRequesters,
            'priorities' => TicketPriority::cases(),
            'canUpdate' => true,
            'exportParams' => array_filter([
                'sector' => $this->selectedSectorId,
                'board' => $this->selectedBoardId,
                'title' => trim($this->titleFilter),
                'group' => $this->selectedGroupId,
                'requester' => trim($this->requesterFilter),
                'assignee' => trim($this->assigneeFilter),
                'updated_from' => $this->updatedFrom,
                'updated_to' => $this->updatedTo,
            ], fn ($value) => ! is_null($value) && $value !== ''),
            'fieldFiltersForExport' => $normalizedFieldFilters,
        ])->layout('layouts.portal', [
            'title' => 'Quadros',
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
        if (! $this->selectedBoardId) {
            return null;
        }

        $board = TicketBoard::query()
            ->with(['sector.company', 'groups', 'fields.options'])
            ->with('statuses')
            ->where('is_active', true)
            ->find($this->selectedBoardId);

        if (! $board || ! auth()->user()->canOperateBoard($board)) {
            return null;
        }

        if ($this->selectedSectorId && $board->sector_id !== $this->selectedSectorId) {
            return null;
        }

        return $board;
    }

    private function configBoard(): ?TicketBoard
    {
        $selectedBoard = $this->board();

        if ($selectedBoard && auth()->user()->can('update', $selectedBoard)) {
            return $selectedBoard;
        }

        $boards = $this->selectedSectorId
            ? $this->boardOptions($this->selectedSectorId, false)
            : $this->boardOptions(null, false);

        $board = $boards->first(fn (TicketBoard $board) => auth()->user()->can('update', $board));

        if (! $board) {
            return null;
        }

        return TicketBoard::query()
            ->with(['sector.company', 'groups', 'statuses', 'fields.options'])
            ->where('is_active', true)
            ->find($board->id);
    }

    private function manualBoard(): ?TicketBoard
    {
        $selectedBoard = $this->board();

        if ($selectedBoard) {
            return $selectedBoard;
        }

        $boards = $this->selectedSectorId
            ? $this->boardOptions($this->selectedSectorId, false)
            : $this->boardOptions(null, false);

        $board = $boards->first();

        if (! $board) {
            return null;
        }

        return TicketBoard::query()
            ->with(['sector.company', 'groups', 'statuses', 'fields.options'])
            ->where('is_active', true)
            ->find($board->id);
    }

    private function availableBoardSectors(): Collection
    {
        $query = Sector::query()->with('company')->orderBy('name');

        if (! auth()->user()->isSuperAdmin()) {
            $sectorIds = collect(auth()->user()->adminSectorIds())
                ->merge(TicketBoard::query()
                    ->whereIn('id', auth()->user()->operationalBoardIds())
                    ->pluck('sector_id'))
                ->map(fn ($sectorId) => (int) $sectorId)
                ->unique()
                ->values()
                ->all();

            $query->whereIn('id', $sectorIds);
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

    private function ensureBoardSelected(): void
    {
        $allBoards = $this->boardOptions(null, false);

        if ($this->selectedBoardId && ! $allBoards->pluck('id')->contains($this->selectedBoardId)) {
            $this->selectedBoardId = null;
        }

        if ($this->selectedBoardId && ! $this->selectedSectorId) {
            $this->selectedSectorId = $allBoards->firstWhere('id', $this->selectedBoardId)?->sector_id;
        }

        $this->ensureBoardSectorSelected();

        if (! $this->selectedSectorId) {
            $this->selectedBoardId = null;

            return;
        }

        $boards = $this->boardOptions($this->selectedSectorId, false);

        if ($boards->isEmpty()) {
            $this->selectedBoardId = null;
            $this->viewMode = 'list';

            return;
        }

        if (! $this->selectedBoardId || ! $boards->pluck('id')->contains($this->selectedBoardId)) {
            $this->selectedBoardId = $boards->first()?->id;
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
        $boardIds = $this->boardIdsForFilters();

        if ($boardIds->isEmpty()) {
            return collect();
        }

        return TicketGroup::query()
            ->whereIn('ticket_board_id', $boardIds->all())
            ->where('is_active', true)
            ->with('board.sector')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function fieldOptions(): Collection
    {
        $boardIds = $this->boardIdsForFilters();

        if ($boardIds->isEmpty()) {
            return collect();
        }

        return TicketField::query()
            ->where('is_active', true)
            ->where('show_on_board', true)
            ->whereIn('ticket_board_id', $boardIds->all())
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

    private function boardOptions(?int $sectorId = null, bool $useSelectedSector = true): Collection
    {
        $filterSectorId = $sectorId ?? ($useSelectedSector ? $this->selectedSectorId : null);

        if ($filterSectorId && ! $this->availableBoardSectors()->pluck('id')->contains($filterSectorId)) {
            return collect();
        }

        if ($filterSectorId && auth()->user()->isSectorAdmin($filterSectorId) && ! TicketBoard::query()->where('sector_id', $filterSectorId)->exists()) {
            if ($sector = Sector::query()->find($filterSectorId)) {
                app(SectorProvisioningService::class)->provision($sector);
            }
        }

        $query = TicketBoard::query()
            ->with('sector.company')
            ->where('is_active', true);

        if (! auth()->user()->isSuperAdmin()) {
            $query->whereIn('id', auth()->user()->operationalBoardIds());
        }

        if ($filterSectorId) {
            $query->where('sector_id', $filterSectorId);
        }

        return $query
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    private function boardIdsForFilters(): Collection
    {
        $boards = $this->boardOptions(null, false);

        if ($this->selectedSectorId) {
            $boards = $boards->where('sector_id', $this->selectedSectorId)->values();
        }

        if ($this->selectedBoardId) {
            $boards = $boards->where('id', $this->selectedBoardId)->values();
        }

        return $boards->pluck('id')->map(fn ($boardId) => (int) $boardId)->values();
    }

    private function boardAssignees(TicketBoard $board): Collection
    {
        $operatorIds = $board->operators()->pluck('users.id')->all();

        return User::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($board, $operatorIds): void {
                $query->withSectorAccess($board->sector_id, ['sector_admin']);

                if ($operatorIds !== []) {
                    $query->orWhereIn('id', $operatorIds);
                }
            })
            ->orderBy('name')
            ->get();
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

    private function normalizeNullableId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }

    private function manualFields(TicketBoard $board): Collection
    {
        return $board->fields
            ->where('is_active', true)
            ->where('show_on_board', true)
            ->values();
    }

    private function manualRequesterOptions(TicketBoard $board): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($board): void {
                $query
                    ->withSectorAccess($board->sector_id)
                    ->orWhere('id', auth()->id());
            })
            ->orderBy('name')
            ->get();
    }

    private function manualTicketRules(TicketBoard $board, Collection $fields): array
    {
        $rules = [
            'manualTicketForm.title' => ['required', 'string', 'max:160'],
            'manualTicketForm.description' => ['nullable', 'string'],
            'manualTicketForm.priority' => ['required', Rule::enum(TicketPriority::class)],
            'manualTicketForm.ticket_group_id' => [
                'nullable',
                'integer',
                Rule::exists('ticket_groups', 'id')->where('ticket_board_id', $board->id),
            ],
            'manualTicketForm.assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'manualTicketForm.requester_id' => ['required', 'integer', 'exists:users,id'],
            'manualTicketForm.dynamic_values' => ['array'],
        ];

        foreach ($fields as $field) {
            $fieldRules = [$field->is_required ? 'required' : 'nullable'];

            $fieldRules[] = match ($field->type->value) {
                'number' => 'numeric',
                'date' => 'date',
                'checkbox' => 'boolean',
                default => 'string',
            };

            $rules["manualTicketForm.dynamic_values.{$field->id}"] = $fieldRules;
        }

        return $rules;
    }

    private function legacyStatusIdForGroup(TicketBoard $board, ?int $groupId): ?int
    {
        if (! $groupId) {
            return $board->statuses->firstWhere('is_default', true)?->id
                ?? $board->statuses->first()?->id;
        }

        $group = $board->groups->firstWhere('id', $groupId);

        if (! $group) {
            return $board->statuses->firstWhere('is_default', true)?->id
                ?? $board->statuses->first()?->id;
        }

        return $board->statuses->firstWhere('sort_order', $group->sort_order)?->id
            ?? $board->statuses->firstWhere('is_closed', $group->is_closed)?->id
            ?? $board->statuses->first()?->id;
    }
}
