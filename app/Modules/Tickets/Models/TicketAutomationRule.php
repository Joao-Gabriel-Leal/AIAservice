<?php

namespace App\Modules\Tickets\Models;

use App\Enums\TicketAutomationRunMode;
use App\Enums\TicketAutomationTrigger;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketAutomationRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_board_id',
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

    public function board(): BelongsTo
    {
        return $this->belongsTo(TicketBoard::class, 'ticket_board_id');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(TicketAutomationRuleCondition::class)->orderBy('sort_order')->orderBy('id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(TicketAutomationRuleAction::class)->orderBy('sort_order')->orderBy('id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(TicketAutomationExecution::class)->latest();
    }
}
