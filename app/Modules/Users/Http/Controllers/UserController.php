<?php

namespace App\Modules\Users\Http\Controllers;

use App\Enums\GlobalUserRole;
use App\Enums\SectorAccessLevel;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Users\Exports\UsersExport;
use App\Modules\Users\Http\Requests\UserRequest;
use App\Modules\Users\Notifications\AccountCreatedNotification;
use App\Modules\Users\Support\UserIndexQuery;
use App\Support\Exports\SpreadsheetExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserController extends Controller
{
    public function __construct(
        private readonly UserIndexQuery $userIndexQuery,
        private readonly SpreadsheetExporter $spreadsheetExporter,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $filters = $this->userIndexQuery->filters($request);
        $query = $this->userIndexQuery->build(auth()->user(), $filters);

        return view('modules.users.index', [
            'users' => $query->paginate(15)->withQueryString(),
            'filters' => $filters,
            'globalRoles' => $this->availableGlobalRoles(),
            'sectors' => $this->availableSectors(),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', User::class);

        $filters = $this->userIndexQuery->filters($request);
        $export = new UsersExport($this->userIndexQuery->build(auth()->user(), $filters)->get());

        return $this->spreadsheetExporter->download($export->fileName(), $export->sheets());
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('modules.users.create', $this->formData(new User));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $user = DB::transaction(function () use ($request) {
            $resolved = $this->resolvedData($request);

            $user = User::query()->create($resolved['payload']);

            $this->syncSectorAccesses($user, $resolved['sector_accesses']);

            return $user;
        });

        $user->notify(new AccountCreatedNotification($user->email, (bool) $user->must_change_password));

        return redirect()->route('users.index')->with('status', 'Usuario criado com sucesso.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('modules.users.edit', $this->formData($user->load('sectorAccesses.sector')));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        DB::transaction(function () use ($request, $user) {
            $resolved = $this->resolvedData($request, $user);

            $user->update($resolved['payload']);

            $this->syncSectorAccesses($user, $resolved['sector_accesses']);
        });

        return redirect()->route('users.index')->with('status', 'Usuario atualizado com sucesso.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('users.index')->with('status', 'Usuario removido com sucesso.');
    }

    private function formData(User $user): array
    {
        return [
            'userModel' => $user->loadMissing('sectorAccesses.sector'),
            'globalRoles' => $this->availableGlobalRoles(),
            'accessLevels' => collect(SectorAccessLevel::cases())
                ->reject(fn (SectorAccessLevel $level) => $level === SectorAccessLevel::REQUESTER)
                ->mapWithKeys(fn (SectorAccessLevel $level) => [$level->value => $level->label()])
                ->all(),
            'sectors' => $this->availableSectors(),
        ];
    }

    private function resolvedData(UserRequest $request, ?User $user = null): array
    {
        $globalRole = auth()->user()->isSuperAdmin()
            ? GlobalUserRole::from((string) $request->input('global_role'))
            : GlobalUserRole::COLLABORATOR;

        $sectorAccesses = $globalRole === GlobalUserRole::SUPER_ADMIN
            ? collect()
            : $this->normalizedSectorAccesses($request);

        $payload = $request->safe()->except([
            'password',
            'password_confirmation',
            'sector_accesses',
        ]);

        $payload['global_role'] = $globalRole;
        $payload['must_change_password'] = $request->boolean('must_change_password', ! $user);
        $payload['is_active'] = $request->boolean('is_active', true);

        if (filled($request->input('password'))) {
            $payload['password'] = Hash::make((string) $request->input('password'));
        } elseif (! $user) {
            $payload['password'] = Hash::make('password');
        }

        return [
            'payload' => array_merge($payload, $this->legacySnapshot($globalRole, $sectorAccesses)),
            'sector_accesses' => $sectorAccesses,
        ];
    }

    private function normalizedSectorAccesses(UserRequest $request): Collection
    {
        return collect($request->input('sector_accesses', []))
            ->filter(fn ($value) => filled($value))
            ->map(fn ($accessLevel, $sectorId) => [
                'sector_id' => (int) $sectorId,
                'access_level' => (string) $accessLevel,
            ])
            ->values();
    }

    private function syncSectorAccesses(User $user, Collection $sectorAccesses): void
    {
        $manageableSectorIds = auth()->user()->isSuperAdmin()
            ? null
            : auth()->user()->adminSectorIds();

        if ($manageableSectorIds === null) {
            $user->sectorAccesses()->delete();
            $selectedAccesses = $sectorAccesses;
        } else {
            $user->sectorAccesses()->whereIn('sector_id', $manageableSectorIds)->delete();
            $selectedAccesses = $sectorAccesses->filter(
                fn (array $access) => in_array($access['sector_id'], $manageableSectorIds, true),
            )->values();
        }

        if ($selectedAccesses->isNotEmpty()) {
            $user->sectorAccesses()->createMany($selectedAccesses->all());
        }
    }

    private function availableGlobalRoles(): array
    {
        return collect(GlobalUserRole::cases())
            ->reject(fn (GlobalUserRole $role) => ! auth()->user()->isSuperAdmin() && $role === GlobalUserRole::SUPER_ADMIN)
            ->mapWithKeys(fn (GlobalUserRole $role) => [$role->value => $role->label()])
            ->all();
    }

    private function availableSectors()
    {
        $query = Sector::query()->with('company')->orderBy('name');

        if (! auth()->user()->isSuperAdmin()) {
            $query->whereIn('id', auth()->user()->adminSectorIds());
        }

        return $query->get();
    }

    private function legacySnapshot(GlobalUserRole $globalRole, Collection $sectorAccesses): array
    {
        if ($globalRole === GlobalUserRole::SUPER_ADMIN) {
            return [
                'role' => UserRole::SUPER_ADMIN,
                'sector_id' => null,
                'room_id' => null,
            ];
        }

        $primaryAccess = $sectorAccesses->first();
        $accessLevels = $sectorAccesses->pluck('access_level');

        $legacyRole = match (true) {
            $accessLevels->contains(SectorAccessLevel::SECTOR_ADMIN->value) => UserRole::SECTOR_ADMIN,
            $accessLevels->contains(SectorAccessLevel::TECHNICIAN->value) => UserRole::TECHNICIAN,
            default => UserRole::REQUESTER,
        };

        return [
            'role' => $legacyRole,
            'sector_id' => $primaryAccess['sector_id'] ?? null,
            'room_id' => null,
        ];
    }
}
