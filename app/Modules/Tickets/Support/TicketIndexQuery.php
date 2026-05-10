<?php

namespace App\Modules\Tickets\Support;

use App\Enums\TicketPriority;
use App\Models\User;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TicketIndexQuery
{
    public function filters(?int $selectedSectorId = null): array
    {
        return [
            'sector_id' => $selectedSectorId,
            'board_id' => null,
            'title' => '',
            'group_id' => null,
            'requester' => '',
            'assignee' => '',
            'assignee_state' => 'all',
            'priority' => '',
            'sla_state' => 'all',
            'updated_from' => '',
            'updated_to' => '',
            'field_filters' => [],
        ];
    }

    public function filtersFromRequest(Request $request): array
    {
        return [
            'sector_id' => $request->integer('sector') ?: null,
            'board_id' => $request->integer('board') ?: null,
            'title' => trim((string) $request->string('title')),
            'group_id' => $request->integer('group') ?: null,
            'requester' => trim((string) $request->string('requester')),
            'assignee' => trim((string) $request->string('assignee')),
            'assignee_state' => trim((string) $request->string('assignee_state', 'all')),
            'priority' => trim((string) $request->string('priority')),
            'sla_state' => trim((string) $request->string('sla', 'all')),
            'updated_from' => trim((string) $request->string('updated_from')),
            'updated_to' => trim((string) $request->string('updated_to')),
            'field_filters' => collect($request->input('field_filters', []))
                ->mapWithKeys(fn ($value, $fieldId) => [(int) $fieldId => is_string($value) ? trim($value) : $value])
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->all(),
        ];
    }

    public function build(User $user, array $filters): Builder
    {
        $includeSubelements = $this->shouldIncludeSubelements($filters);

        $query = Ticket::query()
            ->visibleTo($user)
            ->with(['sector.company', 'group', 'requester', 'assignee', 'rating', 'catalogItem', 'parentTicket', 'fieldValues.field.options'])
            ->withCount([
                'incidentChildren',
                'subTickets',
                'subTickets as open_sub_tickets_count' => fn (Builder $query) => $query->open(),
            ])
            ->when(! $includeSubelements, fn (Builder $query) => $query->topLevel())
            ->when($filters['sector_id'], fn (Builder $query, int $sectorId) => $query->where('sector_id', $sectorId))
            ->when($filters['board_id'] ?? null, fn (Builder $query, int $boardId) => $query->where('ticket_board_id', $boardId))
            ->when(($filters['title'] ?? '') !== '', fn (Builder $query) => $this->applyTicketReferenceOrTitleFilter($query, (string) $filters['title']))
            ->when($filters['group_id'] ?? null, fn (Builder $query, int $groupId) => $query->where('ticket_group_id', $groupId))
            ->when(($filters['requester'] ?? '') !== '', function (Builder $query) use ($filters) {
                $query->whereHas('requester', function (Builder $requesterQuery) use ($filters) {
                    $requesterQuery
                        ->where('name', 'like', '%'.$filters['requester'].'%')
                        ->orWhere('email', 'like', '%'.$filters['requester'].'%');
                });
            })
            ->when(($filters['assignee'] ?? '') !== '', function (Builder $query) use ($filters) {
                $query->whereHas('assignee', function (Builder $assigneeQuery) use ($filters) {
                    $assigneeQuery
                        ->where('name', 'like', '%'.$filters['assignee'].'%')
                        ->orWhere('email', 'like', '%'.$filters['assignee'].'%');
                });
            })
            ->when(($filters['assignee_state'] ?? 'all') !== 'all', function (Builder $query) use ($filters, $user) {
                match ($filters['assignee_state']) {
                    'me' => $query->where('assignee_id', $user->id),
                    'unassigned' => $query->whereNull('assignee_id'),
                    'assigned' => $query->whereNotNull('assignee_id'),
                    default => null,
                };
            })
            ->when(($filters['priority'] ?? '') !== '', function (Builder $query) use ($filters) {
                $priority = (string) $filters['priority'];

                if ($priority === 'high_or_urgent') {
                    $query->whereIn('priority', [TicketPriority::HIGH->value, TicketPriority::URGENT->value]);

                    return;
                }

                $allowed = collect(TicketPriority::cases())->map(fn (TicketPriority $case) => $case->value)->all();

                if (in_array($priority, $allowed, true)) {
                    $query->where('priority', $priority);
                }
            })
            ->when(($filters['updated_from'] ?? '') !== '', fn (Builder $query) => $query->whereDate('updated_at', '>=', $filters['updated_from']))
            ->when(($filters['updated_to'] ?? '') !== '', fn (Builder $query) => $query->whereDate('updated_at', '<=', $filters['updated_to']))
            ->latest('updated_at');

        $this->applySlaStateFilter($query, $filters['sla_state'] ?? 'all');

        return $this->applyDynamicFieldFilters($query, $filters['field_filters'] ?? []);
    }

    private function shouldIncludeSubelements(array $filters): bool
    {
        return in_array($filters['assignee_state'] ?? 'all', ['me', 'assigned'], true)
            || trim((string) ($filters['assignee'] ?? '')) !== '';
    }

    public function applySlaStateFilter(Builder $query, string $state): Builder
    {
        return match ($state) {
            'warning' => $this->applyWarningSlaFilter($query),
            'breached' => $this->applyBreachedSlaFilter($query),
            'critical' => $query->where(function (Builder $slaQuery): void {
                $this->applyWarningSlaFilter($slaQuery);
                $slaQuery->orWhere(function (Builder $breachedQuery): void {
                    $this->applyBreachedSlaFilter($breachedQuery);
                });
            }),
            'ok' => $query->where(function (Builder $okQuery): void {
                $this->applyOpenTicketFilter($okQuery)
                    ->where(function (Builder $safeQuery): void {
                        $safeQuery
                            ->where(function (Builder $firstResponseQuery): void {
                                $firstResponseQuery
                                    ->whereNull('first_response_due_at')
                                    ->orWhereNotNull('first_responded_at')
                                    ->orWhere('first_response_due_at', '>', now()->addMinutes(30));
                            })
                            ->where(function (Builder $resolutionQuery): void {
                                $resolutionQuery
                                    ->whereNull('resolution_due_at')
                                    ->orWhereNotNull('resolved_at')
                                    ->orWhere('resolution_due_at', '>', now()->addMinutes(30));
                            })
                            ->whereNull('first_response_breached_at')
                            ->whereNull('resolution_breached_at');
                    });
            }),
            default => $query,
        };
    }

    private function applyWarningSlaFilter(Builder $query): Builder
    {
        $now = now();
        $warningUntil = now()->addMinutes(30);

        return $this->applyOpenTicketFilter($query)
            ->where(function (Builder $slaQuery) use ($now, $warningUntil): void {
                $slaQuery
                    ->where(function (Builder $firstResponseQuery) use ($now, $warningUntil): void {
                        $firstResponseQuery
                            ->whereNull('first_responded_at')
                            ->whereNull('first_response_breached_at')
                            ->whereBetween('first_response_due_at', [$now, $warningUntil]);
                    })
                    ->orWhere(function (Builder $resolutionQuery) use ($now, $warningUntil): void {
                        $resolutionQuery
                            ->whereNull('resolved_at')
                            ->whereNull('resolution_breached_at')
                            ->whereBetween('resolution_due_at', [$now, $warningUntil]);
                    });
            });
    }

    private function applyBreachedSlaFilter(Builder $query): Builder
    {
        $now = now();

        return $query->where(function (Builder $slaQuery) use ($now): void {
            $slaQuery
                ->whereNotNull('first_response_breached_at')
                ->orWhereNotNull('resolution_breached_at')
                ->orWhere(function (Builder $firstResponseQuery) use ($now): void {
                    $firstResponseQuery
                        ->whereNull('first_responded_at')
                        ->whereNotNull('first_response_due_at')
                        ->where('first_response_due_at', '<', $now);
                })
                ->orWhere(function (Builder $resolutionQuery) use ($now): void {
                    $resolutionQuery
                        ->whereNull('resolved_at')
                        ->whereNotNull('resolution_due_at')
                        ->where('resolution_due_at', '<', $now);
                });
        });
    }

    private function applyOpenTicketFilter(Builder $query): Builder
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

    private function applyDynamicFieldFilters(Builder $query, array $fieldFilters): Builder
    {
        if ($fieldFilters === []) {
            return $query;
        }

        $fields = TicketField::query()
            ->whereIn('id', array_keys($fieldFilters))
            ->get()
            ->keyBy('id');

        foreach ($fieldFilters as $fieldId => $value) {
            /** @var TicketField|null $field */
            $field = $fields->get((int) $fieldId);

            if (! $field || $value === null || $value === '') {
                continue;
            }

            $query->whereHas('fieldValues', function (Builder $fieldValueQuery) use ($field, $value) {
                $fieldValueQuery->where('ticket_field_id', $field->id);

                match ($field->type->value) {
                    'select', 'status', 'user', 'date' => $fieldValueQuery->where('value->value', (string) $value),
                    'checkbox' => $fieldValueQuery->where('value->value', in_array($value, ['1', 1, true, 'true'], true)),
                    'number' => $fieldValueQuery->where('value->value', is_numeric($value) ? (float) $value : $value),
                    default => $fieldValueQuery->where('value->value', 'like', '%'.(string) $value.'%'),
                };
            });
        }

        return $query;
    }

    public function applyTicketReferenceOrTitleFilter(Builder $query, string $term): Builder
    {
        $term = trim($term);
        $normalizedReference = TicketReferenceCode::normalizeLookup($term);

        return $query->where(function (Builder $searchQuery) use ($term, $normalizedReference): void {
            $searchQuery->where('title', 'like', '%'.$term.'%');

            if ($normalizedReference !== '') {
                $searchQuery->orWhere('reference_lookup', 'like', $normalizedReference.'%');
            }

            if (ctype_digit($term)) {
                $searchQuery->orWhere('id', (int) $term);
            }
        });
    }
}
