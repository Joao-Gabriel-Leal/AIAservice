<?php

namespace App\Modules\Tickets\Livewire;

use App\Enums\TicketPriority;
use App\Models\User;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\ServiceCatalogItem;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Models\TicketForm;
use App\Modules\Tickets\Services\SectorProvisioningService;
use App\Modules\Tickets\Services\TicketWorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreatePage extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public ?int $selectedSectorId = null;

    public ?int $selectedCatalogId = null;

    public ?int $selectedFormId = null;

    public string $title = '';

    public string $description = '';

    public string $priority = 'medium';

    public array $dynamicValues = [];

    public array $attachments = [];

    public function mount(?ServiceCatalogItem $catalogItem = null, Request $request): void
    {
        if ($catalogItem) {
            $this->selectedSectorId = $catalogItem->board?->sector_id;
            $this->selectedCatalogId = $catalogItem->id;
            $this->selectedFormId = $catalogItem->ticket_form_id;
            $this->priority = $catalogItem->default_priority?->value ?? TicketPriority::MEDIUM->value;
        } else {
            $this->selectedSectorId = $this->resolveSectorId($request->integer('sector'));
            $this->selectedFormId = $this->resolveFormId($request->integer('form'));
            $this->selectedCatalogId = $this->resolveCatalogIdForForm($this->selectedFormId);
        }
    }

    public function updatedSelectedSectorId(): void
    {
        $this->selectedCatalogId = null;
        $this->selectedFormId = null;
        $this->dynamicValues = [];
        $this->priority = TicketPriority::MEDIUM->value;
    }

    public function updatedSelectedFormId(): void
    {
        $this->selectedCatalogId = $this->resolveCatalogIdForForm($this->selectedFormId);
        $this->dynamicValues = [];

        $catalog = $this->selectedCatalog();
        $this->priority = $catalog?->default_priority?->value ?? TicketPriority::MEDIUM->value;
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
    }

    public function submit(TicketWorkflowService $workflowService)
    {
        $catalog = $this->selectedCatalog();
        $board = $this->board();
        $form = $this->selectedForm();

        abort_unless($form && $board, 404);

        $validated = $this->validate($this->rules($form?->fields ?? collect()));
        $groupId = $catalog->default_ticket_group_id
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
        ], $this->dynamicValues, $this->attachments);

        return redirect()->route('tickets.show', $ticket)->with('status', 'Chamado criado com sucesso.');
    }

    public function render(): View
    {
        $catalog = $this->selectedCatalog();
        $form = $this->selectedForm();
        $formFields = $form?->fields?->sortBy('pivot.sort_order')->values() ?? collect();
        $sectorOptions = $this->availableSectors();
        $selectedSector = $sectorOptions->firstWhere('id', $this->selectedSectorId);

        return view('livewire.tickets.create-page', [
            'sectorOptions' => $sectorOptions,
            'formOptions' => $this->formOptions(),
            'sectorUsers' => $this->sectorUsers(),
            'priorities' => TicketPriority::cases(),
            'formFields' => $formFields,
            'selectedSector' => $selectedSector,
            'selectedForm' => $form,
            'selectedCatalog' => $catalog,
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
            'selectedFormId' => ['required', 'exists:ticket_forms,id'],
            'selectedCatalogId' => ['nullable', 'exists:service_catalog_items,id'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'string'],
            'attachments.*' => ['nullable', 'file', 'max:10240'],
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
            ->with([
                'fields.options',
                'fields',
                'board.groups',
                'board.statuses',
            ])
            ->whereHas('board', fn ($query) => $query->where('sector_id', $this->selectedSectorId))
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
            ->whereHas('board', fn ($query) => $query->where('sector_id', $this->selectedSectorId))
            ->where('is_active', true)
            ->find($this->selectedCatalogId);
    }

    private function board(): ?TicketBoard
    {
        if (! $this->selectedSectorId) {
            return null;
        }

        $board = TicketBoard::query()
            ->with(['groups', 'statuses', 'catalogItems'])
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
            ->with(['groups', 'statuses', 'catalogItems'])
            ->where('sector_id', $this->selectedSectorId)
            ->first();
    }

    private function availableSectors(): Collection
    {
        $query = Sector::query()->with('company')->orderBy('name');

        return $query->where('is_active', true)->get();
    }

    private function formOptions(): Collection
    {
        if (! $this->selectedSectorId) {
            return collect();
        }

        return TicketForm::query()
            ->whereHas('board', fn ($query) => $query->where('sector_id', $this->selectedSectorId))
            ->with(['catalogItems' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->where('is_active', true)
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

    private function resolveFormId(?int $requestedFormId = null): ?int
    {
        if (! $requestedFormId || ! $this->selectedSectorId) {
            return null;
        }

        return TicketForm::query()
            ->whereHas('board', fn ($query) => $query->where('sector_id', $this->selectedSectorId))
            ->where('is_active', true)
            ->whereKey($requestedFormId)
            ->value('id');
    }

    private function resolveCatalogIdForForm(?int $formId = null): ?int
    {
        if (! $formId || ! $this->selectedSectorId) {
            return null;
        }

        return ServiceCatalogItem::query()
            ->whereHas('board', fn ($query) => $query->where('sector_id', $this->selectedSectorId))
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
}
