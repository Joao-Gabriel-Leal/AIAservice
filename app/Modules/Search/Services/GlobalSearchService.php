<?php

namespace App\Modules\Search\Services;

use App\Models\User;
use App\Modules\Assets\Models\Asset;
use App\Modules\Shared\Models\ActivityLog;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GlobalSearchService
{
    private const TYPE_LABELS = [
        'tickets' => 'Chamados',
        'messages' => 'Mensagens',
        'history' => 'Historico',
        'users' => 'Usuarios',
        'assets' => 'Patrimonios',
    ];

    /**
     * @param  array{query?:string,types?:array<int,string>,sector_id?:int|null,date_from?:string,date_to?:string}  $filters
     * @param  array<string,int>  $limits
     * @return array{query:string,groups:array<string,array{key:string,label:string,items:array<int,array<string,mixed>>,count:int,has_more:bool}>,total:int}
     */
    public function search(User $user, array $filters, array $limits = []): array
    {
        $query = trim((string) ($filters['query'] ?? ''));
        $types = $this->normalizeTypes($filters['types'] ?? []);
        $groups = collect(self::TYPE_LABELS)
            ->mapWithKeys(fn (string $label, string $key) => [$key => [
                'key' => $key,
                'label' => $label,
                'items' => [],
                'count' => 0,
                'has_more' => false,
            ]])
            ->all();

        if ($query === '') {
            return [
                'query' => $query,
                'groups' => $groups,
                'total' => 0,
            ];
        }

        foreach ($types as $type) {
            $limit = max(1, (int) ($limits[$type] ?? 5));
            $items = match ($type) {
                'tickets' => $this->searchTickets($user, $filters, $limit + 1),
                'messages' => $this->searchMessages($user, $filters, $limit + 1),
                'history' => $this->searchHistory($user, $filters, $limit + 1),
                'users' => $this->searchUsers($user, $filters, $limit + 1),
                'assets' => $this->searchAssets($user, $filters, $limit + 1),
                default => collect(),
            };

            $groups[$type]['count'] = $items->count();
            $groups[$type]['has_more'] = $items->count() > $limit;
            $groups[$type]['items'] = $items->take($limit)->values()->all();
        }

        return [
            'query' => $query,
            'groups' => $groups,
            'total' => collect($groups)->sum(fn (array $group) => count($group['items'])),
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function groupKeys(): array
    {
        return array_keys(self::TYPE_LABELS);
    }

    /**
     * @return array<int,array{key:string,label:string}>
     */
    public function typeOptions(): array
    {
        return collect(self::TYPE_LABELS)
            ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @param  array<int,string>  $types
     * @return array<int,string>
     */
    private function normalizeTypes(array $types): array
    {
        $normalized = collect($types)
            ->filter(fn ($type) => is_string($type) && array_key_exists($type, self::TYPE_LABELS))
            ->values()
            ->all();

        return $normalized === [] ? self::groupKeys() : $normalized;
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function searchTickets(User $user, array $filters, int $limit): Collection
    {
        $term = trim((string) $filters['query']);
        $termLower = mb_strtolower($term);
        $like = '%'.$termLower.'%';
        $prefix = $termLower.'%';
        $isNumeric = ctype_digit($term);

        return Ticket::query()
            ->visibleTo($user)
            ->with(['sector.company', 'requester', 'assignee', 'group'])
            ->when($filters['sector_id'] ?? null, fn (Builder $query, int $sectorId) => $query->where('sector_id', $sectorId))
            ->where(function (Builder $query) use ($like, $isNumeric, $term) {
                if ($isNumeric) {
                    $query->where('tickets.id', (int) $term)
                        ->orWhereRaw('LOWER(tickets.title) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(tickets.description, \'\')) LIKE ?', [$like])
                        ->orWhereHas('requester', function (Builder $requesterQuery) use ($like) {
                            $requesterQuery
                                ->whereRaw('LOWER(name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                        })
                        ->orWhereHas('assignee', function (Builder $assigneeQuery) use ($like) {
                            $assigneeQuery
                                ->whereRaw('LOWER(name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                        });

                    return;
                }

                $query
                    ->whereRaw('LOWER(tickets.title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(tickets.description, \'\')) LIKE ?', [$like])
                    ->orWhereHas('requester', function (Builder $requesterQuery) use ($like) {
                        $requesterQuery
                            ->whereRaw('LOWER(name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                    })
                    ->orWhereHas('assignee', function (Builder $assigneeQuery) use ($like) {
                        $assigneeQuery
                            ->whereRaw('LOWER(name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                    });
            })
            ->select('tickets.*')
            ->selectRaw(
                '(
                    CASE WHEN ? = 1 AND tickets.id = ? THEN 100 ELSE 0 END +
                    CASE WHEN LOWER(tickets.title) = ? THEN 60 ELSE 0 END +
                    CASE WHEN LOWER(tickets.title) LIKE ? THEN 30 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(tickets.description, \'\')) LIKE ? THEN 12 ELSE 0 END
                ) as search_relevance',
                [$isNumeric ? 1 : 0, $isNumeric ? (int) $term : 0, $termLower, $prefix, $like]
            )
            ->orderByDesc('search_relevance')
            ->orderByDesc('last_activity_at')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(function (Ticket $ticket) use ($term) {
                return [
                    'type' => 'tickets',
                    'title' => $ticket->title,
                    'subtitle' => 'Chamado #'.$ticket->id.' - '.($ticket->sector?->name ?? 'Sem setor'),
                    'snippet' => $this->excerpt($ticket->description, $term),
                    'url' => route('tickets.show', $ticket),
                    'meta' => [
                        'priority' => $ticket->priority?->label(),
                        'requester' => $ticket->requester?->name,
                        'assignee' => $ticket->assignee?->name,
                        'updated_at' => $ticket->updated_at?->diffForHumans(),
                    ],
                    'group_key' => 'tickets',
                ];
            });
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function searchMessages(User $user, array $filters, int $limit): Collection
    {
        $term = trim((string) $filters['query']);
        $termLower = mb_strtolower($term);
        $like = '%'.$termLower.'%';
        $visibleTickets = $this->visibleTicketsQuery($user, $filters['sector_id'] ?? null);

        $messages = TicketMessage::query()
            ->whereIn('ticket_id', $visibleTickets->select('id'))
            ->whereRaw('LOWER(message) LIKE ?', [$like])
            ->with('user')
            ->when(($filters['date_from'] ?? '') !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(($filters['date_to'] ?? '') !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->select('ticket_messages.*')
            ->selectRaw(
                '(
                    CASE WHEN LOWER(message) = ? THEN 50 ELSE 0 END +
                    CASE WHEN LOWER(message) LIKE ? THEN 25 ELSE 0 END
                ) as search_relevance',
                [$termLower, $termLower.'%']
            )
            ->orderByDesc('search_relevance')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $ticketsById = $this->ticketMapForChildren($messages->pluck('ticket_id')->all(), $user);

        return $messages
            ->filter(fn (TicketMessage $message) => $ticketsById->has($message->ticket_id))
            ->map(function (TicketMessage $message) use ($ticketsById, $term) {
                /** @var Ticket $ticket */
                $ticket = $ticketsById->get($message->ticket_id);

                return [
                    'type' => 'messages',
                    'title' => 'Mensagem no chamado #'.$ticket->id,
                    'subtitle' => $ticket->title,
                    'snippet' => $this->excerpt($message->message, $term),
                    'url' => route('tickets.show', $ticket),
                    'meta' => [
                        'author' => $message->user?->name ?? 'Sistema',
                        'created_at' => $message->created_at?->diffForHumans(),
                    ],
                    'group_key' => 'messages',
                ];
            })
            ->values();
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function searchHistory(User $user, array $filters, int $limit): Collection
    {
        $term = trim((string) $filters['query']);
        $termLower = mb_strtolower($term);
        $like = '%'.$termLower.'%';
        $visibleTickets = $this->visibleTicketsQuery($user, $filters['sector_id'] ?? null);

        $logs = ActivityLog::query()
            ->where('subject_type', Ticket::class)
            ->whereIn('subject_id', $visibleTickets->select('id'))
            ->where(function (Builder $query) use ($like) {
                $query
                    ->whereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(event) LIKE ?', [$like]);
            })
            ->with('causer')
            ->when(($filters['date_from'] ?? '') !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(($filters['date_to'] ?? '') !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->select('activity_logs.*')
            ->selectRaw(
                '(
                    CASE WHEN LOWER(COALESCE(description, \'\')) = ? THEN 40 ELSE 0 END +
                    CASE WHEN LOWER(event) = ? THEN 35 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(description, \'\')) LIKE ? THEN 20 ELSE 0 END +
                    CASE WHEN LOWER(event) LIKE ? THEN 18 ELSE 0 END
                ) as search_relevance',
                [$termLower, $termLower, $termLower.'%', $termLower.'%']
            )
            ->orderByDesc('search_relevance')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $ticketsById = $this->ticketMapForChildren($logs->pluck('subject_id')->all(), $user);

        return $logs
            ->filter(fn (ActivityLog $log) => $ticketsById->has($log->subject_id))
            ->map(function (ActivityLog $log) use ($ticketsById, $term) {
                /** @var Ticket $ticket */
                $ticket = $ticketsById->get($log->subject_id);

                return [
                    'type' => 'history',
                    'title' => 'Historico do chamado #'.$ticket->id,
                    'subtitle' => $ticket->title,
                    'snippet' => $this->excerpt($log->description ?: $log->event, $term),
                    'url' => route('tickets.show', $ticket),
                    'meta' => [
                        'author' => $log->causer?->name ?? 'Sistema',
                        'created_at' => $log->created_at?->diffForHumans(),
                    ],
                    'group_key' => 'history',
                ];
            })
            ->values();
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function searchUsers(User $user, array $filters, int $limit): Collection
    {
        if (! $user->can('viewAny', User::class)) {
            return collect();
        }

        $term = trim((string) $filters['query']);
        $termLower = mb_strtolower($term);
        $like = '%'.$termLower.'%';

        return User::query()
            ->with(['sector', 'room'])
            ->where(function (Builder $query) use ($like) {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
            })
            ->select('users.*')
            ->selectRaw(
                '(
                    CASE WHEN LOWER(name) = ? THEN 50 ELSE 0 END +
                    CASE WHEN LOWER(email) = ? THEN 45 ELSE 0 END +
                    CASE WHEN LOWER(name) LIKE ? THEN 20 ELSE 0 END +
                    CASE WHEN LOWER(email) LIKE ? THEN 18 ELSE 0 END
                ) as search_relevance',
                [$termLower, $termLower, $termLower.'%', $termLower.'%']
            )
            ->orderByDesc('search_relevance')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (User $listedUser) => [
                'type' => 'users',
                'title' => $listedUser->name,
                'subtitle' => $listedUser->email,
                'snippet' => trim(implode(' - ', array_filter([
                    $listedUser->global_role?->label(),
                    $listedUser->sector?->name,
                    $listedUser->room?->name,
                ]))),
                'url' => route('users.index', ['search' => $listedUser->email]),
                'meta' => [
                    'status' => $listedUser->is_active ? 'Ativo' : 'Inativo',
                ],
                'group_key' => 'users',
            ]);
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function searchAssets(User $user, array $filters, int $limit): Collection
    {
        $term = trim((string) $filters['query']);
        $termLower = mb_strtolower($term);
        $like = '%'.$termLower.'%';
        $prefix = $termLower.'%';

        return Asset::query()
            ->with(['currentSector', 'currentRoom', 'currentUser'])
            ->when(! $user->isSuperAdmin(), fn (Builder $query) => $query->where('current_user_id', $user->id))
            ->when($filters['sector_id'] ?? null, fn (Builder $query, int $sectorId) => $query->where('current_sector_id', $sectorId))
            ->where(function (Builder $query) use ($like) {
                $query
                    ->whereRaw('LOWER(asset_code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(serial_number, \'\')) LIKE ?', [$like]);
            })
            ->select('assets.*')
            ->selectRaw(
                '(
                    CASE WHEN LOWER(COALESCE(serial_number, \'\')) = ? THEN 60 ELSE 0 END +
                    CASE WHEN LOWER(asset_code) = ? THEN 55 ELSE 0 END +
                    CASE WHEN LOWER(name) = ? THEN 45 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(serial_number, \'\')) LIKE ? THEN 26 ELSE 0 END +
                    CASE WHEN LOWER(asset_code) LIKE ? THEN 22 ELSE 0 END +
                    CASE WHEN LOWER(name) LIKE ? THEN 18 ELSE 0 END
                ) as search_relevance',
                [$termLower, $termLower, $termLower, $prefix, $prefix, $prefix]
            )
            ->orderByDesc('search_relevance')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Asset $asset) => [
                'type' => 'assets',
                'title' => $asset->name,
                'subtitle' => trim(implode(' - ', array_filter([
                    $asset->asset_code,
                    $asset->serial_number ? 'Serial '.$asset->serial_number : null,
                ]))),
                'snippet' => trim(implode(' - ', array_filter([
                    $asset->currentSector?->name,
                    $asset->currentRoom?->name,
                    $asset->currentUser?->name,
                ]))),
                'url' => route('assets.show', $asset),
                'meta' => [
                    'status' => $asset->statusLabel(),
                ],
                'group_key' => 'assets',
            ]);
    }

    private function visibleTicketsQuery(User $user, ?int $sectorId = null): Builder
    {
        return Ticket::query()
            ->visibleTo($user)
            ->when($sectorId, fn (Builder $query, int $selectedSectorId) => $query->where('sector_id', $selectedSectorId));
    }

    /**
     * @param  array<int,int>  $ticketIds
     * @return Collection<int,Ticket>
     */
    private function ticketMapForChildren(array $ticketIds, User $user): Collection
    {
        return Ticket::query()
            ->visibleTo($user)
            ->with(['sector.company', 'requester', 'assignee'])
            ->whereIn('id', $ticketIds)
            ->get()
            ->keyBy('id');
    }

    private function excerpt(?string $text, string $term, int $radius = 72): string
    {
        $normalizedText = trim((string) $text);

        if ($normalizedText === '') {
            return 'Sem detalhes adicionais.';
        }

        $position = mb_stripos($normalizedText, $term);

        if ($position === false) {
            return mb_strlen($normalizedText) > ($radius * 2)
                ? mb_substr($normalizedText, 0, $radius * 2 - 3).'...'
                : $normalizedText;
        }

        $start = max(0, $position - $radius);
        $length = min(mb_strlen($normalizedText) - $start, ($radius * 2) + mb_strlen($term));
        $excerpt = mb_substr($normalizedText, $start, $length);

        if ($start > 0) {
            $excerpt = '...'.$excerpt;
        }

        if (($start + $length) < mb_strlen($normalizedText)) {
            $excerpt .= '...';
        }

        return $excerpt;
    }
}
