<?php

namespace App\Modules\Tickets\Livewire;

use App\Enums\TicketPriority;
use App\Models\User;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\ServiceCatalogItem;
use App\Modules\Tickets\Models\TicketBoard;
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
            $this->priority = $catalogItem->default_priority?->value ?? TicketPriority::MEDIUM->value;
        } else {
            $this->selectedSectorId = $this->resolveSectorId($request->integer('sector'));
            $this->selectedCatalogId = $this->catalogItems()->first()?->id;
        }
    }

    public function updatedSelectedSectorId(): void
    {
        $this->selectedCatalogId = $this->catalogItems()->first()?->id;
        $this->dynamicValues = [];
    }

    public function updatedSelectedCatalogId(): void
    {
        $catalog = $this->selectedCatalog();

        if ($catalog?->default_priority) {
            $this->priority = $catalog->default_priority->value;
        }
    }

    public function submit(TicketWorkflowService $workflowService)
    {
        $catalog = $this->selectedCatalog();
        $board = $this->board();

        abort_unless($catalog && $board, 404);

        $form = $catalog->form;
        $validated = $this->validate($this->rules($form?->fields ?? collect()));
        $status = $board->statuses->firstWhere('is_default', true) ?? $board->statuses->first();

        $ticket = $workflowService->createTicket(auth()->user(), [
            'sector_id' => $board->sector_id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $catalog->default_ticket_group_id,
            'ticket_status_id' => $status?->id,
            'service_catalog_item_id' => $catalog->id,
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
        $formFields = $catalog?->form?->fields?->sortBy('pivot.sort_order')->values() ?? collect();

        return view('livewire.tickets.create-page', [
            'sectorOptions' => $this->availableSectors(),
            'catalogItems' => $this->catalogItems(),
            'sectorUsers' => $this->sectorUsers(),
            'priorities' => TicketPriority::cases(),
            'formFields' => $formFields,
        ])->layout('layouts.portal', [
            'title' => 'Abrir chamado',
            'subtitle' => 'Preencha as informacoes do chamado e envie para o quadro do setor.',
        ]);
    }

    private function rules(Collection $fields): array
    {
        $rules = [
            'selectedSectorId' => ['required', 'exists:sectors,id'],
            'selectedCatalogId' => ['required', 'exists:service_catalog_items,id'],
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

    private function selectedCatalog(): ?ServiceCatalogItem
    {
        if (! $this->selectedCatalogId) {
            return null;
        }

        return ServiceCatalogItem::query()
            ->with([
                'form.fields.options',
                'form.fields',
                'board.statuses',
            ])
            ->whereHas('board', fn ($query) => $query->where('sector_id', $this->selectedSectorId))
            ->find($this->selectedCatalogId);
    }

    private function board(): ?TicketBoard
    {
        if (! $this->selectedSectorId) {
            return null;
        }

        $board = TicketBoard::query()
            ->with(['statuses', 'catalogItems'])
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
            ->with(['statuses', 'catalogItems'])
            ->where('sector_id', $this->selectedSectorId)
            ->first();
    }

    private function availableSectors(): Collection
    {
        $query = Sector::query()->with('company')->orderBy('name');

        return $query->where('is_active', true)->get();
    }

    private function catalogItems(): Collection
    {
        if (! $this->selectedSectorId) {
            return collect();
        }

        return ServiceCatalogItem::query()
            ->whereHas('board', fn ($query) => $query->where('sector_id', $this->selectedSectorId))
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

        return $this->availableSectors()->first()?->id;
    }
}
