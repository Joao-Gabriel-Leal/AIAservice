<?php

namespace App\Modules\KnowledgeBase\Models;

use App\Enums\KnowledgeBaseArticleStatus;
use App\Enums\KnowledgeBaseVisibility;
use App\Models\User;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KnowledgeBaseArticle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sector_id',
        'created_by',
        'generated_from_ticket_id',
        'title',
        'summary',
        'content',
        'visibility',
        'editorial_status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'editorial_status' => KnowledgeBaseArticleStatus::class,
            'visibility' => KnowledgeBaseVisibility::class,
            'is_active' => 'boolean',
        ];
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sourceTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'generated_from_ticket_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(KnowledgeBaseAttachment::class)->latest();
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(KnowledgeBaseArticleFeedback::class);
    }

    public function ticketUsages(): HasMany
    {
        return $this->hasMany(KnowledgeBaseArticleTicketUsage::class);
    }

    public function isDraft(): bool
    {
        return $this->editorial_status === KnowledgeBaseArticleStatus::DRAFT;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $normalizedTerm = trim((string) $term);

        if ($normalizedTerm === '') {
            return $query;
        }

        return $query->where(function (Builder $searchQuery) use ($normalizedTerm) {
            $like = '%'.$normalizedTerm.'%';

            $searchQuery
                ->where('title', 'like', $like)
                ->orWhere('summary', 'like', $like)
                ->orWhere('content', 'like', $like);
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('editorial_status', KnowledgeBaseArticleStatus::PUBLISHED->value);
    }
}
