<?php

namespace App\Modules\Tickets\Livewire;

use App\Modules\Sectors\Models\Sector;
use App\Modules\Shared\Support\CurrentCompanyContext;
use App\Modules\Tickets\Models\TicketForm;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Livewire\Component;

class CentralPage extends Component
{
    public ?int $selectedSectorId = null;
    public string $sectorSearch = '';

    protected array $queryString = [
        'selectedSectorId' => ['as' => 'sector', 'except' => null],
    ];

    public function mount(Request $request): void
    {
        $this->selectedSectorId = $this->resolveSectorId($request->integer('sector'));
    }

    public function selectSector(int $sectorId): void
    {
        $this->selectedSectorId = $this->resolveSectorId($sectorId);
        $this->dispatch('central-sector-selected', sectorId: $this->selectedSectorId);
    }

    public function updatedSectorSearch(): void
    {
        $this->sectorSearch = trim($this->sectorSearch);
    }

    public function render(): View
    {
        $sectors = $this->sectors();
        $filteredSectors = $this->filteredSectors($sectors);
        $selectedSector = $sectors->firstWhere('id', $this->selectedSectorId) ?? $sectors->first();
        $forms = $selectedSector?->boards
            ?->flatMap(fn ($board) => $board->forms->map(function (TicketForm $form) use ($board) {
                $form->setRelation('board', $board);

                return $form;
            }))
            ->values() ?? collect();

        return view('livewire.tickets.central-page', [
            'sectors' => $sectors,
            'filteredSectors' => $filteredSectors,
            'selectedSector' => $selectedSector,
            'forms' => $forms,
        ])->layout('layouts.portal', [
            'title' => 'Central de formularios',
            'subtitle' => 'Escolha o setor, veja os formularios disponiveis e abra o chamado certo com menos atrito.',
            'headerVariant' => 'none',
        ]);
    }

    private function sectors(): Collection
    {
        return Sector::query()
            ->with([
                'company',
                'boards' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderByDesc('is_default')
                    ->orderBy('name'),
                'boards.forms' => fn ($query) => $query
                    ->accessibleTo(auth()->user())
                    ->where('is_active', true)
                    ->with(['catalogItems' => fn ($catalogQuery) => $catalogQuery
                        ->where('is_active', true)
                        ->orderBy('name'),
                    ])
                    ->orderBy('name'),
            ])
            ->where('is_active', true)
            ->where('company_id', app(CurrentCompanyContext::class)->currentCompanyId(auth()->user()) ?: 0)
            ->orderBy('name')
            ->get();
    }

    private function resolveSectorId(?int $requestedSectorId = null): ?int
    {
        $sectors = $this->sectors();

        if ($requestedSectorId && $sectors->pluck('id')->contains($requestedSectorId)) {
            return $requestedSectorId;
        }

        return $sectors->first()?->id;
    }

    private function filteredSectors(Collection $sectors): Collection
    {
        $term = mb_strtolower(trim($this->sectorSearch));

        if ($term === '') {
            return $sectors;
        }

        return $sectors
            ->filter(function (Sector $sector) use ($term) {
                return collect([
                    $sector->name,
                    $sector->company?->name,
                ])
                    ->filter()
                    ->contains(fn (?string $value) => $value !== null && str_contains(mb_strtolower($value), $term));
            })
            ->values();
    }
}
