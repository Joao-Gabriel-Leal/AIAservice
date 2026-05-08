<?php

namespace App\Modules\Search\Services;

use App\Enums\TicketPriority;
use App\Models\User;
use App\Modules\Assets\Models\Asset;
use App\Modules\Companies\Models\Company;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use App\Modules\Licenses\Models\License;
use App\Modules\Rooms\Models\Room;
use App\Modules\SectorTemplates\Models\SectorTemplate;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Shared\Models\ActivityLog;
use App\Modules\Tickets\Models\ServiceCatalogItem;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Models\TicketForm;
use App\Modules\Tickets\Models\TicketGroup;
use App\Modules\Tickets\Models\TicketMessage;
use App\Modules\Tickets\Models\TicketSlaPolicy;
use App\Modules\Tickets\Models\TicketStatus;
use App\Modules\Tickets\Support\TicketReferenceCode;
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
        'knowledge' => 'Base de conhecimento',
        'companies' => 'Empresas',
        'sectors' => 'Setores',
        'rooms' => 'Salas',
        'boards' => 'Quadros',
        'forms' => 'Formularios',
        'catalog' => 'Catalogo',
        'workflow' => 'Etapas e status',
        'slas' => 'SLAs',
        'licenses' => 'Licencas',
        'templates' => 'Templates de setor',
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
                'knowledge' => $this->searchKnowledgeBase($user, $filters, $limit + 1),
                'companies' => $this->searchCompanies($user, $filters, $limit + 1),
                'sectors' => $this->searchSectors($user, $filters, $limit + 1),
                'rooms' => $this->searchRooms($user, $filters, $limit + 1),
                'boards' => $this->searchBoards($user, $filters, $limit + 1),
                'forms' => $this->searchForms($user, $filters, $limit + 1),
                'catalog' => $this->searchCatalog($user, $filters, $limit + 1),
                'workflow' => $this->searchWorkflow($user, $filters, $limit + 1),
                'slas' => $this->searchSlas($user, $filters, $limit + 1),
                'licenses' => $this->searchLicenses($user, $filters, $limit + 1),
                'templates' => $this->searchSectorTemplates($user, $filters, $limit + 1),
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
        $normalizedReference = TicketReferenceCode::normalizeLookup($term);
        $hasReferenceTerm = $normalizedReference !== '';
        $referencePrefix = $normalizedReference.'%';

        return Ticket::query()
            ->visibleTo($user)
            ->with(['sector.company', 'requester', 'assignee', 'group'])
            ->when($filters['sector_id'] ?? null, fn (Builder $query, int $sectorId) => $query->where('sector_id', $sectorId))
            ->where(function (Builder $query) use ($like, $isNumeric, $term, $hasReferenceTerm, $referencePrefix) {
                if ($isNumeric) {
                    $query->where('tickets.id', (int) $term)
                        ->orWhereRaw('LOWER(tickets.title) LIKE ?', [$like]);

                    if ($hasReferenceTerm) {
                        $query->orWhereRaw('UPPER(COALESCE(tickets.reference_lookup, \'\')) LIKE ?', [$referencePrefix]);
                    }

                    $query
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

                if ($hasReferenceTerm) {
                    $query->orWhereRaw('UPPER(COALESCE(tickets.reference_lookup, \'\')) LIKE ?', [$referencePrefix]);
                }
            })
            ->select('tickets.*')
            ->selectRaw(
                '(
                    CASE WHEN ? = 1 AND UPPER(COALESCE(tickets.reference_lookup, \'\')) = ? THEN 120 ELSE 0 END +
                    CASE WHEN ? = 1 AND UPPER(COALESCE(tickets.reference_lookup, \'\')) LIKE ? THEN 70 ELSE 0 END +
                    CASE WHEN ? = 1 AND tickets.id = ? THEN 100 ELSE 0 END +
                    CASE WHEN LOWER(tickets.title) = ? THEN 60 ELSE 0 END +
                    CASE WHEN LOWER(tickets.title) LIKE ? THEN 30 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(tickets.description, \'\')) LIKE ? THEN 12 ELSE 0 END
                ) as search_relevance',
                [
                    $hasReferenceTerm ? 1 : 0,
                    $normalizedReference,
                    $hasReferenceTerm ? 1 : 0,
                    $referencePrefix,
                    $isNumeric ? 1 : 0,
                    $isNumeric ? (int) $term : 0,
                    $termLower,
                    $prefix,
                    $like,
                ]
            )
            ->orderByDesc('search_relevance')
            ->orderByDesc('last_activity_at')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(function (Ticket $ticket) use ($term) {
                return [
                    'type' => 'tickets',
                    'title' => $ticket->publicReference().' - '.$ticket->title,
                    'subtitle' => $ticket->fullReference().' - '.($ticket->sector?->name ?? 'Sem setor'),
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
                    'title' => 'Mensagem em '.$ticket->publicReference(),
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
                    'title' => 'Historico de '.$ticket->publicReference(),
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

    /**
     * @param  array<string,mixed>  $filters
     */
    private function searchKnowledgeBase(User $user, array $filters, int $limit): Collection
    {
        $term = trim((string) $filters['query']);
        $termLower = mb_strtolower($term);
        $like = '%'.$termLower.'%';
        $prefix = $termLower.'%';

        return KnowledgeBaseArticle::query()
            ->with(['sector.company', 'author'])
            ->when($filters['sector_id'] ?? null, fn (Builder $query, int $sectorId) => $query->where('sector_id', $sectorId))
            ->where(function (Builder $query) use ($like) {
                $query
                    ->whereRaw('LOWER(title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(summary, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(content, \'\')) LIKE ?', [$like])
                    ->orWhereHas('sector', fn (Builder $sectorQuery) => $sectorQuery->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('sector.company', fn (Builder $companyQuery) => $companyQuery->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('author', function (Builder $authorQuery) use ($like) {
                        $authorQuery
                            ->whereRaw('LOWER(name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                    });
            })
            ->when(($filters['date_from'] ?? '') !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(($filters['date_to'] ?? '') !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->select('knowledge_base_articles.*')
            ->selectRaw(
                '(
                    CASE WHEN LOWER(title) = ? THEN 60 ELSE 0 END +
                    CASE WHEN LOWER(title) LIKE ? THEN 30 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(summary, \'\')) LIKE ? THEN 18 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(content, \'\')) LIKE ? THEN 10 ELSE 0 END
                ) as search_relevance',
                [$termLower, $prefix, $like, $like]
            )
            ->orderByDesc('search_relevance')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (KnowledgeBaseArticle $article) => [
                'type' => 'knowledge',
                'title' => $article->title,
                'subtitle' => 'Artigo - '.($article->sector?->name ?? 'Sem setor'),
                'snippet' => $this->excerpt($article->summary ?: $article->content, $term),
                'url' => route('knowledge-base.show', $article),
                'meta' => [
                    'status' => $article->editorial_status?->label(),
                    'visibility' => $article->visibility?->label(),
                    'author' => $article->author?->name,
                ],
                'group_key' => 'knowledge',
            ]);
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function searchCompanies(User $user, array $filters, int $limit): Collection
    {
        if (! $user->isSuperAdmin()) {
            return collect();
        }

        $term = trim((string) $filters['query']);
        $termLower = mb_strtolower($term);
        $like = '%'.$termLower.'%';
        $prefix = $termLower.'%';

        return Company::query()
            ->when($filters['sector_id'] ?? null, fn (Builder $query, int $sectorId) => $query->whereHas('sectors', fn (Builder $sectorQuery) => $sectorQuery->whereKey($sectorId)))
            ->where(function (Builder $query) use ($like) {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(legal_name, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(document, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$like])
                    ->orWhereHas('sectors', fn (Builder $sectorQuery) => $sectorQuery->whereRaw('LOWER(name) LIKE ?', [$like]));
            })
            ->select('companies.*')
            ->selectRaw(
                '(
                    CASE WHEN LOWER(name) = ? THEN 55 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(document, \'\')) = ? THEN 50 ELSE 0 END +
                    CASE WHEN LOWER(name) LIKE ? THEN 25 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(legal_name, \'\')) LIKE ? THEN 20 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(document, \'\')) LIKE ? THEN 18 ELSE 0 END
                ) as search_relevance',
                [$termLower, $termLower, $prefix, $like, $like]
            )
            ->withCount('sectors')
            ->orderByDesc('search_relevance')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Company $company) => [
                'type' => 'companies',
                'title' => $company->name,
                'subtitle' => $company->legal_name ?: 'Empresa',
                'snippet' => trim(implode(' - ', array_filter([
                    $company->document,
                    $company->email,
                    $company->phone,
                ]))) ?: 'Cadastro de empresa.',
                'url' => route('companies.index', ['search' => $company->name]),
                'meta' => [
                    'status' => $company->is_active ? 'Ativa' : 'Inativa',
                    'sectors' => $company->sectors_count.' setor(es)',
                ],
                'group_key' => 'companies',
            ]);
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function searchSectors(User $user, array $filters, int $limit): Collection
    {
        $term = trim((string) $filters['query']);
        $termLower = mb_strtolower($term);
        $like = '%'.$termLower.'%';
        $prefix = $termLower.'%';
        $sectorIds = $user->isSuperAdmin() ? null : $user->allSectorIds();

        return Sector::query()
            ->with('company')
            ->when(is_array($sectorIds), fn (Builder $query) => $query->whereIn('id', $sectorIds === [] ? [0] : $sectorIds))
            ->when($filters['sector_id'] ?? null, fn (Builder $query, int $sectorId) => $query->whereKey($sectorId))
            ->where(function (Builder $query) use ($like) {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(slug, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$like])
                    ->orWhereHas('company', function (Builder $companyQuery) use ($like) {
                        $companyQuery
                            ->whereRaw('LOWER(name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(COALESCE(legal_name, \'\')) LIKE ?', [$like]);
                    });
            })
            ->select('sectors.*')
            ->selectRaw(
                '(
                    CASE WHEN LOWER(name) = ? THEN 55 ELSE 0 END +
                    CASE WHEN LOWER(name) LIKE ? THEN 25 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(slug, \'\')) LIKE ? THEN 20 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(description, \'\')) LIKE ? THEN 12 ELSE 0 END
                ) as search_relevance',
                [$termLower, $prefix, $prefix, $like]
            )
            ->withCount(['rooms', 'tickets', 'boards'])
            ->orderByDesc('search_relevance')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Sector $sector) => [
                'type' => 'sectors',
                'title' => $sector->name,
                'subtitle' => $sector->company?->name ?? 'Sem empresa',
                'snippet' => $this->excerpt($sector->description, $term),
                'url' => route('sectors.index', ['search' => $sector->name]),
                'meta' => [
                    'status' => $sector->is_active ? 'Ativo' : 'Inativo',
                    'boards' => $sector->boards_count.' quadro(s)',
                    'tickets' => $sector->tickets_count.' chamado(s)',
                ],
                'group_key' => 'sectors',
            ]);
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function searchRooms(User $user, array $filters, int $limit): Collection
    {
        if (! $user->canManageRooms()) {
            return collect();
        }

        $term = trim((string) $filters['query']);
        $termLower = mb_strtolower($term);
        $like = '%'.$termLower.'%';
        $prefix = $termLower.'%';

        return Room::query()
            ->with('sector.company')
            ->when($filters['sector_id'] ?? null, fn (Builder $query, int $sectorId) => $query->where('sector_id', $sectorId))
            ->where(function (Builder $query) use ($like) {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$like])
                    ->orWhereHas('sector', fn (Builder $sectorQuery) => $sectorQuery->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('sector.company', fn (Builder $companyQuery) => $companyQuery->whereRaw('LOWER(name) LIKE ?', [$like]));
            })
            ->select('rooms.*')
            ->selectRaw(
                '(
                    CASE WHEN LOWER(name) = ? THEN 50 ELSE 0 END +
                    CASE WHEN LOWER(name) LIKE ? THEN 24 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(description, \'\')) LIKE ? THEN 12 ELSE 0 END
                ) as search_relevance',
                [$termLower, $prefix, $like]
            )
            ->withCount(['users', 'tickets', 'currentAssets'])
            ->orderByDesc('search_relevance')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Room $room) => [
                'type' => 'rooms',
                'title' => $room->name,
                'subtitle' => trim(implode(' - ', array_filter([
                    $room->sector?->company?->name,
                    $room->sector?->name,
                ]))),
                'snippet' => $this->excerpt($room->description, $term),
                'url' => route('rooms.index', ['search' => $room->name]),
                'meta' => [
                    'status' => $room->is_active ? 'Ativa' : 'Inativa',
                    'users' => $room->users_count.' usuario(s)',
                    'assets' => $room->current_assets_count.' patrimonio(s)',
                ],
                'group_key' => 'rooms',
            ]);
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function searchBoards(User $user, array $filters, int $limit): Collection
    {
        $term = trim((string) $filters['query']);
        $termLower = mb_strtolower($term);
        $like = '%'.$termLower.'%';
        $prefix = $termLower.'%';

        return TicketBoard::query()
            ->with('sector.company')
            ->when(! $user->isSuperAdmin(), fn (Builder $query) => $query->whereIn('id', $user->operationalBoardIds() ?: [0]))
            ->when($filters['sector_id'] ?? null, fn (Builder $query, int $sectorId) => $query->where('sector_id', $sectorId))
            ->where(function (Builder $query) use ($like) {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(slug, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$like])
                    ->orWhereHas('sector', fn (Builder $sectorQuery) => $sectorQuery->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('sector.company', fn (Builder $companyQuery) => $companyQuery->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('groups', fn (Builder $groupQuery) => $groupQuery->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('statuses', fn (Builder $statusQuery) => $statusQuery->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('fields', fn (Builder $fieldQuery) => $fieldQuery->whereRaw('LOWER(name) LIKE ?', [$like]));
            })
            ->select('ticket_boards.*')
            ->selectRaw(
                '(
                    CASE WHEN LOWER(name) = ? THEN 55 ELSE 0 END +
                    CASE WHEN LOWER(name) LIKE ? THEN 26 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(slug, \'\')) LIKE ? THEN 20 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(description, \'\')) LIKE ? THEN 12 ELSE 0 END
                ) as search_relevance',
                [$termLower, $prefix, $prefix, $like]
            )
            ->withCount(['tickets', 'forms', 'catalogItems'])
            ->orderByDesc('search_relevance')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (TicketBoard $board) => [
                'type' => 'boards',
                'title' => $board->name,
                'subtitle' => trim(implode(' - ', array_filter([
                    $board->sector?->company?->name,
                    $board->sector?->name,
                ]))),
                'snippet' => $this->excerpt($board->description, $term),
                'url' => route('tickets.board.show', $board),
                'meta' => [
                    'status' => $board->is_active ? 'Ativo' : 'Inativo',
                    'tickets' => $board->tickets_count.' chamado(s)',
                    'forms' => $board->forms_count.' formulario(s)',
                ],
                'group_key' => 'boards',
            ]);
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function searchForms(User $user, array $filters, int $limit): Collection
    {
        $term = trim((string) $filters['query']);
        $termLower = mb_strtolower($term);
        $like = '%'.$termLower.'%';
        $prefix = $termLower.'%';

        return TicketForm::query()
            ->accessibleTo($user, $filters['sector_id'] ?? null)
            ->with(['board.sector.company'])
            ->where(function (Builder $query) use ($like) {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$like])
                    ->orWhereHas('board', fn (Builder $boardQuery) => $boardQuery->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('board.sector', fn (Builder $sectorQuery) => $sectorQuery->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('fields', fn (Builder $fieldQuery) => $fieldQuery->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('catalogItems', fn (Builder $catalogQuery) => $catalogQuery->whereRaw('LOWER(name) LIKE ?', [$like]));
            })
            ->select('ticket_forms.*')
            ->selectRaw(
                '(
                    CASE WHEN LOWER(name) = ? THEN 52 ELSE 0 END +
                    CASE WHEN LOWER(name) LIKE ? THEN 24 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(description, \'\')) LIKE ? THEN 14 ELSE 0 END
                ) as search_relevance',
                [$termLower, $prefix, $like]
            )
            ->withCount(['fields', 'catalogItems'])
            ->orderByDesc('search_relevance')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (TicketForm $form) => [
                'type' => 'forms',
                'title' => $form->name,
                'subtitle' => $form->board?->name.' - '.($form->board?->sector?->name ?? 'Sem setor'),
                'snippet' => $this->excerpt($form->description, $term),
                'url' => route('tickets.settings', $form->board),
                'meta' => [
                    'status' => $form->is_active ? 'Ativo' : 'Inativo',
                    'fields' => $form->fields_count.' campo(s)',
                    'catalog' => $form->catalog_items_count.' item(ns)',
                ],
                'group_key' => 'forms',
            ]);
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function searchCatalog(User $user, array $filters, int $limit): Collection
    {
        $term = trim((string) $filters['query']);
        $termLower = mb_strtolower($term);
        $like = '%'.$termLower.'%';
        $prefix = $termLower.'%';

        return ServiceCatalogItem::query()
            ->with(['board.sector.company', 'form'])
            ->when(! $user->isSuperAdmin(), fn (Builder $query) => $query->whereHas('form', fn (Builder $formQuery) => $formQuery->accessibleTo($user)))
            ->when($filters['sector_id'] ?? null, fn (Builder $query, int $sectorId) => $query->whereHas('board', fn (Builder $boardQuery) => $boardQuery->where('sector_id', $sectorId)))
            ->where(function (Builder $query) use ($like) {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$like])
                    ->orWhereHas('form', fn (Builder $formQuery) => $formQuery->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('board', fn (Builder $boardQuery) => $boardQuery->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('board.sector', fn (Builder $sectorQuery) => $sectorQuery->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('board.sector.company', fn (Builder $companyQuery) => $companyQuery->whereRaw('LOWER(name) LIKE ?', [$like]));
            })
            ->select('service_catalog_items.*')
            ->selectRaw(
                '(
                    CASE WHEN LOWER(name) = ? THEN 52 ELSE 0 END +
                    CASE WHEN LOWER(name) LIKE ? THEN 24 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(description, \'\')) LIKE ? THEN 14 ELSE 0 END
                ) as search_relevance',
                [$termLower, $prefix, $like]
            )
            ->orderByDesc('search_relevance')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (ServiceCatalogItem $catalogItem) => [
                'type' => 'catalog',
                'title' => $catalogItem->name,
                'subtitle' => trim(implode(' - ', array_filter([
                    $catalogItem->board?->sector?->name,
                    $catalogItem->form?->name,
                ]))),
                'snippet' => $this->excerpt($catalogItem->description, $term),
                'url' => route('tickets.create', $catalogItem),
                'meta' => [
                    'status' => $catalogItem->is_active ? 'Ativo' : 'Inativo',
                    'priority' => $catalogItem->default_priority?->label(),
                ],
                'group_key' => 'catalog',
            ]);
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function searchWorkflow(User $user, array $filters, int $limit): Collection
    {
        $groups = $this->searchTicketGroups($user, $filters, $limit);
        $statuses = $this->searchTicketStatuses($user, $filters, $limit);

        return $groups
            ->concat($statuses)
            ->sortByDesc(fn (array $item) => $item['relevance'] ?? 0)
            ->take($limit)
            ->map(function (array $item) {
                unset($item['relevance']);

                return $item;
            })
            ->values();
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function searchSlas(User $user, array $filters, int $limit): Collection
    {
        $term = trim((string) $filters['query']);
        $termLower = mb_strtolower($term);
        $like = '%'.$termLower.'%';
        $priorityValues = $this->matchingPriorityValues($termLower);
        $isGenericSlaTerm = str_contains($termLower, 'sla') || str_contains($termLower, 'prazo');
        $isNumeric = ctype_digit($term);

        return TicketSlaPolicy::query()
            ->with(['board.sector.company', 'targets'])
            ->whereHas('board', function (Builder $boardQuery) use ($user, $filters) {
                $boardQuery
                    ->when(! $user->isSuperAdmin(), fn (Builder $query) => $query->whereIn('id', $user->operationalBoardIds() ?: [0]))
                    ->when($filters['sector_id'] ?? null, fn (Builder $query, int $sectorId) => $query->where('sector_id', $sectorId));
            })
            ->where(function (Builder $query) use ($like, $priorityValues, $isGenericSlaTerm, $isNumeric, $term) {
                if ($isGenericSlaTerm) {
                    $query->whereRaw('1 = 1');

                    return;
                }

                $query
                    ->whereHas('board', fn (Builder $boardQuery) => $boardQuery->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('board.sector', fn (Builder $sectorQuery) => $sectorQuery->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('board.sector.company', fn (Builder $companyQuery) => $companyQuery->whereRaw('LOWER(name) LIKE ?', [$like]));

                if ($priorityValues !== [] || $isNumeric) {
                    $query->orWhereHas('targets', function (Builder $targetQuery) use ($priorityValues, $isNumeric, $term) {
                        if ($priorityValues !== []) {
                            $targetQuery->whereIn('priority', $priorityValues);
                        }

                        if ($isNumeric) {
                            $targetQuery
                                ->orWhere('first_response_minutes', (int) $term)
                                ->orWhere('resolution_minutes', (int) $term);
                        }
                    });
                }
            })
            ->orderByDesc('is_active')
            ->limit($limit)
            ->get()
            ->map(function (TicketSlaPolicy $policy) use ($term) {
                $targets = $policy->targets
                    ->map(fn ($target) => trim(implode(': ', array_filter([
                        $target->priority?->label(),
                        trim(($target->first_response_minutes ?? '-').'m resposta / '.($target->resolution_minutes ?? '-').'m resolucao'),
                    ]))))
                    ->implode(' | ');

                return [
                    'type' => 'slas',
                    'title' => 'SLA - '.($policy->board?->name ?? 'Quadro sem nome'),
                    'subtitle' => trim(implode(' - ', array_filter([
                        $policy->board?->sector?->company?->name,
                        $policy->board?->sector?->name,
                    ]))),
                    'snippet' => $this->excerpt($targets, $term),
                    'url' => route('tickets.settings', $policy->board),
                    'meta' => [
                        'status' => $policy->is_active ? 'Ativo' : 'Inativo',
                        'targets' => $policy->targets->count().' prioridade(s)',
                    ],
                    'group_key' => 'slas',
                ];
            });
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function searchLicenses(User $user, array $filters, int $limit): Collection
    {
        $term = trim((string) $filters['query']);
        $termLower = mb_strtolower($term);
        $like = '%'.$termLower.'%';
        $prefix = $termLower.'%';

        return License::query()
            ->with(['sector.company', 'activeAssignments.user'])
            ->when(! $user->isSuperAdmin(), fn (Builder $query) => $query->whereIn('sector_id', $user->operationalSectorIds() ?: [0]))
            ->when($filters['sector_id'] ?? null, fn (Builder $query, int $sectorId) => $query->where('sector_id', $sectorId))
            ->where(function (Builder $query) use ($like) {
                $query
                    ->whereRaw('LOWER(vendor_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(product_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(plan_name, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(license_reference, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(supplier_name, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(notes, \'\')) LIKE ?', [$like])
                    ->orWhereHas('sector', fn (Builder $sectorQuery) => $sectorQuery->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('assignments', function (Builder $assignmentQuery) use ($like) {
                        $assignmentQuery
                            ->whereRaw('LOWER(COALESCE(assigned_email, \'\')) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(COALESCE(display_name, \'\')) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(COALESCE(external_reference, \'\')) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(COALESCE(seat_label, \'\')) LIKE ?', [$like])
                            ->orWhereHas('user', function (Builder $assignedUserQuery) use ($like) {
                                $assignedUserQuery
                                    ->whereRaw('LOWER(name) LIKE ?', [$like])
                                    ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                            });
                    });
            })
            ->when(($filters['date_from'] ?? '') !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(($filters['date_to'] ?? '') !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->select('licenses.*')
            ->selectRaw(
                '(
                    CASE WHEN LOWER(product_name) = ? THEN 55 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(license_reference, \'\')) = ? THEN 50 ELSE 0 END +
                    CASE WHEN LOWER(vendor_name) LIKE ? THEN 24 ELSE 0 END +
                    CASE WHEN LOWER(product_name) LIKE ? THEN 24 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(plan_name, \'\')) LIKE ? THEN 18 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(license_reference, \'\')) LIKE ? THEN 18 ELSE 0 END
                ) as search_relevance',
                [$termLower, $termLower, $prefix, $prefix, $prefix, $prefix]
            )
            ->withCount('activeAssignments')
            ->orderByDesc('search_relevance')
            ->orderBy('vendor_name')
            ->orderBy('product_name')
            ->limit($limit)
            ->get()
            ->map(fn (License $license) => [
                'type' => 'licenses',
                'title' => $license->displayName(),
                'subtitle' => $license->license_reference ?: ($license->supplier_name ?: 'Licenca'),
                'snippet' => trim(implode(' - ', array_filter([
                    $license->sector?->name,
                    $license->notes,
                ]))) ?: 'Controle de licencas e atribuicoes.',
                'url' => route('licenses.show', $license),
                'meta' => [
                    'status' => $license->status?->label(),
                    'seats' => $license->active_assignments_count.'/'.$license->seats_total.' em uso',
                    'due' => $license->dueDate()?->format('d/m/Y'),
                ],
                'group_key' => 'licenses',
            ]);
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function searchSectorTemplates(User $user, array $filters, int $limit): Collection
    {
        if (! $user->isSuperAdmin()) {
            return collect();
        }

        $term = trim((string) $filters['query']);
        $termLower = mb_strtolower($term);
        $like = '%'.$termLower.'%';
        $prefix = $termLower.'%';

        return SectorTemplate::query()
            ->where(function (Builder $query) use ($like) {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(slug, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(form_name, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(form_description, \'\')) LIKE ?', [$like])
                    ->orWhereHas('fields', fn (Builder $fieldQuery) => $fieldQuery->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('catalogItems', fn (Builder $catalogQuery) => $catalogQuery->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('automationRules', function (Builder $automationQuery) use ($like) {
                        $automationQuery
                            ->whereRaw('LOWER(name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$like]);
                    });
            })
            ->when(($filters['date_from'] ?? '') !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(($filters['date_to'] ?? '') !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->select('sector_templates.*')
            ->selectRaw(
                '(
                    CASE WHEN LOWER(name) = ? THEN 52 ELSE 0 END +
                    CASE WHEN LOWER(name) LIKE ? THEN 24 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(form_name, \'\')) LIKE ? THEN 18 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(description, \'\')) LIKE ? THEN 12 ELSE 0 END
                ) as search_relevance',
                [$termLower, $prefix, $prefix, $like]
            )
            ->withCount(['sectors', 'fields', 'catalogItems', 'automationRules'])
            ->orderByDesc('search_relevance')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (SectorTemplate $template) => [
                'type' => 'templates',
                'title' => $template->name,
                'subtitle' => 'Template de setor - '.$template->defaultFormName(),
                'snippet' => $this->excerpt($template->description ?: $template->form_description, $term),
                'url' => route('sector-templates.edit', $template),
                'meta' => [
                    'status' => $template->is_active ? 'Ativo' : 'Inativo',
                    'fields' => $template->fields_count.' campo(s)',
                    'catalog' => $template->catalog_items_count.' catalogo(s)',
                    'usage' => $template->sectors_count.' setor(es)',
                ],
                'group_key' => 'templates',
            ]);
    }

    private function visibleTicketsQuery(User $user, ?int $sectorId = null): Builder
    {
        return Ticket::query()
            ->visibleTo($user)
            ->when($sectorId, fn (Builder $query, int $selectedSectorId) => $query->where('sector_id', $selectedSectorId));
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function searchTicketGroups(User $user, array $filters, int $limit): Collection
    {
        $term = trim((string) $filters['query']);
        $termLower = mb_strtolower($term);
        $like = '%'.$termLower.'%';
        $prefix = $termLower.'%';

        return TicketGroup::query()
            ->with('board.sector.company')
            ->whereHas('board', function (Builder $boardQuery) use ($user, $filters) {
                $boardQuery
                    ->when(! $user->isSuperAdmin(), fn (Builder $query) => $query->whereIn('id', $user->operationalBoardIds() ?: [0]))
                    ->when($filters['sector_id'] ?? null, fn (Builder $query, int $sectorId) => $query->where('sector_id', $sectorId));
            })
            ->where(function (Builder $query) use ($like) {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(slug, \'\')) LIKE ?', [$like])
                    ->orWhereHas('board', fn (Builder $boardQuery) => $boardQuery->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('board.sector', fn (Builder $sectorQuery) => $sectorQuery->whereRaw('LOWER(name) LIKE ?', [$like]));
            })
            ->select('ticket_groups.*')
            ->selectRaw(
                '(
                    CASE WHEN LOWER(name) = ? THEN 44 ELSE 0 END +
                    CASE WHEN LOWER(name) LIKE ? THEN 22 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(slug, \'\')) LIKE ? THEN 16 ELSE 0 END
                ) as search_relevance',
                [$termLower, $prefix, $prefix]
            )
            ->orderByDesc('search_relevance')
            ->orderBy('sort_order')
            ->limit($limit)
            ->get()
            ->map(fn (TicketGroup $group) => [
                'type' => 'workflow',
                'title' => 'Etapa: '.$group->name,
                'subtitle' => $group->board?->name.' - '.($group->board?->sector?->name ?? 'Sem setor'),
                'snippet' => $group->is_closed ? 'Etapa finalizadora do quadro.' : 'Etapa operacional do quadro.',
                'url' => route('tickets.settings', $group->board),
                'meta' => [
                    'status' => $group->is_active ? 'Ativa' : 'Inativa',
                    'kind' => $group->is_default ? 'Padrao' : null,
                ],
                'group_key' => 'workflow',
                'relevance' => (int) ($group->search_relevance ?? 0),
            ]);
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function searchTicketStatuses(User $user, array $filters, int $limit): Collection
    {
        $term = trim((string) $filters['query']);
        $termLower = mb_strtolower($term);
        $like = '%'.$termLower.'%';
        $prefix = $termLower.'%';

        return TicketStatus::query()
            ->with('board.sector.company')
            ->whereHas('board', function (Builder $boardQuery) use ($user, $filters) {
                $boardQuery
                    ->when(! $user->isSuperAdmin(), fn (Builder $query) => $query->whereIn('id', $user->operationalBoardIds() ?: [0]))
                    ->when($filters['sector_id'] ?? null, fn (Builder $query, int $sectorId) => $query->where('sector_id', $sectorId));
            })
            ->where(function (Builder $query) use ($like) {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(slug, \'\')) LIKE ?', [$like])
                    ->orWhereHas('board', fn (Builder $boardQuery) => $boardQuery->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('board.sector', fn (Builder $sectorQuery) => $sectorQuery->whereRaw('LOWER(name) LIKE ?', [$like]));
            })
            ->select('ticket_statuses.*')
            ->selectRaw(
                '(
                    CASE WHEN LOWER(name) = ? THEN 44 ELSE 0 END +
                    CASE WHEN LOWER(name) LIKE ? THEN 22 ELSE 0 END +
                    CASE WHEN LOWER(COALESCE(slug, \'\')) LIKE ? THEN 16 ELSE 0 END
                ) as search_relevance',
                [$termLower, $prefix, $prefix]
            )
            ->orderByDesc('search_relevance')
            ->orderBy('sort_order')
            ->limit($limit)
            ->get()
            ->map(fn (TicketStatus $status) => [
                'type' => 'workflow',
                'title' => 'Status: '.$status->name,
                'subtitle' => $status->board?->name.' - '.($status->board?->sector?->name ?? 'Sem setor'),
                'snippet' => $status->is_closed ? 'Status de encerramento.' : 'Status aberto do fluxo.',
                'url' => route('tickets.settings', $status->board),
                'meta' => [
                    'status' => $status->is_active ? 'Ativo' : 'Inativo',
                    'kind' => $status->is_default ? 'Padrao' : null,
                ],
                'group_key' => 'workflow',
                'relevance' => (int) ($status->search_relevance ?? 0),
            ]);
    }

    /**
     * @return array<int,string>
     */
    private function matchingPriorityValues(string $termLower): array
    {
        return collect(TicketPriority::cases())
            ->filter(function (TicketPriority $priority) use ($termLower) {
                $label = mb_strtolower($priority->label());

                return str_contains($priority->value, $termLower)
                    || str_contains($label, $termLower)
                    || str_contains($termLower, $priority->value)
                    || str_contains($termLower, $label);
            })
            ->map(fn (TicketPriority $priority) => $priority->value)
            ->values()
            ->all();
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
