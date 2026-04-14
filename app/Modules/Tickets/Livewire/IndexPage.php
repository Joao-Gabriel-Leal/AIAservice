<?php

namespace App\Modules\Tickets\Livewire;

use App\Models\User;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\Ticket;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class IndexPage extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public ?int $selectedSectorId = null;

    public function updatedSelectedSectorId(): void
    {
        if (! $this->selectedSectorId) {
            return;
        }

        abort_unless($this->sectorOptions()->pluck('id')->contains($this->selectedSectorId), 403);
        $this->resetPage();
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $sectorOptions = $this->sectorOptions();

        $tickets = Ticket::query()
            ->visibleTo($user)
            ->with(['sector.company', 'group', 'requester', 'assignee', 'rating', 'catalogItem'])
            ->when($this->selectedSectorId, fn ($query) => $query->where('sector_id', $this->selectedSectorId))
            ->latest('updated_at')
            ->paginate(12);

        return view('livewire.tickets.index-page', [
            'tickets' => $tickets,
            'sectorOptions' => $sectorOptions,
            'canAccessBoard' => $user->hasOperationalAccess(),
        ])->layout('layouts.portal', [
            'title' => 'Chamados',
            'subtitle' => 'Acompanhe os chamados visiveis para o seu perfil e acesse os detalhes de cada atendimento.',
        ]);
    }

    private function sectorOptions(): Collection
    {
        /** @var User $user */
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return Sector::query()
                ->with('company')
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        $sectorIds = collect($user->allSectorIds())
            ->merge(
                Ticket::query()
                    ->where('requester_id', $user->id)
                    ->pluck('sector_id')
                    ->all(),
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($sectorIds === []) {
            return collect();
        }

        return Sector::query()
            ->with('company')
            ->whereIn('id', $sectorIds)
            ->orderBy('name')
            ->get();
    }
}
