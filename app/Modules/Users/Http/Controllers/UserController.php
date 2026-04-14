<?php

namespace App\Modules\Users\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Users\Http\Requests\UserRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()->with(['sector', 'room'])->latest();

        if (auth()->user()->isSectorAdmin()) {
            $query->where('sector_id', auth()->user()->sector_id);
        }

        $users = $query->paginate(15);

        return view('modules.users.index', compact('users'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('modules.users.create', [
            'userModel' => new User(),
            'roles' => $this->availableRoles(),
            'sectors' => $this->availableSectors(),
            'rooms' => $this->availableRooms(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $payload = $this->resolvedPayload($request);

        User::query()->create($payload);

        return redirect()->route('users.index')->with('status', 'Usuário criado com sucesso.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('modules.users.edit', [
            'userModel' => $user,
            'roles' => $this->availableRoles(),
            'sectors' => $this->availableSectors(),
            'rooms' => $this->availableRooms(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $user->update($this->resolvedPayload($request, $user));

        return redirect()->route('users.index')->with('status', 'Usuário atualizado com sucesso.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('users.index')->with('status', 'Usuário removido com sucesso.');
    }

    private function resolvedPayload(UserRequest $request, ?User $user = null): array
    {
        $payload = $request->safe()->except(['password', 'password_confirmation']);

        if (auth()->user()->isSectorAdmin()) {
            $payload['sector_id'] = auth()->user()->sector_id;
        }

        if (filled($request->input('password'))) {
            $payload['password'] = Hash::make((string) $request->input('password'));
        } elseif (! $user) {
            $payload['password'] = Hash::make('password');
        }

        $payload['must_change_password'] = $request->boolean('must_change_password', ! $user);
        $payload['is_active'] = $request->boolean('is_active', true);

        if (($payload['role'] ?? null) === UserRole::SUPER_ADMIN->value) {
            $payload['sector_id'] = null;
            $payload['room_id'] = null;
        }

        return $payload;
    }

    private function availableRoles(): array
    {
        return collect(UserRole::cases())
            ->reject(fn (UserRole $role) => auth()->user()->isSectorAdmin() && $role === UserRole::SUPER_ADMIN)
            ->mapWithKeys(fn (UserRole $role) => [$role->value => $role->label()])
            ->all();
    }

    private function availableSectors()
    {
        $query = Sector::query()->with('company')->orderBy('name');

        if (auth()->user()->isSectorAdmin()) {
            $query->where('id', auth()->user()->sector_id);
        }

        return $query->get();
    }

    private function availableRooms()
    {
        $query = Room::query()->with('sector')->orderBy('name');

        if (auth()->user()->isSectorAdmin()) {
            $query->where('sector_id', auth()->user()->sector_id);
        }

        return $query->get();
    }
}
