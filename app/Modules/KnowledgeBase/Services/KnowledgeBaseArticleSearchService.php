<?php

namespace App\Modules\KnowledgeBase\Services;

use App\Enums\KnowledgeBaseArticleStatus;
use App\Enums\KnowledgeBaseVisibility;
use App\Models\User;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class KnowledgeBaseArticleSearchService
{
    private const HELPFUL_FEEDBACK_COUNT_SQL = "(select count(*) from knowledge_base_article_feedback where knowledge_base_articles.id = knowledge_base_article_feedback.knowledge_base_article_id and is_helpful = true)";

    private const NOT_HELPFUL_FEEDBACK_COUNT_SQL = "(select count(*) from knowledge_base_article_feedback where knowledge_base_articles.id = knowledge_base_article_feedback.knowledge_base_article_id and is_helpful = false)";

    public function adminQuery(User $user, ?string $search = null): Builder
    {
        $query = $this->baseQuery($search)
            ->search($search)
            ->latest();

        if (! $user->isSuperAdmin()) {
            $query->whereIn('sector_id', $user->adminSectorIds());
        }

        return $query;
    }

    public function visibleQuery(User $user, ?string $search = null): Builder
    {
        return $this->baseQuery($search)
            ->where('is_active', true)
            ->where('editorial_status', KnowledgeBaseArticleStatus::PUBLISHED->value)
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
            ->orderByRaw('('.self::HELPFUL_FEEDBACK_COUNT_SQL.' - '.self::NOT_HELPFUL_FEEDBACK_COUNT_SQL.') desc')
            ->orderByRaw(
                'case when ('.self::HELPFUL_FEEDBACK_COUNT_SQL.' + '.self::NOT_HELPFUL_FEEDBACK_COUNT_SQL.') = 0 then 0 else (1.0 * '
                .self::HELPFUL_FEEDBACK_COUNT_SQL.' / ('.self::HELPFUL_FEEDBACK_COUNT_SQL.' + '.self::NOT_HELPFUL_FEEDBACK_COUNT_SQL.')) end desc'
            )
            ->orderByDesc('ticket_usages_count')
            ->latest('updated_at');
    }

    public function paginateAdmin(User $user, ?string $search = null, int $perPage = 12): LengthAwarePaginator
    {
        return $this->adminQuery($user, $search)->paginate($perPage)->withQueryString();
    }

    public function paginateVisible(User $user, ?string $search = null, int $perPage = 12): LengthAwarePaginator
    {
        return $this->visibleQuery($user, $search)->paginate($perPage)->withQueryString();
    }

    public function topUseful(User $user, int $limit = 3): \Illuminate\Support\Collection
    {
        return $this->visibleQuery($user)->limit($limit)->get();
    }

    private function baseQuery(?string $search = null): Builder
    {
        return KnowledgeBaseArticle::query()
            ->with(['sector.company', 'author', 'sourceTicket'])
            ->withCount('attachments')
            ->withCount([
                'feedback as helpful_feedback_count' => fn (Builder $query) => $query->where('is_helpful', true),
                'feedback as not_helpful_feedback_count' => fn (Builder $query) => $query->where('is_helpful', false),
                'ticketUsages',
            ])
            ->search($search);
    }
}
