<?php

namespace App\Modules\SectorTemplates\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SectorTemplateSlaPolicy extends Model
{
    protected $fillable = [
        'sector_template_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SectorTemplate::class, 'sector_template_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(SectorTemplateSlaTarget::class)->orderBy('priority');
    }
}
