<?php

namespace App\Modules\Tickets\Livewire;

use App\Models\User;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketField;
use App\Modules\Tickets\Support\TicketIndexOptions;
use App\Modules\Tickets\Support\TicketIndexQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
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

    public array $fieldFilters = [];

    public function updatedSelectedSectorId(): void
    {
        if (! $this->selectedSectorId) {
            $this->selectedGroupId = null;
            $this->fieldFilters = [];
            $this->resetPage();
            return;
        }

        abort_unless($this->sectorOptions()->pluck('id')->contains($this->selectedSectorId), 403);
        $this->selectedGroupId = null;
        $this->fieldFilters = [];
        $this->resetPage();
    }

    public function updated($name): void
    {
        if ($name === 'selectedSectorId') {
            return;
        }

        $this->resetPage();
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $options = app(TicketIndexOptions::class);
        $sectorOptions = $options->sectorOptions($user);
        $groupOptions = $options->groupOptions($user, $this->selectedSectorId);
        $fieldOptions = $options->fieldOptions($user, $this->selectedSectorId);

        $normalizedFieldFilters = collect($this->fieldFilters)
            ->mapWithKeys(fn ($value, $fieldId) => [(int) $fieldId => is_string($value) ? trim($value) : $value])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        $tickets = app(TicketIndexQuery::class)
            ->build($user, [
                'sector_id' => $this->selectedSectorId,
                'title' => trim($this->titleFilter),
                'group_id' => $this->selectedGroupId,
                'requester' => trim($this->requesterFilter),
                'assignee' => trim($this->assigneeFilter),
                'updated_from' => $this->updatedFrom,
                'updated_to' => $this->updatedTo,
                'field_filters' => $normalizedFieldFilters,
            ])
            ->paginate(12);

        return view('livewire.tickets.index-page', [
            'tickets' => $tickets,
            'sectorOptions' => $sectorOptions,
            'groupOptions' => $groupOptions,
            'fieldOptions' => $fieldOptions,
            'canAccessBoard' => $user->hasOperationalAccess(),
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
            'title' => 'Chamados',
            'subtitle' => 'Acompanhe os chamados visiveis para o seu perfil e acesse os detalhes de cada atendimento.',
        ]);
    }

    public function fieldValue(Ticket $ticket, TicketField $field): mixed
    {
        $value = $ticket->fieldValues->firstWhere('ticket_field_id', $field->id)?->primitive_value;

        return is_bool($value) ? ($value ? 'Sim' : 'Nao') : $value;
    }

    private function sectorOptions()
    {
        /** @var User $user */
        $user = auth()->user();

        return app(TicketIndexOptions::class)->sectorOptions($user);
    }
}
