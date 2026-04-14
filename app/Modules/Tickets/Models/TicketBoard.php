<?php

namespace App\Modules\Tickets\Models;

use App\Modules\Sectors\Models\Sector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketBoard extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sector_id',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(TicketGroup::class)->orderBy('sort_order');
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(TicketStatus::class)->orderBy('sort_order');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(TicketField::class)->orderBy('sort_order');
    }

    public function forms(): HasMany
    {
        return $this->hasMany(TicketForm::class);
    }

    public function catalogItems(): HasMany
    {
        return $this->hasMany(ServiceCatalogItem::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function slaPolicy(): HasOne
    {
        return $this->hasOne(TicketSlaPolicy::class);
    }

    public function automationRules(): HasMany
    {
        return $this->hasMany(TicketAutomationRule::class)->orderBy('sort_order')->orderBy('id');
    }
}
