<?php

namespace App\Modules\Tickets\Models;

use App\Enums\TicketSprintStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketSprint extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_board_id',
        'name',
        'goal',
        'starts_at',
        'ends_at',
        'status',
        'closed_at',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'status' => TicketSprintStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(TicketBoard::class, 'ticket_board_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'ticket_sprint_id')
            ->orderBy('sprint_sort_order')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function sprintItems(): HasMany
    {
        return $this->hasMany(TicketSprintItem::class);
    }

    public function isActive(): bool
    {
        return $this->status === TicketSprintStatus::ACTIVE;
    }

    public function isClosed(): bool
    {
        return $this->status === TicketSprintStatus::CLOSED;
    }
}
