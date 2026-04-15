<?php

namespace App\Modules\Tickets\Models;

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
        'started_at',
        'ended_at',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'source' => TicketTimeEntrySource::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
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
}
