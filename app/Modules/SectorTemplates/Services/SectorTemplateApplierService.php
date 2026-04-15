<?php

namespace App\Modules\SectorTemplates\Services;

use App\Enums\TicketAutomationActionType;
use App\Enums\TicketAutomationConditionField;
use App\Modules\SectorTemplates\Models\SectorTemplate;
use App\Modules\Tickets\Models\ServiceCatalogItem;
use App\Modules\Tickets\Models\TicketAutomationRule;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Models\TicketField;
use App\Modules\Tickets\Models\TicketForm;
use App\Modules\Tickets\Services\TicketSlaService;

class SectorTemplateApplierService
{
    public function __construct(
        private readonly TicketSlaService $ticketSlaService,
    ) {
    }

    public function apply(SectorTemplate $template, TicketBoard $board): TicketBoard
    {
        $board->loadMissing(['groups', 'statuses', 'forms', 'fields', 'catalogItems', 'automationRules']);

        $form = $this->ensureForm($template, $board);
        $this->ensureFields($template, $board, $form);
        $this->ensureCatalogItems($template, $board, $form);
        $this->ensureAutomationRules($template, $board);
        $this->ensureSlaPolicy($template, $board);

        return $board->fresh(['groups', 'statuses', 'forms.fields.options', 'catalogItems', 'automationRules.conditions', 'automationRules.actions', 'slaPolicy.targets']);
    }

    private function ensureForm(SectorTemplate $template, TicketBoard $board): TicketForm
    {
        return TicketForm::query()->firstOrCreate(
            [
                'ticket_board_id' => $board->id,
                'name' => $template->defaultFormName(),
            ],
            [
                'description' => $template->defaultFormDescription(),
                'is_default' => true,
                'is_active' => true,
            ],
        );
    }

    private function ensureFields(SectorTemplate $template, TicketBoard $board, TicketForm $form): void
    {
        foreach ($template->fields as $index => $templateField) {
            $field = TicketField::query()->firstOrCreate(
                [
                    'ticket_board_id' => $board->id,
                    'slug' => $templateField->slug,
                ],
                [
                    'name' => $templateField->name,
                    'type' => $templateField->type,
                    'placeholder' => $templateField->placeholder,
                    'help_text' => $templateField->help_text,
                    'settings' => null,
                    'sort_order' => $index + 1,
                    'is_required' => $templateField->is_required,
                    'show_on_board' => $templateField->show_on_board,
                    'is_active' => $templateField->is_active,
                ],
            );

            foreach ($templateField->options ?? [] as $option) {
                $field->options()->firstOrCreate(
                    ['value' => $option['value']],
                    [
                        'label' => $option['label'],
                        'sort_order' => $option['sort_order'] ?? 0,
                        'is_default' => false,
                    ],
                );
            }

            $form->fields()->syncWithoutDetaching([
                $field->id => [
                    'is_required' => $templateField->is_required,
                    'sort_order' => $index + 1,
                ],
            ]);
        }
    }

    private function ensureCatalogItems(SectorTemplate $template, TicketBoard $board, TicketForm $form): void
    {
        foreach ($template->catalogItems as $templateItem) {
            ServiceCatalogItem::query()->firstOrCreate(
                [
                    'ticket_board_id' => $board->id,
                    'name' => $templateItem->name,
                ],
                [
                    'ticket_form_id' => $form->id,
                    'description' => $templateItem->description,
                    'default_ticket_group_id' => $this->groupIdFromSlug($board, $templateItem->default_ticket_group_slug),
                    'default_priority' => $templateItem->default_priority,
                    'is_active' => $templateItem->is_active,
                ],
            );
        }
    }

    private function ensureAutomationRules(SectorTemplate $template, TicketBoard $board): void
    {
        foreach ($template->automationRules as $templateRule) {
            $rule = TicketAutomationRule::query()->firstOrCreate(
                [
                    'ticket_board_id' => $board->id,
                    'name' => $templateRule->name,
                ],
                [
                    'description' => $templateRule->description,
                    'trigger' => $templateRule->trigger,
                    'run_mode' => $templateRule->run_mode,
                    'trigger_settings' => $templateRule->trigger_settings,
                    'cooldown_minutes' => $templateRule->cooldown_minutes,
                    'sort_order' => $templateRule->sort_order,
                    'is_active' => $templateRule->is_active,
                ],
            );

            foreach ($templateRule->conditions as $condition) {
                $rule->conditions()->firstOrCreate(
                    [
                        'field' => $condition->field,
                        'operator' => $condition->operator,
                        'sort_order' => $condition->sort_order,
                    ],
                    [
                        'value' => $this->mapConditionValue($condition->field->value, $condition->value ?? [], $board),
                    ],
                );
            }

            foreach ($templateRule->actions as $action) {
                $rule->actions()->firstOrCreate(
                    [
                        'action' => $action->action,
                        'sort_order' => $action->sort_order,
                    ],
                    [
                        'payload' => $this->mapActionPayload($action->action->value, $action->payload ?? [], $board),
                    ],
                );
            }
        }
    }

    private function ensureSlaPolicy(SectorTemplate $template, TicketBoard $board): void
    {
        if (! $template->slaPolicy) {
            $this->ticketSlaService->ensurePolicy($board);

            return;
        }

        $targets = [];

        foreach ($template->slaPolicy->targets as $target) {
            $targets[$target->priority->value] = [
                'first_response_minutes' => $target->first_response_minutes,
                'resolution_minutes' => $target->resolution_minutes,
            ];
        }

        $this->ticketSlaService->syncPolicy($board, $targets, $template->slaPolicy->is_active);
    }

    private function mapConditionValue(string $field, mixed $value, TicketBoard $board): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return match ($field) {
            TicketAutomationConditionField::GROUP_ID->value => collect($value)->map(fn ($slug) => $this->groupIdFromSlug($board, (string) $slug))->filter()->values()->all(),
            TicketAutomationConditionField::STATUS_ID->value => collect($value)->map(fn ($slug) => $this->statusIdFromSlug($board, (string) $slug))->filter()->values()->all(),
            default => $value,
        };
    }

    private function mapActionPayload(string $action, array $payload, TicketBoard $board): array
    {
        return match ($action) {
            TicketAutomationActionType::CHANGE_GROUP->value => [
                'group_id' => $this->groupIdFromSlug($board, (string) ($payload['group_slug'] ?? '')),
            ],
            TicketAutomationActionType::CHANGE_STATUS->value => [
                'status_id' => $this->statusIdFromSlug($board, (string) ($payload['status_slug'] ?? '')),
            ],
            TicketAutomationActionType::REOPEN_TICKET->value => [
                'target_status_id' => $this->statusIdFromSlug($board, (string) ($payload['status_slug'] ?? '')),
                'message' => $payload['message'] ?? null,
            ],
            default => $payload,
        };
    }

    private function groupIdFromSlug(TicketBoard $board, ?string $slug): ?int
    {
        if (! $slug) {
            return $board->defaultGroup()?->id;
        }

        return $board->groups->firstWhere('slug', $slug)?->id;
    }

    private function statusIdFromSlug(TicketBoard $board, ?string $slug): ?int
    {
        if (! $slug) {
            return null;
        }

        return $board->statuses->firstWhere('slug', $slug)?->id;
    }
}
