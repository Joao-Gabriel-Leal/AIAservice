<?php

namespace App\Modules\Tickets\Models;

use App\Enums\TicketFormOpeningAccessLevel;
use App\Enums\TicketWorkItemType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketForm extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_board_id',
        'name',
        'description',
        'opening_access_level',
        'default_work_item_type',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opening_access_level' => TicketFormOpeningAccessLevel::class,
            'default_work_item_type' => TicketWorkItemType::class,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeAccessibleTo(Builder $query, User $user, ?int $sectorId = null): Builder
    {
        if ($sectorId !== null) {
            $query->whereHas('board', fn (Builder $boardQuery) => $boardQuery->where('sector_id', $sectorId));
        }

        if ($user->isGlobalAdmin()) {
            return $query;
        }

        $operatorBoardIds = $user->operationalBoardIds();
        $managerSectorIds = $user->adminSectorIds();

        return $query->where(function (Builder $accessQuery) use ($operatorBoardIds, $managerSectorIds) {
            $accessQuery->where('opening_access_level', TicketFormOpeningAccessLevel::PUBLIC->value);

            if ($operatorBoardIds !== []) {
                $accessQuery->orWhere(function (Builder $operatorQuery) use ($operatorBoardIds) {
                    $operatorQuery
                        ->where('opening_access_level', TicketFormOpeningAccessLevel::OPERATOR->value)
                        ->whereIn('ticket_board_id', $operatorBoardIds);
                });
            }

            if ($managerSectorIds !== []) {
                $accessQuery->orWhere(function (Builder $managerQuery) use ($managerSectorIds) {
                    $managerQuery
                        ->where('opening_access_level', TicketFormOpeningAccessLevel::MANAGER->value)
                        ->whereHas('board', fn (Builder $boardQuery) => $boardQuery->whereIn('sector_id', $managerSectorIds));
                });
            }
        });
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(TicketBoard::class, 'ticket_board_id');
    }

    public function formFields(): HasMany
    {
        return $this->hasMany(TicketFormField::class)->orderBy('sort_order');
    }

    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(TicketField::class, 'ticket_form_fields')
            ->withPivot([
                'is_required',
                'sort_order',
                'visibility_parent_field_id',
                'visibility_operator',
                'visibility_expected_value',
            ])
            ->withTimestamps();
    }

    public function catalogItems(): HasMany
    {
        return $this->hasMany(ServiceCatalogItem::class);
    }

    public function canBeOpenedBy(User $user): bool
    {
        $board = $this->board;
        $sectorId = $board?->sector_id ?? $this->board()->value('sector_id');

        if (! $sectorId) {
            return false;
        }

        $accessLevel = $this->opening_access_level ?? TicketFormOpeningAccessLevel::PUBLIC;

        if ($accessLevel === TicketFormOpeningAccessLevel::OPERATOR) {
            return $user->canOperateBoard($board ?? $this->ticket_board_id);
        }

        return $accessLevel->allows($user, (int) $sectorId);
    }
}
