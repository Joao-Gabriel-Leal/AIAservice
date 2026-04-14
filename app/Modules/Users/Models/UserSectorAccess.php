<?php

namespace App\Modules\Users\Models;

use App\Enums\SectorAccessLevel;
use App\Models\User;
use App\Modules\Sectors\Models\Sector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSectorAccess extends Model
{
    protected $fillable = [
        'user_id',
        'sector_id',
        'access_level',
    ];

    protected function casts(): array
    {
        return [
            'access_level' => SectorAccessLevel::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }
}
