<?php

namespace App\Modules\Tickets\Services;

use App\Enums\TicketAutomationActionType;
use App\Enums\TicketAutomationConditionField;
use App\Modules\Tickets\Models\TicketAutomationRule;
use App\Modules\Tickets\Models\TicketBoard;
use Illuminate\Support\Collection;

class LegacyTicketAutomationMigrationService
{
    public function migrateAllBoards(): void
    {
        TicketBoard::query()
            ->with([
                'groups' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'statuses' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'automationRules.conditions',
                'automationRules.actions',
            ])
            ->chunkById(50, function ($boards): void {
                foreach ($boards as $board) {
                    $this->migrateBoard($board);
                }
            });
    }

    public function migrateBoard(TicketBoard $board): void
    {
        $board->loadMissing([
            'groups' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'statuses' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'automationRules.conditions',
            'automationRules.actions',
        ]);

        $mapping = $this->statusToGroupMapping($board);

        foreach ($board->automationRules as $rule) {
            $this->migrateRule($rule, $mapping, $board->groups);
        }
    }

    public function migrateRule(TicketAutomationRule $rule, Collection $mapping, Collection $groups): void
    {
        $hasLegacyStatusLogic = $rule->conditions->contains(fn ($condition) => $condition->field === TicketAutomationConditionField::STATUS_ID)
            || $rule->actions->contains(fn ($action) => in_array($action->action, [
                TicketAutomationActionType::CHANGE_STATUS,
                TicketAutomationActionType::REOPEN_TICKET,
            ], true));

        if (! $hasLegacyStatusLogic) {
            return;
        }

        $convertedConditions = [];
        foreach ($rule->conditions as $condition) {
            if ($condition->field !== TicketAutomationConditionField::STATUS_ID) {
                $convertedConditions[] = $condition->only(['field', 'operator', 'value', 'sort_order']);
                continue;
            }

            $groupIds = collect(is_array($condition->value) ? $condition->value : [$condition->value])
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->map(fn ($statusId) => $mapping->get((int) $statusId))
                ->filter()
                ->unique()
                ->values();

            if ($groupIds->isEmpty()) {
                $this->markRuleForReview($rule, 'Regra desativada: nao foi possivel converter condicoes de status para etapas.');

                return;
            }

            $convertedConditions[] = [
                'field' => TicketAutomationConditionField::GROUP_ID->value,
                'operator' => $condition->operator->value,
                'value' => $groupIds->all(),
                'sort_order' => $condition->sort_order,
            ];
        }

        $convertedActions = [];
        foreach ($rule->actions as $action) {
            if ($action->action === TicketAutomationActionType::CHANGE_STATUS) {
                $groupId = $mapping->get((int) data_get($action->payload, 'status_id'));

                if (! $groupId) {
                    $this->markRuleForReview($rule, 'Regra desativada: nao foi possivel converter a acao de status para etapa.');

                    return;
                }

                $convertedActions[] = [
                    'action' => TicketAutomationActionType::CHANGE_GROUP->value,
                    'payload' => ['group_id' => $groupId],
                    'sort_order' => $action->sort_order,
                ];

                continue;
            }

            if ($action->action === TicketAutomationActionType::REOPEN_TICKET) {
                $groupId = $mapping->get((int) data_get($action->payload, 'target_status_id'));

                if (! $groupId || ! $groups->contains('id', $groupId)) {
                    $this->markRuleForReview($rule, 'Regra desativada: nao foi possivel converter a reabertura baseada em status para etapa.');

                    return;
                }

                $convertedActions[] = [
                    'action' => TicketAutomationActionType::CHANGE_GROUP->value,
                    'payload' => ['group_id' => $groupId],
                    'sort_order' => $action->sort_order,
                ];

                $message = trim((string) data_get($action->payload, 'message'));
                if ($message !== '') {
                    $convertedActions[] = [
                        'action' => TicketAutomationActionType::ADD_SYSTEM_MESSAGE->value,
                        'payload' => ['message' => $message],
                        'sort_order' => $action->sort_order + 1,
                    ];
                }

                continue;
            }

            $convertedActions[] = $action->only(['action', 'payload', 'sort_order']);
        }

        $rule->conditions()->delete();
        $rule->actions()->delete();
        $rule->conditions()->createMany(collect($convertedConditions)->sortBy('sort_order')->values()->all());
        $rule->actions()->createMany(
            collect($convertedActions)
                ->sortBy('sort_order')
                ->values()
                ->map(fn (array $action, int $index) => [
                    ...$action,
                    'sort_order' => $index + 1,
                ])
                ->all()
        );
    }

    private function statusToGroupMapping(TicketBoard $board): Collection
    {
        $groupsBySort = $board->groups
            ->groupBy('sort_order')
            ->map(fn (Collection $groups) => $groups->count() === 1 ? $groups->first()->id : null);

        return $board->statuses
            ->mapWithKeys(function ($status) use ($groupsBySort) {
                $groupId = $groupsBySort->get($status->sort_order);

                return [$status->id => $groupId];
            });
    }

    private function markRuleForReview(TicketAutomationRule $rule, string $message): void
    {
        $description = trim(implode(' ', array_filter([
            $rule->description,
            $message,
        ])));

        $rule->forceFill([
            'description' => $description,
            'is_active' => false,
        ])->save();
    }
}
