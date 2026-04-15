<?php

namespace App\Modules\SectorTemplates\Models;

use App\Enums\TicketAutomationConditionField;
use App\Enums\TicketAutomationConditionOperator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectorTemplateAutomationRuleCondition extends Model
{
    protected $fillable = [
        'sector_template_automation_rule_id',
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
        return $this->belongsTo(SectorTemplateAutomationRule::class, 'sector_template_automation_rule_id');
    }
}
