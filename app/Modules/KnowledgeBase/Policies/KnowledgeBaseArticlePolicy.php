<?php

namespace App\Modules\KnowledgeBase\Policies;

use App\Enums\KnowledgeBaseVisibility;
use App\Models\User;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;

class KnowledgeBaseArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, KnowledgeBaseArticle $article): bool
    {
        if (! $article->is_active) {
            return $user->isSuperAdmin()
                || $user->isSectorAdmin($article->sector_id);
        }

        if ($article->visibility === KnowledgeBaseVisibility::PUBLIC) {
            return true;
        }

        return $user->isSuperAdmin() || $user->hasOperationalAccess($article->sector_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isSectorAdmin();
    }

    public function update(User $user, KnowledgeBaseArticle $article): bool
    {
        return $user->isSuperAdmin() || $user->isSectorAdmin($article->sector_id);
    }

    public function delete(User $user, KnowledgeBaseArticle $article): bool
    {
        return $user->isSuperAdmin() || $user->isSectorAdmin($article->sector_id);
    }
}
