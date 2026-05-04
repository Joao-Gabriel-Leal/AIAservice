<?php

namespace App\Modules\Licenses\Models;

use App\Enums\LicenseAssignmentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseAssignment extends Model
{
    protected $fillable = [
        'license_id',
        'user_id',
        'assigned_email',
        'display_name',
        'external_reference',
        'seat_label',
        'status',
        'assigned_at',
        'released_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => LicenseAssignmentStatus::class,
            'assigned_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolvedDisplayName(): string
    {
        return $this->display_name
            ?: $this->user?->name
            ?: $this->assigned_email
            ?: 'Licenca sem identificacao';
    }

    public function resolvedAssignedEmail(): ?string
    {
        return $this->assigned_email ?: $this->user?->email;
    }
}
