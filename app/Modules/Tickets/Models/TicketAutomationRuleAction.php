<?php

namespace App\Modules\Tickets\Models;

use App\Enums\TicketAutomationActionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAutomationRuleAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_automation_rule_id',
        'action',
        'payload',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'action' => TicketAutomationActionType::class,
            'payload' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(TicketAutomationRule::class, 'ticket_automation_rule_id');
    }
}
