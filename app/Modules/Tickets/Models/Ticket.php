<?php

namespace App\Modules\Tickets\Models;

use App\Enums\TicketPriority;
use App\Models\User;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Shared\Models\ActivityLog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sector_id',
        'ticket_board_id',
        'ticket_group_id',
        'ticket_status_id',
        'service_catalog_item_id',
        'room_id',
        'title',
        'description',
        'requester_id',
        'assignee_id',
        'priority',
        'first_response_sla_minutes',
        'first_response_due_at',
        'first_responded_at',
        'first_response_warning_sent_at',
        'first_response_breached_at',
        'resolution_sla_minutes',
        'resolution_due_at',
        'resolution_warning_sent_at',
        'resolution_breached_at',
        'resolved_at',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'first_response_sla_minutes' => 'integer',
            'first_response_due_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'first_response_warning_sent_at' => 'datetime',
            'first_response_breached_at' => 'datetime',
            'resolution_sla_minutes' => 'integer',
            'resolution_due_at' => 'datetime',
            'resolution_warning_sent_at' => 'datetime',
            'resolution_breached_at' => 'datetime',
            'resolved_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $operationalSectorIds = $user->operationalSectorIds();

        return $query->where(function (Builder $visibleQuery) use ($user, $operationalSectorIds) {
            $visibleQuery->where('requester_id', $user->id);

            if ($operationalSectorIds !== []) {
                $visibleQuery->orWhereIn('sector_id', $operationalSectorIds);
            }
        });
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(TicketBoard::class, 'ticket_board_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TicketGroup::class, 'ticket_group_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'ticket_status_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalogItem::class, 'service_catalog_item_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(TicketFieldValue::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->oldest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function rating(): HasOne
    {
        return $this->hasOne(TicketRating::class);
    }

    public function automationExecutions(): HasMany
    {
        return $this->hasMany(TicketAutomationExecution::class)->latest();
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject')->latest();
    }

    public function isClosed(): bool
    {
        return (bool) ($this->group?->is_closed || ! is_null($this->resolved_at));
    }

    public function firstResponseSlaState(): string
    {
        return $this->deadlineState(
            $this->first_response_due_at,
            $this->first_responded_at,
            $this->first_response_breached_at,
        );
    }

    public function resolutionSlaState(): string
    {
        return $this->deadlineState(
            $this->resolution_due_at,
            $this->resolved_at,
            $this->resolution_breached_at,
        );
    }

    public function overallSlaState(): string
    {
        return collect([
            $this->firstResponseSlaState(),
            $this->resolutionSlaState(),
        ])->contains('breached')
            ? 'breached'
            : (collect([$this->firstResponseSlaState(), $this->resolutionSlaState()])->contains('warning') ? 'warning' : 'ok');
    }

    public function slaSummary(): array
    {
        return [
            'first_response' => [
                'label' => 'Primeira resposta',
                'state' => $this->firstResponseSlaState(),
                'due_at' => $this->first_response_due_at,
                'completed_at' => $this->first_responded_at,
            ],
            'resolution' => [
                'label' => 'Resolucao',
                'state' => $this->resolutionSlaState(),
                'due_at' => $this->resolution_due_at,
                'completed_at' => $this->resolved_at,
            ],
        ];
    }

    private function deadlineState(
        ?CarbonInterface $dueAt,
        ?CarbonInterface $completedAt,
        ?CarbonInterface $breachedAt,
    ): string {
        if (! $dueAt) {
            return 'na';
        }

        if ($breachedAt || ($completedAt && $completedAt->greaterThan($dueAt))) {
            return 'breached';
        }

        if ($completedAt) {
            return 'ok';
        }

        if (now()->greaterThan($dueAt)) {
            return 'breached';
        }

        if (now()->diffInMinutes($dueAt, false) <= 30) {
            return 'warning';
        }

        return 'ok';
    }
}
