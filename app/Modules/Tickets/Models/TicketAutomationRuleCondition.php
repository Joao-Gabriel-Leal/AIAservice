<?php

namespace App\Modules\Tickets\Models;

use App\Enums\TicketAutomationConditionField;
use App\Enums\TicketAutomationConditionOperator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAutomationRuleCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_automation_rule_id',
        'field',
        'operator',
        'value',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'field' => TicketAutomationConditionField::class,
            'operator' => TicketAutomationConditionOperator::class,
            'value' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(TicketAutomationRule::class, 'ticket_automation_rule_id');
    }
}
