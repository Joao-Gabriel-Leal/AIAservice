<?php

namespace App\Modules\Tickets\Models;

use App\Models\User;
use App\Modules\Sectors\Models\Sector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketBoard extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sector_id',
        'name',
        'slug',
        'description',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
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

    public function userAccesses(): HasMany
    {
        return $this->hasMany(TicketBoardUserAccess::class);
    }

    public function userPreferences(): HasMany
    {
        return $this->hasMany(TicketBoardUserPreference::class);
    }

    public function savedViews(): HasMany
    {
        return $this->hasMany(TicketBoardSavedView::class);
    }

    public function messageTemplates(): HasMany
    {
        return $this->hasMany(TicketMessageTemplate::class)->orderBy('sort_order')->orderBy('id');
    }

    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_board_user_accesses')
            ->withTimestamps();
    }

    public function slaPolicy(): HasOne
    {
        return $this->hasOne(TicketSlaPolicy::class);
    }

    public function automationRules(): HasMany
    {
        return $this->hasMany(TicketAutomationRule::class)->orderBy('sort_order')->orderBy('id');
    }

    public function defaultGroup(): ?TicketGroup
    {
        return $this->groups->firstWhere('is_default', true)
            ?? $this->groups->firstWhere('is_active', true)
            ?? $this->groups->first();
    }

    public function closedGroup(): ?TicketGroup
    {
        return $this->groups->firstWhere('is_closed', true)
            ?? $this->groups->filter(fn (TicketGroup $group) => $group->is_active)->last()
            ?? $this->groups->last();
    }
}
