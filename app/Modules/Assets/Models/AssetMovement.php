<?php

namespace App\Modules\Assets\Models;

use App\Enums\AssetMovementType;
use App\Enums\AssetStatus;
use App\Models\User;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'type',
        'from_sector_id',
        'from_room_id',
        'from_user_id',
        'from_status',
        'to_sector_id',
        'to_room_id',
        'to_user_id',
        'to_status',
        'moved_by',
        'reason',
        'notes',
        'moved_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AssetMovementType::class,
            'from_status' => AssetStatus::class,
            'to_status' => AssetStatus::class,
            'moved_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function fromSector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'from_sector_id');
    }

    public function fromRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'from_room_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toSector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'to_sector_id');
    }

    public function toRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'to_room_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function movedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }
}
