<?php

namespace App\Models;

use App\Enums\GlobalUserRole;
use App\Enums\SectorAccessLevel;
use App\Enums\UserRole;
use App\Modules\Assets\Models\Asset;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use App\Modules\Licenses\Models\License;
use App\Modules\Licenses\Models\LicenseAssignment;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Shared\Models\ActivityLog;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Models\TicketBoardSavedView;
use App\Modules\Tickets\Models\TicketBoardUserAccess;
use App\Modules\Tickets\Models\TicketBoardUserPreference;
use App\Modules\Tickets\Models\TicketMessage;
use App\Modules\Tickets\Models\TicketMessageTemplate;
use App\Modules\Tickets\Models\TicketRating;
use App\Modules\Tickets\Models\TicketTimeEntry;
use App\Modules\Users\Models\UserSectorAccess;
use App\Support\DatabaseBinary;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_photo_path',
        'theme_preference',
        'role',
        'global_role',
        'sector_id',
        'room_id',
        'must_change_password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'profile_photo_content',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'role' => UserRole::class,
            'global_role' => GlobalUserRole::class,
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
            'profile_photo_size' => 'integer',
            'theme_preference' => 'string',
        ];
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function requestedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    public function scopeWithSectorAccess(Builder $query, int $sectorId, array $levels = []): Builder
    {
        $normalizedLevels = collect($levels)
            ->map(fn (SectorAccessLevel|string $level) => $level instanceof SectorAccessLevel ? $level->value : $level)
            ->all();

        return $query->whereHas('sectorAccesses', function (Builder $sectorAccessQuery) use ($sectorId, $normalizedLevels) {
            $sectorAccessQuery->where('sector_id', $sectorId);

            if ($normalizedLevels !== []) {
                $sectorAccessQuery->whereIn('access_level', $normalizedLevels);
            }
        });
    }

    public function scopeWithAnySectorAccess(Builder $query, array $sectorIds, array $levels = []): Builder
    {
        $normalizedLevels = collect($levels)
            ->map(fn (SectorAccessLevel|string $level) => $level instanceof SectorAccessLevel ? $level->value : $level)
            ->all();

        return $query->whereHas('sectorAccesses', function (Builder $sectorAccessQuery) use ($sectorIds, $normalizedLevels) {
            $sectorAccessQuery->whereIn('sector_id', $sectorIds);

            if ($normalizedLevels !== []) {
                $sectorAccessQuery->whereIn('access_level', $normalizedLevels);
            }
        });
    }

    public function scopeGlobalAdmins(Builder $query): Builder
    {
        return $query->where(function (Builder $globalAdminQuery) {
            $globalAdminQuery
                ->whereIn('global_role', [GlobalUserRole::DEV->value, GlobalUserRole::SUPER_ADMIN->value])
                ->orWhereIn('role', [UserRole::DEV->value, UserRole::SUPER_ADMIN->value]);
        });
    }

    public function sectorAccesses(): HasMany
    {
        return $this->hasMany(UserSectorAccess::class)->with('sector');
    }

    public function ticketBoardAccesses(): HasMany
    {
        return $this->hasMany(TicketBoardUserAccess::class)->with('board');
    }

    public function ticketBoardPreferences(): HasMany
    {
        return $this->hasMany(TicketBoardUserPreference::class);
    }

    public function ticketBoardSavedViews(): HasMany
    {
        return $this->hasMany(TicketBoardSavedView::class);
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assignee_id');
    }

    public function currentAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'current_user_id');
    }

    public function ticketMessages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    public function ticketMessageTemplates(): HasMany
    {
        return $this->hasMany(TicketMessageTemplate::class);
    }

    public function ticketRatings(): HasMany
    {
        return $this->hasMany(TicketRating::class);
    }

    public function ticketTimeEntries(): HasMany
    {
        return $this->hasMany(TicketTimeEntry::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'causer_id');
    }

    public function knowledgeBaseArticles(): HasMany
    {
        return $this->hasMany(KnowledgeBaseArticle::class, 'created_by');
    }

    public function createdLicenses(): HasMany
    {
        return $this->hasMany(License::class, 'created_by');
    }

    public function licenseAssignments(): HasMany
    {
        return $this->hasMany(LicenseAssignment::class);
    }

    public function createdLicenseAssignments(): HasMany
    {
        return $this->hasMany(LicenseAssignment::class, 'created_by');
    }

    public function isSuperAdmin(): bool
    {
        return $this->isGlobalAdmin();
    }

    public function isGlobalAdmin(): bool
    {
        return in_array($this->global_role, [GlobalUserRole::DEV, GlobalUserRole::SUPER_ADMIN], true)
            || in_array($this->role, [UserRole::DEV, UserRole::SUPER_ADMIN], true);
    }

    public function isDeveloper(): bool
    {
        return $this->isGlobalAdmin();
    }

    public function canManageRooms(): bool
    {
        return $this->isGlobalAdmin();
    }

    public function isSectorAdmin(?int $sectorId = null): bool
    {
        if ($this->isGlobalAdmin()) {
            return true;
        }

        if ($sectorId !== null) {
            return $this->hasSectorAccess($sectorId, [SectorAccessLevel::SECTOR_ADMIN]);
        }

        return $this->hasAnySectorAccess([SectorAccessLevel::SECTOR_ADMIN]);
    }

    public function isTechnician(?int $sectorId = null): bool
    {
        if ($this->isGlobalAdmin()) {
            return true;
        }

        if ($sectorId !== null) {
            return $this->hasSectorAccess($sectorId, [SectorAccessLevel::TECHNICIAN]);
        }

        return $this->hasAnySectorAccess([SectorAccessLevel::TECHNICIAN]);
    }

    public function isRequester(?int $sectorId = null): bool
    {
        if ($sectorId !== null) {
            return $this->hasSectorAccess($sectorId, [SectorAccessLevel::REQUESTER]);
        }

        return ! $this->isGlobalAdmin() && ! $this->hasOperationalAccess();
    }

    public function sectorAccessLevel(int $sectorId): ?SectorAccessLevel
    {
        if ($this->isGlobalAdmin()) {
            return SectorAccessLevel::SECTOR_ADMIN;
        }

        return $this->sectorAccessCollection()
            ->firstWhere('sector_id', $sectorId)
            ?->access_level;
    }

    public function hasSectorAccess(int $sectorId, array $levels = []): bool
    {
        if ($this->isGlobalAdmin()) {
            return true;
        }

        $normalizedLevels = collect($levels)
            ->map(fn (SectorAccessLevel|string $level) => $level instanceof SectorAccessLevel ? $level->value : $level)
            ->all();

        $access = $this->sectorAccessCollection()->firstWhere('sector_id', $sectorId);

        if (! $access) {
            return false;
        }

        if ($normalizedLevels === []) {
            return true;
        }

        return in_array($access->access_level?->value, $normalizedLevels, true);
    }

    public function adminSectorIds(): array
    {
        return $this->sectorIds([SectorAccessLevel::SECTOR_ADMIN]);
    }

    public function sectorIdsForLevel(SectorAccessLevel|string $level): array
    {
        return $this->sectorIds([$level]);
    }

    public function operationalSectorIds(): array
    {
        return $this->sectorIds([SectorAccessLevel::SECTOR_ADMIN, SectorAccessLevel::TECHNICIAN]);
    }

    public function operationalBoardIds(): array
    {
        if ($this->isGlobalAdmin()) {
            return TicketBoard::query()
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn ($boardId) => (int) $boardId)
                ->all();
        }

        $sectorIds = $this->operationalSectorIds();

        if ($sectorIds === []) {
            return [];
        }

        return TicketBoard::query()
            ->where('is_active', true)
            ->whereIn('sector_id', $sectorIds)
            ->pluck('id')
            ->map(fn ($boardId) => (int) $boardId)
            ->unique()
            ->values()
            ->all();
    }

    public function allSectorIds(): array
    {
        return $this->sectorIds();
    }

    public function hasOperationalAccess(?int $sectorId = null): bool
    {
        if ($this->isGlobalAdmin()) {
            return true;
        }

        $levels = [SectorAccessLevel::SECTOR_ADMIN, SectorAccessLevel::TECHNICIAN];

        if ($sectorId !== null) {
            return $this->hasSectorAccess($sectorId, $levels);
        }

        return $this->hasAnySectorAccess($levels);
    }

    public function canOperateBoard(TicketBoard|int|null $board): bool
    {
        if (! $board instanceof TicketBoard) {
            $board = $board ? TicketBoard::query()->find($board) : null;
        }

        if (! $board || ! $board->is_active) {
            return false;
        }

        if ($this->isGlobalAdmin()) {
            return true;
        }

        return $this->hasOperationalAccess($board->sector_id);
    }

    public function accessSummary(): string
    {
        if ($this->isGlobalAdmin()) {
            return 'Acesso global';
        }

        $count = count($this->allSectorIds());

        return $count === 0
            ? 'Sem vinculos setoriais'
            : "{$count} setor(es) vinculado(s)";
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function hasProfilePhoto(): bool
    {
        return ! is_null($this->profile_photo_content) && (int) ($this->profile_photo_size ?? 0) > 0;
    }

    public function profilePhotoUrl(): ?string
    {
        if (! $this->hasProfilePhoto()) {
            return null;
        }

        return route('users.profile-photo.show', $this);
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profilePhotoUrl();
    }

    public function updateProfilePhoto(UploadedFile $photo): void
    {
        $content = file_get_contents($photo->getRealPath());

        if ($content === false) {
            throw new \RuntimeException('Nao foi possivel ler a foto enviada.');
        }

        $extension = $photo->extension() ?: $photo->getClientOriginalExtension() ?: 'bin';
        $photoPath = "profile-photos/{$this->getKey()}/".Str::uuid().".{$extension}";

        $this->forceFill([
            'profile_photo_path' => $photoPath,
            'profile_photo_original_name' => $photo->getClientOriginalName(),
            'profile_photo_mime_type' => $photo->getMimeType() ?: 'application/octet-stream',
            'profile_photo_size' => strlen($content),
            'profile_photo_content' => DatabaseBinary::encode($content, $this->getConnection()->getDriverName()),
        ])->save();
    }

    public function deleteProfilePhoto(): void
    {
        if (! $this->hasProfilePhoto()) {
            return;
        }

        $this->forceFill([
            'profile_photo_path' => null,
            'profile_photo_original_name' => null,
            'profile_photo_mime_type' => null,
            'profile_photo_size' => null,
            'profile_photo_content' => null,
        ])->save();
    }

    public function profilePhotoContent(): ?string
    {
        return $this->normalizeBinaryValue($this->profile_photo_content);
    }

    public function profilePhotoDownloadName(): string
    {
        return $this->profile_photo_original_name
            ?? basename((string) $this->profile_photo_path)
            ?: "avatar-{$this->getKey()}.bin";
    }

    public function preferredTheme(): string
    {
        return in_array($this->theme_preference, ['light', 'dark'], true)
            ? $this->theme_preference
            : 'light';
    }

    private function hasAnySectorAccess(array $levels = []): bool
    {
        return $this->sectorIds($levels) !== [];
    }

    private function sectorIds(array $levels = []): array
    {
        if ($this->isGlobalAdmin()) {
            return Sector::query()->pluck('id')->all();
        }

        $normalizedLevels = collect($levels)
            ->map(fn (SectorAccessLevel|string $level) => $level instanceof SectorAccessLevel ? $level->value : $level)
            ->all();

        return $this->sectorAccessCollection()
            ->when($normalizedLevels !== [], function (Collection $collection) use ($normalizedLevels) {
                return $collection->whereIn('access_level.value', $normalizedLevels);
            })
            ->pluck('sector_id')
            ->map(fn ($sectorId) => (int) $sectorId)
            ->unique()
            ->values()
            ->all();
    }

    private function sectorAccessCollection(): Collection
    {
        if ($this->relationLoaded('sectorAccesses')) {
            return $this->getRelation('sectorAccesses');
        }

        $this->load('sectorAccesses.sector');

        return $this->getRelation('sectorAccesses');
    }

    private function normalizeBinaryValue(mixed $value): ?string
    {
        return DatabaseBinary::decode($value);
    }
}
