<?php

namespace App\Modules\Search\Livewire;

use App\Models\User;
use App\Modules\Search\Services\GlobalSearchService;
use App\Modules\Sectors\Models\Sector;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class SearchPage extends Component
{
    #[Url(as: 'q')]
    public string $query = '';

    #[Url(as: 'types')]
    public array $types = [];

    #[Url(as: 'sector')]
    public ?int $sectorId = null;

    #[Url(as: 'date_from')]
    public string $dateFrom = '';

    #[Url(as: 'date_to')]
    public string $dateTo = '';

    /**
     * @var array<string,int>
     */
    public array $groupLimits = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->isGlobalAdmin(), 403);

        $this->types = $this->normalizedTypes($this->types);
        $this->groupLimits = collect(GlobalSearchService::groupKeys())
            ->mapWithKeys(fn (string $groupKey) => [$groupKey => 5])
            ->all();
    }

    public function updatedTypes(): void
    {
        $this->types = $this->normalizedTypes($this->types);
    }

    public function toggleType(string $type): void
    {
        $types = collect($this->normalizedTypes($this->types));

        if ($types->contains($type)) {
            $types = $types->reject(fn (string $selectedType) => $selectedType === $type)->values();
        } else {
            $types = $types->push($type)->unique()->values();
        }

        $this->types = $types->all();
    }

    public function clearFilters(): void
    {
        $this->reset('types', 'sectorId', 'dateFrom', 'dateTo');
        $this->types = [];
    }

    public function loadMore(string $groupKey): void
    {
        if (! in_array($groupKey, GlobalSearchService::groupKeys(), true)) {
            return;
        }

        $this->groupLimits[$groupKey] = min(($this->groupLimits[$groupKey] ?? 5) + 5, 25);
    }

    public function render(GlobalSearchService $searchService): View
    {
        /** @var User $user */
        $user = auth()->user();

        $results = $searchService->search($user, [
            'query' => $this->query,
            'types' => $this->normalizedTypes($this->types),
            'sector_id' => $this->sectorId,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ], $this->groupLimits);

        return view('livewire.search.page', [
            'results' => $results,
            'typeOptions' => $searchService->typeOptions(),
            'sectorOptions' => $this->sectorOptions($user),
            'hasActiveFilters' => $this->types !== [] || $this->sectorId !== null || $this->dateFrom !== '' || $this->dateTo !== '',
        ])->layout('layouts.portal', [
            'title' => 'Busca global',
            'subtitle' => 'Procure chamados, conhecimento, cadastros, SLAs, licencas e operacao em uma unica tela.',
            'headerVariant' => 'none',
        ]);
    }

    /**
     * @param  array<int|string,mixed>  $types
     * @return array<int,string>
     */
    private function normalizedTypes(array $types): array
    {
        return collect($types)
            ->map(fn ($type) => is_string($type) ? trim($type) : '')
            ->filter(fn (string $type) => in_array($type, GlobalSearchService::groupKeys(), true))
            ->unique()
            ->values()
            ->all();
    }

    private function sectorOptions(User $user): Collection
    {
        $sectorIds = $user->isGlobalAdmin() ? null : $user->allSectorIds();

        return Sector::query()
            ->when(is_array($sectorIds), fn ($query) => $query->whereIn('id', $sectorIds === [] ? [0] : $sectorIds))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
