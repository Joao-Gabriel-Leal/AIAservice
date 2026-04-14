<?php

namespace App\Modules\Tickets\Models;

use App\Enums\TicketAutomationExecutionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAutomationExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_automation_rule_id',
        'ticket_id',
        'trigger',
        'trigger_context_hash',
        'status',
        'matched_conditions',
        'actions_count',
        'idempotency_key',
        'started_at',
        'finished_at',
        'error_message',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketAutomationExecutionStatus::class,
            'matched_conditions' => 'boolean',
            'actions_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'context' => 'array',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(TicketAutomationRule::class, 'ticket_automation_rule_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
