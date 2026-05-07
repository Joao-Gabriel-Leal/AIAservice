<div class="space-y-6">
    @foreach ($groups as $group)
        <section
            class="ui-panel ui-board-lane"
            style="--ui-lane-color: {{ $group->color ?: '#2563eb' }}"
            wire:key="group-stages-{{ $group->id }}"
            data-board-drop-zone="true"
            data-board-group-id="{{ $group->id }}"
            x-bind:class="{ 'ui-kanban-column-dragover': isDragTarget({{ $group->id }}) }"
        >
            <button
                type="button"
                wire:click="toggleGroup({{ $group->id }})"
                wire:loading.class="ui-loading"
                wire:target="toggleGroup"
                class="ui-row-interactive ui-board-lane-header flex w-full items-center justify-between gap-4 border-b border-slate-200 px-6 py-5 text-left"
            >
                <div class="flex items-center gap-4">
                    <span class="flex size-11 items-center justify-center rounded-2xl text-sm font-semibold shadow-sm" style="background-color: color-mix(in srgb, {{ $group->color ?: '#2563eb' }} 16%, white); color: color-mix(in srgb, {{ $group->color ?: '#2563eb' }} 72%, black 28%);">
                        {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                    </span>

                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-lg font-semibold text-slate-900">{{ $group->name }}</h3>

                            @if ($group->is_default)
                                <span class="ui-tone-chip ui-tone-chip-neutral">Entrada</span>
                            @endif

                            @if ($group->is_closed)
                                <span class="ui-tone-chip" style="--ui-pill-color: {{ $group->color ?: '#2563eb' }}">
                                    <span class="ui-tone-dot"></span>
                                    Final
                                </span>
                            @endif
                        </div>

                        <p class="mt-1 text-sm text-slate-500">{{ $ticketsByGroup->get($group->id)?->count() ?? 0 }} chamados nesta etapa</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <span class="ui-tone-chip" style="--ui-pill-color: {{ $group->color ?: '#2563eb' }}">
                        <span class="ui-tone-dot"></span>
                        {{ $ticketsByGroup->get($group->id)?->count() ?? 0 }}
                    </span>

                    <span class="rounded-full border border-slate-200 bg-white/80 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                        {{ ($collapsedGroups[$group->id] ?? false) ? 'Expandir' : 'Recolher' }}
                    </span>
                </div>
            </button>

            @if (! ($collapsedGroups[$group->id] ?? false))
                <div class="overflow-x-auto">
                    <table class="ui-board-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50/80 text-left text-slate-500">
                            <tr>
                                <th>Titulo</th>
                                <th>Solicitante</th>
                                <th>Responsavel</th>
                                <th>Prioridade</th>
                                <th>Etapa</th>
                                <th>SLA</th>
                                @foreach ($fields as $field)
                                    <th>{{ $field->name }}</th>
                                @endforeach
                                <th>Abrir</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($ticketsByGroup->get($group->id, collect()) as $ticket)
                                @php
                                    $slaMeta = $this->slaMeta($ticket);
                                @endphp

                                <tr
                                    class="ui-row-interactive align-top hover:bg-slate-50"
                                    wire:key="ticket-row-stages-{{ $ticket->id }}"
                                    x-on:pointerdown="beginPointerDrag($event, {{ $ticket->id }}, {{ $ticket->ticket_group_id ?? 'null' }})"
                                    x-bind:class="{
                                        'ui-kanban-card-dragging': isDraggingTicket({{ $ticket->id }}),
                                        'ui-kanban-card-lifted': isPointerCandidate({{ $ticket->id }})
                                    }"
                                >
                                    <td>
                                        <div wire:key="ticket-title-stages-{{ $ticket->id }}">
                                            <input type="text" value="{{ $ticket->title }}" wire:change="updateFixedField({{ $ticket->id }}, 'title', $event.target.value)" class="ui-input w-72" />
                                        </div>
                                    </td>

                                    <td class="text-slate-600">
                                        <div class="space-y-1">
                                            <p class="font-medium text-slate-700">{{ $ticket->requester?->name ?? 'Nao informado' }}</p>
                                            <p class="text-xs text-slate-400">{{ $ticket->catalogItem?->name ?? 'Formulario padrao' }}</p>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="ui-native-pill-select w-52" style="--ui-pill-color: {{ $ticket->assignee_id ? '#3b82f6' : '#94a3b8' }}" wire:key="ticket-assignee-stages-{{ $ticket->id }}">
                                            <span class="ui-native-pill-dot"></span>
                                            <select wire:change="updateFixedField({{ $ticket->id }}, 'assignee_id', $event.target.value)" class="ui-native-select ui-native-select-pill w-full">
                                                <option value="">Nao atribuido</option>
                                                @foreach ($assignees as $assignee)
                                                    <option value="{{ $assignee->id }}" @selected($ticket->assignee_id === $assignee->id)>{{ $assignee->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="ui-native-pill-select w-44" style="--ui-pill-color: {{ $this->priorityColor($ticket->priority) }}" wire:key="ticket-priority-stages-{{ $ticket->id }}">
                                            <span class="ui-native-pill-dot"></span>
                                            <select wire:change="updateFixedField({{ $ticket->id }}, 'priority', $event.target.value)" class="ui-native-select ui-native-select-pill w-full">
                                                @foreach ($priorities as $priority)
                                                    <option value="{{ $priority->value }}" @selected($ticket->priority === $priority)>{{ $priority->label() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="ui-native-pill-select w-48" style="--ui-pill-color: {{ $ticket->group?->color ?: '#94a3b8' }}" wire:key="ticket-group-stages-{{ $ticket->id }}">
                                            <span class="ui-native-pill-dot"></span>
                                            <select x-on:change="changeGroup({{ $ticket->id }}, $event.target.value)" class="ui-native-select ui-native-select-pill w-full">
                                                <option value="">Sem etapa</option>
                                                @foreach ($board->groups as $boardGroup)
                                                    <option value="{{ $boardGroup->id }}" @selected($ticket->ticket_group_id === $boardGroup->id)>{{ $boardGroup->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="space-y-2">
                                            <span class="ui-tone-chip" style="--ui-pill-color: {{ $slaMeta['color'] }}">
                                                <span class="ui-tone-dot"></span>
                                                {{ $slaMeta['label'] }}
                                            </span>
                                            <div class="text-xs text-slate-500">
                                                <div>1a resp.: {{ $ticket->first_response_due_at?->format('d/m H:i') ?? '-' }}</div>
                                                <div>Resol.: {{ $ticket->resolution_due_at?->format('d/m H:i') ?? '-' }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    @foreach ($fields as $field)
                                        @php
                                            $value = $this->fieldValue($ticket, $field);
                                            $selectedOption = $this->fieldOption($field, $value);
                                        @endphp

                                        <td wire:key="ticket-field-stages-{{ $ticket->id }}-{{ $field->id }}">
                                            @if (in_array($field->type->value, ['select', 'status'], true))
                                                <div class="ui-native-pill-select w-48" style="--ui-pill-color: {{ $selectedOption?->color ?: '#94a3b8' }}">
                                                    <span class="ui-native-pill-dot"></span>
                                                    <select wire:change="updateDynamicField({{ $ticket->id }}, {{ $field->id }}, $event.target.value)" class="ui-native-select ui-native-select-pill w-full">
                                                        <option value="">Selecione</option>
                                                        @foreach ($field->options as $option)
                                                            <option value="{{ $option->value }}" @selected((string) $value === (string) $option->value)>{{ $option->label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @elseif ($field->type->value === 'checkbox')
                                                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                                                    <input type="checkbox" @checked((bool) $value) wire:change="updateDynamicField({{ $ticket->id }}, {{ $field->id }}, $event.target.checked)" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                                                    Ativo
                                                </label>
                                            @elseif ($field->type->value === 'date')
                                                <input type="date" value="{{ $value }}" wire:change="updateDynamicField({{ $ticket->id }}, {{ $field->id }}, $event.target.value)" class="ui-input w-40" />
                                            @elseif ($field->type->value === 'number')
                                                <input type="number" value="{{ $value }}" wire:change="updateDynamicField({{ $ticket->id }}, {{ $field->id }}, $event.target.value)" class="ui-input w-32" />
                                            @elseif ($field->type->value === 'user')
                                                <div class="ui-native-pill-select w-48" style="--ui-pill-color: {{ $value ? '#3b82f6' : '#94a3b8' }}">
                                                    <span class="ui-native-pill-dot"></span>
                                                    <select wire:change="updateDynamicField({{ $ticket->id }}, {{ $field->id }}, $event.target.value)" class="ui-native-select ui-native-select-pill w-full">
                                                        <option value="">Selecione</option>
                                                        @foreach ($sectorUsers as $sectorUser)
                                                            <option value="{{ $sectorUser->id }}" @selected((string) $value === (string) $sectorUser->id)>{{ $sectorUser->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @else
                                                <input type="text" value="{{ $value }}" wire:change="updateDynamicField({{ $ticket->id }}, {{ $field->id }}, $event.target.value)" data-mask="auto" data-mask-label="{{ $field->name }}" data-mask-placeholder="{{ $field->placeholder }}" class="ui-input w-48" />
                                            @endif
                                        </td>
                                    @endforeach

                                    <td>
                                        <a href="{{ route('tickets.show', $ticket) }}" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm">Ver</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 7 + $fields->count() }}" class="px-4 py-8 text-center text-slate-500">Nenhum chamado neste grupo com os filtros atuais.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endforeach

    @if ($ungroupedTickets->isNotEmpty())
        <section
            class="ui-panel rounded-3xl border border-slate-200 bg-white shadow-sm"
            wire:key="ungrouped-stages"
            data-board-drop-zone="true"
            data-board-group-id="__null__"
            x-bind:class="{ 'ui-kanban-column-dragover': isDragTarget(null) }"
        >
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Sem etapa</h3>
                <p class="text-sm text-slate-500">Chamados sem etapa definida no quadro.</p>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach ($ungroupedTickets as $ticket)
                    @php
                        $slaMeta = $this->slaMeta($ticket);
                    @endphp

                    <div
                        class="ui-row-interactive flex items-center justify-between gap-4 px-6 py-4 hover:bg-slate-50"
                        wire:key="ticket-ungrouped-stages-{{ $ticket->id }}"
                        x-on:pointerdown="beginPointerDrag($event, {{ $ticket->id }}, null)"
                        x-bind:class="{
                            'ui-kanban-card-dragging': isDraggingTicket({{ $ticket->id }}),
                            'ui-kanban-card-lifted': isPointerCandidate({{ $ticket->id }})
                        }"
                    >
                        <div>
                            <p class="font-medium text-slate-900">{{ $ticket->title }}</p>
                            <p class="text-sm text-slate-500">{{ $ticket->requester?->name ?? 'Nao informado' }}</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <span class="ui-tone-chip ui-tone-chip-neutral">Sem etapa</span>
                            <span class="ui-tone-chip" style="--ui-pill-color: {{ $slaMeta['color'] }}">
                                <span class="ui-tone-dot"></span>
                                {{ $slaMeta['label'] }}
                            </span>
                            <a href="{{ route('tickets.show', $ticket) }}" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm" data-no-drag>Ver</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
