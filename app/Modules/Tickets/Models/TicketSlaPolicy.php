<?php

namespace App\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketSlaPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_board_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(TicketBoard::class, 'ticket_board_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(TicketSlaTarget::class)->orderBy('priority');
    }
}
