<?php

namespace App\Modules\Assets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetImportBatch extends Model
{
    protected $fillable = [
        'source_path',
        'source_sheet',
        'reference_sheet',
        'total_rows',
        'ready_rows',
        'pending_review_rows',
        'error_rows',
        'promoted_rows',
        'metadata',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'total_rows' => 'integer',
            'ready_rows' => 'integer',
            'pending_review_rows' => 'integer',
            'error_rows' => 'integer',
            'promoted_rows' => 'integer',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(AssetImportRow::class, 'batch_id');
    }
}
