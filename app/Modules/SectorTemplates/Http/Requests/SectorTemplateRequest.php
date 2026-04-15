<?php

namespace App\Modules\SectorTemplates\Http\Requests;

use App\Enums\TicketAutomationActionType;
use App\Enums\TicketAutomationConditionField;
use App\Enums\TicketAutomationConditionOperator;
use App\Enums\TicketAutomationTrigger;
use App\Enums\TicketFieldType;
use App\Enums\TicketPriority;
use App\Modules\SectorTemplates\Models\SectorTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SectorTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('sector_templates', 'name')->ignore($this->route('sector_template')?->id),
            ],
            'description' => ['nullable', 'string'],
            'form_name' => ['required', 'string', 'max:120'],
            'form_description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'fields' => ['nullable', 'array'],
            'fields.*.name' => ['required', 'string', 'max:120'],
            'fields.*.type' => ['required', Rule::in(array_column(TicketFieldType::cases(), 'value'))],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.help_text' => ['nullable', 'string'],
            'fields.*.options_text' => ['nullable', 'string'],
            'fields.*.is_required' => ['nullable', 'boolean'],
            'fields.*.show_on_board' => ['nullable', 'boolean'],
            'fields.*.is_active' => ['nullable', 'boolean'],
            'catalog_items' => ['nullable', 'array'],
            'catalog_items.*.name' => ['required', 'string', 'max:120'],
            'catalog_items.*.description' => ['nullable', 'string'],
            'catalog_items.*.default_ticket_group_slug' => ['nullable', Rule::in(array_keys(SectorTemplate::defaultGroupOptions()))],
            'catalog_items.*.default_priority' => ['required', Rule::in(array_column(TicketPriority::cases(), 'value'))],
            'catalog_items.*.is_active' => ['nullable', 'boolean'],
            'automation_rules' => ['nullable', 'array'],
            'automation_rules.*.name' => ['required', 'string', 'max:120'],
            'automation_rules.*.description' => ['nullable', 'string'],
            'automation_rules.*.trigger' => ['required', Rule::in(array_column(TicketAutomationTrigger::cases(), 'value'))],
            'automation_rules.*.cooldown_minutes' => ['nullable', 'integer', 'min:1'],
            'automation_rules.*.inactive_for_minutes' => ['nullable', 'integer', 'min:1'],
            'automation_rules.*.is_active' => ['nullable', 'boolean'],
            'automation_rules.*.conditions_json' => ['nullable', 'string'],
            'automation_rules.*.actions_json' => ['nullable', 'string'],
            'sla_is_active' => ['nullable', 'boolean'],
            'sla_targets' => ['nullable', 'array'],
            'sla_targets.*.first_response_minutes' => ['nullable', 'integer', 'min:1'],
            'sla_targets.*.resolution_minutes' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ($this->input('automation_rules', []) as $index => $rule) {
                $conditions = $this->decodeJsonArray($rule['conditions_json'] ?? '[]');
                $actions = $this->decodeJsonArray($rule['actions_json'] ?? '[]');

                if ($conditions === null) {
                    $validator->errors()->add("automation_rules.$index.conditions_json", 'Use um JSON valido para as condicoes.');
                }

                if ($actions === null) {
                    $validator->errors()->add("automation_rules.$index.actions_json", 'Use um JSON valido para as acoes.');
                }

                foreach ($conditions ?? [] as $conditionIndex => $condition) {
                    if (! in_array($condition['field'] ?? null, array_column(TicketAutomationConditionField::cases(), 'value'), true)) {
                        $validator->errors()->add("automation_rules.$index.conditions_json", "Condicao #".($conditionIndex + 1)." usa um campo invalido.");
                    }

                    if (! in_array($condition['operator'] ?? null, array_column(TicketAutomationConditionOperator::cases(), 'value'), true)) {
                        $validator->errors()->add("automation_rules.$index.conditions_json", "Condicao #".($conditionIndex + 1)." usa um operador invalido.");
                    }
                }

                foreach ($actions ?? [] as $actionIndex => $action) {
                    $actionType = $action['action'] ?? null;

                    if (! in_array($actionType, array_column(TicketAutomationActionType::cases(), 'value'), true)) {
                        $validator->errors()->add("automation_rules.$index.actions_json", "Acao #".($actionIndex + 1)." usa um tipo invalido.");
                        continue;
                    }

                    if ($actionType === TicketAutomationActionType::ASSIGN_FIXED_ASSIGNEE->value) {
                        $validator->errors()->add("automation_rules.$index.actions_json", 'Templates nao aceitam atribuicao fixa de usuario.');
                    }

                    if ($actionType === TicketAutomationActionType::CHANGE_GROUP->value
                        && ! in_array(data_get($action, 'payload.group_slug'), array_keys(SectorTemplate::defaultGroupOptions()), true)) {
                        $validator->errors()->add("automation_rules.$index.actions_json", "Acao #".($actionIndex + 1)." precisa informar payload.group_slug valido.");
                    }

                    if (in_array($actionType, [
                        TicketAutomationActionType::CHANGE_STATUS->value,
                        TicketAutomationActionType::REOPEN_TICKET->value,
                    ], true) && ! in_array(data_get($action, 'payload.status_slug'), array_keys(SectorTemplate::defaultStatusOptions()), true)) {
                        $validator->errors()->add("automation_rules.$index.actions_json", "Acao #".($actionIndex + 1)." precisa informar payload.status_slug valido.");
                    }
                }
            }
        });
    }

    private function decodeJsonArray(string $value): ?array
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
    }
}
