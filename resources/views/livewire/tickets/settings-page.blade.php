<div class="space-y-6">
    @if (session('status'))
        <div class="ui-panel rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="ui-panel rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ session('error') }}
        </div>
    @endif

    <div class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm text-slate-500">Configuracao do ambiente de chamados por setor.</p>
                <h2 class="mt-1 text-2xl font-semibold text-slate-900">{{ $board?->name ?? 'Quadro do setor' }}</h2>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                @if ($sectorOptions->count() > 1)
                    <label class="text-sm text-slate-600">
                        <span class="mb-1 block font-medium">Setor</span>
                        <select wire:model.live="selectedSectorId" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none">
                            @foreach ($sectorOptions as $sectorOption)
                                <option value="{{ $sectorOption->id }}">{{ $sectorOption->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                @if ($board)
                    <a href="{{ route('tickets.board', $board->sector_id) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">
                        Voltar ao quadro
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if (! $board)
        <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-slate-500">
            Nenhum quadro disponivel para configuracao.
        </div>
    @else
        <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-slate-900">Dados do quadro</h3>
                <p class="text-sm text-slate-500">Nome e descricao usados no ambiente do setor.</p>
            </div>

            <form wire:submit="saveBoardMeta" class="grid gap-4 md:grid-cols-2">
                <label class="text-sm text-slate-600">
                    <span class="mb-2 block font-medium">Nome</span>
                    <input type="text" wire:model="boardName" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" />
                    @error('boardName') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>

                <label class="text-sm text-slate-600">
                    <span class="mb-2 block font-medium">Descricao</span>
                    <textarea wire:model="boardDescription" rows="3" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none"></textarea>
                    @error('boardDescription') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>

                <div class="md:col-span-2">
                    <button type="submit" wire:loading.attr="disabled" wire:loading.class="ui-loading" wire:target="saveBoardMeta" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">
                        <span wire:loading.remove wire:target="saveBoardMeta">Salvar quadro</span>
                        <span wire:loading wire:target="saveBoardMeta">Salvando...</span>
                    </button>
                </div>
            </form>
        </section>

        <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-slate-900">Politica de SLA</h3>
                <p class="text-sm text-slate-500">Prazos por prioridade para primeira resposta e resolucao do chamado.</p>
            </div>

            <form wire:submit="saveSlaPolicy" class="space-y-4">
                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" wire:model="slaIsActive" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                    SLA ativo neste board
                </label>

                <div class="overflow-x-auto rounded-2xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-medium">Prioridade</th>
                                <th class="px-4 py-3 font-medium">Primeira resposta (min)</th>
                                <th class="px-4 py-3 font-medium">Resolucao (min)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($priorities as $priority)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $priority->label() }}</td>
                                    <td class="px-4 py-3">
                                        <input
                                            type="number"
                                            min="1"
                                            wire:model="slaTargets.{{ $priority->value }}.first_response_minutes"
                                            class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none"
                                        />
                                        @error("slaTargets.{$priority->value}.first_response_minutes") <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                    </td>
                                    <td class="px-4 py-3">
                                        <input
                                            type="number"
                                            min="1"
                                            wire:model="slaTargets.{{ $priority->value }}.resolution_minutes"
                                            class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none"
                                        />
                                        @error("slaTargets.{$priority->value}.resolution_minutes") <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="submit" wire:loading.attr="disabled" wire:loading.class="ui-loading" wire:target="saveSlaPolicy" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">
                    <span wire:loading.remove wire:target="saveSlaPolicy">Salvar SLA</span>
                    <span wire:loading wire:target="saveSlaPolicy">Salvando...</span>
                </button>
            </form>
        </section>

        <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Automacoes</h3>
                    <p class="text-sm text-slate-500">Regras estruturadas por gatilho, condicoes e acoes para este board.</p>
                </div>
                <button type="button" wire:click="cancelAutomationEditing" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">
                    Nova regra
                </button>
            </div>

            <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
                <div class="space-y-4">
                    @if ($board->automationRules->isEmpty())
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-slate-500">
                            Nenhuma automacao configurada ainda.
                        </div>
                    @else
                        @foreach ($board->automationRules as $rule)
                            <div class="ui-panel rounded-2xl border border-slate-200 p-4">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="space-y-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-medium text-slate-900">{{ $rule->name }}</p>
                                            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $rule->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                                {{ $rule->is_active ? 'Ativa' : 'Inativa' }}
                                            </span>
                                            <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-700">
                                                {{ $rule->trigger->label() }}
                                            </span>
                                        </div>
                                        @if ($rule->description)
                                            <p class="text-sm text-slate-500">{{ $rule->description }}</p>
                                        @endif
                                        <p class="text-xs text-slate-500">
                                            Ordem {{ $rule->sort_order }}
                                            @if ($rule->cooldown_minutes)
                                                , cooldown {{ $rule->cooldown_minutes }} min
                                            @endif
                                        </p>

                                        <div>
                                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Condicoes</p>
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                @foreach ($rule->conditions as $condition)
                                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">{{ $this->describeAutomationCondition($condition) }}</span>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div>
                                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Acoes</p>
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                @foreach ($rule->actions as $action)
                                                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs text-amber-800">{{ $this->describeAutomationAction($action) }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" wire:click="startEditingAutomation({{ $rule->id }})" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm">Editar</button>
                                        <button type="button" wire:click="toggleAutomationActive({{ $rule->id }})" class="ui-action rounded-xl border border-sky-200 px-3 py-2 text-sm text-sky-700 hover:bg-sky-50">
                                            {{ $rule->is_active ? 'Desativar' : 'Ativar' }}
                                        </button>
                                        <button type="button" wire:click="deleteAutomation({{ $rule->id }})" class="ui-action ui-action-danger rounded-xl px-3 py-2 text-sm">Excluir</button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                <form wire:submit="saveAutomation" class="space-y-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div>
                        <h4 class="text-base font-semibold text-slate-900">{{ $editingAutomationRuleId ? 'Editar automacao' : 'Nova automacao' }}</h4>
                        <p class="text-sm text-slate-500">CRUD simples, com campos previsiveis e execucao ordenada.</p>
                    </div>

                    <div class="grid gap-3">
                        <input type="text" wire:model="automationForm.name" placeholder="Nome da regra" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                        @error('automationForm.name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror

                        <textarea wire:model="automationForm.description" rows="2" placeholder="Descricao opcional" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none"></textarea>

                        <div class="grid gap-3 md:grid-cols-2">
                            <select wire:model.live="automationForm.trigger" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                @foreach ($automationTriggers as $trigger)
                                    <option value="{{ $trigger->value }}">{{ $trigger->label() }}</option>
                                @endforeach
                            </select>

                            <input type="number" min="1" wire:model="automationForm.sort_order" placeholder="Ordem" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <input type="number" min="1" wire:model="automationForm.cooldown_minutes" placeholder="Cooldown (min)" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />

                            @if ($automationForm['trigger'] === 'ticket_inactive')
                                <input type="number" min="1" wire:model="automationForm.inactive_for_minutes" placeholder="Inativo por (min)" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                            @endif
                        </div>
                        @error('automationForm.sort_order') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        @error('automationForm.cooldown_minutes') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        @error('automationForm.inactive_for_minutes') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror

                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" wire:model="automationForm.is_active" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                            Regra ativa
                        </label>
                    </div>

                    <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-slate-800">Condicoes</p>
                            <button type="button" wire:click="addAutomationCondition" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm">Adicionar</button>
                        </div>

                        @foreach ($automationConditions as $index => $condition)
                            <div class="space-y-3 rounded-2xl border border-slate-200 p-3">
                                <div class="grid gap-3 md:grid-cols-3">
                                    <select wire:model.live="automationConditions.{{ $index }}.field" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                        @foreach ($automationConditionFields as $field)
                                            <option value="{{ $field->value }}">{{ $field->label() }}</option>
                                        @endforeach
                                    </select>
                                    <select wire:model="automationConditions.{{ $index }}.operator" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                        @foreach ($automationConditionOperators as $operator)
                                            <option value="{{ $operator->value }}">{{ $operator->label() }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" wire:click="removeAutomationCondition({{ $index }})" class="ui-action ui-action-danger rounded-2xl px-3 py-3 text-sm">Remover</button>
                                </div>

                                @if (in_array($condition['field'], ['priority', 'status_id', 'group_id'], true))
                                    <select wire:model="automationConditions.{{ $index }}.value" multiple class="min-h-28 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                        @if ($condition['field'] === 'priority')
                                            @foreach ($priorities as $priority)
                                                <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                                            @endforeach
                                        @elseif ($condition['field'] === 'status_id')
                                            @foreach ($board->statuses as $status)
                                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                                            @endforeach
                                        @else
                                            @foreach ($board->groups as $group)
                                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                @else
                                    <p class="text-xs text-slate-500">Esta condicao usa apenas o operador selecionado.</p>
                                @endif
                            </div>
                        @endforeach
                        @error('automationConditions') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-slate-800">Acoes</p>
                            <button type="button" wire:click="addAutomationAction" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm">Adicionar</button>
                        </div>

                        @foreach ($automationActions as $index => $action)
                            <div class="space-y-3 rounded-2xl border border-slate-200 p-3">
                                <div class="grid gap-3 md:grid-cols-[1fr_auto]">
                                    <select wire:model.live="automationActions.{{ $index }}.action" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                        @foreach ($automationActionTypes as $actionType)
                                            <option value="{{ $actionType->value }}">{{ $actionType->label() }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" wire:click="removeAutomationAction({{ $index }})" class="ui-action ui-action-danger rounded-2xl px-3 py-3 text-sm">Remover</button>
                                </div>

                                @if ($action['action'] === 'assign_fixed_assignee')
                                    <select wire:model="automationActions.{{ $index }}.payload.assignee_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                        <option value="">Selecione o responsavel</option>
                                        @foreach ($automationAssignees as $assignee)
                                            <option value="{{ $assignee->id }}">{{ $assignee->name }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($action['action'] === 'change_status')
                                    <select wire:model="automationActions.{{ $index }}.payload.status_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                        <option value="">Selecione o status</option>
                                        @foreach ($board->statuses as $status)
                                            <option value="{{ $status->id }}">{{ $status->name }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($action['action'] === 'change_group')
                                    <select wire:model="automationActions.{{ $index }}.payload.group_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                        <option value="">Selecione o grupo</option>
                                        @foreach ($board->groups as $group)
                                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($action['action'] === 'change_priority')
                                    <select wire:model="automationActions.{{ $index }}.payload.priority" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                        <option value="">Selecione a prioridade</option>
                                        @foreach ($priorities as $priority)
                                            <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($action['action'] === 'add_system_message')
                                    <textarea wire:model="automationActions.{{ $index }}.payload.message" rows="3" placeholder="Mensagem automatica no chat" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none"></textarea>
                                @elseif ($action['action'] === 'send_notification')
                                    <div class="grid gap-3">
                                        <input type="text" wire:model="automationActions.{{ $index }}.payload.title" placeholder="Titulo da notificacao" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                                        <textarea wire:model="automationActions.{{ $index }}.payload.message" rows="3" placeholder="Mensagem da notificacao" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none"></textarea>
                                    </div>
                                @elseif ($action['action'] === 'reopen_ticket')
                                    <div class="grid gap-3">
                                        <select wire:model="automationActions.{{ $index }}.payload.target_status_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                            <option value="">Selecione o status de reabertura</option>
                                            @foreach ($board->statuses->where('is_closed', false) as $status)
                                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                                            @endforeach
                                        </select>
                                        <textarea wire:model="automationActions.{{ $index }}.payload.message" rows="3" placeholder="Mensagem opcional de reabertura" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none"></textarea>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                        @error('automationActions') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" wire:loading.attr="disabled" wire:loading.class="ui-loading" wire:target="saveAutomation" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">
                            <span wire:loading.remove wire:target="saveAutomation">{{ $editingAutomationRuleId ? 'Salvar automacao' : 'Criar automacao' }}</span>
                            <span wire:loading wire:target="saveAutomation">Salvando...</span>
                        </button>
                        @if ($editingAutomationRuleId)
                            <button type="button" wire:click="cancelAutomationEditing" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">
                                Cancelar edicao
                            </button>
                        @endif
                    </div>
                </form>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-slate-900">Grupos</h3>
                    <p class="text-sm text-slate-500">Faixas do board com manutencao segura e reordenacao persistida.</p>
                </div>

                <form wire:submit="addGroup" class="grid gap-3 md:grid-cols-2">
                    <input type="text" wire:model="groupForm.name" placeholder="Nome do grupo" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                    <input type="text" wire:model="groupForm.color" placeholder="#2563eb" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" wire:model="groupForm.is_collapsed_by_default" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        Iniciar recolhido
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" wire:model="groupForm.is_active" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        Ativo
                    </label>
                    <div class="md:col-span-2">
                        <button type="submit" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Adicionar grupo</button>
                    </div>
                </form>

                <div class="mt-6 space-y-3">
                    @foreach ($board->groups as $group)
                        @php($impact = $groupImpacts[$group->id] ?? ['tickets_count' => 0, 'catalog_count' => 0])
                        <div class="ui-panel rounded-2xl border border-slate-200 px-4 py-4">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="space-y-2">
                                    <div class="flex items-center gap-3">
                                        <span class="size-3 rounded-full" style="background-color: {{ $group->color ?: '#2563eb' }}"></span>
                                        <div>
                                            <p class="font-medium text-slate-900">{{ $group->name }}</p>
                                            <p class="text-xs text-slate-500">{{ $group->slug }} @if (! $group->is_active) / Inativo @endif</p>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2 text-xs text-slate-500">
                                        <span class="rounded-full bg-slate-100 px-3 py-1">{{ $impact['tickets_count'] }} tickets</span>
                                        <span class="rounded-full bg-slate-100 px-3 py-1">{{ $impact['catalog_count'] }} itens de catalogo</span>
                                        <span class="rounded-full bg-slate-100 px-3 py-1">{{ $group->is_collapsed_by_default ? 'Inicia recolhido' : 'Inicia expandido' }}</span>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button type="button" wire:click="moveGroupUp({{ $group->id }})" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm" @disabled($loop->first)>Subir</button>
                                    <button type="button" wire:click="moveGroupDown({{ $group->id }})" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm" @disabled($loop->last)>Descer</button>
                                    <button type="button" wire:click="startEditingGroup({{ $group->id }})" class="ui-action rounded-xl border border-sky-200 px-3 py-2 text-sm text-sky-700 hover:bg-sky-50">Editar</button>
                                    <button type="button" wire:click="confirmDeleteGroup({{ $group->id }})" class="ui-action ui-action-danger rounded-xl px-3 py-2 text-sm">Excluir</button>
                                </div>
                            </div>

                            @if ($editingGroupId === $group->id)
                                <form wire:submit="updateGroup" class="mt-4 grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-2">
                                    <label class="text-sm text-slate-600">
                                        <span class="mb-1 block font-medium">Nome</span>
                                        <input type="text" wire:model="editGroupForm.name" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" />
                                        @error('editGroupForm.name') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                    </label>
                                    <label class="text-sm text-slate-600">
                                        <span class="mb-1 block font-medium">Cor</span>
                                        <input type="text" wire:model="editGroupForm.color" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" />
                                        @error('editGroupForm.color') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                                        <input type="checkbox" wire:model="editGroupForm.is_collapsed_by_default" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                                        Iniciar recolhido
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                                        <input type="checkbox" wire:model="editGroupForm.is_active" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                                        Ativo
                                    </label>
                                    <div class="flex flex-wrap gap-2 md:col-span-2">
                                        <button type="submit" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Salvar grupo</button>
                                        <button type="button" wire:click="cancelEditingGroup" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Cancelar</button>
                                    </div>
                                </form>
                            @endif

                            @if ($pendingDeletionType === 'group' && $pendingDeletionId === $group->id)
                                <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                                    <p class="font-medium">Excluir grupo com tratamento de impacto</p>
                                    <p class="mt-1">Tickets vinculados: {{ $deletionContext['tickets_count'] ?? 0 }}. Itens de catalogo afetados: {{ $deletionContext['catalog_count'] ?? 0 }}.</p>

                                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                                        <label class="text-sm text-slate-700">
                                            <span class="mb-1 block font-medium">Novo grupo para tickets</span>
                                            <select wire:model="replacementSelection.group_ticket_group_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none">
                                                <option value="">Sem grupo</option>
                                                @foreach ($deletionContext['replacement_groups'] ?? [] as $replacementGroup)
                                                    <option value="{{ $replacementGroup['id'] }}">{{ $replacementGroup['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </label>

                                        <label class="text-sm text-slate-700">
                                            <span class="mb-1 block font-medium">Novo grupo padrao do catalogo</span>
                                            <select wire:model="replacementSelection.group_catalog_group_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none">
                                                <option value="">Sem grupo</option>
                                                @foreach ($deletionContext['replacement_groups'] ?? [] as $replacementGroup)
                                                    <option value="{{ $replacementGroup['id'] }}">{{ $replacementGroup['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                    </div>

                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <button type="button" wire:click="deleteGroup" class="ui-action rounded-2xl bg-rose-600 px-4 py-3 text-sm font-medium text-white hover:bg-rose-500">Confirmar exclusao</button>
                                        <button type="button" wire:click="cancelDeletion" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Cancelar</button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-slate-900">Status</h3>
                    <p class="text-sm text-slate-500">Estados usados pelo fluxo do chamado com validacao de default ativo.</p>
                </div>

                <form wire:submit="addStatus" class="grid gap-3 md:grid-cols-2">
                    <input type="text" wire:model="statusForm.name" placeholder="Nome do status" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                    <input type="text" wire:model="statusForm.color" placeholder="#2563eb" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" wire:model="statusForm.is_default" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        Padrao
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" wire:model="statusForm.is_closed" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        Fecha o chamado
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600 md:col-span-2">
                        <input type="checkbox" wire:model="statusForm.is_active" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        Ativo
                    </label>
                    <div class="md:col-span-2">
                        <button type="submit" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Adicionar status</button>
                    </div>
                </form>

                <div class="mt-6 space-y-3">
                    @foreach ($board->statuses as $status)
                        @php($impact = $statusImpacts[$status->id] ?? ['tickets_count' => 0, 'active_statuses_count' => 0, 'available_replacements_count' => 0])
                        <div class="ui-panel rounded-2xl border border-slate-200 px-4 py-4">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="space-y-2">
                                    <div class="flex items-center gap-3">
                                        <span class="size-3 rounded-full" style="background-color: {{ $status->color ?: '#2563eb' }}"></span>
                                        <div>
                                            <p class="font-medium text-slate-900">{{ $status->name }}</p>
                                            <p class="text-xs text-slate-500">
                                                {{ $status->is_default ? 'Padrao ativo' : 'Opcional' }} /
                                                {{ $status->is_closed ? 'Fechado' : 'Aberto' }}
                                                @if (! $status->is_active) / Inativo @endif
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2 text-xs text-slate-500">
                                        <span class="rounded-full bg-slate-100 px-3 py-1">{{ $impact['tickets_count'] }} tickets</span>
                                        <span class="rounded-full bg-slate-100 px-3 py-1">{{ $impact['active_statuses_count'] }} status ativos</span>
                                        <span class="rounded-full bg-slate-100 px-3 py-1">{{ $impact['available_replacements_count'] }} substitutos ativos</span>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button type="button" wire:click="moveStatusUp({{ $status->id }})" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm" @disabled($loop->first)>Subir</button>
                                    <button type="button" wire:click="moveStatusDown({{ $status->id }})" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm" @disabled($loop->last)>Descer</button>
                                    <button type="button" wire:click="startEditingStatus({{ $status->id }})" class="ui-action rounded-xl border border-sky-200 px-3 py-2 text-sm text-sky-700 hover:bg-sky-50">Editar</button>
                                    <button type="button" wire:click="confirmDeleteStatus({{ $status->id }})" class="ui-action ui-action-danger rounded-xl px-3 py-2 text-sm">Excluir</button>
                                </div>
                            </div>

                            @if ($editingStatusId === $status->id)
                                <form wire:submit="updateStatus" class="mt-4 grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-2">
                                    <label class="text-sm text-slate-600">
                                        <span class="mb-1 block font-medium">Nome</span>
                                        <input type="text" wire:model="editStatusForm.name" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" />
                                        @error('editStatusForm.name') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                    </label>
                                    <label class="text-sm text-slate-600">
                                        <span class="mb-1 block font-medium">Cor</span>
                                        <input type="text" wire:model="editStatusForm.color" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" />
                                        @error('editStatusForm.color') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                                        <input type="checkbox" wire:model="editStatusForm.is_default" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                                        Status padrao
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                                        <input type="checkbox" wire:model="editStatusForm.is_closed" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                                        Fecha o chamado
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-600 md:col-span-2">
                                        <input type="checkbox" wire:model="editStatusForm.is_active" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                                        Ativo
                                    </label>
                                    <div class="flex flex-wrap gap-2 md:col-span-2">
                                        <button type="submit" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Salvar status</button>
                                        <button type="button" wire:click="cancelEditingStatus" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Cancelar</button>
                                    </div>
                                </form>
                            @endif

                            @if ($pendingDeletionType === 'status' && $pendingDeletionId === $status->id)
                                <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                                    <p class="font-medium">Excluir status com substituicao obrigatoria</p>
                                    <p class="mt-1">Tickets vinculados: {{ $deletionContext['tickets_count'] ?? 0 }}. Status ativos disponiveis para substituir: {{ $deletionContext['available_replacements_count'] ?? 0 }}.</p>

                                    <label class="mt-4 block text-sm text-slate-700">
                                        <span class="mb-1 block font-medium">Status substituto</span>
                                        <select wire:model="replacementSelection.status_replacement_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none">
                                            <option value="">Selecione</option>
                                            @foreach ($deletionContext['replacement_statuses'] ?? [] as $replacementStatus)
                                                <option value="{{ $replacementStatus['id'] }}">{{ $replacementStatus['name'] }}{{ $replacementStatus['is_default'] ? ' (padrao)' : '' }}</option>
                                            @endforeach
                                        </select>
                                    </label>

                                    @if (($deletionContext['active_statuses_count'] ?? 0) <= 1)
                                        <p class="mt-3 text-xs text-rose-700">Este status e o ultimo ativo do board e nao pode ser excluido.</p>
                                    @endif

                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            wire:click="deleteStatus"
                                            class="ui-action rounded-2xl bg-rose-600 px-4 py-3 text-sm font-medium text-white hover:bg-rose-500"
                                            @disabled(($deletionContext['active_statuses_count'] ?? 0) <= 1 || ($deletionContext['available_replacements_count'] ?? 0) < 1)
                                        >
                                            Confirmar exclusao
                                        </button>
                                        <button type="button" wire:click="cancelDeletion" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Cancelar</button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-slate-900">Campos dinamicos</h3>
                    <p class="text-sm text-slate-500">Estrutura reaproveitada por tabela e formularios.</p>
                </div>

                <form wire:submit="addField" class="space-y-3">
                    <div class="grid gap-3 md:grid-cols-2">
                        <input type="text" wire:model="fieldForm.name" placeholder="Nome do campo" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                        <select wire:model.live="fieldForm.type" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                            @foreach ($fieldTypes as $fieldType)
                                <option value="{{ $fieldType->value }}">{{ $fieldType->label() }}</option>
                            @endforeach
                        </select>
                        <input type="text" wire:model="fieldForm.placeholder" placeholder="Placeholder" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                        <input type="text" wire:model="fieldForm.help_text" placeholder="Ajuda para o usuario" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                    </div>

                    <textarea wire:model="fieldForm.options_text" rows="4" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" placeholder="Opcoes, uma por linha. Use Label|#cor para definir cor quando fizer sentido."></textarea>

                    <div class="flex flex-wrap gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" wire:model="fieldForm.is_required" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                            Obrigatorio
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" wire:model="fieldForm.is_active" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                            Ativo
                        </label>
                    </div>

                    <button type="submit" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Adicionar campo</button>
                </form>

                <div class="mt-6 space-y-3">
                    @foreach ($board->fields as $field)
                        <div class="ui-panel rounded-2xl border border-slate-200 px-4 py-3">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $field->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $field->type->label() }} / {{ $field->slug }}</p>
                                    @if ($field->options->isNotEmpty())
                                        <p class="mt-2 text-xs text-slate-500">{{ $field->options->pluck('label')->join(', ') }}</p>
                                    @endif
                                </div>
                                <button type="button" wire:click="deleteField({{ $field->id }})" class="ui-action ui-action-danger rounded-xl px-3 py-2 text-sm">Excluir</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-slate-900">Formularios e catalogo</h3>
                    <p class="text-sm text-slate-500">Monte experiencias de abertura especificas por tipo de servico.</p>
                </div>

                <form wire:submit="addForm" class="space-y-3">
                    <input type="text" wire:model="formForm.name" placeholder="Nome do formulario" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                    <textarea wire:model="formForm.description" rows="3" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" placeholder="Descricao do formulario"></textarea>

                    <div class="space-y-2 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-medium text-slate-700">Campos incluidos</p>
                        @foreach ($board->fields as $field)
                            <label class="flex items-center justify-between gap-3 rounded-xl bg-white px-3 py-2 text-sm text-slate-700">
                                <span class="inline-flex items-center gap-2">
                                    <input type="checkbox" value="{{ $field->id }}" wire:model="formForm.field_ids" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                                    {{ $field->name }}
                                </span>
                                <span class="inline-flex items-center gap-2 text-xs text-slate-500">
                                    <input type="checkbox" value="{{ $field->id }}" wire:model="formForm.required_field_ids" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                                    Obrigatorio
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" wire:model="formForm.is_default" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                            Padrao
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" wire:model="formForm.is_active" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                            Ativo
                        </label>
                    </div>

                    <button type="submit" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Criar formulario</button>
                </form>

                <div class="mt-6 space-y-3">
                    @foreach ($board->forms as $form)
                        <div class="ui-panel rounded-2xl border border-slate-200 px-4 py-3">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $form->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $form->fields->pluck('name')->join(', ') ?: 'Sem campos vinculados' }}</p>
                                </div>
                                <button type="button" wire:click="deleteForm({{ $form->id }})" class="ui-action ui-action-danger rounded-xl px-3 py-2 text-sm">Excluir</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <form wire:submit="addCatalogItem" class="mt-8 space-y-3 border-t border-slate-200 pt-6">
                    <input type="text" wire:model="catalogForm.name" placeholder="Nome do item de catalogo" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                    <textarea wire:model="catalogForm.description" rows="3" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" placeholder="Descricao do item"></textarea>

                    <div class="grid gap-3 md:grid-cols-3">
                        <select wire:model="catalogForm.ticket_form_id" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                            <option value="">Sem formulario</option>
                            @foreach ($board->forms as $form)
                                <option value="{{ $form->id }}">{{ $form->name }}</option>
                            @endforeach
                        </select>
                        <select wire:model="catalogForm.default_ticket_group_id" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                            <option value="">Sem grupo</option>
                            @foreach ($board->groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                        <select wire:model="catalogForm.default_priority" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" wire:model="catalogForm.is_active" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        Ativo
                    </label>

                    <button type="submit" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Criar item de catalogo</button>
                </form>

                <div class="mt-6 space-y-3">
                    @foreach ($board->catalogItems as $catalogItem)
                        <div class="ui-panel rounded-2xl border border-slate-200 px-4 py-3">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $catalogItem->name }}</p>
                                    <p class="text-xs text-slate-500">Formulario: {{ $catalogItem->form?->name ?? 'Sem formulario' }} / Grupo: {{ $catalogItem->defaultGroup?->name ?? 'Livre' }}</p>
                                </div>
                                <button type="button" wire:click="deleteCatalogItem({{ $catalogItem->id }})" class="ui-action ui-action-danger rounded-xl px-3 py-2 text-sm">Excluir</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    @endif
</div>
