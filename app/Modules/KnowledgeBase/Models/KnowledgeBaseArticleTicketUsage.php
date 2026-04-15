<?php

namespace App\Modules\KnowledgeBase\Models;

use App\Models\User;
use App\Modules\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBaseArticleTicketUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'knowledge_base_article_id',
        'ticket_id',
        'used_by_id',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseArticle::class, 'knowledge_base_article_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by_id');
    }
}
