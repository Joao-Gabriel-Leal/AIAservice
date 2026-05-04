<?php

namespace App\Modules\Sectors\Models;

use App\Models\User;
use App\Modules\Assets\Models\Asset;
use App\Modules\Companies\Models\Company;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use App\Modules\Licenses\Models\License;
use App\Modules\Rooms\Models\Room;
use App\Modules\SectorTemplates\Models\SectorTemplate;
use App\Modules\Sectors\Support\SectorColor;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Users\Models\UserSectorAccess;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'sector_template_id',
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

    protected function color(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value) => SectorColor::normalize(is_string($value) ? $value : null),
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SectorTemplate::class, 'sector_template_id');
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

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function displayColor(): string
    {
        return SectorColor::display(is_string($this->getAttribute('color')) ? $this->getAttribute('color') : null);
    }

    public function softColor(): string
    {
        return SectorColor::soft(is_string($this->getAttribute('color')) ? $this->getAttribute('color') : null);
    }

    public function borderColor(): string
    {
        return SectorColor::border(is_string($this->getAttribute('color')) ? $this->getAttribute('color') : null);
    }
}
