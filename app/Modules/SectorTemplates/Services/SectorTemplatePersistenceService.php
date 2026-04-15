<?php

namespace App\Modules\SectorTemplates\Services;

use App\Modules\SectorTemplates\Models\SectorTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SectorTemplatePersistenceService
{
    public function save(?SectorTemplate $template, array $payload): SectorTemplate
    {
        return DB::transaction(function () use ($template, $payload): SectorTemplate {
            $template ??= new SectorTemplate();

            $template->fill([
                'name' => $payload['name'],
                'slug' => Str::slug($payload['name']),
                'description' => $payload['description'] ?: null,
                'form_name' => $payload['form_name'],
                'form_description' => $payload['form_description'] ?: null,
                'is_active' => (bool) ($payload['is_active'] ?? false),
            ])->save();

            $template->fields()->delete();
            $template->catalogItems()->delete();
            $template->automationRules()->delete();

            if ($template->slaPolicy) {
                $template->slaPolicy->targets()->delete();
                $template->slaPolicy->delete();
            }

            foreach (array_values($payload['fields'] ?? []) as $index => $field) {
                $template->fields()->create([
                    'name' => $field['name'],
                    'slug' => Str::slug($field['name']),
                    'type' => $field['type'],
                    'placeholder' => $field['placeholder'] ?: null,
                    'help_text' => $field['help_text'] ?: null,
                    'options' => $this->parseOptions($field['options_text'] ?? ''),
                    'sort_order' => $index + 1,
                    'is_required' => (bool) ($field['is_required'] ?? false),
                    'show_on_board' => (bool) ($field['show_on_board'] ?? false),
                    'is_active' => array_key_exists('is_active', $field) ? (bool) $field['is_active'] : true,
                ]);
            }

            foreach (array_values($payload['catalog_items'] ?? []) as $index => $catalogItem) {
                $template->catalogItems()->create([
                    'name' => $catalogItem['name'],
                    'description' => $catalogItem['description'] ?: null,
                    'default_ticket_group_slug' => $catalogItem['default_ticket_group_slug'] ?: null,
                    'default_priority' => $catalogItem['default_priority'],
                    'sort_order' => $index + 1,
                    'is_active' => array_key_exists('is_active', $catalogItem) ? (bool) $catalogItem['is_active'] : true,
                ]);
            }

            foreach (array_values($payload['automation_rules'] ?? []) as $index => $ruleData) {
                $triggerSettings = [];

                if (($ruleData['trigger'] ?? null) === 'ticket_inactive' && ! empty($ruleData['inactive_for_minutes'])) {
                    $triggerSettings['inactive_for_minutes'] = (int) $ruleData['inactive_for_minutes'];
                }

                $rule = $template->automationRules()->create([
                    'name' => $ruleData['name'],
                    'description' => $ruleData['description'] ?: null,
                    'trigger' => $ruleData['trigger'],
                    'run_mode' => ($ruleData['trigger'] ?? null) === 'ticket_inactive' ? 'scheduled' : 'sync',
                    'trigger_settings' => $triggerSettings ?: null,
                    'cooldown_minutes' => $ruleData['cooldown_minutes'] ?: null,
                    'sort_order' => $index + 1,
                    'is_active' => array_key_exists('is_active', $ruleData) ? (bool) $ruleData['is_active'] : true,
                ]);

                foreach ($this->decodeJsonArray($ruleData['conditions_json'] ?? '[]') as $conditionIndex => $condition) {
                    $rule->conditions()->create([
                        'field' => $condition['field'],
                        'operator' => $condition['operator'],
                        'value' => $condition['value'] ?? null,
                        'sort_order' => $conditionIndex + 1,
                    ]);
                }

                foreach ($this->decodeJsonArray($ruleData['actions_json'] ?? '[]') as $actionIndex => $action) {
                    $rule->actions()->create([
                        'action' => $action['action'],
                        'payload' => $action['payload'] ?? null,
                        'sort_order' => $actionIndex + 1,
                    ]);
                }
            }

            $policy = $template->slaPolicy()->create([
                'is_active' => (bool) ($payload['sla_is_active'] ?? false),
            ]);

            foreach ($payload['sla_targets'] ?? [] as $priority => $target) {
                $policy->targets()->create([
                    'priority' => $priority,
                    'first_response_minutes' => $target['first_response_minutes'] ?: null,
                    'resolution_minutes' => $target['resolution_minutes'] ?: null,
                ]);
            }

            return $template->fresh(['fields', 'catalogItems', 'automationRules.conditions', 'automationRules.actions', 'slaPolicy.targets']);
        });
    }

    private function parseOptions(string $optionsText): ?array
    {
        $lines = collect(preg_split('/\r\n|\r|\n/', $optionsText) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values();

        if ($lines->isEmpty()) {
            return null;
        }

        return $lines->map(function (string $line, int $index): array {
            [$label, $value] = array_pad(explode('|', $line, 2), 2, null);

            return [
                'label' => trim($label),
                'value' => trim((string) ($value ?? $label)),
                'sort_order' => $index + 1,
            ];
        })->all();
    }

    private function decodeJsonArray(string $value): array
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : [];
    }
}
