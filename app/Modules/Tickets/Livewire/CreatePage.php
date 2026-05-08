<?php

namespace App\Modules\Tickets\Livewire;

use App\Enums\TicketPriority;
use App\Models\User;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\ServiceCatalogItem;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Models\TicketField;
use App\Modules\Tickets\Models\TicketForm;
use App\Modules\Tickets\Services\SectorProvisioningService;
use App\Modules\Tickets\Services\TicketCreationSuggestionService;
use App\Modules\Tickets\Services\TicketWorkflowService;
use App\Modules\Tickets\Support\TicketAttachmentRules;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreatePage extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public ?int $selectedSectorId = null;

    public ?int $selectedBoardId = null;

    public ?int $selectedCatalogId = null;

    public ?int $selectedFormId = null;

    public string $title = '';

    public string $description = '';

    public string $priority = 'medium';

    public array $dynamicValues = [];

    public array $attachments = [];

    public array $suggestions = [
        'articles' => [],
        'similar_tickets' => [],
        'previous_solutions' => [],
    ];

    public bool $contextSelectionLocked = false;

    public function mount(?ServiceCatalogItem $catalogItem = null): void
    {
        if ($catalogItem && ! $catalogItem->exists) {
            $catalogItem = null;
        }

        if ($catalogItem) {
            abort_unless($catalogItem->is_active && $catalogItem->form?->is_active, 404);
            abort_unless($catalogItem->form?->canBeOpenedBy(auth()->user()), 403);

            $this->selectedSectorId = $catalogItem->board?->sector_id;
            $this->selectedBoardId = $catalogItem->ticket_board_id;
            $this->selectedCatalogId = $catalogItem->id;
            $this->selectedFormId = $catalogItem->ticket_form_id;
            $this->priority = $catalogItem->default_priority?->value ?? TicketPriority::MEDIUM->value;
            $this->contextSelectionLocked = true;
        } else {
            $requestedBoardId = request()->integer('board');
            $requestedFormId = request()->integer('form');
            $requestedBoard = $requestedBoardId
                ? TicketBoard::query()->where('is_active', true)->find($requestedBoardId)
                : null;

            if ($requestedBoard && $this->availableSectors()->pluck('id')->contains($requestedBoard->sector_id)) {
                $this->selectedSectorId = $requestedBoard->sector_id;
                $this->selectedBoardId = $requestedBoard->id;
            } else {
                $this->selectedSectorId = $this->resolveSectorId(request()->integer('sector'));
                $this->selectedBoardId = $this->resolveBoardId($requestedBoardId);
            }

            $this->selectedFormId = $this->resolveFormId($requestedFormId);
            $this->selectedCatalogId = $this->resolveCatalogIdForForm($this->selectedFormId);
            $this->contextSelectionLocked = (bool) (
                $requestedFormId
                && $this->selectedSectorId
                && $this->selectedBoardId
                && $this->selectedFormId
            );
        }
    }

    public function updatedSelectedSectorId(): void
    {
        $this->selectedBoardId = $this->boardOptions()->first()?->id;
        $this->selectedCatalogId = null;
        $this->selectedFormId = null;
        $this->dynamicValues = [];
        $this->priority = TicketPriority::MEDIUM->value;
        $this->refreshSuggestions();
    }

    public function updatedSelectedBoardId(): void
    {
        if ($this->selectedBoardId) {
            abort_unless($this->boardOptions()->pluck('id')->contains($this->selectedBoardId), 403);
        }

        $this->selectedCatalogId = null;
        $this->selectedFormId = null;
        $this->dynamicValues = [];
        $this->priority = TicketPriority::MEDIUM->value;
        $this->refreshSuggestions();
    }

    public function updatedSelectedFormId(): void
    {
        $this->selectedCatalogId = $this->resolveCatalogIdForForm($this->selectedFormId);
        $this->dynamicValues = [];

        $catalog = $this->selectedCatalog();
        $this->priority = $catalog?->default_priority?->value ?? TicketPriority::MEDIUM->value;
        $this->refreshSuggestions();
    }

    public function updatedSelectedCatalogId(): void
    {
        $catalog = $this->selectedCatalog();
        $this->selectedFormId = $catalog?->ticket_form_id;

        if ($catalog?->default_priority) {
            $this->priority = $catalog->default_priority->value;
        } else {
            $this->priority = TicketPriority::MEDIUM->value;
        }

        $this->refreshSuggestions();
    }

    public function updatedTitle(): void
    {
        $this->refreshSuggestions();
    }

    public function updatedDescription(): void
    {
        $this->refreshSuggestions();
    }

    public function updatedDynamicValues($value, $key): void
    {
        unset($value, $key);

        $this->sanitizeDynamicValuesForVisibility();
    }

    public function submit(TicketWorkflowService $workflowService)
    {
        $catalog = $this->selectedCatalog();
        $board = $this->board();
        $form = $this->selectedForm();
        $allFields = $form?->fields ?? collect();
        $visibleFields = $this->visibleFormFields($allFields);

        abort_unless($board, 404);
        abort_if($this->selectedFormId !== null && $form === null, 403);

        $this->sanitizeDynamicValuesForVisibility($allFields);
        $validated = $this->validate($this->rules($visibleFields));
        $attachments = TicketAttachmentRules::validate($validated['attachments'] ?? [], 'attachments');
        $groupId = $catalog?->default_ticket_group_id
            ?? $board->defaultGroup()?->id;
        $legacyStatusId = $this->legacyStatusIdForGroup($board, $groupId);

        $ticket = $workflowService->createTicket(auth()->user(), [
            'sector_id' => $board->sector_id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $groupId,
            'ticket_status_id' => $legacyStatusId,
            'service_catalog_item_id' => $catalog?->id,
            'room_id' => null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'requester_id' => auth()->id(),
            'priority' => $validated['priority'],
        ], $this->visibleDynamicValues($visibleFields), $attachments);

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('status', 'Chamado '.$ticket->fullReference().' criado com sucesso.');
    }

    public function render(): View
    {
        $catalog = $this->selectedCatalog();
        $form = $this->selectedForm();
        $allFormFields = $form?->fields?->sortBy('pivot.sort_order')->values() ?? collect();
        $this->sanitizeDynamicValuesForVisibility($allFormFields);
        $formFields = $this->visibleFormFields($allFormFields);
        $sectorOptions = $this->availableSectors();
        $selectedSector = $sectorOptions->firstWhere('id', $this->selectedSectorId);

        return view('livewire.tickets.create-page', [
            'sectorOptions' => $sectorOptions,
            'boardOptions' => $this->boardOptions(),
            'formOptions' => $this->formOptions(),
            'sectorUsers' => $this->sectorUsers(),
            'priorities' => TicketPriority::cases(),
            'formFields' => $formFields,
            'selectedSector' => $selectedSector,
            'selectedBoard' => $this->board(),
            'selectedForm' => $form,
            'selectedCatalog' => $catalog,
            'contextSelectionLocked' => $this->contextSelectionLocked,
            'suggestions' => $this->suggestions,
        ])->layout('layouts.portal', [
            'title' => 'Abrir chamado',
            'subtitle' => 'Preencha o formulario guiado para abrir o chamado certo com menos atrito.',
            'portalMode' => 'focused-form',
        ]);
    }

    private function rules(Collection $fields): array
    {
        $rules = [
            'selectedSectorId' => ['required', 'exists:sectors,id'],
            'selectedBoardId' => ['required', 'exists:ticket_boards,id'],
            'selectedFormId' => ['required', 'exists:ticket_forms,id'],
            'selectedCatalogId' => ['nullable', 'exists:service_catalog_items,id'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'string'],
            ...TicketAttachmentRules::validationRules('attachments'),
        ];

        foreach ($fields as $field) {
            $fieldRules = ['nullable'];

            if ($field->pivot?->is_required || $field->is_required) {
                $fieldRules = ['required'];
            }

            $fieldRules[] = match ($field->type->value) {
                'number' => 'numeric',
                'date' => 'date',
                'checkbox' => 'boolean',
                default => 'string',
            };

            $rules["dynamicValues.{$field->id}"] = $fieldRules;
        }

        return $rules;
    }

    private function selectedForm(): ?TicketForm
    {
        if (! $this->selectedFormId) {
            return null;
        }

        return TicketForm::query()
            ->accessibleTo(auth()->user(), $this->selectedSectorId)
            ->with([
                'fields.options',
                'fields',
                'board.groups',
                'board.statuses',
            ])
            ->where('ticket_board_id', $this->selectedBoardId)
            ->where('is_active', true)
            ->find($this->selectedFormId);
    }

    private function selectedCatalog(): ?ServiceCatalogItem
    {
        if (! $this->selectedCatalogId) {
            return null;
        }

        return ServiceCatalogItem::query()
            ->with([
                'form.fields.options',
                'form.fields',
                'board.groups',
                'board.statuses',
            ])
            ->whereHas('form', fn ($query) => $query->accessibleTo(auth()->user(), $this->selectedSectorId))
            ->where('ticket_board_id', $this->selectedBoardId)
            ->where('is_active', true)
            ->find($this->selectedCatalogId);
    }

    private function board(): ?TicketBoard
    {
        if (! $this->selectedBoardId) {
            return null;
        }

        $board = TicketBoard::query()
            ->with(['groups', 'statuses', 'catalogItems'])
            ->where('is_active', true)
            ->find($this->selectedBoardId);

        if ($board) {
            return $board;
        }

        $sector = Sector::query()->find($this->selectedSectorId);

        if (! $sector) {
            return null;
        }

        app(SectorProvisioningService::class)->provision($sector);

        $fallbackBoard = TicketBoard::query()
            ->with(['groups', 'statuses', 'catalogItems'])
            ->where('sector_id', $this->selectedSectorId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();

        $this->selectedBoardId = $fallbackBoard?->id;

        return $fallbackBoard;
    }

    private function availableSectors(): Collection
    {
        $query = Sector::query()->with('company')->orderBy('name');

        return $query->where('is_active', true)->get();
    }

    private function formOptions(): Collection
    {
        if (! $this->selectedSectorId || ! $this->selectedBoardId) {
            return collect();
        }

        return TicketForm::query()
            ->accessibleTo(auth()->user(), $this->selectedSectorId)
            ->where('ticket_board_id', $this->selectedBoardId)
            ->with(['catalogItems' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function boardOptions(): Collection
    {
        if (! $this->selectedSectorId) {
            return collect();
        }

        if (! TicketBoard::query()->where('sector_id', $this->selectedSectorId)->exists()) {
            if ($sector = Sector::query()->find($this->selectedSectorId)) {
                app(SectorProvisioningService::class)->provision($sector);
            }
        }

        return TicketBoard::query()
            ->where('sector_id', $this->selectedSectorId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    private function sectorUsers(): Collection
    {
        if (! $this->selectedSectorId) {
            return collect();
        }

        return User::query()
            ->withSectorAccess($this->selectedSectorId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function resolveSectorId(?int $requestedSectorId = null): ?int
    {
        if ($requestedSectorId && $this->availableSectors()->pluck('id')->contains($requestedSectorId)) {
            return $requestedSectorId;
        }

        return null;
    }

    private function resolveBoardId(?int $requestedBoardId = null): ?int
    {
        if (! $this->selectedSectorId) {
            return null;
        }

        $boards = $this->boardOptions();

        if ($requestedBoardId && $boards->pluck('id')->contains($requestedBoardId)) {
            return $requestedBoardId;
        }

        return $boards->first()?->id;
    }

    private function resolveFormId(?int $requestedFormId = null): ?int
    {
        if (! $requestedFormId || ! $this->selectedSectorId || ! $this->selectedBoardId) {
            return null;
        }

        return TicketForm::query()
            ->accessibleTo(auth()->user(), $this->selectedSectorId)
            ->where('ticket_board_id', $this->selectedBoardId)
            ->where('is_active', true)
            ->whereKey($requestedFormId)
            ->value('id');
    }

    private function resolveCatalogIdForForm(?int $formId = null): ?int
    {
        if (! $formId || ! $this->selectedSectorId || ! $this->selectedBoardId) {
            return null;
        }

        return ServiceCatalogItem::query()
            ->whereHas('form', fn ($query) => $query->accessibleTo(auth()->user(), $this->selectedSectorId))
            ->where('ticket_board_id', $this->selectedBoardId)
            ->where('ticket_form_id', $formId)
            ->where('is_active', true)
            ->orderBy('name')
            ->value('id');
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

    private function refreshSuggestions(): void
    {
        /** @var TicketCreationSuggestionService $service */
        $service = app(TicketCreationSuggestionService::class);

        $this->suggestions = $service->suggest(
            auth()->user(),
            $this->selectedSectorId,
            $this->title,
            $this->description,
        );
    }

    private function visibleFormFields(Collection $fields): Collection
    {
        return $fields
            ->sortBy('pivot.sort_order')
            ->values()
            ->filter(fn (TicketField $field) => $this->isFieldVisible($field, $fields))
            ->values();
    }

    private function isFieldVisible(TicketField $field, Collection $fields, array $visited = []): bool
    {
        $parentFieldId = $field->pivot?->visibility_parent_field_id;

        if (! $parentFieldId) {
            return true;
        }

        if (in_array($field->id, $visited, true)) {
            return false;
        }

        /** @var TicketField|null $parentField */
        $parentField = $fields->firstWhere('id', $parentFieldId);

        if (! $parentField) {
            return false;
        }

        if (! $this->isFieldVisible($parentField, $fields, [...$visited, $field->id])) {
            return false;
        }

        return $this->fieldMatchesVisibilityCondition(
            $parentField,
            $field->pivot?->visibility_operator,
            $field->pivot?->visibility_expected_value,
            $this->dynamicValues[$parentFieldId] ?? null,
        );
    }

    private function fieldMatchesVisibilityCondition(TicketField $parentField, ?string $operator, mixed $expectedValue, mixed $actualValue): bool
    {
        if ($operator !== 'equals') {
            return true;
        }

        return $this->normalizeVisibilityComparableValue($parentField, $actualValue)
            === $this->normalizeVisibilityComparableValue($parentField, $expectedValue);
    }

    private function normalizeVisibilityComparableValue(TicketField $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($field->type->value === 'checkbox') {
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            return in_array((string) $value, ['1', 'true', 'on', 'yes'], true) ? '1' : '0';
        }

        $normalized = $field->normalizeMaskedValue($value);

        return is_scalar($normalized) ? (string) $normalized : null;
    }

    private function sanitizeDynamicValuesForVisibility(?Collection $fields = null): void
    {
        $fields ??= $this->selectedForm()?->fields ?? collect();

        if ($fields->isEmpty()) {
            return;
        }

        $visibleFieldIds = $this->visibleFormFields($fields)->pluck('id')->all();
        $hiddenFieldIds = $fields->pluck('id')->reject(fn (int $fieldId) => in_array($fieldId, $visibleFieldIds, true));

        foreach ($hiddenFieldIds as $fieldId) {
            if (array_key_exists($fieldId, $this->dynamicValues)) {
                unset($this->dynamicValues[$fieldId]);
            }

            $this->resetValidation("dynamicValues.{$fieldId}");
        }
    }

    private function visibleDynamicValues(Collection $visibleFields): array
    {
        return collect($this->dynamicValues)
            ->only($visibleFields->pluck('id')->map(fn (int $fieldId) => (string) $fieldId)->all())
            ->all();
    }
}
