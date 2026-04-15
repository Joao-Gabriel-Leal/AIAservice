<?php

namespace App\Modules\Assets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetFinancialProfile extends Model
{
    protected $fillable = [
        'asset_id',
        'category_code',
        'category_name',
        'source_link',
        'invoice_number',
        'registered_at',
        'acquisition_value',
        'useful_life_months',
        'remaining_life_months',
        'depreciation_amount',
        'legacy_status',
        'legacy_collaborator_name',
        'legacy_position_text',
        'legacy_observation',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'date',
            'acquisition_value' => 'decimal:2',
            'depreciation_amount' => 'decimal:2',
            'useful_life_months' => 'integer',
            'remaining_life_months' => 'integer',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
