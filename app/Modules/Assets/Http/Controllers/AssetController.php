<?php

namespace App\Modules\Assets\Http\Controllers;

use App\Enums\AssetAllocationStatus;
use App\Enums\AssetStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Assets\Exports\AssetsExport;
use App\Modules\Assets\Http\Requests\AssetMovementRequest;
use App\Modules\Assets\Http\Requests\AssetRequest;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetImportBatch;
use App\Modules\Assets\Services\AssetMovementService;
use App\Modules\Assets\Support\AssetIndexQuery;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Shared\Support\AccessScope;
use App\Support\Exports\SpreadsheetExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssetController extends Controller
{
    public function __construct(
        private readonly AssetMovementService $assetMovementService,
        private readonly AssetIndexQuery $assetIndexQuery,
        private readonly SpreadsheetExporter $spreadsheetExporter,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Asset::class);

        $filters = $this->assetIndexQuery->filters($request);

        $assets = $this->assetIndexQuery
            ->build($filters, $request->user())
            ->paginate(12)
            ->withQueryString();

        ['latestImportBatch' => $latestImportBatch, 'latestImportPendingRows' => $latestImportPendingRows] = $this->legacyImportSummary();

        return view('modules.assets.index', [
            'assets' => $assets,
            'filters' => $filters,
            'statuses' => AssetStatus::cases(),
            'allocationStatuses' => AssetAllocationStatus::cases(),
            'sectors' => $this->availableSectors($request->user()),
            'rooms' => $this->availableRooms($request->user()),
            'collaborators' => User::query()
                ->with('sectorAccesses')
                ->where('global_role', 'collaborator')
                ->withAnySectorAccess(AccessScope::currentCompanySectorIds($request->user()))
                ->orderBy('name')
                ->get(),
            'latestImportBatch' => $latestImportBatch,
            'latestImportPendingRows' => $latestImportPendingRows,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Asset::class);

        $filters = $this->assetIndexQuery->filters($request);
        $export = new AssetsExport($this->assetIndexQuery->build($filters, $request->user())->get());

        return $this->spreadsheetExporter->download($export->fileName(), $export->sheets());
    }

    public function create(): View
    {
        $this->authorize('create', Asset::class);

        return view('modules.assets.create', [
            'asset' => new Asset,
            ...$this->formData(),
        ]);
    }

    public function store(AssetRequest $request): RedirectResponse
    {
        $this->authorize('create', Asset::class);
        $this->authorizeCurrentSector((int) $request->validated('current_sector_id'));

        $asset = $this->assetMovementService->register($request->validated(), $request->user());

        return redirect()->route('assets.show', $asset)->with('status', 'Patrimonio cadastrado com sucesso.');
    }

    public function show(Asset $asset): View
    {
        $this->authorize('view', $asset);

        $relations = [
            'currentSector',
            'currentRoom',
            'currentUser',
            'creator',
            'movements.fromSector',
            'movements.fromRoom',
            'movements.fromUser',
            'movements.toSector',
            'movements.toRoom',
            'movements.toUser',
            'movements.movedBy',
        ];

        if ($this->hasTable('asset_financial_profiles')) {
            $relations[] = 'financialProfile';
        }

        $asset->load($relations);

        if (! $this->hasTable('asset_financial_profiles')) {
            $asset->setRelation('financialProfile', null);
        }

        return view('modules.assets.show', [
            'asset' => $asset,
        ]);
    }

    public function publicShow(Asset $asset): View
    {
        return view('modules.assets.public-show', [
            'asset' => $asset->load([
                'currentSector.company',
                'currentRoom',
                'currentUser',
            ]),
        ]);
    }

    public function edit(Asset $asset): View
    {
        $this->authorize('update', $asset);

        return view('modules.assets.edit', [
            'asset' => $asset,
            ...$this->formData(),
        ]);
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);
        $this->authorizeCurrentSector((int) $request->validated('current_sector_id'));

        $asset->update($request->validated());

        return redirect()->route('assets.show', $asset)->with('status', 'Dados cadastrais atualizados com sucesso.');
    }

    public function createMovement(Asset $asset): View
    {
        $this->authorize('move', $asset);

        return view('modules.assets.movement', [
            'asset' => $asset->load(['currentSector', 'currentRoom', 'currentUser']),
            ...$this->formData(),
        ]);
    }

    public function storeMovement(AssetMovementRequest $request, Asset $asset): RedirectResponse
    {
        $this->authorize('move', $asset);
        $this->authorizeCurrentSector((int) $request->validated('current_sector_id'));

        $this->assetMovementService->move($asset, $request->validated(), $request->user());

        return redirect()->route('assets.show', $asset)->with('status', 'Movimentacao registrada com sucesso.');
    }

    private function formData(): array
    {
        return [
            'statuses' => AssetStatus::cases(),
            'sectors' => $this->availableSectors(auth()->user()),
            'rooms' => $this->availableRooms(auth()->user()),
            'collaborators' => User::query()
                ->with('sectorAccesses.sector')
                ->where('global_role', 'collaborator')
                ->withAnySectorAccess(AccessScope::currentCompanySectorIds(auth()->user()))
                ->orderBy('name')
                ->get(),
        ];
    }

    private function availableSectors(User $user)
    {
        return Sector::query()
            ->with('company')
            ->whereIn('id', AccessScope::currentCompanySectorIds($user))
            ->orderBy('name')
            ->get();
    }

    private function authorizeCurrentSector(int $sectorId): void
    {
        abort_unless(in_array($sectorId, AccessScope::currentCompanySectorIds(auth()->user()), true), 403);
    }

    private function availableRooms(User $user)
    {
        $query = Room::query()->with('sector')->orderBy('name');

        AccessScope::applyCurrentCompanyScope($query, $user);

        return $query->get();
    }

    private function legacyImportSummary(): array
    {
        if (! $this->hasTable('asset_import_batches') || ! $this->hasTable('asset_import_rows')) {
            return [
                'latestImportBatch' => null,
                'latestImportPendingRows' => collect(),
            ];
        }

        $latestImportBatch = AssetImportBatch::query()->latest('id')->first();

        return [
            'latestImportBatch' => $latestImportBatch,
            'latestImportPendingRows' => $latestImportBatch?->rows()
                ->where('processing_status', 'pending_review')
                ->orderBy('source_row')
                ->limit(8)
                ->get() ?? collect(),
        ];
    }

    private function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }
}
