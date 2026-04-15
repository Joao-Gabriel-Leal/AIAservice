<?php

namespace App\Modules\SectorTemplates\Models;

use App\Enums\TicketAutomationRunMode;
use App\Enums\TicketAutomationTrigger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SectorTemplateAutomationRule extends Model
{
    protected $fillable = [
        'sector_template_id',
        'name',
        'description',
        'trigger',
        'run_mode',
        'trigger_settings',
        'cooldown_minutes',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'trigger' => TicketAutomationTrigger::class,
            'run_mode' => TicketAutomationRunMode::class,
            'trigger_settings' => 'array',
            'cooldown_minutes' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SectorTemplate::class, 'sector_template_id');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(SectorTemplateAutomationRuleCondition::class)->orderBy('sort_order')->orderBy('id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(SectorTemplateAutomationRuleAction::class)->orderBy('sort_order')->orderBy('id');
    }
}
