<?php

namespace App\Modules\KnowledgeBase\Services;

use App\Enums\KnowledgeBaseVisibility;
use App\Models\User;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class KnowledgeBaseArticleSearchService
{
    public function adminQuery(User $user, ?string $search = null): Builder
    {
        $query = KnowledgeBaseArticle::query()
            ->with(['sector.company', 'author'])
            ->withCount('attachments')
            ->search($search)
            ->latest();

        if (! $user->isSuperAdmin()) {
            $query->whereIn('sector_id', $user->adminSectorIds());
        }

        return $query;
    }

    public function visibleQuery(User $user, ?string $search = null): Builder
    {
        return KnowledgeBaseArticle::query()
            ->with(['sector.company', 'author'])
            ->withCount('attachments')
            ->where('is_active', true)
            ->where(function (Builder $query) use ($user) {
                $query->where('visibility', KnowledgeBaseVisibility::PUBLIC->value);

                $operationalSectorIds = $user->operationalSectorIds();

                if ($operationalSectorIds !== []) {
                    $query->orWhere(function (Builder $privateQuery) use ($operationalSectorIds) {
                        $privateQuery
                            ->where('visibility', KnowledgeBaseVisibility::PRIVATE->value)
                            ->whereIn('sector_id', $operationalSectorIds);
                    });
                }
            })
            ->search($search)
            ->latest();
    }

    public function paginateAdmin(User $user, ?string $search = null, int $perPage = 12): LengthAwarePaginator
    {
        return $this->adminQuery($user, $search)->paginate($perPage)->withQueryString();
    }

    public function paginateVisible(User $user, ?string $search = null, int $perPage = 12): LengthAwarePaginator
    {
        return $this->visibleQuery($user, $search)->paginate($perPage)->withQueryString();
    }
}
