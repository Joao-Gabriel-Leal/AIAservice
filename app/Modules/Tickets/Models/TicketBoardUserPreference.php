<?php

namespace App\Modules\Tickets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketBoardUserPreference extends Model
{
    protected $fillable = [
        'user_id',
        'ticket_board_id',
        'is_favorite',
        'last_opened_at',
    ];

    protected function casts(): array
    {
        return [
            'is_favorite' => 'boolean',
            'last_opened_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(TicketBoard::class, 'ticket_board_id');
    }
}
