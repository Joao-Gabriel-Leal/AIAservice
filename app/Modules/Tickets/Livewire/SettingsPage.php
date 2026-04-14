<?php

namespace App\Modules\Tickets\Livewire;

use App\Enums\TicketFieldType;
use App\Enums\TicketPriority;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\ServiceCatalogItem;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Models\TicketField;
use App\Modules\Tickets\Models\TicketFieldOption;
use App\Modules\Tickets\Models\TicketForm;
use App\Modules\Tickets\Models\TicketGroup;
use App\Modules\Tickets\Models\TicketStatus;
use App\Modules\Tickets\Services\SectorProvisioningService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class SettingsPage extends Component
{
    use AuthorizesRequests;

    public ?int $selectedSectorId = null;

    public string $boardName = '';

    public string $boardDescription = '';

    public array $groupForm = [
        'name' => '',
        'color' => '#2563eb',
        'is_collapsed_by_default' => false,
        'is_active' => true,
    ];

    public array $statusForm = [
        'name' => '',
        'color' => '#2563eb',
        'is_default' => false,
        'is_closed' => false,
        'is_active' => true,
    ];

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

    public function mount(?Sector $sector = null): void
    {
        $this->selectedSectorId = $sector?->id
            ?? auth()->user()->sector_id
            ?? $this->availableSectors()->first()?->id;

        $this->loadBoardMeta();
    }

    public function updatedSelectedSectorId(): void
    {
        abort_unless($this->availableSectors()->pluck('id')->contains($this->selectedSectorId), 403);
        $this->loadBoardMeta();
        $this->resetForms();
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

    public function deleteGroup(int $groupId): void
    {
        $group = TicketGroup::query()->findOrFail($groupId);
        $this->authorize('update', $group->board);

        $group->tickets()->update(['ticket_group_id' => null]);
        $group->board->catalogItems()->where('default_ticket_group_id', $group->id)->update(['default_ticket_group_id' => null]);
        $group->delete();

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

        if ($validated['statusForm']['is_default']) {
            $board->statuses()->update(['is_default' => false]);
        }

        TicketStatus::query()->create([
            'ticket_board_id' => $board->id,
            'name' => $validated['statusForm']['name'],
            'slug' => $this->uniqueSlug(TicketStatus::class, $board->id, $validated['statusForm']['name']),
            'color' => $validated['statusForm']['color'],
            'sort_order' => ((int) $board->statuses()->max('sort_order')) + 1,
            'is_default' => (bool) $validated['statusForm']['is_default'],
            'is_closed' => (bool) $validated['statusForm']['is_closed'],
            'is_active' => (bool) $validated['statusForm']['is_active'],
        ]);

        $this->statusForm = [
            'name' => '',
            'color' => '#2563eb',
            'is_default' => false,
            'is_closed' => false,
            'is_active' => true,
        ];

        session()->flash('status', 'Status criado com sucesso.');
    }

    public function deleteStatus(int $statusId): void
    {
        $status = TicketStatus::query()->findOrFail($statusId);
        $this->authorize('update', $status->board);

        $fallbackStatusId = $status->board->statuses()
            ->whereKeyNot($status->id)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->value('id');

        $status->tickets()->update(['ticket_status_id' => $fallbackStatusId]);
        $status->delete();

        session()->flash('status', 'Status removido com sucesso.');
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

    public function render(): View
    {
        return view('livewire.tickets.settings-page', [
            'board' => $this->board(),
            'sectorOptions' => $this->availableSectors(),
            'fieldTypes' => TicketFieldType::cases(),
            'priorities' => TicketPriority::cases(),
        ])->layout('layouts.portal', [
            'title' => 'Configurar quadro',
            'subtitle' => 'Grupos, status, campos, formularios e catalogo de servicos do setor.',
        ]);
    }

    private function loadBoardMeta(): void
    {
        $board = $this->board();
        $this->boardName = $board?->name ?? '';
        $this->boardDescription = $board?->description ?? '';
        $this->catalogForm['ticket_form_id'] = $board?->forms()->where('is_default', true)->value('id');
        $this->catalogForm['default_ticket_group_id'] = $board?->groups()->value('id');
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
            ])
            ->where('sector_id', $sector->id)
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

        $this->statusForm = [
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
