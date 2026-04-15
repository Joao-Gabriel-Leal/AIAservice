<?php

namespace App\Modules\SectorTemplates\Models;

use App\Enums\TicketPriority;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectorTemplateCatalogItem extends Model
{
    protected $fillable = [
        'sector_template_id',
        'name',
        'description',
        'default_ticket_group_slug',
        'default_priority',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_priority' => TicketPriority::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SectorTemplate::class, 'sector_template_id');
    }
}
