<?php

namespace App\Modules\Tickets\Livewire;

use App\Enums\TicketAutomationActionType;
use App\Enums\TicketAutomationConditionField;
use App\Enums\TicketAutomationConditionOperator;
use App\Enums\TicketAutomationTrigger;
use App\Enums\TicketFieldType;
use App\Enums\TicketPriority;
use App\Models\User;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\ServiceCatalogItem;
use App\Modules\Tickets\Models\TicketAutomationRule;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Models\TicketField;
use App\Modules\Tickets\Models\TicketFieldOption;
use App\Modules\Tickets\Models\TicketForm;
use App\Modules\Tickets\Models\TicketGroup;
use App\Modules\Tickets\Models\TicketStatus;
use App\Modules\Tickets\Services\SectorProvisioningService;
use App\Modules\Tickets\Services\TicketSlaService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class SettingsPage extends Component
{
    use AuthorizesRequests;

    public ?int $selectedSectorId = null;

    public string $boardName = '';

    public string $boardDescription = '';

    public bool $slaIsActive = true;

    public array $slaTargets = [];

    public array $groupForm = [
        'name' => '',
        'color' => '#2563eb',
        'is_collapsed_by_default' => false,
        'is_active' => true,
    ];

    public array $editGroupForm = [
        'name' => '',
        'color' => '#2563eb',
        'is_collapsed_by_default' => false,
        'is_active' => true,
    ];

    public ?int $editingGroupId = null;

    public array $statusForm = [
        'name' => '',
        'color' => '#2563eb',
        'is_default' => false,
        'is_closed' => false,
        'is_active' => true,
    ];

    public array $editStatusForm = [
        'name' => '',
        'color' => '#2563eb',
        'is_default' => false,
        'is_closed' => false,
        'is_active' => true,
    ];

    public ?int $editingStatusId = null;

    public array $fieldForm = [
        'name' => '',
        'type' => 'text',
        'placeholder' => '',
        'help_text' => '',
        'is_required' => false,
        'is_active' => true,
        'options_text' => '',
    ];

    public array $formForm = [
        'name' => '',
        'description' => '',
        'field_ids' => [],
        'required_field_ids' => [],
        'is_default' => false,
        'is_active' => true,
    ];

    public array $catalogForm = [
        'name' => '',
        'description' => '',
        'ticket_form_id' => null,
        'default_ticket_group_id' => null,
        'default_priority' => 'medium',
        'is_active' => true,
    ];

    public ?string $pendingDeletionType = null;

    public ?int $pendingDeletionId = null;

    public array $deletionContext = [];

    public array $replacementSelection = [
        'group_ticket_group_id' => '',
        'group_catalog_group_id' => '',
        'status_replacement_id' => '',
    ];

    public ?int $editingAutomationRuleId = null;

    public array $automationForm = [
        'name' => '',
        'description' => '',
        'trigger' => 'ticket_created',
        'sort_order' => 1,
        'cooldown_minutes' => null,
        'inactive_for_minutes' => null,
        'is_active' => true,
    ];

    public array $automationConditions = [];

    public array $automationActions = [];

    public function mount(?Sector $sector = null): void
    {
        $this->selectedSectorId = $sector?->id
            ?? $this->availableSectors()->first()?->id;

        abort_if(! $this->selectedSectorId, 403);

        $this->loadBoardMeta();
        $this->resetForms();
    }

    public function updatedSelectedSectorId(): void
    {
        abort_unless($this->availableSectors()->pluck('id')->contains($this->selectedSectorId), 403);
        $this->loadBoardMeta();
        $this->resetForms();
        $this->cancelBoardMaintenanceState();
    }

    public function updatedAutomationFormTrigger(string $trigger): void
    {
        if ($trigger !== TicketAutomationTrigger::TICKET_INACTIVE->value) {
            $this->automationForm['inactive_for_minutes'] = null;
        }
    }

    public function saveBoardMeta(): void
    {
        $validated = $this->validate([
            'boardName' => ['required', 'string', 'max:120'],
            'boardDescription' => ['nullable', 'string'],
        ]);

        $board = $this->board();
        $this->authorize('update', $board);

        $board->update([
            'name' => $validated['boardName'],
            'description' => $validated['boardDescription'] ?: null,
        ]);

        session()->flash('status', 'Quadro atualizado com sucesso.');
    }

    public function saveSlaPolicy(TicketSlaService $ticketSlaService): void
    {
        $rules = ['slaIsActive' => ['boolean']];

        foreach (TicketPriority::cases() as $priority) {
            $rules["slaTargets.{$priority->value}.first_response_minutes"] = ['nullable', 'integer', 'min:1'];
            $rules["slaTargets.{$priority->value}.resolution_minutes"] = ['nullable', 'integer', 'min:1'];
        }

        $validated = $this->validate($rules);

        $board = $this->board();
        $this->authorize('update', $board);

        $ticketSlaService->syncPolicy($board, $validated['slaTargets'] ?? [], (bool) $validated['slaIsActive']);
        $this->loadBoardMeta();

        session()->flash('status', 'Politica de SLA atualizada com sucesso.');
    }

    public function addGroup(): void
    {
        $validated = $this->validate([
            'groupForm.name' => ['required', 'string', 'max:80'],
            'groupForm.color' => ['required', 'string', 'max:20'],
            'groupForm.is_collapsed_by_default' => ['boolean'],
            'groupForm.is_active' => ['boolean'],
        ]);

        $board = $this->board();
        $this->authorize('update', $board);

        TicketGroup::query()->create([
            'ticket_board_id' => $board->id,
            'name' => $validated['groupForm']['name'],
            'slug' => $this->uniqueSlug(TicketGroup::class, $board->id, $validated['groupForm']['name']),
            'color' => $validated['groupForm']['color'],
            'sort_order' => ((int) $board->groups()->max('sort_order')) + 1,
            'is_collapsed_by_default' => (bool) $validated['groupForm']['is_collapsed_by_default'],
            'is_active' => (bool) $validated['groupForm']['is_active'],
        ]);

        $this->groupForm = [
            'name' => '',
            'color' => '#2563eb',
            'is_collapsed_by_default' => false,
            'is_active' => true,
        ];

        session()->flash('status', 'Grupo criado com sucesso.');
    }

    public function startEditingGroup(int $groupId): void
    {
        $group = $this->groupForBoard($groupId);

        $this->editingGroupId = $group->id;
        $this->editGroupForm = [
            'name' => $group->name,
            'color' => $group->color,
            'is_collapsed_by_default' => $group->is_collapsed_by_default,
            'is_active' => $group->is_active,
        ];

        $this->cancelDeletion();
        $this->resetValidation();
    }

    public function cancelEditingGroup(): void
    {
        $this->editingGroupId = null;
        $this->editGroupForm = [
            'name' => '',
            'color' => '#2563eb',
            'is_collapsed_by_default' => false,
            'is_active' => true,
        ];
        $this->resetValidation();
    }

    public function updateGroup(): void
    {
        $validated = $this->validate([
            'editGroupForm.name' => ['required', 'string', 'max:80'],
            'editGroupForm.color' => ['required', 'string', 'max:20'],
            'editGroupForm.is_collapsed_by_default' => ['boolean'],
            'editGroupForm.is_active' => ['boolean'],
        ]);

        $group = $this->groupForBoard($this->editingGroupId);

        $group->update([
            'name' => $validated['editGroupForm']['name'],
            'color' => $validated['editGroupForm']['color'],
            'is_collapsed_by_default' => (bool) $validated['editGroupForm']['is_collapsed_by_default'],
            'is_active' => (bool) $validated['editGroupForm']['is_active'],
        ]);

        $this->cancelEditingGroup();
        $this->loadBoardMeta();

        session()->flash('status', 'Grupo atualizado com sucesso.');
    }

    public function moveGroupUp(int $groupId): void
    {
        $this->moveOrderedItem($this->groupForBoard($groupId), 'up');
        $this->loadBoardMeta();

        session()->flash('status', 'Ordem dos grupos atualizada.');
    }

    public function moveGroupDown(int $groupId): void
    {
        $this->moveOrderedItem($this->groupForBoard($groupId), 'down');
        $this->loadBoardMeta();

        session()->flash('status', 'Ordem dos grupos atualizada.');
    }

    public function confirmDeleteGroup(int $groupId): void
    {
        $group = $this->groupForBoard($groupId);

        $this->pendingDeletionType = 'group';
        $this->pendingDeletionId = $group->id;
        $this->deletionContext = array_merge($this->groupImpact($group), [
            'replacement_groups' => $group->board->groups()
                ->whereKeyNot($group->id)
                ->get(['id', 'name'])
                ->map(fn (TicketGroup $replacement) => [
                    'id' => $replacement->id,
                    'name' => $replacement->name,
                ])
                ->all(),
        ]);
        $this->replacementSelection = [
            'group_ticket_group_id' => '',
            'group_catalog_group_id' => '',
            'status_replacement_id' => '',
        ];

        $this->cancelEditingGroup();
        $this->cancelEditingStatus();
    }

    public function deleteGroup(): void
    {
        abort_unless($this->pendingDeletionType === 'group', 404);

        $group = $this->groupForBoard($this->pendingDeletionId);
        $ticketReplacement = $this->nullableReplacementId($this->replacementSelection['group_ticket_group_id'] ?? '');
        $catalogReplacement = $this->nullableReplacementId($this->replacementSelection['group_catalog_group_id'] ?? '');

        if ($ticketReplacement !== null) {
            $this->ensureGroupReplacementBelongsToBoard($group->ticket_board_id, $ticketReplacement, $group->id);
        }

        if ($catalogReplacement !== null) {
            $this->ensureGroupReplacementBelongsToBoard($group->ticket_board_id, $catalogReplacement, $group->id);
        }

        DB::transaction(function () use ($group, $ticketReplacement, $catalogReplacement) {
            $group->tickets()->update(['ticket_group_id' => $ticketReplacement]);
            $group->board->catalogItems()->where('default_ticket_group_id', $group->id)->update([
                'default_ticket_group_id' => $catalogReplacement,
            ]);
            $group->delete();
            $this->normalizeSortOrder(TicketGroup::class, $group->ticket_board_id);
        });

        $this->cancelDeletion();
        $this->loadBoardMeta();

        session()->flash('status', 'Grupo removido com sucesso.');
    }

    public function addStatus(): void
    {
        $validated = $this->validate([
            'statusForm.name' => ['required', 'string', 'max:80'],
            'statusForm.color' => ['required', 'string', 'max:20'],
            'statusForm.is_default' => ['boolean'],
            'statusForm.is_closed' => ['boolean'],
            'statusForm.is_active' => ['boolean'],
        ]);

        $board = $this->board();
        $this->authorize('update', $board);

        DB::transaction(function () use ($validated, $board) {
            $hasActiveDefault = $board->statuses()->where('is_active', true)->where('is_default', true)->exists();
            $shouldBeDefault = (bool) $validated['statusForm']['is_default'] || ! $hasActiveDefault;
            $isActive = $shouldBeDefault ? true : (bool) $validated['statusForm']['is_active'];

            if ($shouldBeDefault) {
                $board->statuses()->update(['is_default' => false]);
            }

            TicketStatus::query()->create([
                'ticket_board_id' => $board->id,
                'name' => $validated['statusForm']['name'],
                'slug' => $this->uniqueSlug(TicketStatus::class, $board->id, $validated['statusForm']['name']),
                'color' => $validated['statusForm']['color'],
                'sort_order' => ((int) $board->statuses()->max('sort_order')) + 1,
                'is_default' => $shouldBeDefault,
                'is_closed' => (bool) $validated['statusForm']['is_closed'],
                'is_active' => $isActive,
            ]);
        });

        $this->statusForm = [
            'name' => '',
            'color' => '#2563eb',
            'is_default' => false,
            'is_closed' => false,
            'is_active' => true,
        ];

        session()->flash('status', 'Status criado com sucesso.');
    }

    public function startEditingStatus(int $statusId): void
    {
        $status = $this->statusForBoard($statusId);

        $this->editingStatusId = $status->id;
        $this->editStatusForm = [
            'name' => $status->name,
            'color' => $status->color,
            'is_default' => $status->is_default,
            'is_closed' => $status->is_closed,
            'is_active' => $status->is_active,
        ];

        $this->cancelDeletion();
        $this->resetValidation();
    }

    public function cancelEditingStatus(): void
    {
        $this->editingStatusId = null;
        $this->editStatusForm = [
            'name' => '',
            'color' => '#2563eb',
            'is_default' => false,
            'is_closed' => false,
            'is_active' => true,
        ];
        $this->resetValidation();
    }

    public function updateStatus(): void
    {
        $validated = $this->validate([
            'editStatusForm.name' => ['required', 'string', 'max:80'],
            'editStatusForm.color' => ['required', 'string', 'max:20'],
            'editStatusForm.is_default' => ['boolean'],
            'editStatusForm.is_closed' => ['boolean'],
            'editStatusForm.is_active' => ['boolean'],
        ]);

        $status = $this->statusForBoard($this->editingStatusId);
        $requestedDefault = (bool) $validated['editStatusForm']['is_default'];
        $requestedActive = $requestedDefault ? true : (bool) $validated['editStatusForm']['is_active'];

        if ($status->is_default && ! $requestedDefault) {
            session()->flash('error', 'O board precisa manter exatamente um status padrao ativo.');

            return;
        }

        if ($status->is_default && ! $requestedActive) {
            session()->flash('error', 'O status padrao nao pode ser inativado sem definir outro padrao ativo.');

            return;
        }

        DB::transaction(function () use ($validated, $status, $requestedDefault, $requestedActive) {
            if ($requestedDefault) {
                $status->board->statuses()->whereKeyNot($status->id)->update(['is_default' => false]);
            }

            $status->update([
                'name' => $validated['editStatusForm']['name'],
                'color' => $validated['editStatusForm']['color'],
                'is_default' => $requestedDefault,
                'is_closed' => (bool) $validated['editStatusForm']['is_closed'],
                'is_active' => $requestedActive,
            ]);

            $this->ensureSingleActiveDefaultStatus(
                $status->ticket_board_id,
                $requestedDefault ? $status->id : null,
            );
        });

        $this->cancelEditingStatus();
        $this->loadBoardMeta();

        session()->flash('status', 'Status atualizado com sucesso.');
    }

    public function moveStatusUp(int $statusId): void
    {
        $this->moveOrderedItem($this->statusForBoard($statusId), 'up');
        $this->loadBoardMeta();

        session()->flash('status', 'Ordem dos status atualizada.');
    }

    public function moveStatusDown(int $statusId): void
    {
        $this->moveOrderedItem($this->statusForBoard($statusId), 'down');
        $this->loadBoardMeta();

        session()->flash('status', 'Ordem dos status atualizada.');
    }

    public function confirmDeleteStatus(int $statusId): void
    {
        $status = $this->statusForBoard($statusId);

        $this->pendingDeletionType = 'status';
        $this->pendingDeletionId = $status->id;
        $this->deletionContext = array_merge($this->statusImpact($status), [
            'replacement_statuses' => $status->board->statuses()
                ->whereKeyNot($status->id)
                ->where('is_active', true)
                ->get(['id', 'name', 'is_default'])
                ->map(fn (TicketStatus $replacement) => [
                    'id' => $replacement->id,
                    'name' => $replacement->name,
                    'is_default' => $replacement->is_default,
                ])
                ->all(),
        ]);
        $this->replacementSelection = [
            'group_ticket_group_id' => '',
            'group_catalog_group_id' => '',
            'status_replacement_id' => '',
        ];

        $this->cancelEditingGroup();
        $this->cancelEditingStatus();
    }

    public function deleteStatus(): void
    {
        abort_unless($this->pendingDeletionType === 'status', 404);

        $status = $this->statusForBoard($this->pendingDeletionId);
        $replacementId = $this->replacementSelection['status_replacement_id'] ?? '';

        if ($replacementId === '') {
            session()->flash('error', 'Selecione um status substituto para continuar.');

            return;
        }

        $replacement = TicketStatus::query()
            ->where('ticket_board_id', $status->ticket_board_id)
            ->where('is_active', true)
            ->whereKey($replacementId)
            ->whereKeyNot($status->id)
            ->first();

        if (! $replacement) {
            session()->flash('error', 'O status substituto precisa ser ativo e pertencer a este board.');

            return;
        }

        DB::transaction(function () use ($status, $replacement) {
            $status->tickets()->update(['ticket_status_id' => $replacement->id]);

            if ($status->is_default || ! $status->board->statuses()
                ->whereKeyNot($status->id)
                ->where('is_active', true)
                ->where('is_default', true)
                ->exists()) {
                $status->board->statuses()->update(['is_default' => false]);
                $replacement->forceFill(['is_default' => true, 'is_active' => true])->save();
            }

            $status->delete();
            $this->normalizeSortOrder(TicketStatus::class, $status->ticket_board_id);
            $this->ensureSingleActiveDefaultStatus($status->ticket_board_id, $replacement->id);
        });

        $this->cancelDeletion();
        $this->loadBoardMeta();

        session()->flash('status', 'Status removido com sucesso.');
    }

    public function cancelDeletion(): void
    {
        $this->pendingDeletionType = null;
        $this->pendingDeletionId = null;
        $this->deletionContext = [];
        $this->replacementSelection = [
            'group_ticket_group_id' => '',
            'group_catalog_group_id' => '',
            'status_replacement_id' => '',
        ];
    }

    public function addField(): void
    {
        $validated = $this->validate([
            'fieldForm.name' => ['required', 'string', 'max:80'],
            'fieldForm.type' => ['required', Rule::enum(TicketFieldType::class)],
            'fieldForm.placeholder' => ['nullable', 'string', 'max:120'],
            'fieldForm.help_text' => ['nullable', 'string'],
            'fieldForm.is_required' => ['boolean'],
            'fieldForm.is_active' => ['boolean'],
            'fieldForm.options_text' => ['nullable', 'string'],
        ]);

        $board = $this->board();
        $this->authorize('update', $board);

        $field = TicketField::query()->create([
            'ticket_board_id' => $board->id,
            'name' => $validated['fieldForm']['name'],
            'slug' => $this->uniqueSlug(TicketField::class, $board->id, $validated['fieldForm']['name']),
            'type' => $validated['fieldForm']['type'],
            'placeholder' => $validated['fieldForm']['placeholder'] ?: null,
            'help_text' => $validated['fieldForm']['help_text'] ?: null,
            'settings' => null,
            'sort_order' => ((int) $board->fields()->max('sort_order')) + 1,
            'is_required' => (bool) $validated['fieldForm']['is_required'],
            'is_active' => (bool) $validated['fieldForm']['is_active'],
        ]);

        foreach ($this->parsedOptions($validated['fieldForm']['options_text']) as $index => $option) {
            TicketFieldOption::query()->create([
                'ticket_field_id' => $field->id,
                'label' => $option['label'],
                'value' => $option['value'],
                'color' => $option['color'],
                'sort_order' => $index + 1,
                'is_default' => false,
            ]);
        }

        $this->fieldForm = [
            'name' => '',
            'type' => 'text',
            'placeholder' => '',
            'help_text' => '',
            'is_required' => false,
            'is_active' => true,
            'options_text' => '',
        ];

        session()->flash('status', 'Campo criado com sucesso.');
    }

    public function deleteField(int $fieldId): void
    {
        $field = TicketField::query()->findOrFail($fieldId);
        $this->authorize('update', $field->board);
        $field->delete();

        session()->flash('status', 'Campo removido com sucesso.');
    }

    public function addForm(): void
    {
        $validated = $this->validate([
            'formForm.name' => ['required', 'string', 'max:80'],
            'formForm.description' => ['nullable', 'string'],
            'formForm.field_ids' => ['array'],
            'formForm.field_ids.*' => ['integer', 'exists:ticket_fields,id'],
            'formForm.required_field_ids' => ['array'],
            'formForm.required_field_ids.*' => ['integer', 'exists:ticket_fields,id'],
            'formForm.is_default' => ['boolean'],
            'formForm.is_active' => ['boolean'],
        ]);

        $board = $this->board();
        $this->authorize('update', $board);

        if ($validated['formForm']['is_default']) {
            $board->forms()->update(['is_default' => false]);
        }

        $form = TicketForm::query()->create([
            'ticket_board_id' => $board->id,
            'name' => $validated['formForm']['name'],
            'description' => $validated['formForm']['description'] ?: null,
            'is_default' => (bool) $validated['formForm']['is_default'],
            'is_active' => (bool) $validated['formForm']['is_active'],
        ]);

        $syncPayload = collect($validated['formForm']['field_ids'])
            ->mapWithKeys(fn (int $fieldId, int $index) => [
                $fieldId => [
                    'is_required' => in_array($fieldId, $validated['formForm']['required_field_ids'], true),
                    'sort_order' => $index + 1,
                ],
            ])
            ->all();

        $form->fields()->sync($syncPayload);

        $this->formForm = [
            'name' => '',
            'description' => '',
            'field_ids' => [],
            'required_field_ids' => [],
            'is_default' => false,
            'is_active' => true,
        ];

        session()->flash('status', 'Formulario criado com sucesso.');
    }

    public function deleteForm(int $formId): void
    {
        $form = TicketForm::query()->findOrFail($formId);
        $this->authorize('update', $form->board);

        $form->catalogItems()->update(['ticket_form_id' => null]);
        $form->delete();

        session()->flash('status', 'Formulario removido com sucesso.');
    }

    public function addCatalogItem(): void
    {
        $validated = $this->validate([
            'catalogForm.name' => ['required', 'string', 'max:120'],
            'catalogForm.description' => ['nullable', 'string'],
            'catalogForm.ticket_form_id' => ['nullable', 'exists:ticket_forms,id'],
            'catalogForm.default_ticket_group_id' => ['nullable', 'exists:ticket_groups,id'],
            'catalogForm.default_priority' => ['required', Rule::enum(TicketPriority::class)],
            'catalogForm.is_active' => ['boolean'],
        ]);

        $board = $this->board();
        $this->authorize('update', $board);

        ServiceCatalogItem::query()->create([
            'ticket_board_id' => $board->id,
            'ticket_form_id' => $validated['catalogForm']['ticket_form_id'],
            'name' => $validated['catalogForm']['name'],
            'description' => $validated['catalogForm']['description'] ?: null,
            'default_ticket_group_id' => $validated['catalogForm']['default_ticket_group_id'],
            'default_priority' => $validated['catalogForm']['default_priority'],
            'is_active' => (bool) $validated['catalogForm']['is_active'],
        ]);

        $this->catalogForm = [
            'name' => '',
            'description' => '',
            'ticket_form_id' => $board->forms()->where('is_default', true)->value('id'),
            'default_ticket_group_id' => $board->groups()->value('id'),
            'default_priority' => 'medium',
            'is_active' => true,
        ];

        session()->flash('status', 'Item do catalogo criado com sucesso.');
    }

    public function deleteCatalogItem(int $catalogItemId): void
    {
        $catalogItem = ServiceCatalogItem::query()->findOrFail($catalogItemId);
        $this->authorize('update', $catalogItem->board);
        $catalogItem->delete();

        session()->flash('status', 'Item do catalogo removido com sucesso.');
    }

    public function addAutomationCondition(): void
    {
        $this->automationConditions[] = $this->emptyCondition(count($this->automationConditions) + 1);
    }

    public function removeAutomationCondition(int $index): void
    {
        unset($this->automationConditions[$index]);
        $this->automationConditions = array_values($this->automationConditions);
        $this->reindexAutomationConditions();
    }

    public function addAutomationAction(): void
    {
        $this->automationActions[] = $this->emptyAction(count($this->automationActions) + 1);
    }

    public function removeAutomationAction(int $index): void
    {
        unset($this->automationActions[$index]);
        $this->automationActions = array_values($this->automationActions);
        $this->reindexAutomationActions();
    }

    public function startEditingAutomation(int $ruleId): void
    {
        $rule = TicketAutomationRule::query()
            ->with(['conditions', 'actions'])
            ->findOrFail($ruleId);

        $this->authorize('update', $rule->board);

        $this->editingAutomationRuleId = $rule->id;
        $this->automationForm = [
            'name' => $rule->name,
            'description' => $rule->description ?? '',
            'trigger' => $rule->trigger->value,
            'sort_order' => $rule->sort_order,
            'cooldown_minutes' => $rule->cooldown_minutes,
            'inactive_for_minutes' => data_get($rule->trigger_settings, 'inactive_for_minutes'),
            'is_active' => $rule->is_active,
        ];

        $this->automationConditions = $rule->conditions
            ->map(fn ($condition, $index) => [
                'field' => $condition->field->value,
                'operator' => $condition->operator->value,
                'value' => $condition->value,
                'sort_order' => $index + 1,
            ])
            ->values()
            ->all();

        $this->automationActions = $rule->actions
            ->map(fn ($action, $index) => [
                'action' => $action->action->value,
                'payload' => $action->payload ?? [],
                'sort_order' => $index + 1,
            ])
            ->values()
            ->all();
    }

    public function cancelAutomationEditing(): void
    {
        $this->resetAutomationForm();
    }

    public function saveAutomation(): void
    {
        $validated = $this->validate([
            'automationForm.name' => ['required', 'string', 'max:120'],
            'automationForm.description' => ['nullable', 'string'],
            'automationForm.trigger' => ['required', Rule::enum(TicketAutomationTrigger::class)],
            'automationForm.sort_order' => ['required', 'integer', 'min:1'],
            'automationForm.cooldown_minutes' => ['nullable', 'integer', 'min:1'],
            'automationForm.inactive_for_minutes' => ['nullable', 'integer', 'min:1'],
            'automationForm.is_active' => ['boolean'],
        ]);

        $board = $this->board();
        $this->authorize('update', $board);

        if ($validated['automationForm']['trigger'] === TicketAutomationTrigger::TICKET_INACTIVE->value && ! $validated['automationForm']['inactive_for_minutes']) {
            throw ValidationException::withMessages([
                'automationForm.inactive_for_minutes' => 'Informe o tempo de inatividade em minutos.',
            ]);
        }

        $normalizedConditions = $this->validatedAutomationConditions($board);
        $normalizedActions = $this->validatedAutomationActions($board);

        $rule = $this->editingAutomationRuleId
            ? TicketAutomationRule::query()->whereKey($this->editingAutomationRuleId)->firstOrFail()
            : new TicketAutomationRule();

        if ($this->editingAutomationRuleId) {
            $this->authorize('update', $rule->board);
        }

        $rule->fill([
            'ticket_board_id' => $board->id,
            'name' => $validated['automationForm']['name'],
            'description' => $validated['automationForm']['description'] ?: null,
            'trigger' => $validated['automationForm']['trigger'],
            'run_mode' => $validated['automationForm']['trigger'] === TicketAutomationTrigger::TICKET_INACTIVE->value ? 'scheduled' : 'sync',
            'trigger_settings' => $validated['automationForm']['trigger'] === TicketAutomationTrigger::TICKET_INACTIVE->value
                ? ['inactive_for_minutes' => (int) $validated['automationForm']['inactive_for_minutes']]
                : null,
            'cooldown_minutes' => $validated['automationForm']['cooldown_minutes'],
            'sort_order' => $validated['automationForm']['sort_order'],
            'is_active' => (bool) $validated['automationForm']['is_active'],
        ]);
        $rule->save();

        $rule->conditions()->delete();
        $rule->actions()->delete();
        $rule->conditions()->createMany($normalizedConditions);
        $rule->actions()->createMany($normalizedActions);

        $this->loadBoardMeta();
        $this->resetAutomationForm();

        session()->flash('status', 'Automacao salva com sucesso.');
    }

    public function toggleAutomationActive(int $ruleId): void
    {
        $rule = TicketAutomationRule::query()->findOrFail($ruleId);
        $this->authorize('update', $rule->board);

        $rule->update(['is_active' => ! $rule->is_active]);
        $this->loadBoardMeta();

        session()->flash('status', 'Automacao atualizada com sucesso.');
    }

    public function deleteAutomation(int $ruleId): void
    {
        $rule = TicketAutomationRule::query()->findOrFail($ruleId);
        $this->authorize('update', $rule->board);

        $rule->delete();
        $this->loadBoardMeta();

        if ($this->editingAutomationRuleId === $ruleId) {
            $this->resetAutomationForm();
        }

        session()->flash('status', 'Automacao removida com sucesso.');
    }

    public function describeAutomationCondition(array|\App\Modules\Tickets\Models\TicketAutomationRuleCondition $condition): string
    {
        $field = TicketAutomationConditionField::from(is_array($condition) ? $condition['field'] : $condition->field->value);
        $operator = TicketAutomationConditionOperator::from(is_array($condition) ? $condition['operator'] : $condition->operator->value);
        $value = is_array($condition) ? ($condition['value'] ?? null) : $condition->value;

        if (in_array($field, [TicketAutomationConditionField::HAS_ASSIGNEE, TicketAutomationConditionField::IS_CLOSED], true)) {
            return "{$field->label()}: {$operator->label()}";
        }

        $formattedValue = collect(is_array($value) ? $value : [$value])
            ->filter(fn ($item) => ! is_null($item) && $item !== '')
            ->map(fn ($item) => (string) $item)
            ->join(', ');

        return "{$field->label()}: {$operator->label()} {$formattedValue}";
    }

    public function describeAutomationAction(array|\App\Modules\Tickets\Models\TicketAutomationRuleAction $action): string
    {
        $type = TicketAutomationActionType::from(is_array($action) ? $action['action'] : $action->action->value);

        return $type->label();
    }

    public function render(): View
    {
        $board = $this->board();

        return view('livewire.tickets.settings-page', [
            'board' => $board,
            'sectorOptions' => $this->availableSectors(),
            'fieldTypes' => TicketFieldType::cases(),
            'priorities' => TicketPriority::cases(),
            'groupImpacts' => $board
                ? $board->groups->mapWithKeys(fn (TicketGroup $group) => [$group->id => $this->groupImpact($group)])->all()
                : [],
            'statusImpacts' => $board
                ? $board->statuses->mapWithKeys(fn (TicketStatus $status) => [$status->id => $this->statusImpact($status)])->all()
                : [],
            'automationTriggers' => TicketAutomationTrigger::cases(),
            'automationConditionFields' => TicketAutomationConditionField::cases(),
            'automationConditionOperators' => TicketAutomationConditionOperator::cases(),
            'automationActionTypes' => TicketAutomationActionType::cases(),
            'automationAssignees' => $this->boardAssignees(),
        ])->layout('layouts.portal', [
            'title' => 'Configurar quadro',
            'subtitle' => 'Grupos, status, campos, formularios, catalogo, SLA e automacoes do setor.',
        ]);
    }

    private function loadBoardMeta(): void
    {
        $board = $this->board();

        $this->boardName = $board?->name ?? '';
        $this->boardDescription = $board?->description ?? '';
        $this->catalogForm['ticket_form_id'] = $board?->forms()->where('is_default', true)->value('id');
        $this->catalogForm['default_ticket_group_id'] = $board?->groups()->value('id');

        $policy = $board?->slaPolicy;
        $this->slaIsActive = $policy?->is_active ?? true;
        $this->slaTargets = [];

        foreach (TicketPriority::cases() as $priority) {
            $target = $policy?->targets?->firstWhere('priority', $priority);
            $this->slaTargets[$priority->value] = [
                'first_response_minutes' => $target?->first_response_minutes,
                'resolution_minutes' => $target?->resolution_minutes,
            ];
        }

        $this->automationForm['sort_order'] = ((int) ($board?->automationRules->max('sort_order') ?? 0)) + 1;
    }

    private function board(): ?TicketBoard
    {
        if (! $this->selectedSectorId) {
            return null;
        }

        $sector = Sector::query()->find($this->selectedSectorId);

        if (! $sector) {
            return null;
        }

        abort_unless($this->availableSectors()->pluck('id')->contains($sector->id), 403);

        $board = TicketBoard::query()
            ->with([
                'sector.company',
                'groups',
                'statuses',
                'fields.options',
                'forms.fields',
                'catalogItems.form',
                'catalogItems.defaultGroup',
                'slaPolicy.targets',
                'automationRules.conditions',
                'automationRules.actions',
            ])
            ->where('sector_id', $sector->id)
            ->first();

        if ($board) {
            return $board;
        }

        app(SectorProvisioningService::class)->provision($sector);

        return TicketBoard::query()
            ->with([
                'sector.company',
                'groups',
                'statuses',
                'fields.options',
                'forms.fields',
                'catalogItems.form',
                'catalogItems.defaultGroup',
                'slaPolicy.targets',
                'automationRules.conditions',
                'automationRules.actions',
            ])
            ->where('sector_id', $sector->id)
            ->first();
    }

    private function availableSectors(): Collection
    {
        $query = Sector::query()->with('company')->orderBy('name');

        if (! auth()->user()->isSuperAdmin()) {
            $query->whereIn('id', auth()->user()->adminSectorIds());
        }

        return $query->where('is_active', true)->get();
    }

    private function boardAssignees(): Collection
    {
        if (! $this->selectedSectorId) {
            return collect();
        }

        return User::query()
            ->withSectorAccess($this->selectedSectorId, ['sector_admin', 'technician'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function groupForBoard(?int $groupId): TicketGroup
    {
        $group = TicketGroup::query()->findOrFail($groupId);
        $this->authorize('update', $group->board);

        abort_unless($group->ticket_board_id === $this->board()?->id, 404);

        return $group;
    }

    private function statusForBoard(?int $statusId): TicketStatus
    {
        $status = TicketStatus::query()->findOrFail($statusId);
        $this->authorize('update', $status->board);

        abort_unless($status->ticket_board_id === $this->board()?->id, 404);

        return $status;
    }

    private function groupImpact(TicketGroup $group): array
    {
        return [
            'tickets_count' => $group->tickets()->count(),
            'catalog_count' => $group->board->catalogItems()->where('default_ticket_group_id', $group->id)->count(),
        ];
    }

    private function statusImpact(TicketStatus $status): array
    {
        $otherStatuses = $status->board->statuses()->whereKeyNot($status->id);

        return [
            'tickets_count' => $status->tickets()->count(),
            'active_statuses_count' => $status->board->statuses()->where('is_active', true)->count(),
            'has_other_active_default' => $otherStatuses->where('is_active', true)->where('is_default', true)->exists(),
            'available_replacements_count' => $otherStatuses->where('is_active', true)->count(),
        ];
    }

    private function moveOrderedItem(Model $item, string $direction): void
    {
        $column = $direction === 'up' ? '<' : '>';
        $sort = $direction === 'up' ? 'desc' : 'asc';

        $neighbour = $item::query()
            ->where('ticket_board_id', $item->getAttribute('ticket_board_id'))
            ->where('sort_order', $column, $item->getAttribute('sort_order'))
            ->orderBy('sort_order', $sort)
            ->first();

        if (! $neighbour) {
            return;
        }

        DB::transaction(function () use ($item, $neighbour) {
            $currentOrder = $item->getAttribute('sort_order');

            $item->update(['sort_order' => $neighbour->getAttribute('sort_order')]);
            $neighbour->update(['sort_order' => $currentOrder]);
            $this->normalizeSortOrder($item::class, $item->getAttribute('ticket_board_id'));
        });
    }

    private function normalizeSortOrder(string $modelClass, int $boardId): void
    {
        $modelClass::query()
            ->where('ticket_board_id', $boardId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values()
            ->each(fn (Model $model, int $index) => $model->update(['sort_order' => $index + 1]));
    }

    private function parsedOptions(?string $optionsText): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $optionsText))
            ->filter()
            ->values()
            ->map(function (string $line) {
                [$label, $color] = array_pad(array_map('trim', explode('|', $line, 2)), 2, null);

                return [
                    'label' => $label,
                    'value' => Str::slug($label, '_'),
                    'color' => $color ?: null,
                ];
            })
            ->all();
    }

    private function resetForms(): void
    {
        $this->resetValidation();

        $this->groupForm = [
            'name' => '',
            'color' => '#2563eb',
            'is_collapsed_by_default' => false,
            'is_active' => true,
        ];

        $this->editGroupForm = [
            'name' => '',
            'color' => '#2563eb',
            'is_collapsed_by_default' => false,
            'is_active' => true,
        ];

        $this->statusForm = [
            'name' => '',
            'color' => '#2563eb',
            'is_default' => false,
            'is_closed' => false,
            'is_active' => true,
        ];

        $this->editStatusForm = [
            'name' => '',
            'color' => '#2563eb',
            'is_default' => false,
            'is_closed' => false,
            'is_active' => true,
        ];

        $this->fieldForm = [
            'name' => '',
            'type' => 'text',
            'placeholder' => '',
            'help_text' => '',
            'is_required' => false,
            'is_active' => true,
            'options_text' => '',
        ];

        $this->formForm = [
            'name' => '',
            'description' => '',
            'field_ids' => [],
            'required_field_ids' => [],
            'is_default' => false,
            'is_active' => true,
        ];

        $board = $this->board();

        $this->catalogForm = [
            'name' => '',
            'description' => '',
            'ticket_form_id' => $board?->forms()->where('is_default', true)->value('id'),
            'default_ticket_group_id' => $board?->groups()->value('id'),
            'default_priority' => 'medium',
            'is_active' => true,
        ];

        $this->resetAutomationForm();
        $this->cancelBoardMaintenanceState();
    }

    private function resetAutomationForm(): void
    {
        $this->editingAutomationRuleId = null;
        $this->automationForm = [
            'name' => '',
            'description' => '',
            'trigger' => TicketAutomationTrigger::TICKET_CREATED->value,
            'sort_order' => ((int) ($this->board()?->automationRules->max('sort_order') ?? 0)) + 1,
            'cooldown_minutes' => null,
            'inactive_for_minutes' => null,
            'is_active' => true,
        ];
        $this->automationConditions = [$this->emptyCondition(1)];
        $this->automationActions = [$this->emptyAction(1)];
    }

    private function cancelBoardMaintenanceState(): void
    {
        $this->cancelEditingGroup();
        $this->cancelEditingStatus();
        $this->cancelDeletion();
    }

    private function ensureSingleActiveDefaultStatus(int $boardId, ?int $preferredStatusId = null): void
    {
        $statuses = TicketStatus::query()
            ->where('ticket_board_id', $boardId)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->get();

        $preferred = $preferredStatusId ? $statuses->firstWhere('id', $preferredStatusId) : null;

        $defaultStatus = $preferred && $preferred->is_active
            ? $preferred
            : $statuses->first(fn (TicketStatus $status) => $status->is_active && $status->is_default)
                ?? $statuses->first(fn (TicketStatus $status) => $status->is_active);

        if (! $defaultStatus) {
            throw new \RuntimeException('O board precisa manter ao menos um status ativo.');
        }

        TicketStatus::query()
            ->where('ticket_board_id', $boardId)
            ->update(['is_default' => false]);

        $defaultStatus->forceFill([
            'is_default' => true,
            'is_active' => true,
        ])->save();
    }

    private function nullableReplacementId(mixed $value): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return (int) $value;
    }

    private function ensureGroupReplacementBelongsToBoard(int $boardId, int $replacementId, int $deletedGroupId): void
    {
        $exists = TicketGroup::query()
            ->where('ticket_board_id', $boardId)
            ->whereKey($replacementId)
            ->whereKeyNot($deletedGroupId)
            ->exists();

        abort_unless($exists, 422);
    }

    private function emptyCondition(int $sortOrder): array
    {
        return [
            'field' => TicketAutomationConditionField::PRIORITY->value,
            'operator' => TicketAutomationConditionOperator::IN->value,
            'value' => [],
            'sort_order' => $sortOrder,
        ];
    }

    private function emptyAction(int $sortOrder): array
    {
        return [
            'action' => TicketAutomationActionType::CHANGE_STATUS->value,
            'payload' => [
                'status_id' => null,
            ],
            'sort_order' => $sortOrder,
        ];
    }

    private function reindexAutomationConditions(): void
    {
        foreach ($this->automationConditions as $index => &$condition) {
            $condition['sort_order'] = $index + 1;
        }
    }

    private function reindexAutomationActions(): void
    {
        foreach ($this->automationActions as $index => &$action) {
            $action['sort_order'] = $index + 1;
        }
    }

    private function validatedAutomationConditions(TicketBoard $board): array
    {
        if (count($this->automationConditions) === 0) {
            throw ValidationException::withMessages([
                'automationConditions' => 'Adicione ao menos uma condicao.',
            ]);
        }

        $statusIds = $board->statuses->pluck('id')->all();
        $groupIds = $board->groups->pluck('id')->all();
        $priorityValues = collect(TicketPriority::cases())->map->value->all();

        return collect($this->automationConditions)->values()->map(function (array $condition, int $index) use ($statusIds, $groupIds, $priorityValues) {
            $field = TicketAutomationConditionField::tryFrom((string) ($condition['field'] ?? ''));
            $operator = TicketAutomationConditionOperator::tryFrom((string) ($condition['operator'] ?? ''));

            if (! $field || ! $operator) {
                throw ValidationException::withMessages([
                    "automationConditions.{$index}.field" => 'Condicao invalida.',
                ]);
            }

            $value = $condition['value'] ?? null;

            if ($field === TicketAutomationConditionField::PRIORITY) {
                $values = array_values(array_filter(is_array($value) ? $value : [$value], fn ($item) => $item !== null && $item !== ''));

                if ($values === [] || collect($values)->diff($priorityValues)->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        "automationConditions.{$index}.value" => 'Selecione prioridades validas.',
                    ]);
                }

                $value = $values;
            }

            if ($field === TicketAutomationConditionField::STATUS_ID) {
                $values = array_map('intval', array_values(array_filter(is_array($value) ? $value : [$value])));

                if ($values === [] || collect($values)->diff($statusIds)->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        "automationConditions.{$index}.value" => 'Selecione status validos.',
                    ]);
                }

                $value = $values;
            }

            if ($field === TicketAutomationConditionField::GROUP_ID) {
                $values = array_map('intval', array_values(array_filter(is_array($value) ? $value : [$value])));

                if ($values === [] || collect($values)->diff($groupIds)->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        "automationConditions.{$index}.value" => 'Selecione grupos validos.',
                    ]);
                }

                $value = $values;
            }

            if (in_array($field, [TicketAutomationConditionField::HAS_ASSIGNEE, TicketAutomationConditionField::IS_CLOSED], true)) {
                $value = null;
            }

            return [
                'field' => $field->value,
                'operator' => $operator->value,
                'value' => $value,
                'sort_order' => $index + 1,
            ];
        })->all();
    }

    private function validatedAutomationActions(TicketBoard $board): array
    {
        if (count($this->automationActions) === 0) {
            throw ValidationException::withMessages([
                'automationActions' => 'Adicione ao menos uma acao.',
            ]);
        }

        $statusIds = $board->statuses->pluck('id')->all();
        $openStatusIds = $board->statuses->where('is_closed', false)->pluck('id')->all();
        $groupIds = $board->groups->pluck('id')->all();
        $assigneeIds = $this->boardAssignees()->pluck('id')->all();
        $priorityValues = collect(TicketPriority::cases())->map->value->all();

        return collect($this->automationActions)->values()->map(function (array $action, int $index) use ($statusIds, $openStatusIds, $groupIds, $assigneeIds, $priorityValues) {
            $type = TicketAutomationActionType::tryFrom((string) ($action['action'] ?? ''));

            if (! $type) {
                throw ValidationException::withMessages([
                    "automationActions.{$index}.action" => 'Acao invalida.',
                ]);
            }

            $payload = is_array($action['payload'] ?? null) ? $action['payload'] : [];

            if ($type === TicketAutomationActionType::ASSIGN_FIXED_ASSIGNEE) {
                $assigneeId = isset($payload['assignee_id']) ? (int) $payload['assignee_id'] : 0;

                if (! in_array($assigneeId, $assigneeIds, true)) {
                    throw ValidationException::withMessages([
                        "automationActions.{$index}.payload.assignee_id" => 'Selecione um responsavel valido.',
                    ]);
                }
            }

            if ($type === TicketAutomationActionType::CHANGE_STATUS) {
                $statusId = isset($payload['status_id']) ? (int) $payload['status_id'] : 0;

                if (! in_array($statusId, $statusIds, true)) {
                    throw ValidationException::withMessages([
                        "automationActions.{$index}.payload.status_id" => 'Selecione um status valido.',
                    ]);
                }
            }

            if ($type === TicketAutomationActionType::CHANGE_GROUP) {
                $groupId = isset($payload['group_id']) ? (int) $payload['group_id'] : 0;

                if (! in_array($groupId, $groupIds, true)) {
                    throw ValidationException::withMessages([
                        "automationActions.{$index}.payload.group_id" => 'Selecione um grupo valido.',
                    ]);
                }
            }

            if ($type === TicketAutomationActionType::CHANGE_PRIORITY) {
                $priority = (string) ($payload['priority'] ?? '');

                if (! in_array($priority, $priorityValues, true)) {
                    throw ValidationException::withMessages([
                        "automationActions.{$index}.payload.priority" => 'Selecione uma prioridade valida.',
                    ]);
                }
            }

            if ($type === TicketAutomationActionType::ADD_SYSTEM_MESSAGE) {
                if (trim((string) ($payload['message'] ?? '')) === '') {
                    throw ValidationException::withMessages([
                        "automationActions.{$index}.payload.message" => 'Informe a mensagem automatica.',
                    ]);
                }
            }

            if ($type === TicketAutomationActionType::SEND_NOTIFICATION) {
                if (trim((string) ($payload['title'] ?? '')) === '' || trim((string) ($payload['message'] ?? '')) === '') {
                    throw ValidationException::withMessages([
                        "automationActions.{$index}.payload.title" => 'Informe titulo e mensagem da notificacao.',
                    ]);
                }
            }

            if ($type === TicketAutomationActionType::REOPEN_TICKET) {
                $statusId = isset($payload['target_status_id']) ? (int) $payload['target_status_id'] : 0;

                if (! in_array($statusId, $openStatusIds, true)) {
                    throw ValidationException::withMessages([
                        "automationActions.{$index}.payload.target_status_id" => 'Selecione um status aberto para reabrir o chamado.',
                    ]);
                }
            }

            return [
                'action' => $type->value,
                'payload' => $payload,
                'sort_order' => $index + 1,
            ];
        })->all();
    }

    private function uniqueSlug(string $modelClass, int $boardId, string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while ($modelClass::query()->where('ticket_board_id', $boardId)->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
