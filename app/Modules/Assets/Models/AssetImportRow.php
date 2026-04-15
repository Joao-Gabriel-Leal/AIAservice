<?php

namespace App\Modules\Assets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetImportRow extends Model
{
    protected $fillable = [
        'batch_id',
        'asset_id',
        'source_sheet',
        'source_row',
        'asset_code',
        'item_name',
        'legacy_status',
        'processing_status',
        'pending_reason',
        'resolved_status',
        'resolved_sector_id',
        'resolved_room_id',
        'allocation_status',
        'raw_payload',
        'normalized_payload',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'normalized_payload' => 'array',
            'resolved_sector_id' => 'integer',
            'resolved_room_id' => 'integer',
            'source_row' => 'integer',
            'imported_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AssetImportBatch::class, 'batch_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
