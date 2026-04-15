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
    public string $openSection = 'groups';
    public string $boardName = '';
    public string $boardDescription = '';
    public bool $slaIsActive = true;
    public array $slaTargets = [];
    public array $groupForm = [
        'name' => '',
        'color' => '#2563eb',
        'is_collapsed_by_default' => false,
        'is_default' => false,
        'is_closed' => false,
        'is_active' => true,
    ];
    public array $editGroupForm = [
        'name' => '',
        'color' => '#2563eb',
        'is_collapsed_by_default' => false,
        'is_default' => false,
        'is_closed' => false,
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
        'show_on_board' => true,
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
    public ?int $editingFormId = null;
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
        $this->selectedSectorId = $sector?->id ?? $this->availableSectors()->first()?->id;

        if (! $this->selectedSectorId && $this->hasAnyActiveSector()) {
            abort(403);
        }

        $this->loadBoardMeta();
        $this->resetForms();
    }

    public function updatedSelectedSectorId(): void
    {
        abort_unless($this->availableSectors()->pluck('id')->contains($this->selectedSectorId), 403);
        $this->loadBoardMeta();
        $this->resetForms();
    }

    public function updatedAutomationFormTrigger(string $trigger): void
    {
        if ($trigger !== TicketAutomationTrigger::TICKET_INACTIVE->value) {
            $this->automationForm['inactive_for_minutes'] = null;
        }
    }

    public function setOpenSection(string $section): void
    {
        if (in_array($section, ['board', 'groups', 'statuses', 'sla', 'automations', 'fields', 'forms'], true)) {
            $this->openSection = $section;
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

        $this->setOpenSection('board');
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
        $this->setOpenSection('sla');

        session()->flash('status', 'Politica de SLA atualizada com sucesso.');
    }

    public function addGroup(): void
    {
        $validated = $this->validate($this->groupRules('groupForm'));
        $board = $this->board();
        $this->authorize('update', $board);

        DB::transaction(function () use ($validated, $board): void {
            $hasActiveDefault = $board->groups()->where('is_active', true)->where('is_default', true)->exists();
            $hasActiveClosed = $board->groups()->where('is_active', true)->where('is_closed', true)->exists();
            $shouldBeDefault = (bool) $validated['groupForm']['is_default'] || ! $hasActiveDefault;
            $shouldBeClosed = (bool) $validated['groupForm']['is_closed'] || ! $hasActiveClosed;
            $isActive = $shouldBeDefault || $shouldBeClosed ? true : (bool) $validated['groupForm']['is_active'];

            if ($shouldBeDefault) {
                $board->groups()->update(['is_default' => false]);
            }

            if ($shouldBeClosed) {
                $board->groups()->update(['is_closed' => false]);
            }

            TicketGroup::query()->create([
                'ticket_board_id' => $board->id,
                'name' => $validated['groupForm']['name'],
                'slug' => $this->uniqueSlug(TicketGroup::class, $board->id, $validated['groupForm']['name']),
                'color' => $validated['groupForm']['color'],
                'sort_order' => ((int) $board->groups()->max('sort_order')) + 1,
                'is_collapsed_by_default' => (bool) $validated['groupForm']['is_collapsed_by_default'],
                'is_default' => $shouldBeDefault,
                'is_closed' => $shouldBeClosed,
                'is_active' => $isActive,
            ]);
        });

        $this->groupForm = $this->emptyGroupForm();
        $this->loadBoardMeta();
        $this->setOpenSection('groups');

        session()->flash('status', 'Etapa criada com sucesso.');
    }

    public function startEditingGroup(int $groupId): void
    {
        $group = $this->groupForBoard($groupId);

        $this->editingGroupId = $group->id;
        $this->editGroupForm = [
            'name' => $group->name,
            'color' => $group->color,
            'is_collapsed_by_default' => $group->is_collapsed_by_default,
            'is_default' => $group->is_default,
            'is_closed' => $group->is_closed,
            'is_active' => $group->is_active,
        ];

        $this->cancelDeletion();
        $this->resetValidation();
        $this->setOpenSection('groups');
    }

    public function cancelEditingGroup(): void
    {
        $this->editingGroupId = null;
        $this->editGroupForm = $this->emptyGroupForm();
        $this->resetValidation();
    }

    public function updateGroup(): void
    {
        $validated = $this->validate($this->groupRules('editGroupForm'));
        $group = $this->groupForBoard($this->editingGroupId);
        $requestedDefault = (bool) $validated['editGroupForm']['is_default'];
        $requestedClosed = (bool) $validated['editGroupForm']['is_closed'];
        $requestedActive = ($requestedDefault || $requestedClosed) ? true : (bool) $validated['editGroupForm']['is_active'];

        if ($group->is_default && ! $requestedDefault) {
            session()->flash('error', 'O quadro precisa manter uma etapa inicial ativa.');
            return;
        }

        if ($group->is_default && ! $requestedActive) {
            session()->flash('error', 'A etapa inicial nao pode ser inativada sem definir outra etapa inicial.');
            return;
        }

        if ($group->is_closed && ! $requestedClosed) {
            session()->flash('error', 'O quadro precisa manter uma etapa final ativa.');
            return;
        }

        if ($group->is_closed && ! $requestedActive) {
            session()->flash('error', 'A etapa final nao pode ser inativada sem definir outra etapa final.');
            return;
        }

        DB::transaction(function () use ($validated, $group, $requestedDefault, $requestedClosed, $requestedActive): void {
            if ($requestedDefault) {
                $group->board->groups()->whereKeyNot($group->id)->update(['is_default' => false]);
            }

            if ($requestedClosed) {
                $group->board->groups()->whereKeyNot($group->id)->update(['is_closed' => false]);
            }

            $group->update([
                'name' => $validated['editGroupForm']['name'],
                'color' => $validated['editGroupForm']['color'],
                'is_collapsed_by_default' => (bool) $validated['editGroupForm']['is_collapsed_by_default'],
                'is_default' => $requestedDefault,
                'is_closed' => $requestedClosed,
                'is_active' => $requestedActive,
            ]);

            $this->ensureSingleActiveGroupFlag($group->ticket_board_id, 'is_default', $requestedDefault ? $group->id : null);
            $this->ensureSingleActiveGroupFlag($group->ticket_board_id, 'is_closed', $requestedClosed ? $group->id : null);
        });

        $this->cancelEditingGroup();
        $this->loadBoardMeta();
        $this->setOpenSection('groups');

        session()->flash('status', 'Etapa atualizada com sucesso.');
    }

    public function moveGroupUp(int $groupId): void
    {
        $this->moveOrderedItem($this->groupForBoard($groupId), 'up');
        $this->loadBoardMeta();
        $this->setOpenSection('groups');
        session()->flash('status', 'Ordem das etapas atualizada.');
    }

    public function moveGroupDown(int $groupId): void
    {
        $this->moveOrderedItem($this->groupForBoard($groupId), 'down');
        $this->loadBoardMeta();
        $this->setOpenSection('groups');
        session()->flash('status', 'Ordem das etapas atualizada.');
    }

    public function confirmDeleteGroup(int $groupId): void
    {
        $group = $this->groupForBoard($groupId);
        $this->pendingDeletionType = 'group';
        $this->pendingDeletionId = $group->id;
        $this->deletionContext = array_merge($this->groupImpact($group), [
            'replacement_groups' => $group->board->groups()->whereKeyNot($group->id)->get(['id', 'name'])->map(
                fn (TicketGroup $replacement) => ['id' => $replacement->id, 'name' => $replacement->name]
            )->all(),
        ]);
        $this->replacementSelection = ['group_ticket_group_id' => '', 'group_catalog_group_id' => ''];
        $this->cancelEditingGroup();
        $this->setOpenSection('groups');
    }

    public function deleteGroup(): void
    {
        abort_unless($this->pendingDeletionType === 'group', 404);
        $group = $this->groupForBoard($this->pendingDeletionId);

        if (($this->deletionContext['remaining_groups_count'] ?? 0) < 1) {
            session()->flash('error', 'O quadro precisa manter ao menos uma etapa.');
            return;
        }

        $ticketReplacement = $this->nullableReplacementId($this->replacementSelection['group_ticket_group_id'] ?? '');
        $catalogReplacement = $this->nullableReplacementId($this->replacementSelection['group_catalog_group_id'] ?? '');

        if ($ticketReplacement !== null) {
            $this->ensureGroupReplacementBelongsToBoard($group->ticket_board_id, $ticketReplacement, $group->id);
        }

        if ($catalogReplacement !== null) {
            $this->ensureGroupReplacementBelongsToBoard($group->ticket_board_id, $catalogReplacement, $group->id);
        }

        DB::transaction(function () use ($group, $ticketReplacement, $catalogReplacement): void {
            $group->tickets()->update(['ticket_group_id' => $ticketReplacement]);
            $group->board->catalogItems()->where('default_ticket_group_id', $group->id)->update([
                'default_ticket_group_id' => $catalogReplacement,
            ]);
            $wasDefault = $group->is_default;
            $wasClosed = $group->is_closed;
            $group->delete();
            $this->normalizeSortOrder(TicketGroup::class, $group->ticket_board_id);

            if ($wasDefault) {
                $this->ensureSingleActiveGroupFlag($group->ticket_board_id, 'is_default', $ticketReplacement);
            }

            if ($wasClosed) {
                $this->ensureSingleActiveGroupFlag($group->ticket_board_id, 'is_closed', $catalogReplacement);
            }
        });

        $this->cancelDeletion();
        $this->loadBoardMeta();
        $this->setOpenSection('groups');
        session()->flash('status', 'Etapa removida com sucesso.');
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

        DB::transaction(function () use ($validated, $board): void {
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

        $this->statusForm = $this->emptyStatusForm();
        $this->loadBoardMeta();
        $this->setOpenSection('statuses');
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
        $this->setOpenSection('statuses');
    }

    public function cancelEditingStatus(): void
    {
        $this->editingStatusId = null;
        $this->editStatusForm = $this->emptyStatusForm();
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

        DB::transaction(function () use ($validated, $status, $requestedDefault, $requestedActive): void {
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

            $this->ensureSingleActiveDefaultStatus($status->ticket_board_id, $requestedDefault ? $status->id : null);
        });

        $this->cancelEditingStatus();
        $this->loadBoardMeta();
        $this->setOpenSection('statuses');
        session()->flash('status', 'Status atualizado com sucesso.');
    }

    public function moveStatusUp(int $statusId): void
    {
        $this->moveOrderedItem($this->statusForBoard($statusId), 'up');
        $this->loadBoardMeta();
        $this->setOpenSection('statuses');
        session()->flash('status', 'Ordem dos status atualizada.');
    }

    public function moveStatusDown(int $statusId): void
    {
        $this->moveOrderedItem($this->statusForBoard($statusId), 'down');
        $this->loadBoardMeta();
        $this->setOpenSection('statuses');
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
        $this->setOpenSection('statuses');
    }

    public function deleteStatus(): void
    {
        abort_unless($this->pendingDeletionType === 'status', 404);

        $status = $this->statusForBoard($this->pendingDeletionId);
        $activeStatusesCount = (int) ($this->deletionContext['active_statuses_count'] ?? 0);

        if ($activeStatusesCount <= 1) {
            session()->flash('error', 'Este status e o ultimo ativo do board e nao pode ser excluido.');
            return;
        }

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

        DB::transaction(function () use ($status, $replacement): void {
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
        $this->setOpenSection('statuses');
        session()->flash('status', 'Status removido com sucesso.');
    }

    public function cancelDeletion(): void
    {
        $this->pendingDeletionType = null;
        $this->pendingDeletionId = null;
        $this->deletionContext = [];
        $this->replacementSelection = ['group_ticket_group_id' => '', 'group_catalog_group_id' => '', 'status_replacement_id' => ''];
    }

    public function addField(): void
    {
        $validated = $this->validate([
            'fieldForm.name' => ['required', 'string', 'max:80'],
            'fieldForm.type' => ['required', Rule::enum(TicketFieldType::class)],
            'fieldForm.placeholder' => ['nullable', 'string', 'max:120'],
            'fieldForm.help_text' => ['nullable', 'string'],
            'fieldForm.is_required' => ['boolean'],
            'fieldForm.show_on_board' => ['boolean'],
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
            'show_on_board' => (bool) $validated['fieldForm']['show_on_board'],
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

        $this->fieldForm = $this->emptyFieldForm();
        $this->loadBoardMeta();
        $this->setOpenSection('fields');
        session()->flash('status', 'Campo criado com sucesso.');
    }

    public function deleteField(int $fieldId): void
    {
        $field = $this->fieldForBoard($fieldId);
        $field->delete();

        if (in_array($fieldId, $this->formForm['field_ids'], true)) {
            $this->formForm['field_ids'] = array_values(array_diff($this->formForm['field_ids'], [$fieldId]));
            $this->formForm['required_field_ids'] = array_values(array_diff($this->formForm['required_field_ids'], [$fieldId]));
        }

        $this->loadBoardMeta();
        $this->setOpenSection('fields');
        session()->flash('status', 'Campo removido com sucesso.');
    }

    public function startEditingForm(int $formId): void
    {
        $form = $this->formForBoard($formId);

        $this->editingFormId = $form->id;
        $this->formForm = [
            'name' => $form->name,
            'description' => $form->description ?? '',
            'field_ids' => $form->fields->sortBy('pivot.sort_order')->pluck('id')->all(),
            'required_field_ids' => $form->fields->filter(fn (TicketField $field) => (bool) $field->pivot?->is_required)->pluck('id')->all(),
            'is_default' => $form->is_default,
            'is_active' => $form->is_active,
        ];

        $this->resetValidation();
        $this->setOpenSection('forms');
    }

    public function cancelEditingForm(): void
    {
        $this->editingFormId = null;
        $this->formForm = $this->emptyFormForm();
        $this->resetValidation();
    }

    public function saveForm(): void
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

        $fieldIds = array_values(array_unique(array_map('intval', $validated['formForm']['field_ids'] ?? [])));
        $requiredIds = array_values(array_intersect(array_map('intval', $validated['formForm']['required_field_ids'] ?? []), $fieldIds));
        $form = $this->editingFormId ? $this->formForBoard($this->editingFormId) : new TicketForm();

        if ($validated['formForm']['is_default']) {
            $board->forms()->whereKeyNot($form->id)->update(['is_default' => false]);
        }

        $form->fill([
            'ticket_board_id' => $board->id,
            'name' => $validated['formForm']['name'],
            'description' => $validated['formForm']['description'] ?: null,
            'is_default' => (bool) $validated['formForm']['is_default'],
            'is_active' => (bool) $validated['formForm']['is_active'],
        ]);
        $form->save();

        $syncPayload = collect($fieldIds)->mapWithKeys(fn (int $fieldId, int $index) => [
            $fieldId => ['is_required' => in_array($fieldId, $requiredIds, true), 'sort_order' => $index + 1],
        ])->all();

        $form->fields()->sync($syncPayload);

        $wasEditing = $this->editingFormId !== null;
        $this->cancelEditingForm();
        $this->loadBoardMeta();
        $this->setOpenSection('forms');
        session()->flash('status', $wasEditing ? 'Formulario atualizado com sucesso.' : 'Formulario criado com sucesso.');
    }

    public function deleteForm(int $formId): void
    {
        $form = $this->formForBoard($formId);
        $form->catalogItems()->update(['ticket_form_id' => null]);
        $form->delete();

        if ($this->editingFormId === $formId) {
            $this->cancelEditingForm();
        }

        $this->loadBoardMeta();
        $this->setOpenSection('forms');
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

        $this->catalogForm = $this->emptyCatalogForm($board);
        $this->loadBoardMeta();
        $this->setOpenSection('forms');
        session()->flash('status', 'Item do catalogo criado com sucesso.');
    }

    public function deleteCatalogItem(int $catalogItemId): void
    {
        $catalogItem = $this->catalogItemForBoard($catalogItemId);
        $catalogItem->delete();

        $this->loadBoardMeta();
        $this->setOpenSection('forms');
        session()->flash('status', 'Item do catalogo removido com sucesso.');
    }

    public function addAutomationCondition(): void
    {
        $this->automationConditions[] = $this->emptyCondition(count($this->automationConditions) + 1);
        $this->setOpenSection('automations');
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
        $this->setOpenSection('automations');
    }

    public function removeAutomationAction(int $index): void
    {
        unset($this->automationActions[$index]);
        $this->automationActions = array_values($this->automationActions);
        $this->reindexAutomationActions();
    }

    public function startEditingAutomation(int $ruleId): void
    {
        $rule = TicketAutomationRule::query()->with(['conditions', 'actions'])->findOrFail($ruleId);
        $this->authorize('update', $rule->board);

        if (
            $rule->conditions->contains(fn ($condition) => ! in_array($condition->field->value, $this->supportedAutomationConditionFieldValues(), true))
            || $rule->actions->contains(fn ($action) => ! in_array($action->action->value, $this->supportedAutomationActionTypeValues(), true))
        ) {
            session()->flash('error', 'Esta automacao ainda usa configuracoes legadas de status e precisa ser revisada antes da edicao.');
            $this->setOpenSection('automations');

            return;
        }

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
        $this->automationConditions = $rule->conditions->map(fn ($condition, int $index) => [
            'field' => $condition->field->value,
            'operator' => $condition->operator->value,
            'value' => $condition->value,
            'sort_order' => $index + 1,
        ])->values()->all();
        $this->automationActions = $rule->actions->map(fn ($action, int $index) => [
            'action' => $action->action->value,
            'payload' => $action->payload ?? [],
            'sort_order' => $index + 1,
        ])->values()->all();
        $this->setOpenSection('automations');
    }

    public function cancelAutomationEditing(): void
    {
        $this->resetAutomationForm();
        $this->setOpenSection('automations');
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
        $rule = $this->editingAutomationRuleId ? TicketAutomationRule::query()->whereKey($this->editingAutomationRuleId)->firstOrFail() : new TicketAutomationRule();

        if ($this->editingAutomationRuleId) {
            $this->authorize('update', $rule->board);
        }

        $rule->fill([
            'ticket_board_id' => $board->id,
            'name' => $validated['automationForm']['name'],
            'description' => $validated['automationForm']['description'] ?: null,
            'trigger' => $validated['automationForm']['trigger'],
            'run_mode' => $validated['automationForm']['trigger'] === TicketAutomationTrigger::TICKET_INACTIVE->value ? 'scheduled' : 'sync',
            'trigger_settings' => $validated['automationForm']['trigger'] === TicketAutomationTrigger::TICKET_INACTIVE->value ? ['inactive_for_minutes' => (int) $validated['automationForm']['inactive_for_minutes']] : null,
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
        $this->setOpenSection('automations');
        session()->flash('status', 'Automacao salva com sucesso.');
    }

    public function toggleAutomationActive(int $ruleId): void
    {
        $rule = TicketAutomationRule::query()->findOrFail($ruleId);
        $this->authorize('update', $rule->board);
        $rule->update(['is_active' => ! $rule->is_active]);
        $this->loadBoardMeta();
        $this->setOpenSection('automations');
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

        $this->setOpenSection('automations');
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

        $formattedValue = collect(is_array($value) ? $value : [$value])->filter(fn ($item) => ! is_null($item) && $item !== '')
            ->map(fn ($item) => $this->automationConditionValueLabel($field, $item))->join(', ');

        return trim("{$field->label()}: {$operator->label()} {$formattedValue}");
    }

    public function describeAutomationAction(array|\App\Modules\Tickets\Models\TicketAutomationRuleAction $action): string
    {
        $type = TicketAutomationActionType::from(is_array($action) ? $action['action'] : $action->action->value);
        $payload = is_array($action) ? ($action['payload'] ?? []) : ($action->payload ?? []);

        return match ($type) {
            TicketAutomationActionType::ASSIGN_FIXED_ASSIGNEE => 'Atribuir '.($this->assigneeName((int) data_get($payload, 'assignee_id')) ?? 'responsavel'),
            TicketAutomationActionType::CHANGE_GROUP => 'Mover para '.($this->groupName((int) data_get($payload, 'group_id')) ?? 'etapa'),
            TicketAutomationActionType::CHANGE_PRIORITY => 'Mudar prioridade para '.($this->priorityLabel((string) data_get($payload, 'priority')) ?? 'nova prioridade'),
            TicketAutomationActionType::ADD_SYSTEM_MESSAGE => 'Registrar mensagem automatica',
            TicketAutomationActionType::SEND_NOTIFICATION => 'Enviar notificacao',
            TicketAutomationActionType::CHANGE_STATUS => 'Acao legada de status',
            TicketAutomationActionType::REOPEN_TICKET => 'Acao legada de reabertura',
        };
    }

    private function supportedAutomationConditionFields(): array
    {
        return [
            TicketAutomationConditionField::PRIORITY,
            TicketAutomationConditionField::GROUP_ID,
            TicketAutomationConditionField::HAS_ASSIGNEE,
            TicketAutomationConditionField::IS_CLOSED,
        ];
    }

    private function supportedAutomationActionTypes(): array
    {
        return [
            TicketAutomationActionType::ASSIGN_FIXED_ASSIGNEE,
            TicketAutomationActionType::CHANGE_GROUP,
            TicketAutomationActionType::CHANGE_PRIORITY,
            TicketAutomationActionType::ADD_SYSTEM_MESSAGE,
            TicketAutomationActionType::SEND_NOTIFICATION,
        ];
    }

    private function supportedAutomationConditionFieldValues(): array
    {
        return array_map(fn (TicketAutomationConditionField $field) => $field->value, $this->supportedAutomationConditionFields());
    }

    private function supportedAutomationActionTypeValues(): array
    {
        return array_map(fn (TicketAutomationActionType $type) => $type->value, $this->supportedAutomationActionTypes());
    }

    private function validatedAutomationConditions(TicketBoard $board): array
    {
        $conditions = collect($this->automationConditions)
            ->values()
            ->filter(fn ($condition) => filled(data_get($condition, 'field')));

        if ($conditions->isEmpty()) {
            throw ValidationException::withMessages([
                'automationConditions' => 'Adicione pelo menos uma condicao para a automacao.',
            ]);
        }

        $validGroupIds = $board->groups->pluck('id')->map(fn ($id) => (int) $id)->all();
        $validPriorityValues = collect(TicketPriority::cases())->map(fn (TicketPriority $priority) => $priority->value)->all();
        $supportedFields = $this->supportedAutomationConditionFieldValues();

        return $conditions->map(function (array $condition, int $index) use ($validGroupIds, $validPriorityValues, $supportedFields) {
            $field = TicketAutomationConditionField::tryFrom((string) data_get($condition, 'field'));
            $operator = TicketAutomationConditionOperator::tryFrom((string) data_get($condition, 'operator'));

            if (! $field || ! $operator) {
                throw ValidationException::withMessages([
                    'automationConditions' => 'Existe uma condicao com campo ou operador invalido.',
                ]);
            }

            if (! in_array($field->value, $supportedFields, true)) {
                throw ValidationException::withMessages([
                    'automationConditions' => 'As automacoes novas aceitam apenas prioridade, etapa, responsavel e fechamento.',
                ]);
            }

            if (
                in_array($field, [TicketAutomationConditionField::HAS_ASSIGNEE, TicketAutomationConditionField::IS_CLOSED], true)
                && ! in_array($operator, [TicketAutomationConditionOperator::IS_TRUE, TicketAutomationConditionOperator::IS_FALSE], true)
            ) {
                throw ValidationException::withMessages([
                    'automationConditions' => "A condicao {$field->label()} aceita apenas operadores booleanos.",
                ]);
            }

            if (
                ! in_array($field, [TicketAutomationConditionField::HAS_ASSIGNEE, TicketAutomationConditionField::IS_CLOSED], true)
                && in_array($operator, [TicketAutomationConditionOperator::IS_TRUE, TicketAutomationConditionOperator::IS_FALSE], true)
            ) {
                throw ValidationException::withMessages([
                    'automationConditions' => "A condicao {$field->label()} precisa de um operador baseado em valor.",
                ]);
            }

            $rawValues = is_array(data_get($condition, 'value'))
                ? data_get($condition, 'value')
                : [data_get($condition, 'value')];

            $values = collect($rawValues)
                ->filter(fn ($value) => ! is_null($value) && $value !== '')
                ->map(fn ($value) => is_numeric($value) ? (int) $value : (string) $value)
                ->values();

            if (in_array($field, [TicketAutomationConditionField::HAS_ASSIGNEE, TicketAutomationConditionField::IS_CLOSED], true)) {
                $normalizedValue = [];
            } elseif ($values->isEmpty()) {
                throw ValidationException::withMessages([
                    'automationConditions' => "Informe ao menos um valor para a condicao {$field->label()}.",
                ]);
            } else {
                $normalizedValue = match ($field) {
                    TicketAutomationConditionField::PRIORITY => $values
                        ->each(function ($value) use ($field, $validPriorityValues): void {
                            if (! in_array((string) $value, $validPriorityValues, true)) {
                                throw ValidationException::withMessages([
                                    'automationConditions' => "A condicao {$field->label()} recebeu uma prioridade invalida.",
                                ]);
                            }
                        })
                        ->map(fn ($value) => (string) $value)
                        ->all(),
                    TicketAutomationConditionField::GROUP_ID => $values
                        ->each(function ($value) use ($field, $validGroupIds): void {
                            if (! in_array((int) $value, $validGroupIds, true)) {
                                throw ValidationException::withMessages([
                                    'automationConditions' => "A condicao {$field->label()} recebeu uma etapa invalida para este quadro.",
                                ]);
                            }
                        })
                        ->map(fn ($value) => (int) $value)
                        ->all(),
                    default => $values->all(),
                };
            }

            return [
                'field' => $field->value,
                'operator' => $operator->value,
                'value' => $normalizedValue,
                'sort_order' => $index + 1,
            ];
        })->all();
    }

    private function validatedAutomationActions(TicketBoard $board): array
    {
        $actions = collect($this->automationActions)
            ->values()
            ->filter(fn ($action) => filled(data_get($action, 'action')));

        if ($actions->isEmpty()) {
            throw ValidationException::withMessages([
                'automationActions' => 'Adicione pelo menos uma acao para a automacao.',
            ]);
        }

        $validAssigneeIds = $this->boardAssignees()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $validStatusIds = $board->statuses->pluck('id')->map(fn ($id) => (int) $id)->all();
        $validGroupIds = $board->groups->pluck('id')->map(fn ($id) => (int) $id)->all();
        $validPriorityValues = collect(TicketPriority::cases())->map(fn (TicketPriority $priority) => $priority->value)->all();
        $supportedActions = $this->supportedAutomationActionTypeValues();

        return $actions->map(function (array $action, int $index) use ($board, $validAssigneeIds, $validStatusIds, $validGroupIds, $validPriorityValues, $supportedActions) {
            $type = TicketAutomationActionType::tryFrom((string) data_get($action, 'action'));

            if (! $type) {
                throw ValidationException::withMessages([
                    'automationActions' => 'Existe uma acao com tipo invalido.',
                ]);
            }

            $payload = (array) data_get($action, 'payload', []);

            if (! in_array($type->value, $supportedActions, true)) {
                if ($type === TicketAutomationActionType::CHANGE_STATUS) {
                    $type = TicketAutomationActionType::CHANGE_GROUP;
                    $payload = $this->validatedLegacyStatusToGroupPayload($board, $payload, $validStatusIds, false);
                } elseif ($type === TicketAutomationActionType::REOPEN_TICKET) {
                    $type = TicketAutomationActionType::CHANGE_GROUP;
                    $payload = $this->validatedLegacyStatusToGroupPayload($board, [
                        'status_id' => data_get($payload, 'target_status_id'),
                    ], $validStatusIds, true);
                } else {
                    throw ValidationException::withMessages([
                        'automationActions' => 'As automacoes novas aceitam apenas acoes de etapa, prioridade, responsavel, mensagem e notificacao.',
                    ]);
                }
            }

            $normalizedPayload = match ($type) {
                TicketAutomationActionType::ASSIGN_FIXED_ASSIGNEE => $this->validatedAssigneePayload($payload, $validAssigneeIds),
                TicketAutomationActionType::CHANGE_GROUP => $this->validatedGroupPayload($payload, $validGroupIds),
                TicketAutomationActionType::CHANGE_PRIORITY => $this->validatedPriorityPayload($payload, $validPriorityValues),
                TicketAutomationActionType::ADD_SYSTEM_MESSAGE => $this->validatedSystemMessagePayload($payload),
                TicketAutomationActionType::SEND_NOTIFICATION => $this->validatedNotificationPayload($payload),
                default => throw ValidationException::withMessages([
                    'automationActions' => 'Existe uma acao que nao pode mais ser usada neste editor.',
                ]),
            };

            return [
                'action' => $type->value,
                'payload' => $normalizedPayload,
                'sort_order' => $index + 1,
            ];
        })->all();
    }

    private function validatedAssigneePayload(array $payload, array $validAssigneeIds): array
    {
        $assigneeId = data_get($payload, 'assignee_id');

        if (! filled($assigneeId) || ! in_array((int) $assigneeId, $validAssigneeIds, true)) {
            throw ValidationException::withMessages([
                'automationActions' => 'Selecione um responsavel valido para a acao de atribuicao.',
            ]);
        }

        return ['assignee_id' => (int) $assigneeId];
    }

    private function validatedStatusPayload(array $payload, array $validStatusIds): array
    {
        $statusId = data_get($payload, 'status_id');

        if (! filled($statusId) || ! in_array((int) $statusId, $validStatusIds, true)) {
            throw ValidationException::withMessages([
                'automationActions' => 'Selecione um status valido para este quadro.',
            ]);
        }

        return ['status_id' => (int) $statusId];
    }

    private function validatedGroupPayload(array $payload, array $validGroupIds): array
    {
        $groupId = data_get($payload, 'group_id');

        if (! filled($groupId) || ! in_array((int) $groupId, $validGroupIds, true)) {
            throw ValidationException::withMessages([
                'automationActions' => 'Selecione uma etapa valida para este quadro.',
            ]);
        }

        return ['group_id' => (int) $groupId];
    }

    private function validatedLegacyStatusToGroupPayload(TicketBoard $board, array $payload, array $validStatusIds, bool $requireOpenGroup): array
    {
        $statusId = data_get($payload, 'status_id');

        if (! filled($statusId) || ! in_array((int) $statusId, $validStatusIds, true)) {
            throw ValidationException::withMessages([
                'automationActions' => 'Selecione um status legado valido para converter a automacao.',
            ]);
        }

        $status = $board->statuses->firstWhere('id', (int) $statusId);
        $targetGroup = $board->groups->firstWhere('sort_order', $status?->sort_order);

        if (! $targetGroup) {
            $targetGroup = $requireOpenGroup
                ? $board->groups->firstWhere('is_closed', false)
                : $board->groups->firstWhere('is_closed', (bool) $status?->is_closed);
        }

        if (! $targetGroup || ($requireOpenGroup && $targetGroup->is_closed)) {
            throw ValidationException::withMessages([
                'automationActions' => 'Nao foi possivel converter o status legado para uma etapa valida deste quadro.',
            ]);
        }

        return ['group_id' => (int) $targetGroup->id];
    }

    private function validatedPriorityPayload(array $payload, array $validPriorityValues): array
    {
        $priority = data_get($payload, 'priority');

        if (! is_string($priority) || ! in_array($priority, $validPriorityValues, true)) {
            throw ValidationException::withMessages([
                'automationActions' => 'Selecione uma prioridade valida.',
            ]);
        }

        return ['priority' => $priority];
    }

    private function validatedSystemMessagePayload(array $payload): array
    {
        $message = trim((string) data_get($payload, 'message'));

        if ($message === '') {
            throw ValidationException::withMessages([
                'automationActions' => 'Informe a mensagem automatica que sera registrada no ticket.',
            ]);
        }

        return ['message' => $message];
    }

    private function validatedNotificationPayload(array $payload): array
    {
        $title = trim((string) data_get($payload, 'title'));
        $message = trim((string) data_get($payload, 'message'));

        if ($title === '' || $message === '') {
            throw ValidationException::withMessages([
                'automationActions' => 'Informe titulo e mensagem para a notificacao automatica.',
            ]);
        }

        return [
            'title' => $title,
            'message' => $message,
        ];
    }

    private function validatedReopenPayload(array $payload, array $validOpenStatusIds): array
    {
        $targetStatusId = data_get($payload, 'target_status_id');

        if (! filled($targetStatusId) || ! in_array((int) $targetStatusId, $validOpenStatusIds, true)) {
            throw ValidationException::withMessages([
                'automationActions' => 'Selecione um status aberto valido para a reabertura.',
            ]);
        }

        $message = trim((string) data_get($payload, 'message'));

        return [
            'target_status_id' => (int) $targetStatusId,
            'message' => $message !== '' ? $message : null,
        ];
    }

    private function automationConditionValueLabel(TicketAutomationConditionField $field, mixed $value): string
    {
        return match ($field) {
            TicketAutomationConditionField::PRIORITY => $this->priorityLabel((string) $value) ?? (string) $value,
            TicketAutomationConditionField::STATUS_ID => $this->statusName((int) $value) ?? 'Status removido',
            TicketAutomationConditionField::GROUP_ID => $this->groupName((int) $value) ?? 'Etapa removida',
            TicketAutomationConditionField::HAS_ASSIGNEE => (bool) $value ? 'Sim' : 'Nao',
            TicketAutomationConditionField::IS_CLOSED => (bool) $value ? 'Fechado' : 'Aberto',
        };
    }

    private function assigneeName(int $assigneeId): ?string
    {
        return $this->boardAssignees()->firstWhere('id', $assigneeId)?->name;
    }

    private function statusName(int $statusId): ?string
    {
        return $this->board()?->statuses->firstWhere('id', $statusId)?->name;
    }

    private function groupName(int $groupId): ?string
    {
        return $this->board()?->groups->firstWhere('id', $groupId)?->name;
    }

    private function priorityLabel(string $priority): ?string
    {
        return TicketPriority::tryFrom($priority)?->label();
    }

    public function render(): View
    {
        $board = $this->board();

        return view('livewire.tickets.settings-page', [
            'board' => $board,
            'sectorOptions' => $this->availableSectors(),
            'fieldTypes' => TicketFieldType::cases(),
            'priorities' => TicketPriority::cases(),
            'groupImpacts' => $board ? $board->groups->mapWithKeys(fn (TicketGroup $group) => [$group->id => $this->groupImpact($group)])->all() : [],
            'automationTriggers' => TicketAutomationTrigger::cases(),
            'automationConditionFields' => $this->supportedAutomationConditionFields(),
            'automationConditionOperators' => TicketAutomationConditionOperator::cases(),
            'automationActionTypes' => $this->supportedAutomationActionTypes(),
            'automationAssignees' => $this->boardAssignees(),
        ])->layout('layouts.portal', [
            'title' => 'Configurar quadro',
            'subtitle' => 'Etapas, SLA, automacoes, campos e formularios do setor.',
        ]);
    }

    private function loadBoardMeta(): void
    {
        $board = $this->board();
        $this->boardName = $board?->name ?? '';
        $this->boardDescription = $board?->description ?? '';
        $this->catalogForm['ticket_form_id'] = $board?->forms()->where('is_default', true)->value('id');
        $this->catalogForm['default_ticket_group_id'] = $board?->defaultGroup()?->id;
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

        $board = TicketBoard::query()->with([
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
        ])->where('sector_id', $sector->id)->first();

        if ($board) {
            return $board;
        }

        app(SectorProvisioningService::class)->provision($sector);

        return TicketBoard::query()->with([
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
        ])->where('sector_id', $sector->id)->first();
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

        return User::query()->withSectorAccess($this->selectedSectorId, ['sector_admin', 'technician'])
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

    private function fieldForBoard(int $fieldId): TicketField
    {
        $field = TicketField::query()->findOrFail($fieldId);
        $this->authorize('update', $field->board);
        abort_unless($field->ticket_board_id === $this->board()?->id, 404);
        return $field;
    }

    private function formForBoard(int $formId): TicketForm
    {
        $form = TicketForm::query()->with('fields')->findOrFail($formId);
        $this->authorize('update', $form->board);
        abort_unless($form->ticket_board_id === $this->board()?->id, 404);
        return $form;
    }

    private function catalogItemForBoard(int $catalogItemId): ServiceCatalogItem
    {
        $catalogItem = ServiceCatalogItem::query()->findOrFail($catalogItemId);
        $this->authorize('update', $catalogItem->board);
        abort_unless($catalogItem->ticket_board_id === $this->board()?->id, 404);
        return $catalogItem;
    }

    private function hasAnyActiveSector(): bool
    {
        return Sector::query()->where('is_active', true)->exists();
    }

    private function groupImpact(TicketGroup $group): array
    {
        return [
            'tickets_count' => $group->tickets()->count(),
            'catalog_count' => $group->board->catalogItems()->where('default_ticket_group_id', $group->id)->count(),
            'remaining_groups_count' => max($group->board->groups()->count() - 1, 0),
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
        $neighbour = $item::query()->where('ticket_board_id', $item->getAttribute('ticket_board_id'))
            ->where('sort_order', $column, $item->getAttribute('sort_order'))
            ->orderBy('sort_order', $sort)
            ->first();

        if (! $neighbour) {
            return;
        }

        DB::transaction(function () use ($item, $neighbour): void {
            $currentOrder = $item->getAttribute('sort_order');
            $item->update(['sort_order' => $neighbour->getAttribute('sort_order')]);
            $neighbour->update(['sort_order' => $currentOrder]);
            $this->normalizeSortOrder($item::class, $item->getAttribute('ticket_board_id'));
        });
    }

    private function normalizeSortOrder(string $modelClass, int $boardId): void
    {
        $modelClass::query()->where('ticket_board_id', $boardId)->orderBy('sort_order')->orderBy('id')->get()
            ->values()->each(fn (Model $model, int $index) => $model->update(['sort_order' => $index + 1]));
    }

    private function parsedOptions(?string $optionsText): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $optionsText))->filter()->values()
            ->map(function (string $line) {
                [$label, $color] = array_pad(array_map('trim', explode('|', $line, 2)), 2, null);
                return ['label' => $label, 'value' => Str::slug($label, '_'), 'color' => $color ?: null];
            })->all();
    }

    private function resetForms(): void
    {
        $this->resetValidation();
        $this->openSection = 'groups';
        $this->groupForm = $this->emptyGroupForm();
        $this->editGroupForm = $this->emptyGroupForm();
        $this->statusForm = $this->emptyStatusForm();
        $this->editStatusForm = $this->emptyStatusForm();
        $this->fieldForm = $this->emptyFieldForm();
        $this->formForm = $this->emptyFormForm();
        $this->editingGroupId = null;
        $this->editingStatusId = null;
        $this->editingFormId = null;
        $board = $this->board();
        $this->catalogForm = $this->emptyCatalogForm($board);
        $this->resetAutomationForm();
        $this->cancelDeletion();
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

    private function ensureSingleActiveGroupFlag(int $boardId, string $flag, ?int $preferredGroupId = null): void
    {
        $groups = TicketGroup::query()->where('ticket_board_id', $boardId)->orderBy('sort_order')->orderBy('id')->get();
        $preferred = $preferredGroupId ? $groups->firstWhere('id', $preferredGroupId) : null;

        $selectedGroup = $preferred && $preferred->is_active
            ? $preferred
            : ($flag === 'is_closed'
                ? ($groups->first(fn (TicketGroup $group) => $group->is_active && $group->is_closed) ?? $groups->where('is_active', true)->last() ?? $groups->last())
                : ($groups->first(fn (TicketGroup $group) => $group->is_active && $group->is_default) ?? $groups->firstWhere('is_active', true) ?? $groups->first()));

        if (! $selectedGroup) {
            throw new \RuntimeException('O quadro precisa manter ao menos uma etapa.');
        }

        TicketGroup::query()->where('ticket_board_id', $boardId)->update([$flag => false]);
        $selectedGroup->forceFill([$flag => true, 'is_active' => true])->save();
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

        TicketStatus::query()
            ->whereKey($defaultStatus->id)
            ->update([
                'is_default' => true,
                'is_active' => true,
            ]);
    }

    private function nullableReplacementId(mixed $value): ?int
    {
        return ($value === '' || $value === null) ? null : (int) $value;
    }

    private function ensureGroupReplacementBelongsToBoard(int $boardId, int $replacementId, int $deletedGroupId): void
    {
        $exists = TicketGroup::query()->where('ticket_board_id', $boardId)->whereKey($replacementId)->whereKeyNot($deletedGroupId)->exists();
        abort_unless($exists, 422);
    }

    private function groupRules(string $prefix): array
    {
        return [
            "{$prefix}.name" => ['required', 'string', 'max:80'],
            "{$prefix}.color" => ['required', 'string', 'max:20'],
            "{$prefix}.is_collapsed_by_default" => ['boolean'],
            "{$prefix}.is_default" => ['boolean'],
            "{$prefix}.is_closed" => ['boolean'],
            "{$prefix}.is_active" => ['boolean'],
        ];
    }

    private function emptyGroupForm(): array
    {
        return ['name' => '', 'color' => '#2563eb', 'is_collapsed_by_default' => false, 'is_default' => false, 'is_closed' => false, 'is_active' => true];
    }

    private function emptyFieldForm(): array
    {
        return ['name' => '', 'type' => 'text', 'placeholder' => '', 'help_text' => '', 'is_required' => false, 'show_on_board' => true, 'is_active' => true, 'options_text' => ''];
    }

    private function emptyStatusForm(): array
    {
        return ['name' => '', 'color' => '#2563eb', 'is_default' => false, 'is_closed' => false, 'is_active' => true];
    }

    private function emptyFormForm(): array
    {
        return ['name' => '', 'description' => '', 'field_ids' => [], 'required_field_ids' => [], 'is_default' => false, 'is_active' => true];
    }

    private function emptyCatalogForm(?TicketBoard $board): array
    {
        return [
            'name' => '',
            'description' => '',
            'ticket_form_id' => $board?->forms()->where('is_default', true)->value('id'),
            'default_ticket_group_id' => $board?->defaultGroup()?->id,
            'default_priority' => TicketPriority::MEDIUM->value,
            'is_active' => true,
        ];
    }

    private function emptyCondition(int $sortOrder): array
    {
        return ['field' => TicketAutomationConditionField::PRIORITY->value, 'operator' => TicketAutomationConditionOperator::IN->value, 'value' => [], 'sort_order' => $sortOrder];
    }

    private function emptyAction(int $sortOrder): array
    {
        return ['action' => TicketAutomationActionType::CHANGE_GROUP->value, 'payload' => ['group_id' => null], 'sort_order' => $sortOrder];
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
