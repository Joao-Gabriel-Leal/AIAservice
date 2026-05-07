@php
    $fieldRows = old('fields', $template->exists
        ? $template->fields->map(fn ($field) => [
            'name' => $field->name,
            'type' => $field->type->value,
            'placeholder' => $field->placeholder,
            'help_text' => $field->help_text,
            'options_text' => collect($field->options ?? [])->map(fn ($option) => ($option['label'] ?? '').'|'.($option['value'] ?? ''))->implode("\n"),
            'is_required' => $field->is_required,
            'show_on_board' => $field->show_on_board,
            'is_active' => $field->is_active,
        ])->all()
        : [[
            'name' => '',
            'type' => 'text',
            'placeholder' => '',
            'help_text' => '',
            'options_text' => '',
            'is_required' => false,
            'show_on_board' => true,
            'is_active' => true,
        ]]);

    $catalogRows = old('catalog_items', $template->exists
        ? $template->catalogItems->map(fn ($item) => [
            'name' => $item->name,
            'description' => $item->description,
            'default_ticket_group_slug' => $item->default_ticket_group_slug,
            'default_priority' => $item->default_priority->value,
            'is_active' => $item->is_active,
        ])->all()
        : [[
            'name' => 'Solicitacao geral',
            'description' => 'Catalogo padrao para demandas do setor.',
            'default_ticket_group_slug' => 'aberto',
            'default_priority' => 'medium',
            'is_active' => true,
        ]]);

    $automationRows = old('automation_rules', $template->exists
        ? $template->automationRules->map(fn ($rule) => [
            'name' => $rule->name,
            'description' => $rule->description,
            'trigger' => $rule->trigger->value,
            'cooldown_minutes' => $rule->cooldown_minutes,
            'inactive_for_minutes' => data_get($rule->trigger_settings, 'inactive_for_minutes'),
            'is_active' => $rule->is_active,
            'conditions_json' => json_encode($rule->conditions->map(fn ($condition) => [
                'field' => $condition->field->value,
                'operator' => $condition->operator->value,
                'value' => $condition->value,
            ])->values()->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'actions_json' => json_encode($rule->actions->map(fn ($action) => [
                'action' => $action->action->value,
                'payload' => $action->payload,
            ])->values()->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ])->all()
        : [[
            'name' => '',
            'description' => '',
            'trigger' => 'ticket_created',
            'cooldown_minutes' => '',
            'inactive_for_minutes' => '',
            'is_active' => true,
            'conditions_json' => "[]",
            'actions_json' => "[]",
        ]]);

    $slaTargets = old('sla_targets', collect($priorities)->mapWithKeys(function ($priority) use ($template) {
        $target = $template->slaPolicy?->targets?->firstWhere('priority', $priority);

        return [
            $priority->value => [
                'first_response_minutes' => $target?->first_response_minutes,
                'resolution_minutes' => $target?->resolution_minutes,
            ],
        ];
    })->all());
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="ui-panel space-y-4 rounded-3xl p-6">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Dados principais</h2>
                <p class="mt-1 text-sm text-slate-500">Nome, descricao e o formulario base desse template.</p>
            </div>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Nome do template</span>
                <input type="text" name="name" value="{{ old('name', $template->name) }}" class="ui-input w-full" required>
                @error('name') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Descricao</span>
                <textarea name="description" rows="3" class="ui-textarea w-full">{{ old('description', $template->description) }}</textarea>
                @error('description') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Nome do formulario base</span>
                <input type="text" name="form_name" value="{{ old('form_name', $template->form_name ?: 'Abertura padrao') }}" class="ui-input w-full" required>
                @error('form_name') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Descricao do formulario</span>
                <textarea name="form_description" rows="3" class="ui-textarea w-full">{{ old('form_description', $template->form_description) }}</textarea>
                @error('form_description') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active ?? true)) class="size-4 rounded border-slate-300">
                <span class="text-sm text-slate-700">Template ativo</span>
            </label>
        </div>

        <div class="ui-panel space-y-4 rounded-3xl p-6">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Montagem guiada</h2>
                <p class="mt-1 text-sm text-slate-500">Preencha o essencial primeiro. Campos, catalogo, SLA e automacoes ficam separados para nao misturar configuracoes.</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-900">1. Dados</p>
                    <p class="mt-1 text-sm text-slate-500">Nome do template e formulario base.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-900">2. Campos</p>
                    <p class="mt-1 text-sm text-slate-500">Perguntas que vao aparecer na abertura.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-900">3. Catalogo</p>
                    <p class="mt-1 text-sm text-slate-500">Tipos de chamado que o setor publica.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-900">4. SLA</p>
                    <p class="mt-1 text-sm text-slate-500">Metas por prioridade.</p>
                </div>
            </div>

            <details class="rounded-2xl border border-slate-200 bg-white p-4 text-xs text-slate-600">
                <summary class="cursor-pointer text-sm font-semibold text-slate-800">Modo avancado de automacoes</summary>
                <div class="mt-4 space-y-2">
                    <p><strong>Condicao</strong>: <code>[{"field":"priority","operator":"in","value":["high","urgent"]}]</code></p>
                    <p><strong>Acao etapa</strong>: <code>[{"action":"change_group","payload":{"group_slug":"em-andamento"}}]</code></p>
                    <p><strong>Acao status</strong>: <code>[{"action":"change_status","payload":{"status_slug":"em-atendimento"}}]</code></p>
                    <p><strong>Reabrir</strong>: <code>[{"action":"reopen_ticket","payload":{"status_slug":"novo","message":"Chamado reaberto automaticamente."}}]</code></p>
                    <p class="pt-2"><strong>Etapas</strong>: {{ implode(', ', array_keys($groupOptions)) }}</p>
                    <p><strong>Status</strong>: {{ implode(', ', array_keys($statusOptions)) }}</p>
                </div>
            </details>
        </div>
    </div>

    <div class="ui-panel rounded-3xl p-6">
        <div class="mb-4 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Campos do formulario</h2>
                <p class="mt-1 text-sm text-slate-500">Os campos abaixo serao copiados para o formulario base do setor.</p>
            </div>
            <button type="button" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm" data-add-row="fields">Adicionar campo</button>
        </div>

        <div class="space-y-4" data-repeater="fields">
            @foreach ($fieldRows as $index => $field)
                <div class="rounded-3xl border border-slate-200 p-4" data-row>
                    <div class="grid gap-4 lg:grid-cols-3">
                        <input type="text" name="fields[{{ $index }}][name]" value="{{ $field['name'] ?? '' }}" class="ui-input w-full" placeholder="Nome do campo">
                        <select name="fields[{{ $index }}][type]" class="ui-native-select w-full text-sm text-slate-700">
                            @foreach ($fieldTypes as $type)
                                <option value="{{ $type->value }}" @selected(($field['type'] ?? 'text') === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="fields[{{ $index }}][placeholder]" value="{{ $field['placeholder'] ?? '' }}" class="ui-input w-full" placeholder="Placeholder">
                    </div>
                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <textarea name="fields[{{ $index }}][help_text]" rows="2" class="ui-textarea w-full" placeholder="Texto de ajuda">{{ $field['help_text'] ?? '' }}</textarea>
                        <textarea name="fields[{{ $index }}][options_text]" rows="2" class="ui-textarea w-full" placeholder="Opcao A|opcao_a&#10;Opcao B|opcao_b">{{ $field['options_text'] ?? '' }}</textarea>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-4 text-sm text-slate-600">
                        <label class="flex items-center gap-2"><input type="checkbox" name="fields[{{ $index }}][is_required]" value="1" @checked($field['is_required'] ?? false)> Obrigatorio</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="fields[{{ $index }}][show_on_board]" value="1" @checked($field['show_on_board'] ?? true)> Mostrar no quadro</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="fields[{{ $index }}][is_active]" value="1" @checked($field['is_active'] ?? true)> Ativo</label>
                        <button type="button" class="text-rose-600" data-remove-row>Remover</button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="ui-panel rounded-3xl p-6">
        <div class="mb-4 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Catalogo de chamados</h2>
                <p class="mt-1 text-sm text-slate-500">Cada item vira um modelo de chamado disponivel para o setor novo.</p>
            </div>
            <button type="button" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm" data-add-row="catalog">Adicionar item</button>
        </div>

        <div class="space-y-4" data-repeater="catalog">
            @foreach ($catalogRows as $index => $item)
                <div class="rounded-3xl border border-slate-200 p-4" data-row>
                    <div class="grid gap-4 lg:grid-cols-4">
                        <input type="text" name="catalog_items[{{ $index }}][name]" value="{{ $item['name'] ?? '' }}" class="ui-input w-full" placeholder="Nome do item">
                        <select name="catalog_items[{{ $index }}][default_ticket_group_slug]" class="ui-native-select w-full text-sm text-slate-700">
                            <option value="">Grupo padrao</option>
                            @foreach ($groupOptions as $slug => $label)
                                <option value="{{ $slug }}" @selected(($item['default_ticket_group_slug'] ?? '') === $slug)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <select name="catalog_items[{{ $index }}][default_priority]" class="ui-native-select w-full text-sm text-slate-700">
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority->value }}" @selected(($item['default_priority'] ?? 'medium') === $priority->value)>{{ $priority->label() }}</option>
                            @endforeach
                        </select>
                        <label class="flex items-center gap-2 rounded-2xl border border-slate-200 px-3 py-3 text-sm text-slate-700">
                            <input type="checkbox" name="catalog_items[{{ $index }}][is_active]" value="1" @checked($item['is_active'] ?? true)> Ativo
                        </label>
                    </div>
                    <div class="mt-4 flex gap-4">
                        <textarea name="catalog_items[{{ $index }}][description]" rows="2" class="ui-textarea w-full" placeholder="Descricao do item">{{ $item['description'] ?? '' }}</textarea>
                        <button type="button" class="shrink-0 text-rose-600" data-remove-row>Remover</button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <details class="ui-panel rounded-3xl p-6" @if (count($automationRows) > 1 || filled($automationRows[0]['name'] ?? '')) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Automacoes avancadas</h2>
                <p class="mt-1 text-sm text-slate-500">Opcional. Abra somente se o template precisar criar regras automaticas.</p>
            </div>
            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-500">Abrir</span>
        </summary>

        <div class="mt-5 flex justify-end">
            <button type="button" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm" data-add-row="automation">Adicionar regra</button>
        </div>

        <div class="space-y-4" data-repeater="automation">
            @foreach ($automationRows as $index => $rule)
                <div class="rounded-3xl border border-slate-200 p-4" data-row>
                    <div class="grid gap-4 lg:grid-cols-4">
                        <input type="text" name="automation_rules[{{ $index }}][name]" value="{{ $rule['name'] ?? '' }}" class="ui-input w-full" placeholder="Nome da regra">
                        <select name="automation_rules[{{ $index }}][trigger]" class="ui-native-select w-full text-sm text-slate-700">
                            @foreach ($automationTriggers as $trigger)
                                <option value="{{ $trigger->value }}" @selected(($rule['trigger'] ?? 'ticket_created') === $trigger->value)>{{ $trigger->label() }}</option>
                            @endforeach
                        </select>
                        <input type="number" min="1" name="automation_rules[{{ $index }}][cooldown_minutes]" value="{{ $rule['cooldown_minutes'] ?? '' }}" class="ui-input w-full" placeholder="Cooldown (min)">
                        <label class="flex items-center gap-2 rounded-2xl border border-slate-200 px-3 py-3 text-sm text-slate-700">
                            <input type="checkbox" name="automation_rules[{{ $index }}][is_active]" value="1" @checked($rule['is_active'] ?? true)> Ativa
                        </label>
                    </div>
                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <input type="text" name="automation_rules[{{ $index }}][description]" value="{{ $rule['description'] ?? '' }}" class="ui-input w-full" placeholder="Descricao">
                        <input type="number" min="1" name="automation_rules[{{ $index }}][inactive_for_minutes]" value="{{ $rule['inactive_for_minutes'] ?? '' }}" class="ui-input w-full" placeholder="Inatividade (apenas trigger ticket_inactive)">
                    </div>
                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Condicoes JSON</label>
                            <textarea name="automation_rules[{{ $index }}][conditions_json]" rows="6" class="ui-textarea w-full">{{ $rule['conditions_json'] ?? '[]' }}</textarea>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Acoes JSON</label>
                            <textarea name="automation_rules[{{ $index }}][actions_json]" rows="6" class="ui-textarea w-full">{{ $rule['actions_json'] ?? '[]' }}</textarea>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="button" class="text-rose-600" data-remove-row>Remover regra</button>
                    </div>
                </div>
            @endforeach
        </div>
    </details>

    <div class="ui-panel rounded-3xl p-6">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-slate-900">SLA</h2>
            <p class="mt-1 text-sm text-slate-500">Defina metas por prioridade para serem copiadas no quadro do setor novo.</p>
        </div>

        <label class="mb-4 flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <input type="checkbox" name="sla_is_active" value="1" @checked(old('sla_is_active', $template->slaPolicy?->is_active ?? true)) class="size-4 rounded border-slate-300">
            <span class="text-sm text-slate-700">Politica de SLA ativa</span>
        </label>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($priorities as $priority)
                <div class="rounded-3xl border border-slate-200 p-4">
                    <p class="font-medium text-slate-900">{{ $priority->label() }}</p>
                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        <input type="number" min="1" name="sla_targets[{{ $priority->value }}][first_response_minutes]" value="{{ $slaTargets[$priority->value]['first_response_minutes'] ?? '' }}" class="ui-input w-full" placeholder="Primeira resposta (min)">
                        <input type="number" min="1" name="sla_targets[{{ $priority->value }}][resolution_minutes]" value="{{ $slaTargets[$priority->value]['resolution_minutes'] ?? '' }}" class="ui-input w-full" placeholder="Resolucao (min)">
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('sector-templates.index') }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Cancelar</a>
        <button type="submit" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Salvar template</button>
    </div>
</form>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const configs = {
                fields: {
                    defaults: { name: '', type: 'text', placeholder: '', help_text: '', options_text: '', is_required: false, show_on_board: true, is_active: true },
                    render(index, values) {
                        return `
                            <div class="rounded-3xl border border-slate-200 p-4" data-row>
                                <div class="grid gap-4 lg:grid-cols-3">
                                    <input type="text" name="fields[${index}][name]" value="${values.name}" class="ui-input w-full" placeholder="Nome do campo">
                                    <select name="fields[${index}][type]" class="ui-native-select w-full text-sm text-slate-700">
                                        @foreach ($fieldTypes as $type)
                                            <option value="{{ $type->value }}" ${values.type === '{{ $type->value }}' ? 'selected' : ''}>{{ $type->label() }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="fields[${index}][placeholder]" value="${values.placeholder}" class="ui-input w-full" placeholder="Placeholder">
                                </div>
                                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                    <textarea name="fields[${index}][help_text]" rows="2" class="ui-textarea w-full" placeholder="Texto de ajuda">${values.help_text}</textarea>
                                    <textarea name="fields[${index}][options_text]" rows="2" class="ui-textarea w-full" placeholder="Opcao A|opcao_a&#10;Opcao B|opcao_b">${values.options_text}</textarea>
                                </div>
                                <div class="mt-4 flex flex-wrap gap-4 text-sm text-slate-600">
                                    <label class="flex items-center gap-2"><input type="checkbox" name="fields[${index}][is_required]" value="1" ${values.is_required ? 'checked' : ''}> Obrigatorio</label>
                                    <label class="flex items-center gap-2"><input type="checkbox" name="fields[${index}][show_on_board]" value="1" ${values.show_on_board ? 'checked' : ''}> Mostrar no quadro</label>
                                    <label class="flex items-center gap-2"><input type="checkbox" name="fields[${index}][is_active]" value="1" ${values.is_active ? 'checked' : ''}> Ativo</label>
                                    <button type="button" class="text-rose-600" data-remove-row>Remover</button>
                                </div>
                            </div>`;
                    },
                },
                catalog: {
                    defaults: { name: '', description: '', default_ticket_group_slug: 'aberto', default_priority: 'medium', is_active: true },
                    render(index, values) {
                        return `
                            <div class="rounded-3xl border border-slate-200 p-4" data-row>
                                <div class="grid gap-4 lg:grid-cols-4">
                                    <input type="text" name="catalog_items[${index}][name]" value="${values.name}" class="ui-input w-full" placeholder="Nome do item">
                                    <select name="catalog_items[${index}][default_ticket_group_slug]" class="ui-native-select w-full text-sm text-slate-700">
                                        <option value="">Grupo padrao</option>
                                        @foreach ($groupOptions as $slug => $label)
                                            <option value="{{ $slug }}" ${values.default_ticket_group_slug === '{{ $slug }}' ? 'selected' : ''}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <select name="catalog_items[${index}][default_priority]" class="ui-native-select w-full text-sm text-slate-700">
                                        @foreach ($priorities as $priority)
                                            <option value="{{ $priority->value }}" ${values.default_priority === '{{ $priority->value }}' ? 'selected' : ''}>{{ $priority->label() }}</option>
                                        @endforeach
                                    </select>
                                    <label class="flex items-center gap-2 rounded-2xl border border-slate-200 px-3 py-3 text-sm text-slate-700">
                                        <input type="checkbox" name="catalog_items[${index}][is_active]" value="1" ${values.is_active ? 'checked' : ''}> Ativo
                                    </label>
                                </div>
                                <div class="mt-4 flex gap-4">
                                    <textarea name="catalog_items[${index}][description]" rows="2" class="ui-textarea w-full" placeholder="Descricao do item">${values.description}</textarea>
                                    <button type="button" class="shrink-0 text-rose-600" data-remove-row>Remover</button>
                                </div>
                            </div>`;
                    },
                },
                automation: {
                    defaults: { name: '', description: '', trigger: 'ticket_created', cooldown_minutes: '', inactive_for_minutes: '', is_active: true, conditions_json: '[]', actions_json: '[]' },
                    render(index, values) {
                        return `
                            <div class="rounded-3xl border border-slate-200 p-4" data-row>
                                <div class="grid gap-4 lg:grid-cols-4">
                                    <input type="text" name="automation_rules[${index}][name]" value="${values.name}" class="ui-input w-full" placeholder="Nome da regra">
                                    <select name="automation_rules[${index}][trigger]" class="ui-native-select w-full text-sm text-slate-700">
                                        @foreach ($automationTriggers as $trigger)
                                            <option value="{{ $trigger->value }}" ${values.trigger === '{{ $trigger->value }}' ? 'selected' : ''}>{{ $trigger->label() }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" min="1" name="automation_rules[${index}][cooldown_minutes]" value="${values.cooldown_minutes}" class="ui-input w-full" placeholder="Cooldown (min)">
                                    <label class="flex items-center gap-2 rounded-2xl border border-slate-200 px-3 py-3 text-sm text-slate-700">
                                        <input type="checkbox" name="automation_rules[${index}][is_active]" value="1" ${values.is_active ? 'checked' : ''}> Ativa
                                    </label>
                                </div>
                                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                    <input type="text" name="automation_rules[${index}][description]" value="${values.description}" class="ui-input w-full" placeholder="Descricao">
                                    <input type="number" min="1" name="automation_rules[${index}][inactive_for_minutes]" value="${values.inactive_for_minutes}" class="ui-input w-full" placeholder="Inatividade (apenas trigger ticket_inactive)">
                                </div>
                                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                    <div>
                                        <label class="mb-2 block text-sm font-medium text-slate-700">Condicoes JSON</label>
                                        <textarea name="automation_rules[${index}][conditions_json]" rows="6" class="ui-textarea w-full">${values.conditions_json}</textarea>
                                    </div>
                                    <div>
                                        <label class="mb-2 block text-sm font-medium text-slate-700">Acoes JSON</label>
                                        <textarea name="automation_rules[${index}][actions_json]" rows="6" class="ui-textarea w-full">${values.actions_json}</textarea>
                                    </div>
                                </div>
                                <div class="mt-4 flex justify-end">
                                    <button type="button" class="text-rose-600" data-remove-row>Remover regra</button>
                                </div>
                            </div>`;
                    },
                },
            };

            document.querySelectorAll('[data-add-row]').forEach(button => {
                button.addEventListener('click', () => {
                    const type = button.dataset.addRow;
                    const container = document.querySelector(`[data-repeater="${type}"]`);
                    const index = container.querySelectorAll('[data-row]').length;
                    container.insertAdjacentHTML('beforeend', configs[type].render(index, configs[type].defaults));
                });
            });

            document.addEventListener('click', event => {
                const removeButton = event.target.closest('[data-remove-row]');

                if (!removeButton) {
                    return;
                }

                const row = removeButton.closest('[data-row]');
                const container = row.parentElement;

                if (container.querySelectorAll('[data-row]').length <= 1) {
                    return;
                }

                row.remove();
            });
        });
    </script>
@endpush
