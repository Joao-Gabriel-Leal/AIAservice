<?php

namespace App\Modules\Tickets\Services;

use App\Models\User;
use App\Modules\KnowledgeBase\Services\KnowledgeBaseArticleSearchService;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TicketCreationSuggestionService
{
    private const MIN_USEFUL_CHARACTERS = 4;

    private const RESULT_LIMIT = 3;

    public function __construct(
        private readonly KnowledgeBaseArticleSearchService $knowledgeBaseArticleSearchService,
    ) {}

    public function suggest(User $user, ?int $sectorId, string $title, string $description): array
    {
        $searchText = $this->buildSearchText($title, $description);

        if (! $sectorId || mb_strlen($searchText) < self::MIN_USEFUL_CHARACTERS) {
            return $this->emptyPayload();
        }

        $terms = $this->terms($searchText);

        return [
            'articles' => $this->articleSuggestions($user, $sectorId, $searchText, $terms),
            'similar_tickets' => $this->similarTicketSuggestions($user, $sectorId, $searchText, $terms),
            'previous_solutions' => $this->previousSolutionSuggestions($user, $sectorId, $searchText, $terms),
        ];
    }

    public function emptyPayload(): array
    {
        return [
            'articles' => [],
            'similar_tickets' => [],
            'previous_solutions' => [],
        ];
    }

    private function articleSuggestions(User $user, int $sectorId, string $searchText, Collection $terms): array
    {
        return $this->knowledgeBaseArticleSearchService
            ->visibleQuery($user)
            ->where(function (Builder $query) use ($searchText, $terms) {
                $this->applyTextSearch($query, ['title', 'summary', 'content'], $searchText, $terms);
            })
            ->orderByRaw('case when sector_id = ? then 0 else 1 end', [$sectorId])
            ->latest()
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(fn ($article) => [
                'id' => $article->id,
                'title' => $article->title,
                'url' => route('knowledge-base.show', $article),
                'sector_name' => $article->sector?->name,
                'matched_excerpt' => $this->bestExcerpt([
                    $article->summary,
                    $article->content,
                ], $terms),
            ])
            ->all();
    }

    private function similarTicketSuggestions(User $user, int $sectorId, string $searchText, Collection $terms): array
    {
        return $this->ticketSuggestionBaseQuery($user, $sectorId, $searchText, $terms)
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(fn (Ticket $ticket) => [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'url' => route('tickets.show', $ticket),
                'sector_name' => $ticket->sector?->name,
                'matched_excerpt' => $this->bestExcerpt([
                    $ticket->description,
                    $ticket->title,
                ], $terms),
                'resolved_at' => $ticket->resolved_at?->format('d/m/Y H:i'),
            ])
            ->all();
    }

    private function previousSolutionSuggestions(User $user, int $sectorId, string $searchText, Collection $terms): array
    {
        return $this->ticketSuggestionBaseQuery($user, $sectorId, $searchText, $terms)
            ->whereNotNull('resolved_at')
            ->with([
                'messages' => fn ($query) => $query
                    ->where('is_system', false)
                    ->latest('created_at')
                    ->limit(1),
            ])
            ->orderByDesc('resolved_at')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(function (Ticket $ticket) use ($terms) {
                /** @var TicketMessage|null $latestMessage */
                $latestMessage = $ticket->messages->first();

                return [
                    'id' => $ticket->id,
                    'title' => $ticket->title,
                    'url' => route('tickets.show', $ticket),
                    'sector_name' => $ticket->sector?->name,
                    'matched_excerpt' => $this->bestExcerpt([
                        $ticket->description,
                        $ticket->title,
                    ], $terms),
                    'resolved_at' => $ticket->resolved_at?->format('d/m/Y H:i'),
                    'solution_excerpt' => $latestMessage
                        ? Str::limit(Str::squish($latestMessage->message), 180)
                        : null,
                ];
            })
            ->all();
    }

    private function ticketSuggestionBaseQuery(User $user, int $sectorId, string $searchText, Collection $terms): Builder
    {
        return Ticket::query()
            ->visibleTo($user)
            ->with(['sector.company'])
            ->where(function (Builder $query) use ($searchText, $terms) {
                $this->applyTextSearch($query, ['title', 'description'], $searchText, $terms);
            })
            ->orderByRaw('case when sector_id = ? then 0 else 1 end', [$sectorId])
            ->orderByDesc('last_activity_at');
    }

    private function applyTextSearch(Builder $query, array $columns, string $searchText, Collection $terms): void
    {
        $likeSearch = '%'.$searchText.'%';

        foreach ($columns as $column) {
            $query->orWhere($column, 'like', $likeSearch);
        }

        foreach ($terms as $term) {
            $query->orWhere(function (Builder $termQuery) use ($columns, $term) {
                $likeTerm = '%'.$term.'%';

                foreach ($columns as $column) {
                    $termQuery->orWhere($column, 'like', $likeTerm);
                }
            });
        }
    }

    private function buildSearchText(string $title, string $description): string
    {
        return trim((string) Str::of($title.' '.$description)
            ->lower()
            ->replaceMatches('/[^\pL\pN]+/u', ' ')
            ->squish()
            ->value());
    }

    private function terms(string $searchText): Collection
    {
        $terms = collect(explode(' ', $searchText))
            ->filter(fn (string $term) => mb_strlen($term) >= self::MIN_USEFUL_CHARACTERS)
            ->take(6)
            ->values();

        return $terms->isNotEmpty()
            ? $terms
            : collect([$searchText]);
    }

    private function bestExcerpt(array $candidates, Collection $terms): ?string
    {
        foreach ($terms as $term) {
            foreach ($candidates as $candidate) {
                $excerpt = $this->excerptAroundTerm($candidate, $term);

                if ($excerpt !== null) {
                    return $excerpt;
                }
            }
        }

        $fallback = collect($candidates)
            ->filter()
            ->map(fn (string $candidate) => Str::limit(Str::squish(strip_tags($candidate)), 160))
            ->first();

        return $fallback ?: null;
    }

    private function excerptAroundTerm(?string $text, string $term): ?string
    {
        $normalizedText = Str::squish(strip_tags((string) $text));

        if ($normalizedText === '') {
            return null;
        }

        $position = mb_stripos($normalizedText, $term);

        if ($position === false) {
            return null;
        }

        $start = max(0, $position - 50);
        $excerpt = mb_substr($normalizedText, $start, 160);

        return Str::limit(ltrim($excerpt), 160);
    }
}
