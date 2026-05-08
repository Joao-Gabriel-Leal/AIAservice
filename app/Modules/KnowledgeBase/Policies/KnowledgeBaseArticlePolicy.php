<?php

namespace App\Modules\KnowledgeBase\Policies;

use App\Enums\KnowledgeBaseArticleStatus;
use App\Enums\KnowledgeBaseVisibility;
use App\Models\User;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use App\Modules\Tickets\Models\Ticket;

class KnowledgeBaseArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, KnowledgeBaseArticle $article): bool
    {
        if ($article->editorial_status === KnowledgeBaseArticleStatus::DRAFT) {
            return $user->isGlobalAdmin()
                || $user->isSectorAdmin($article->sector_id)
                || $article->created_by === $user->id;
        }

        if (! $article->is_active) {
            return $user->isGlobalAdmin()
                || $user->isSectorAdmin($article->sector_id);
        }

        if ($article->visibility === KnowledgeBaseVisibility::PUBLIC) {
            return true;
        }

        return $user->isGlobalAdmin() || $user->hasOperationalAccess($article->sector_id);
    }

    public function create(User $user): bool
    {
        return $user->isGlobalAdmin() || $user->isSectorAdmin();
    }

    public function createFromTicket(User $user, Ticket $ticket): bool
    {
        return $ticket->isClosed() && $user->hasOperationalAccess($ticket->sector_id);
    }

    public function update(User $user, KnowledgeBaseArticle $article): bool
    {
        return $user->isGlobalAdmin() || $user->isSectorAdmin($article->sector_id);
    }

    public function delete(User $user, KnowledgeBaseArticle $article): bool
    {
        return $user->isGlobalAdmin() || $user->isSectorAdmin($article->sector_id);
    }
}
