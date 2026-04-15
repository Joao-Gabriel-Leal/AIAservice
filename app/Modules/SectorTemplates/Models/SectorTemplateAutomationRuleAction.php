<?php

namespace App\Modules\SectorTemplates\Models;

use App\Enums\TicketAutomationActionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectorTemplateAutomationRuleAction extends Model
{
    protected $fillable = [
        'sector_template_automation_rule_id',
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
        return $this->belongsTo(SectorTemplateAutomationRule::class, 'sector_template_automation_rule_id');
    }
}
