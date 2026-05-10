<?php

namespace App\Modules\Tickets\Models;

use App\Enums\TicketSprintItemResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketSprintItem extends Model
{
    protected $fillable = [
        'ticket_sprint_id',
        'ticket_id',
        'result',
        'moved_to_sprint_id',
        'added_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => TicketSprintItemResult::class,
            'added_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(TicketSprint::class, 'ticket_sprint_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function movedToSprint(): BelongsTo
    {
        return $this->belongsTo(TicketSprint::class, 'moved_to_sprint_id');
    }
}
