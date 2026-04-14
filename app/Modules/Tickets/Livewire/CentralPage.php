<?php

namespace App\Modules\Tickets\Livewire;

use App\Modules\Sectors\Models\Sector;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Livewire\Component;

class CentralPage extends Component
{
    public ?int $selectedSectorId = null;

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
    }

    public function render(): View
    {
        $sectors = $this->sectors();
        $selectedSector = $sectors->firstWhere('id', $this->selectedSectorId) ?? $sectors->first();
        $catalogItems = $selectedSector?->board?->catalogItems ?? collect();

        return view('livewire.tickets.central-page', [
            'sectors' => $sectors,
            'selectedSector' => $selectedSector,
            'catalogItems' => $catalogItems,
        ])->layout('layouts.portal', [
            'title' => 'Central de formularios',
            'subtitle' => 'Escolha o setor, veja os formularios disponiveis e abra o chamado certo com menos atrito.',
        ]);
    }

    private function sectors(): Collection
    {
        return Sector::query()
            ->with([
                'company',
                'board.catalogItems' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with('form')
                    ->orderBy('name'),
            ])
            ->where('is_active', true)
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
}
