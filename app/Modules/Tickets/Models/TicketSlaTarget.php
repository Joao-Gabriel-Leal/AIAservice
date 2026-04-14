<?php

namespace App\Modules\Tickets\Models;

use App\Enums\TicketPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketSlaTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_sla_policy_id',
        'priority',
        'first_response_minutes',
        'resolution_minutes',
    ];

    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'first_response_minutes' => 'integer',
            'resolution_minutes' => 'integer',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(TicketSlaPolicy::class, 'ticket_sla_policy_id');
    }
}
