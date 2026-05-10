<?php

namespace App\Modules\Rooms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Exports\RoomsExport;
use App\Modules\Rooms\Http\Requests\RoomRequest;
use App\Modules\Rooms\Models\Room;
use App\Modules\Rooms\Support\RoomIndexQuery;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Shared\Support\AccessScope;
use App\Support\Exports\SpreadsheetExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RoomController extends Controller
{
    public function __construct(
        private readonly RoomIndexQuery $roomIndexQuery,
        private readonly SpreadsheetExporter $spreadsheetExporter,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Room::class);

        $filters = $this->roomIndexQuery->filters($request);
        $rooms = $this->roomIndexQuery->build(auth()->user(), $filters)->paginate(12)->withQueryString();

        return view('modules.rooms.index', [
            'rooms' => $rooms,
            'filters' => $filters,
            'sectors' => $this->availableSectors(),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Room::class);

        $filters = $this->roomIndexQuery->filters($request);
        $export = new RoomsExport($this->roomIndexQuery->build(auth()->user(), $filters)->get());

        return $this->spreadsheetExporter->download($export->fileName(), $export->sheets());
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Room::class);

        $sectors = $this->availableSectors();
        $sectorId = $request->integer('sector_id') ?: null;
        $sectorId = $sectorId && $sectors->contains('id', $sectorId) ? $sectorId : null;
        $selectedSector = $sectorId ? $sectors->firstWhere('id', $sectorId) : null;

        return view('modules.rooms.create', [
            'room' => new Room(['sector_id' => $sectorId]),
            'sectors' => $sectors,
            'returnToCompanyId' => $this->returnToCompanyId($request, $selectedSector?->company_id),
        ]);
    }

    public function store(RoomRequest $request): RedirectResponse
    {
        $this->authorize('create', Room::class);

        $payload = $request->validated();
        $returnToCompanyId = $payload['return_to_company_id'] ?? null;
        unset($payload['return_to_company_id']);

        abort_unless(in_array((int) $payload['sector_id'], AccessScope::currentCompanySectorIds(auth()->user()), true), 403);

        if (! auth()->user()->canManageRooms()) {
            abort_unless(in_array((int) $payload['sector_id'], auth()->user()->adminSectorIds(), true), 403);
        }

        Room::query()->create([
            ...$payload,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->redirectAfterMutation($returnToCompanyId, 'Sala criada com sucesso.');
    }

    public function edit(Request $request, Room $room): View
    {
        $this->authorize('update', $room);

        return view('modules.rooms.edit', [
            'room' => $room,
            'sectors' => $this->availableSectors(),
            'returnToCompanyId' => $this->returnToCompanyId($request),
        ]);
    }

    public function update(RoomRequest $request, Room $room): RedirectResponse
    {
        $this->authorize('update', $room);

        $payload = $request->validated();
        $returnToCompanyId = $payload['return_to_company_id'] ?? null;
        unset($payload['return_to_company_id']);

        abort_unless(in_array((int) $payload['sector_id'], AccessScope::currentCompanySectorIds(auth()->user()), true), 403);

        if (! auth()->user()->canManageRooms()) {
            abort_unless(in_array((int) $payload['sector_id'], auth()->user()->adminSectorIds(), true), 403);
        }

        $room->update([
            ...$payload,
            'is_active' => $request->boolean('is_active', false),
        ]);

        return $this->redirectAfterMutation($returnToCompanyId, 'Sala atualizada com sucesso.');
    }

    public function destroy(Request $request, Room $room): RedirectResponse
    {
        $this->authorize('delete', $room);

        $room->delete();

        return $this->redirectAfterMutation($this->returnToCompanyId($request), 'Sala removida com sucesso.');
    }

    private function availableSectors()
    {
        $query = Sector::query()->with('company')->orderBy('name');

        if (! auth()->user()->canManageRooms()) {
            $query->whereIn('id', auth()->user()->adminSectorIds());
        }

        $query->where('company_id', app(\App\Modules\Shared\Support\CurrentCompanyContext::class)->currentCompanyId(auth()->user()) ?: 0);

        return $query->get();
    }

    private function returnToCompanyId(Request $request, ?int $fallbackCompanyId = null): ?int
    {
        $companyId = $request->integer('return_to_company_id') ?: $fallbackCompanyId;

        return $companyId && Company::query()->whereKey($companyId)->exists() ? $companyId : null;
    }

    private function redirectAfterMutation(?int $companyId, string $status): RedirectResponse
    {
        if ($companyId) {
            return redirect()->route('companies.show', $companyId)->with('status', $status);
        }

        return redirect()->route('rooms.index')->with('status', $status);
    }
}
