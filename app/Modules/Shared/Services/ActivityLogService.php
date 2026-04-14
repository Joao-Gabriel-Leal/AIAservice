<?php

namespace App\Modules\Shared\Services;

use App\Models\User;
use App\Modules\Shared\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class ActivityLogService
{
    public function log(?User $causer, Model $subject, string $event, ?string $description = null, array $properties = []): ActivityLog
    {
        return $subject->activityLogs()->create([
            'sector_id' => $properties['sector_id'] ?? data_get($subject, 'sector_id'),
            'causer_id' => $causer?->id,
            'event' => $event,
            'description' => $description,
            'properties' => $properties,
        ]);
    }
}
