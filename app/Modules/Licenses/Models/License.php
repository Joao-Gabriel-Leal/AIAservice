<?php

namespace App\Modules\Licenses\Models;

use App\Enums\LicenseAssignmentStatus;
use App\Enums\LicenseBillingCycle;
use App\Enums\LicenseStatus;
use App\Models\User;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Shared\Models\ActivityLog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class License extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sector_id',
        'vendor_name',
        'product_name',
        'plan_name',
        'license_reference',
        'supplier_name',
        'seats_total',
        'status',
        'billing_cycle',
        'cost_amount',
        'cost_currency',
        'purchased_at',
        'renewal_date',
        'expires_at',
        'auto_renew',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => LicenseStatus::class,
            'billing_cycle' => LicenseBillingCycle::class,
            'cost_amount' => 'decimal:2',
            'purchased_at' => 'date',
            'renewal_date' => 'date',
            'expires_at' => 'date',
            'auto_renew' => 'boolean',
            'seats_total' => 'integer',
        ];
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LicenseAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->assignments()->where('status', LicenseAssignmentStatus::ACTIVE->value);
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject')->latest();
    }

    public function displayName(): string
    {
        return collect([$this->vendor_name, $this->product_name, $this->plan_name])
            ->filter(fn (?string $value) => filled($value))
            ->implode(' - ');
    }

    public function dueDate(): ?CarbonInterface
    {
        return $this->expires_at ?? $this->renewal_date;
    }

    public function isExpired(): bool
    {
        $dueDate = $this->dueDate();

        return $dueDate instanceof CarbonInterface
            ? $dueDate->startOfDay()->lt(now()->startOfDay())
            : false;
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        $dueDate = $this->dueDate();

        if (! $dueDate instanceof CarbonInterface) {
            return false;
        }

        $today = now()->startOfDay();

        return ! $this->isExpired()
            && $dueDate->startOfDay()->betweenIncluded($today, $today->copy()->addDays($days));
    }

    public function seatsInUse(): int
    {
        $preloadedCount = $this->getAttribute('active_assignments_count');

        if (is_numeric($preloadedCount)) {
            return (int) $preloadedCount;
        }

        if ($this->relationLoaded('assignments')) {
            return $this->assignments
                ->where('status', LicenseAssignmentStatus::ACTIVE)
                ->count();
        }

        return $this->activeAssignments()->count();
    }

    public function seatsAvailable(): int
    {
        return max(0, (int) $this->seats_total - $this->seatsInUse());
    }
}
