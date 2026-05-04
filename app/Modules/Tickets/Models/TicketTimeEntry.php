<?php

namespace App\Modules\Tickets\Models;

use App\Enums\TicketTimeEntryApprovalStatus;
use App\Enums\TicketTimeEntrySource;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketTimeEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'source',
        'approval_status',
        'started_at',
        'ended_at',
        'duration_seconds',
        'reviewed_by_id',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'source' => TicketTimeEntrySource::class,
            'approval_status' => TicketTimeEntryApprovalStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TicketTimeEntry $timeEntry): void {
            if ($timeEntry->approval_status !== null) {
                return;
            }

            $source = $timeEntry->source instanceof TicketTimeEntrySource
                ? $timeEntry->source
                : TicketTimeEntrySource::tryFrom((string) $timeEntry->source);

            $timeEntry->approval_status = $source === TicketTimeEntrySource::MANUAL
                ? TicketTimeEntryApprovalStatus::PENDING
                : TicketTimeEntryApprovalStatus::APPROVED;
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id')->withTrashed();
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    public function elapsedSeconds(?CarbonInterface $reference = null): int
    {
        if ($this->isRunning()) {
            if (! $this->started_at) {
                return 0;
            }

            return max(0, ($reference ?? now())->getTimestamp() - $this->started_at->getTimestamp());
        }

        return max(0, (int) ($this->duration_seconds ?? 0));
    }

    public function isPendingApproval(): bool
    {
        return $this->approval_status === TicketTimeEntryApprovalStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->approval_status === TicketTimeEntryApprovalStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->approval_status === TicketTimeEntryApprovalStatus::REJECTED;
    }

    public function countsTowardTotals(): bool
    {
        return $this->isApproved();
    }
}
