<?php

namespace App\Modules\Assets\Models;

use App\Enums\AssetStatus;
use App\Models\User;
use App\Modules\Assets\Services\AssetQrCodeService;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Shared\Models\ActivityLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'asset_code',
        'name',
        'description',
        'serial_number',
        'brand',
        'model',
        'status',
        'current_sector_id',
        'current_room_id',
        'current_user_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function currentSector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'current_sector_id');
    }

    public function currentRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'current_room_id');
    }

    public function currentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class)->orderByDesc('moved_at')->orderByDesc('id');
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject')->latest();
    }

    public function detailUrl(): string
    {
        return route('assets.show', $this);
    }

    public function qrCodeSvg(int $size = 180): string
    {
        return app(AssetQrCodeService::class)->svg($this->detailUrl(), $size);
    }

    public function statusLabel(): string
    {
        return $this->status?->label() ?? 'Sem status';
    }

    public function operationalStateLabel(): string
    {
        return match ($this->status) {
            AssetStatus::EM_USO => $this->currentUser ? 'Em uso com colaborador' : 'Em uso sem colaborador',
            AssetStatus::MANUTENCAO => 'Em manutencao',
            AssetStatus::BAIXADO => 'Baixado do parque',
            AssetStatus::EXTRAVIADO => 'Item extraviado',
            default => $this->currentUser ? 'Disponivel com responsavel' : 'Disponivel no setor',
        };
    }
}
