<?php

namespace App\Modules\Sectors\Models;

use App\Models\User;
use App\Modules\Assets\Models\Asset;
use App\Modules\Companies\Models\Company;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use App\Modules\Rooms\Models\Room;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Users\Models\UserSectorAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sector extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'color',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function userAccesses(): HasMany
    {
        return $this->hasMany(UserSectorAccess::class);
    }

    public function board(): HasOne
    {
        return $this->hasOne(TicketBoard::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function knowledgeBaseArticles(): HasMany
    {
        return $this->hasMany(KnowledgeBaseArticle::class);
    }

    public function currentAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'current_sector_id');
    }

    public function displayColor(): string
    {
        $color = (string) ($this->getAttribute('color') ?? '');

        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color) !== 1) {
            return '#3D567B';
        }

        return strtoupper($color);
    }

    public function softColor(): string
    {
        return $this->displayColor().'14';
    }

    public function borderColor(): string
    {
        return $this->displayColor().'2E';
    }
}
