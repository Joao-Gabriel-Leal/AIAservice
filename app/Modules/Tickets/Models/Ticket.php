<?php

namespace App\Modules\Tickets\Models;

use App\Enums\TicketPriority;
use App\Models\User;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticleTicketUsage;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Shared\Models\ActivityLog;
use App\Modules\Tickets\Support\TicketReferenceCode;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_ticket_id',
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
        'board_sort_order',
        'subticket_sort_order',
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
        'is_major_incident',
        'major_incident_ticket_id',
    ];

    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'parent_ticket_id' => 'integer',
            'board_sort_order' => 'integer',
            'subticket_sort_order' => 'integer',
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
            'is_major_incident' => 'boolean',
            'major_incident_ticket_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Ticket $ticket): void {
            if (! $ticket->parent_ticket_id) {
                return;
            }

            if ($ticket->exists && (int) $ticket->parent_ticket_id === (int) $ticket->getKey()) {
                throw ValidationException::withMessages([
                    'subelement' => 'Um subelemento nao pode ser pai de si mesmo.',
                ]);
            }

            $parentIsSubelement = static::withTrashed()
                ->whereKey($ticket->parent_ticket_id)
                ->whereNotNull('parent_ticket_id')
                ->exists();

            if ($parentIsSubelement) {
                throw ValidationException::withMessages([
                    'subelement' => 'Subelementos nao podem ter outros subelementos.',
                ]);
            }
        });

        static::creating(function (Ticket $ticket): void {
            $ticket->ensureReferenceCode();

            if ($ticket->parent_ticket_id !== null) {
                $ticket->board_sort_order = null;
                $ticket->subticket_sort_order ??= ((int) static::query()
                    ->where('parent_ticket_id', $ticket->parent_ticket_id)
                    ->max('subticket_sort_order')) + 1;

                return;
            }

            if ($ticket->board_sort_order !== null || ! $ticket->ticket_board_id) {
                return;
            }

            $maxOrderQuery = static::query()->where('ticket_board_id', $ticket->ticket_board_id);

            if ($ticket->ticket_group_id === null) {
                $maxOrderQuery->whereNull('ticket_group_id');
            } else {
                $maxOrderQuery->where('ticket_group_id', $ticket->ticket_group_id);
            }

            $ticket->board_sort_order = ((int) $maxOrderQuery->max('board_sort_order')) + 1;
        });
    }

    public function scopeOrderedForBoardDisplay(Builder $query): Builder
    {
        return $query
            ->orderBy('board_sort_order')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function scopeOrderedSubelements(Builder $query): Builder
    {
        return $query
            ->orderBy('subticket_sort_order')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_ticket_id');
    }

    public function scopeSubelements(Builder $query): Builder
    {
        return $query->whereNotNull('parent_ticket_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query
            ->whereNull('resolved_at')
            ->where(function (Builder $statusQuery): void {
                $statusQuery
                    ->whereDoesntHave('group')
                    ->orWhereHas('group', fn (Builder $groupQuery) => $groupQuery->where('is_closed', false));
            })
            ->where(function (Builder $statusQuery): void {
                $statusQuery
                    ->whereDoesntHave('status')
                    ->orWhereHas('status', fn (Builder $ticketStatusQuery) => $ticketStatusQuery->where('is_closed', false));
            });
    }

    public function publicReference(): string
    {
        if (filled($this->reference_code)) {
            return (string) $this->reference_code;
        }

        return $this->technicalReference();
    }

    public function fullReference(): string
    {
        if (! $this->id || $this->publicReference() === $this->technicalReference()) {
            return $this->publicReference();
        }

        return $this->publicReference().' ('.$this->technicalReference().')';
    }

    public function technicalReference(): string
    {
        return '#'.($this->id ?? '?');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $operationalBoardIds = $user->operationalBoardIds();

        return $query->where(function (Builder $visibleQuery) use ($user, $operationalBoardIds) {
            $visibleQuery->where(function (Builder $requesterQuery) use ($user): void {
                $requesterQuery
                    ->topLevel()
                    ->where('requester_id', $user->id);
            });

            if ($operationalBoardIds !== []) {
                $visibleQuery->orWhereIn('ticket_board_id', $operationalBoardIds);
            }
        });
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function parentTicket(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_ticket_id');
    }

    public function subTickets(): HasMany
    {
        return $this->hasMany(self::class, 'parent_ticket_id')
            ->orderedSubelements();
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

    public function majorIncident(): BelongsTo
    {
        return $this->belongsTo(self::class, 'major_incident_ticket_id');
    }

    public function incidentChildren(): HasMany
    {
        return $this->hasMany(self::class, 'major_incident_ticket_id')
            ->latest('updated_at');
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

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TicketTimeEntry::class)->latest('started_at');
    }

    public function rating(): HasOne
    {
        return $this->hasOne(TicketRating::class);
    }

    public function generatedKnowledgeBaseArticle(): HasOne
    {
        return $this->hasOne(KnowledgeBaseArticle::class, 'generated_from_ticket_id');
    }

    public function knowledgeBaseUsages(): HasMany
    {
        return $this->hasMany(KnowledgeBaseArticleTicketUsage::class);
    }

    public function hasRating(): bool
    {
        if ($this->relationLoaded('rating')) {
            return $this->rating !== null;
        }

        return $this->rating()->exists();
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
        return (bool) ($this->group?->is_closed || $this->status?->is_closed || ! is_null($this->resolved_at));
    }

    public function isSubelement(): bool
    {
        return $this->parent_ticket_id !== null;
    }

    public function openSubelementsCount(): int
    {
        if ($this->relationLoaded('subTickets')) {
            return $this->subTickets->filter(fn (Ticket $ticket) => ! $ticket->isClosed())->count();
        }

        return $this->subTickets()->open()->count();
    }

    public function hasOpenSubelements(): bool
    {
        return $this->openSubelementsCount() > 0;
    }

    public function canBeRatedBy(User $user): bool
    {
        return ! $this->isSubelement()
            && $this->requester_id === $user->id
            && $this->isClosed()
            && ! $this->hasRating();
    }

    public function activeTimeEntryForUser(User $user): ?TicketTimeEntry
    {
        $timeEntries = $this->timeEntriesCollection();

        return $timeEntries
            ->first(fn (TicketTimeEntry $timeEntry) => $timeEntry->user_id === $user->id && $timeEntry->isRunning());
    }

    public function timeEntriesTotalSeconds(?CarbonInterface $reference = null): int
    {
        return $this->timeEntriesCollection()
            ->filter(fn (TicketTimeEntry $timeEntry) => $timeEntry->countsTowardTotals())
            ->sum(fn (TicketTimeEntry $timeEntry) => $timeEntry->elapsedSeconds($reference));
    }

    public function timeEntriesTotalByUser(?CarbonInterface $reference = null): Collection
    {
        return $this->timeEntriesCollection()
            ->filter(fn (TicketTimeEntry $timeEntry) => $timeEntry->countsTowardTotals())
            ->groupBy('user_id')
            ->map(function (Collection $entries) use ($reference) {
                /** @var TicketTimeEntry $firstEntry */
                $firstEntry = $entries->first();
                $activeEntry = $entries->first(fn (TicketTimeEntry $timeEntry) => $timeEntry->isRunning());

                return [
                    'user_id' => $firstEntry->user_id,
                    'user' => $firstEntry->user,
                    'total_seconds' => $entries->sum(fn (TicketTimeEntry $timeEntry) => $timeEntry->elapsedSeconds($reference)),
                    'active_entry_id' => $activeEntry?->id,
                    'active_started_at' => $activeEntry?->started_at,
                ];
            })
            ->sortByDesc('total_seconds')
            ->values();
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

    private function timeEntriesCollection(): Collection
    {
        if ($this->relationLoaded('timeEntries')) {
            return $this->getRelation('timeEntries');
        }

        return $this->timeEntries()->with('user')->get();
    }

    private function ensureReferenceCode(): void
    {
        if (filled($this->reference_code) && blank($this->reference_lookup)) {
            $this->reference_lookup = TicketReferenceCode::normalizeLookup($this->reference_code);
        }

        if (filled($this->reference_code) && filled($this->reference_lookup)) {
            return;
        }

        $sectorSlug = Sector::query()
            ->whereKey($this->sector_id)
            ->value('slug');

        $reference = TicketReferenceCode::generateUniqueForSectorSlug(
            $sectorSlug,
            fn (string $lookup): bool => static::withTrashed()
                ->where('reference_lookup', $lookup)
                ->exists(),
        );

        $this->reference_code = $reference['reference_code'];
        $this->reference_lookup = $reference['reference_lookup'];
    }
}
