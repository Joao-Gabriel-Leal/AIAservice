<?php

namespace App\Modules\SectorTemplates\Models;

use App\Enums\TicketFieldType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectorTemplateField extends Model
{
    protected $fillable = [
        'sector_template_id',
        'name',
        'slug',
        'type',
        'placeholder',
        'help_text',
        'options',
        'sort_order',
        'is_required',
        'show_on_board',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => TicketFieldType::class,
            'options' => 'array',
            'sort_order' => 'integer',
            'is_required' => 'boolean',
            'show_on_board' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SectorTemplate::class, 'sector_template_id');
    }
}
