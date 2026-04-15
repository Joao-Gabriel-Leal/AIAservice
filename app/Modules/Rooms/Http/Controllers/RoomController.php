<?php

namespace App\Modules\Rooms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Rooms\Exports\RoomsExport;
use App\Modules\Rooms\Http\Requests\RoomRequest;
use App\Modules\Rooms\Models\Room;
use App\Modules\Rooms\Support\RoomIndexQuery;
use App\Modules\Sectors\Models\Sector;
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
    ) {
    }

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

    public function create(): View
    {
        $this->authorize('create', Room::class);

        return view('modules.rooms.create', [
            'room' => new Room(),
            'sectors' => $this->availableSectors(),
        ]);
    }

    public function store(RoomRequest $request): RedirectResponse
    {
        $this->authorize('create', Room::class);

        $payload = $request->validated();

        if (! auth()->user()->isSuperAdmin()) {
            abort_unless(in_array((int) $payload['sector_id'], auth()->user()->adminSectorIds(), true), 403);
        }

        Room::query()->create([
            ...$payload,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('rooms.index')->with('status', 'Sala criada com sucesso.');
    }

    public function edit(Room $room): View
    {
        $this->authorize('update', $room);

        return view('modules.rooms.edit', [
            'room' => $room,
            'sectors' => $this->availableSectors(),
        ]);
    }

    public function update(RoomRequest $request, Room $room): RedirectResponse
    {
        $this->authorize('update', $room);

        $payload = $request->validated();

        if (! auth()->user()->isSuperAdmin()) {
            abort_unless(in_array((int) $payload['sector_id'], auth()->user()->adminSectorIds(), true), 403);
        }

        $room->update([
            ...$payload,
            'is_active' => $request->boolean('is_active', false),
        ]);

        return redirect()->route('rooms.index')->with('status', 'Sala atualizada com sucesso.');
    }

    public function destroy(Room $room): RedirectResponse
    {
        $this->authorize('delete', $room);

        $room->delete();

        return redirect()->route('rooms.index')->with('status', 'Sala removida com sucesso.');
    }

    private function availableSectors()
    {
        $query = Sector::query()->with('company')->orderBy('name');

        if (! auth()->user()->isSuperAdmin()) {
            $query->whereIn('id', auth()->user()->adminSectorIds());
        }

        return $query->get();
    }
}
