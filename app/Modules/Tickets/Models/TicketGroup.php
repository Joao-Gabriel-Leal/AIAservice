<?php

namespace App\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_board_id',
        'name',
        'slug',
        'color',
        'sort_order',
        'is_collapsed_by_default',
        'is_default',
        'is_closed',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_collapsed_by_default' => 'boolean',
            'is_default' => 'boolean',
            'is_closed' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(TicketBoard::class, 'ticket_board_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'ticket_group_id');
    }
}
