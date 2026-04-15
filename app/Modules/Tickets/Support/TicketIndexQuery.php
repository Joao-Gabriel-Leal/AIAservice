<?php

namespace App\Modules\Tickets\Support;

use App\Models\User;
use App\Modules\Tickets\Models\TicketField;
use App\Modules\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TicketIndexQuery
{
    public function filters(?int $selectedSectorId = null): array
    {
        return [
            'sector_id' => $selectedSectorId,
            'title' => '',
            'group_id' => null,
            'requester' => '',
            'assignee' => '',
            'updated_from' => '',
            'updated_to' => '',
            'field_filters' => [],
        ];
    }

    public function filtersFromRequest(Request $request): array
    {
        return [
            'sector_id' => $request->integer('sector') ?: null,
            'title' => trim((string) $request->string('title')),
            'group_id' => $request->integer('group') ?: null,
            'requester' => trim((string) $request->string('requester')),
            'assignee' => trim((string) $request->string('assignee')),
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
        $query = Ticket::query()
            ->visibleTo($user)
            ->with(['sector.company', 'group', 'requester', 'assignee', 'rating', 'catalogItem', 'fieldValues.field.options'])
            ->when($filters['sector_id'], fn (Builder $query, int $sectorId) => $query->where('sector_id', $sectorId))
            ->when(($filters['title'] ?? '') !== '', fn (Builder $query) => $query->where('title', 'like', '%'.$filters['title'].'%'))
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
            ->when(($filters['updated_from'] ?? '') !== '', fn (Builder $query) => $query->whereDate('updated_at', '>=', $filters['updated_from']))
            ->when(($filters['updated_to'] ?? '') !== '', fn (Builder $query) => $query->whereDate('updated_at', '<=', $filters['updated_to']))
            ->latest('updated_at');

        return $this->applyDynamicFieldFilters($query, $filters['field_filters'] ?? []);
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
}
