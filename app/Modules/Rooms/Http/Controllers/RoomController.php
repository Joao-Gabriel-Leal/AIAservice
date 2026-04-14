<?php

namespace App\Modules\Rooms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Rooms\Http\Requests\RoomRequest;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Shared\Support\AccessScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class RoomController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Room::class);

        $rooms = AccessScope::applySectorScope(Room::query()->with('sector'), auth()->user())
            ->latest()
            ->paginate(12);

        return view('modules.rooms.index', compact('rooms'));
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

        if (auth()->user()->isSectorAdmin()) {
            $payload['sector_id'] = auth()->user()->sector_id;
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

        if (auth()->user()->isSectorAdmin()) {
            $payload['sector_id'] = auth()->user()->sector_id;
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

        if (auth()->user()->isSectorAdmin()) {
            $query->where('id', auth()->user()->sector_id);
        }

        return $query->get();
    }
}
