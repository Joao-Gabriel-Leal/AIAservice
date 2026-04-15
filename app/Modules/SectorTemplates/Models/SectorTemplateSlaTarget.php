<?php

namespace App\Modules\SectorTemplates\Models;

use App\Enums\TicketPriority;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectorTemplateSlaTarget extends Model
{
    protected $fillable = [
        'sector_template_sla_policy_id',
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
        return $this->belongsTo(SectorTemplateSlaPolicy::class, 'sector_template_sla_policy_id');
    }
}
