<?php

namespace App\Modules\Users\Http\Controllers;

use App\Enums\GlobalUserRole;
use App\Enums\SectorAccessLevel;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Shared\Support\AccessScope;
use App\Modules\Users\Exports\UsersExport;
use App\Modules\Users\Http\Requests\UserRequest;
use App\Modules\Users\Notifications\AccountCreatedNotification;
use App\Modules\Users\Notifications\DefaultPasswordResetNotification;
use App\Modules\Users\Support\UserIndexQuery;
use App\Support\Exports\SpreadsheetExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class UserController extends Controller
{
    public const DEFAULT_PASSWORD = 'Anadem@2026!';

    public function __construct(
        private readonly UserIndexQuery $userIndexQuery,
        private readonly SpreadsheetExporter $spreadsheetExporter,
    ) {}

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

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        $workStatusOptions = $this->workStatusOptions();
        $profileUser = $user->load([
            'sectorAccesses.sector.company',
            'currentAssets.currentSector',
            'currentAssets.currentRoom',
            'licenseAssignments.license',
        ]);

        return view('modules.users.show', [
            ...$this->formData($profileUser),
            'profileUser' => $profileUser,
            'workStatus' => $workStatusOptions[(string) $user->work_status] ?? $workStatusOptions['office'],
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $temporaryPassword = $this->issueTemporaryPassword();

        $user = DB::transaction(function () use ($request, $temporaryPassword) {
            $resolved = $this->resolvedData($request, generatedPassword: $temporaryPassword);

            $user = User::query()->create($resolved['payload']);

            $this->syncSectorAccesses($user, $resolved['sector_accesses']);

            return $user;
        });

        $accessUrl = url('/');

        try {
            $user->notify(new AccountCreatedNotification(
                $user->email,
                (bool) $user->must_change_password,
                $accessUrl,
            ));
        } catch (Throwable $throwable) {
            Log::warning('Account created notification delivery failed.', [
                'user_id' => $user->id,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }

        return redirect()
            ->route('users.index')
            ->with('status', 'Usuario criado com sucesso.')
            ->with('created_user_access', [
                'name' => $user->name,
                'email' => $user->email,
                'password' => $temporaryPassword,
                'login_url' => $accessUrl,
            ]);
    }

    public function edit(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        return redirect(route('users.show', $user).'#editar-usuario');
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $wasActive = (bool) $user->is_active;

        DB::transaction(function () use ($request, $user) {
            $resolved = $this->resolvedData($request, $user);

            $user->update($resolved['payload']);

            $this->syncSectorAccesses($user, $resolved['sector_accesses']);
        });

        if ($wasActive && ! $user->fresh()->is_active) {
            $this->invalidateUserAccess($user);
        }

        return redirect()->route('users.show', $user)->with('status', 'Usuario atualizado com sucesso.');
    }

    public function resetDefaultPassword(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $user->forceFill([
            'password' => Hash::make(self::DEFAULT_PASSWORD),
            'must_change_password' => true,
            'remember_token' => Str::random(60),
        ])->save();

        $this->invalidateUserAccess($user);

        try {
            $user->notify(new DefaultPasswordResetNotification(
                $user->email,
                self::DEFAULT_PASSWORD,
                url('/'),
            ));
        } catch (Throwable $throwable) {
            Log::warning('Default password reset notification delivery failed.', [
                'user_id' => $user->id,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }

        return redirect()
            ->route('users.show', $user)
            ->with('status', 'Senha redefinida para o padrao e e-mail enviado ao usuario.');
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

    private function resolvedData(UserRequest $request, ?User $user = null, ?string $generatedPassword = null): array
    {
        $globalRole = auth()->user()->isGlobalAdmin()
            ? GlobalUserRole::from((string) $request->input('global_role'))
            : GlobalUserRole::COLLABORATOR;

        $sectorAccesses = in_array($globalRole, [GlobalUserRole::SUPER_ADMIN, GlobalUserRole::DEV], true)
            ? collect()
            : $this->normalizedSectorAccesses($request);

        $payload = $request->safe()->except([
            'password',
            'password_confirmation',
            'sector_accesses',
        ]);

        $payload['global_role'] = $globalRole;
        $payload['must_change_password'] = $user
            ? $request->boolean('must_change_password', false)
            : true;
        $payload['is_active'] = $request->boolean('is_active', true);

        if (! $user) {
            $payload['password'] = Hash::make($generatedPassword ?? Str::random(40));
        } elseif (filled($request->input('password'))) {
            $payload['password'] = Hash::make((string) $request->input('password'));
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
        $manageableSectorIds = auth()->user()->isGlobalAdmin()
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
            ->reject(fn (GlobalUserRole $role) => $role === GlobalUserRole::SUPER_ADMIN)
            ->mapWithKeys(fn (GlobalUserRole $role) => [$role->value => $role->label()])
            ->all();
    }

    private function availableSectors()
    {
        $query = Sector::query()->with('company')->orderBy('name');

        if (! auth()->user()->isGlobalAdmin()) {
            $query->whereIn('id', auth()->user()->adminSectorIds());
        }

        $query->whereIn('id', AccessScope::currentCompanySectorIds(auth()->user()));

        return $query->get();
    }

    private function workStatusOptions(): array
    {
        return [
            'office' => ['label' => 'No escritorio', 'classes' => 'border-sky-300 bg-sky-50 text-sky-700'],
            'home' => ['label' => 'Trabalhando de casa', 'classes' => 'border-indigo-300 bg-indigo-50 text-indigo-700'],
            'external' => ['label' => 'Trabalhando externamente', 'classes' => 'border-cyan-300 bg-cyan-50 text-cyan-700'],
            'away' => ['label' => 'Ausente', 'classes' => 'border-slate-300 bg-slate-100 text-slate-700'],
            'do_not_disturb' => ['label' => 'Nao perturbe', 'classes' => 'border-rose-300 bg-rose-50 text-rose-700'],
            'vacation' => ['label' => 'Ferias', 'classes' => 'border-amber-300 bg-amber-50 text-amber-700'],
            'medical_leave' => ['label' => 'Licenca medica', 'classes' => 'border-violet-300 bg-violet-50 text-violet-700'],
        ];
    }

    private function legacySnapshot(GlobalUserRole $globalRole, Collection $sectorAccesses): array
    {
        if (in_array($globalRole, [GlobalUserRole::SUPER_ADMIN, GlobalUserRole::DEV], true)) {
            return [
                'role' => UserRole::DEV,
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

    private function issueTemporaryPassword(): string
    {
        return (string) random_int(100000, 999999);
    }

    private function invalidateUserAccess(User $user): void
    {
        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->delete();

        $user->forceFill([
            'remember_token' => Str::random(60),
        ])->saveQuietly();
    }
}
