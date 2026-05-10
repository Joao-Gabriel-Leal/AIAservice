<div class="space-y-6">
    @foreach ($groups as $group)
        <section
            class="ui-panel ui-board-lane"
            style="--ui-lane-color: {{ $group->color ?: '#2563eb' }}"
            wire:key="group-stages-{{ $group->id }}"
            x-bind:class="{ 'ui-kanban-column-dragover': isDragTarget({{ $group->id }}) }"
        >
            <button
                type="button"
                wire:click="toggleGroup({{ $group->id }})"
                wire:loading.class="ui-loading"
                wire:target="toggleGroup"
                title="{{ ($collapsedGroups[$group->id] ?? false) ? 'Expandir etapa' : 'Recolher etapa' }}"
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

                        <p class="mt-1 text-sm text-slate-500">{{ $ticketsByGroup->get($group->id)?->count() ?? 0 }} de {{ $groupTicketTotals->get($group->id, 0) }} chamados nesta etapa</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <span class="ui-tone-chip" style="--ui-pill-color: {{ $group->color ?: '#2563eb' }}">
                        <span class="ui-tone-dot"></span>
                        {{ $groupTicketTotals->get($group->id, 0) }}
                    </span>

                    <span class="ui-collapse-indicator" aria-hidden="true">
                        @if ($collapsedGroups[$group->id] ?? false)
                            <flux:icon.chevron-right variant="micro" />
                        @else
                            <flux:icon.chevron-down variant="micro" />
                        @endif
                    </span>
                    <span class="sr-only">{{ ($collapsedGroups[$group->id] ?? false) ? 'Expandir etapa' : 'Recolher etapa' }}</span>
                </div>
            </button>

            @if (! ($collapsedGroups[$group->id] ?? false))
                <div class="hidden overflow-x-auto lg:block">
                    <table class="ui-board-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50/80 text-left text-slate-500">
                            <tr>
                                <th class="w-12"></th>
                                <th>Titulo</th>
                                <th class="ui-person-column-head">Solicitante</th>
                                <th>Responsavel</th>
                                <th>Prioridade</th>
                                <th>Etapa</th>
                                <th>SLA</th>
                                @foreach ($fields as $field)
                                    <th>{{ $field->name }}</th>
                                @endforeach
                                <th>Acoes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100" data-board-drop-zone="true" data-board-group-id="{{ $group->id }}">
                            @forelse ($ticketsByGroup->get($group->id, collect()) as $ticket)
                                @php
                                    $slaMeta = $this->slaMeta($ticket);
                                @endphp

                                <tr
                                    x-cloak
                                    x-show="isDropIndicator({{ $group->id }}, {{ $ticket->id }})"
                                    class="ui-board-drop-row"
                                    data-board-drop-placement="before"
                                    data-board-drop-before-ticket-id="{{ $ticket->id }}"
                                >
                                    <td colspan="{{ 8 + $fields->count() }}">
                                        <div class="ui-board-drop-indicator ui-board-drop-indicator-line"></div>
                                    </td>
                                </tr>

                                <tr
                                    class="ui-row-interactive ui-row-zebra {{ $loop->even ? 'ui-row-zebra-alt' : '' }} align-top"
                                    wire:key="ticket-row-stages-{{ $ticket->id }}"
                                    data-board-ticket-id="{{ $ticket->id }}"
                                    x-on:pointerdown="beginPointerDrag($event, {{ $ticket->id }}, {{ $ticket->ticket_group_id ?? 'null' }})"
                                    x-bind:class="{
                                        'ui-kanban-card-dragging': isDraggingTicket({{ $ticket->id }}),
                                        'ui-kanban-card-lifted': isPointerCandidate({{ $ticket->id }})
                                    }"
                                >
                                    <td class="w-12">
                                        <button
                                            type="button"
                                            wire:click.stop="toggleSubelements({{ $ticket->id }})"
                                            class="inline-flex size-8 items-center justify-center rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-600 transition hover:border-sky-300 hover:text-sky-700"
                                            title="{{ ($expandedSubelements[$ticket->id] ?? false) ? 'Recolher subelementos' : 'Expandir subelementos' }}"
                                            data-no-drag
                                        >
                                            {{ ($expandedSubelements[$ticket->id] ?? false) ? '-' : '+' }}
                                        </button>
                                    </td>
                                    <td>
                                        <div class="space-y-1">
                                            <div
                                                wire:key="ticket-title-stages-{{ $ticket->id }}"
                                                x-data="ticketInlineTitle({ ticketId: {{ $ticket->id }}, title: @js($ticket->title) })"
                                                class="ui-inline-title-editor"
                                                data-no-drag
                                            >
                                                <button
                                                    type="button"
                                                    x-show="! editing"
                                                    x-on:pointerdown.stop="$event.stopPropagation()"
                                                    x-on:click.stop.prevent="startEditing()"
                                                    x-bind:title="value"
                                                    class="ui-inline-title-display ui-inline-title-display-list"
                                                >
                                                    <span class="ui-inline-title-text" x-text="value">{{ $ticket->title }}</span>
                                                </button>

                                                <input
                                                    x-cloak
                                                    x-show="editing"
                                                    x-ref="input"
                                                    type="text"
                                                    x-model="value"
                                                    x-on:pointerdown.stop="$event.stopPropagation()"
                                                    x-on:click.stop="$event.stopPropagation()"
                                                    x-on:keydown.enter.prevent="saveTitle($wire)"
                                                    x-on:keydown.escape.prevent="cancelEditing()"
                                                    x-on:blur="saveTitle($wire)"
                                                    class="ui-input ui-inline-title-input w-72"
                                                />
                                            </div>

                                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-700">{{ $ticket->fullReference() }}</p>
                                            @if ($ticket->is_major_incident)
                                                <span class="mt-1 inline-flex rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-medium text-rose-700 ring-1 ring-inset ring-rose-200">
                                                    Incidente massivo - {{ $ticket->incident_children_count ?? 0 }}
                                                </span>
                                            @elseif ($ticket->major_incident_ticket_id)
                                                <span class="mt-1 inline-flex rounded-full bg-sky-50 px-2.5 py-1 text-[11px] font-medium text-sky-700 ring-1 ring-inset ring-sky-200">
                                                    Vinculado a incidente
                                                </span>
                                            @endif
                                            @if (($ticket->sub_tickets_count ?? 0) > 0)
                                                <span class="mt-1 inline-flex rounded-full bg-cyan-50 px-2.5 py-1 text-[11px] font-medium text-cyan-700 ring-1 ring-inset ring-cyan-200">
                                                    {{ $ticket->sub_tickets_count }} subelemento(s), {{ $ticket->open_sub_tickets_count ?? 0 }} aberto(s)
                                                </span>
                                            @endif
                                            <p class="text-xs text-slate-400">{{ $ticket->catalogItem?->name ?? 'Formulario padrao' }}</p>
                                        </div>
                                    </td>

                                    <td class="ui-person-column-cell">
                                        <x-person-reference :user="$ticket->requester" empty-label="Nao informado" />
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
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('tickets.show', $ticket) }}" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm">Ver</a>
                                            <button
                                                type="button"
                                                wire:click="deleteTicket({{ $ticket->id }})"
                                                data-confirm
                                                data-confirm-variant="danger"
                                                data-confirm-title="Remover chamado?"
                                                data-confirm-message="Tem certeza? O chamado e seus subelementos vao para a lixeira por 30 dias e podem ser restaurados nesse prazo."
                                                data-confirm-label="Sim, remover"
                                                wire:loading.attr="disabled"
                                                wire:loading.class="ui-loading"
                                                wire:target="deleteTicket"
                                                class="ui-action ui-action-danger rounded-xl px-3 py-2 text-sm"
                                            >
                                                <flux:icon.trash class="size-4" />
                                                <span>Excluir</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                @if ($expandedSubelements[$ticket->id] ?? false)
                                    <tr wire:key="subelements-stages-{{ $ticket->id }}">
                                        <td class="bg-slate-50/60"></td>
                                        <td colspan="{{ 7 + $fields->count() }}" class="bg-slate-50/60 px-4 py-4">
                                            <div class="border-l-4 border-cyan-400 bg-white shadow-sm">
                                                <div class="overflow-x-auto">
                                                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                                                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">
                                                            <tr>
                                                                <th class="px-4 py-3">Subelemento</th>
                                                                <th class="px-4 py-3">Responsavel</th>
                                                                <th class="px-4 py-3">Prioridade</th>
                                                                <th class="px-4 py-3">Etapa</th>
                                                                <th class="px-4 py-3">SLA</th>
                                                                @foreach ($fields as $field)
                                                                    <th class="px-4 py-3">{{ $field->name }}</th>
                                                                @endforeach
                                                                <th class="px-4 py-3">Acoes</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-slate-100">
                                                            @foreach ($ticket->subTickets as $subelement)
                                                                @php
                                                                    $subelementSlaMeta = $this->slaMeta($subelement);
                                                                @endphp
                                                                <tr wire:key="subelement-row-stages-{{ $subelement->id }}" class="align-top">
                                                                    <td class="px-4 py-3">
                                                                        <input type="text" value="{{ $subelement->title }}" wire:change="updateFixedField({{ $subelement->id }}, 'title', $event.target.value)" class="ui-input w-72" />
                                                                        <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-cyan-700">{{ $subelement->fullReference() }}</p>
                                                                    </td>
                                                                    <td class="px-4 py-3">
                                                                        <div class="ui-native-pill-select w-52" style="--ui-pill-color: {{ $subelement->assignee_id ? '#3b82f6' : '#94a3b8' }}">
                                                                            <span class="ui-native-pill-dot"></span>
                                                                            <select wire:change="updateFixedField({{ $subelement->id }}, 'assignee_id', $event.target.value)" class="ui-native-select ui-native-select-pill w-full">
                                                                                <option value="">Nao atribuido</option>
                                                                                @foreach ($assignees as $assignee)
                                                                                    <option value="{{ $assignee->id }}" @selected($subelement->assignee_id === $assignee->id)>{{ $assignee->name }}</option>
                                                                                @endforeach
                                                                            </select>
                                                                        </div>
                                                                    </td>
                                                                    <td class="px-4 py-3">
                                                                        <div class="ui-native-pill-select w-44" style="--ui-pill-color: {{ $this->priorityColor($subelement->priority) }}">
                                                                            <span class="ui-native-pill-dot"></span>
                                                                            <select wire:change="updateFixedField({{ $subelement->id }}, 'priority', $event.target.value)" class="ui-native-select ui-native-select-pill w-full">
                                                                                @foreach ($priorities as $priority)
                                                                                    <option value="{{ $priority->value }}" @selected($subelement->priority === $priority)>{{ $priority->label() }}</option>
                                                                                @endforeach
                                                                            </select>
                                                                        </div>
                                                                    </td>
                                                                    <td class="px-4 py-3">
                                                                        <div class="ui-native-pill-select w-48" style="--ui-pill-color: {{ $subelement->group?->color ?: '#94a3b8' }}">
                                                                            <span class="ui-native-pill-dot"></span>
                                                                            <select wire:change="updateFixedField({{ $subelement->id }}, 'ticket_group_id', $event.target.value)" class="ui-native-select ui-native-select-pill w-full">
                                                                                <option value="">Sem etapa</option>
                                                                                @foreach ($board->groups as $boardGroup)
                                                                                    <option value="{{ $boardGroup->id }}" @selected($subelement->ticket_group_id === $boardGroup->id)>{{ $boardGroup->name }}</option>
                                                                                @endforeach
                                                                            </select>
                                                                        </div>
                                                                    </td>
                                                                    <td class="px-4 py-3">
                                                                        <span class="ui-tone-chip" style="--ui-pill-color: {{ $subelementSlaMeta['color'] }}">
                                                                            <span class="ui-tone-dot"></span>
                                                                            {{ $subelementSlaMeta['label'] }}
                                                                        </span>
                                                                    </td>
                                                                    @foreach ($fields as $field)
                                                                        @php
                                                                            $value = $this->fieldValue($subelement, $field);
                                                                            $selectedOption = $this->fieldOption($field, $value);
                                                                        @endphp
                                                                        <td class="px-4 py-3" wire:key="subelement-field-stages-{{ $subelement->id }}-{{ $field->id }}">
                                                                            @if (in_array($field->type->value, ['select', 'status'], true))
                                                                                <div class="ui-native-pill-select w-48" style="--ui-pill-color: {{ $selectedOption?->color ?: '#94a3b8' }}">
                                                                                    <span class="ui-native-pill-dot"></span>
                                                                                    <select wire:change="updateDynamicField({{ $subelement->id }}, {{ $field->id }}, $event.target.value)" class="ui-native-select ui-native-select-pill w-full">
                                                                                        <option value="">Selecione</option>
                                                                                        @foreach ($field->options as $option)
                                                                                            <option value="{{ $option->value }}" @selected((string) $value === (string) $option->value)>{{ $option->label }}</option>
                                                                                        @endforeach
                                                                                    </select>
                                                                                </div>
                                                                            @elseif ($field->type->value === 'checkbox')
                                                                                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                                                                                    <input type="checkbox" @checked((bool) $value) wire:change="updateDynamicField({{ $subelement->id }}, {{ $field->id }}, $event.target.checked)" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                                                                                    Ativo
                                                                                </label>
                                                                            @elseif ($field->type->value === 'date')
                                                                                <input type="date" value="{{ $value }}" wire:change="updateDynamicField({{ $subelement->id }}, {{ $field->id }}, $event.target.value)" class="ui-input w-40" />
                                                                            @elseif ($field->type->value === 'number')
                                                                                <input type="number" value="{{ $value }}" wire:change="updateDynamicField({{ $subelement->id }}, {{ $field->id }}, $event.target.value)" class="ui-input w-32" />
                                                                            @elseif ($field->type->value === 'user')
                                                                                <div class="ui-native-pill-select w-48" style="--ui-pill-color: {{ $value ? '#3b82f6' : '#94a3b8' }}">
                                                                                    <span class="ui-native-pill-dot"></span>
                                                                                    <select wire:change="updateDynamicField({{ $subelement->id }}, {{ $field->id }}, $event.target.value)" class="ui-native-select ui-native-select-pill w-full">
                                                                                        <option value="">Selecione</option>
                                                                                        @foreach ($sectorUsers as $sectorUser)
                                                                                            <option value="{{ $sectorUser->id }}" @selected((string) $value === (string) $sectorUser->id)>{{ $sectorUser->name }}</option>
                                                                                        @endforeach
                                                                                    </select>
                                                                                </div>
                                                                            @else
                                                                                <input type="text" value="{{ $value }}" wire:change="updateDynamicField({{ $subelement->id }}, {{ $field->id }}, $event.target.value)" data-mask="auto" data-mask-label="{{ $field->name }}" data-mask-placeholder="{{ $field->placeholder }}" class="ui-input w-48" />
                                                                            @endif
                                                                        </td>
                                                                    @endforeach
                                                                    <td class="px-4 py-3">
                                                                        <div class="flex items-center gap-2">
                                                                            <a href="{{ route('tickets.show', $subelement) }}" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm">Ver</a>
                                                                            <button
                                                                                type="button"
                                                                                wire:click="deleteTicket({{ $subelement->id }})"
                                                                                data-confirm
                                                                                data-confirm-variant="danger"
                                                                                data-confirm-title="Remover subelemento?"
                                                                                data-confirm-message="Tem certeza? O subelemento vai para a lixeira por 30 dias e podera ser restaurado nesse prazo."
                                                                                data-confirm-label="Sim, remover"
                                                                                wire:loading.attr="disabled"
                                                                                wire:loading.class="ui-loading"
                                                                                wire:target="deleteTicket"
                                                                                class="ui-action ui-action-danger rounded-xl px-3 py-2 text-sm"
                                                                            >
                                                                                <flux:icon.trash class="size-4" />
                                                                                <span>Excluir</span>
                                                                            </button>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                            <tr>
                                                                <td colspan="{{ 6 + $fields->count() }}" class="px-4 py-3">
                                                                    <form wire:submit.prevent="createSubelement({{ $ticket->id }})" class="flex flex-wrap items-start gap-3">
                                                                        <div class="min-w-[280px] flex-1">
                                                                            <input wire:model="newSubelementTitles.{{ $ticket->id }}" type="text" class="ui-input w-full" placeholder="+ Adicionar subelemento" />
                                                                            @error("newSubelementTitles.{$ticket->id}") <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                                                        </div>
                                                                        <button type="submit" class="ui-action ui-action-primary rounded-xl px-3 py-2 text-sm">Adicionar</button>
                                                                    </form>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr
                                    x-cloak
                                    x-show="isDropAtEmpty({{ $group->id }})"
                                    class="ui-board-drop-row"
                                    data-board-drop-placement="top"
                                >
                                    <td colspan="{{ 8 + $fields->count() }}">
                                        <div class="ui-board-drop-indicator ui-board-drop-indicator-line"></div>
                                    </td>
                                </tr>

                                <tr>
                                    <td colspan="{{ 8 + $fields->count() }}" class="px-4 py-8 text-center text-slate-500">Nenhum chamado neste grupo com os filtros atuais.</td>
                                </tr>
                            @endforelse

                            <tr
                                x-cloak
                                x-show="isDropAtEnd({{ $group->id }})"
                                class="ui-board-drop-row"
                                data-board-drop-placement="end"
                            >
                                <td colspan="{{ 8 + $fields->count() }}">
                                    <div class="ui-board-drop-indicator ui-board-drop-indicator-line"></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="ticket-mobile-stage-list lg:hidden">
                    @forelse ($ticketsByGroup->get($group->id, collect()) as $ticket)
                        @php
                            $slaMeta = $this->slaMeta($ticket);
                        @endphp

                        <article class="ticket-mobile-card ticket-mobile-card-editable" wire:key="ticket-card-stages-mobile-{{ $ticket->id }}">
                            <div class="ticket-mobile-card-header">
                                <div class="min-w-0 flex-1">
                                    <p class="ticket-mobile-reference">{{ $ticket->fullReference() }}</p>
                                    <div
                                        wire:key="ticket-title-stages-mobile-{{ $ticket->id }}"
                                        x-data="ticketInlineTitle({ ticketId: {{ $ticket->id }}, title: @js($ticket->title) })"
                                        class="ui-inline-title-editor"
                                        data-no-drag
                                    >
                                        <button
                                            type="button"
                                            x-show="! editing"
                                            x-on:pointerdown.stop="$event.stopPropagation()"
                                            x-on:click.stop.prevent="startEditing()"
                                            x-bind:title="value"
                                            class="ui-inline-title-display ui-inline-title-display-mobile"
                                        >
                                            <span class="ui-inline-title-text" x-text="value">{{ $ticket->title }}</span>
                                        </button>

                                        <input
                                            x-cloak
                                            x-show="editing"
                                            x-ref="input"
                                            type="text"
                                            x-model="value"
                                            x-on:pointerdown.stop="$event.stopPropagation()"
                                            x-on:click.stop="$event.stopPropagation()"
                                            x-on:keydown.enter.prevent="saveTitle($wire)"
                                            x-on:keydown.escape.prevent="cancelEditing()"
                                            x-on:blur="saveTitle($wire)"
                                            class="ui-input ui-inline-title-input ticket-mobile-title-input"
                                        />
                                    </div>
                                    <p class="ticket-mobile-subtitle">{{ $ticket->catalogItem?->name ?? 'Formulario padrao' }}</p>
                                </div>

                                <div class="flex shrink-0 flex-col gap-2">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="ui-action ui-action-secondary ticket-mobile-open-button">
                                        Ver
                                    </a>
                                    <button
                                        type="button"
                                        wire:click="deleteTicket({{ $ticket->id }})"
                                        data-confirm
                                        data-confirm-variant="danger"
                                        data-confirm-title="Remover chamado?"
                                        data-confirm-message="Tem certeza? O chamado e seus subelementos vao para a lixeira por 30 dias e podem ser restaurados nesse prazo."
                                        data-confirm-label="Sim, remover"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="ui-loading"
                                        wire:target="deleteTicket"
                                        class="ui-action ui-action-danger ticket-mobile-open-button"
                                    >
                                        Excluir
                                    </button>
                                </div>
                            </div>

                            <div class="ticket-mobile-chip-row">
                                <span class="ticket-mobile-chip" style="--ticket-mobile-chip-color: {{ $ticket->group?->color ?: '#94a3b8' }}">
                                    {{ $ticket->group?->name ?? 'Sem etapa' }}
                                </span>
                                <span class="ticket-mobile-chip" style="--ticket-mobile-chip-color: {{ $slaMeta['color'] }}">
                                    {{ $slaMeta['label'] }}
                                </span>
                                @if ($ticket->is_major_incident)
                                    <span class="ticket-mobile-chip ticket-mobile-chip-danger">
                                        Incidente - {{ $ticket->incident_children_count ?? 0 }}
                                    </span>
                                @elseif ($ticket->major_incident_ticket_id)
                                    <span class="ticket-mobile-chip ticket-mobile-chip-info">
                                        Vinculado
                                    </span>
                                @endif
                                @if (($ticket->sub_tickets_count ?? 0) > 0)
                                    <span class="ticket-mobile-chip ticket-mobile-chip-info">
                                        {{ $ticket->sub_tickets_count }} subelemento(s)
                                    </span>
                                @endif
                            </div>

                            <dl class="ticket-mobile-meta-grid">
                                <div>
                                    <dt>Solicitante</dt>
                                    <dd><x-person-reference :user="$ticket->requester" empty-label="Nao informado" /></dd>
                                </div>
                                <div>
                                    <dt>SLA</dt>
                                    <dd>
                                        <span>1a resp.: {{ $ticket->first_response_due_at?->format('d/m H:i') ?? '-' }}</span>
                                        <span>Resol.: {{ $ticket->resolution_due_at?->format('d/m H:i') ?? '-' }}</span>
                                    </dd>
                                </div>
                            </dl>

                            <div class="ticket-mobile-control-grid">
                                <label>
                                    <span>Responsavel</span>
                                    <div class="ui-native-pill-select" style="--ui-pill-color: {{ $ticket->assignee_id ? '#3b82f6' : '#94a3b8' }}">
                                        <span class="ui-native-pill-dot"></span>
                                        <select wire:change="updateFixedField({{ $ticket->id }}, 'assignee_id', $event.target.value)" class="ui-native-select ui-native-select-pill w-full">
                                            <option value="">Nao atribuido</option>
                                            @foreach ($assignees as $assignee)
                                                <option value="{{ $assignee->id }}" @selected($ticket->assignee_id === $assignee->id)>{{ $assignee->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </label>

                                <label>
                                    <span>Prioridade</span>
                                    <div class="ui-native-pill-select" style="--ui-pill-color: {{ $this->priorityColor($ticket->priority) }}">
                                        <span class="ui-native-pill-dot"></span>
                                        <select wire:change="updateFixedField({{ $ticket->id }}, 'priority', $event.target.value)" class="ui-native-select ui-native-select-pill w-full">
                                            @foreach ($priorities as $priority)
                                                <option value="{{ $priority->value }}" @selected($ticket->priority === $priority)>{{ $priority->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </label>

                                <label>
                                    <span>Etapa</span>
                                    <div class="ui-native-pill-select" style="--ui-pill-color: {{ $ticket->group?->color ?: '#94a3b8' }}">
                                        <span class="ui-native-pill-dot"></span>
                                        <select x-on:change="changeGroup({{ $ticket->id }}, $event.target.value)" class="ui-native-select ui-native-select-pill w-full">
                                            <option value="">Sem etapa</option>
                                            @foreach ($board->groups as $boardGroup)
                                                <option value="{{ $boardGroup->id }}" @selected($ticket->ticket_group_id === $boardGroup->id)>{{ $boardGroup->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </label>
                            </div>

                            @if ($fields->isNotEmpty())
                                <details class="ticket-mobile-details">
                                    <summary>Campos do quadro</summary>
                                    <div class="ticket-mobile-control-grid">
                                        @foreach ($fields as $field)
                                            @php
                                                $value = $this->fieldValue($ticket, $field);
                                                $selectedOption = $this->fieldOption($field, $value);
                                            @endphp

                                            <label wire:key="ticket-field-stages-mobile-{{ $ticket->id }}-{{ $field->id }}">
                                                <span>{{ $field->name }}</span>

                                                @if (in_array($field->type->value, ['select', 'status'], true))
                                                    <div class="ui-native-pill-select" style="--ui-pill-color: {{ $selectedOption?->color ?: '#94a3b8' }}">
                                                        <span class="ui-native-pill-dot"></span>
                                                        <select wire:change="updateDynamicField({{ $ticket->id }}, {{ $field->id }}, $event.target.value)" class="ui-native-select ui-native-select-pill w-full">
                                                            <option value="">Selecione</option>
                                                            @foreach ($field->options as $option)
                                                                <option value="{{ $option->value }}" @selected((string) $value === (string) $option->value)>{{ $option->label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                @elseif ($field->type->value === 'checkbox')
                                                    <span class="ticket-mobile-checkbox">
                                                        <input type="checkbox" @checked((bool) $value) wire:change="updateDynamicField({{ $ticket->id }}, {{ $field->id }}, $event.target.checked)" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                                                        Ativo
                                                    </span>
                                                @elseif ($field->type->value === 'date')
                                                    <input type="date" value="{{ $value }}" wire:change="updateDynamicField({{ $ticket->id }}, {{ $field->id }}, $event.target.value)" class="ui-input w-full" />
                                                @elseif ($field->type->value === 'number')
                                                    <input type="number" value="{{ $value }}" wire:change="updateDynamicField({{ $ticket->id }}, {{ $field->id }}, $event.target.value)" class="ui-input w-full" />
                                                @elseif ($field->type->value === 'user')
                                                    <div class="ui-native-pill-select" style="--ui-pill-color: {{ $value ? '#3b82f6' : '#94a3b8' }}">
                                                        <span class="ui-native-pill-dot"></span>
                                                        <select wire:change="updateDynamicField({{ $ticket->id }}, {{ $field->id }}, $event.target.value)" class="ui-native-select ui-native-select-pill w-full">
                                                            <option value="">Selecione</option>
                                                            @foreach ($sectorUsers as $sectorUser)
                                                                <option value="{{ $sectorUser->id }}" @selected((string) $value === (string) $sectorUser->id)>{{ $sectorUser->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                @else
                                                    <input type="text" value="{{ $value }}" wire:change="updateDynamicField({{ $ticket->id }}, {{ $field->id }}, $event.target.value)" data-mask="auto" data-mask-label="{{ $field->name }}" data-mask-placeholder="{{ $field->placeholder }}" class="ui-input w-full" />
                                                @endif
                                            </label>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </article>
                    @empty
                        <div class="ticket-mobile-empty">Nenhum chamado neste grupo com os filtros atuais.</div>
                    @endforelse
                </div>
                @if (($ticketsByGroup->get($group->id)?->count() ?? 0) < $groupTicketTotals->get($group->id, 0))
                    <div class="border-t border-slate-100 px-6 py-4 text-center">
                        <button type="button" wire:click="loadMoreColumn('{{ $group->id }}')" class="ui-action ui-action-secondary rounded-2xl px-4 py-2 text-sm">
                            Carregar mais chamados desta etapa
                        </button>
                    </div>
                @endif
            @endif
        </section>
    @endforeach

    @if ($ungroupedTickets->isNotEmpty() || $ungroupedTicketsTotal > 0)
        <section
            class="ui-panel rounded-3xl border border-slate-200 bg-white shadow-sm"
            wire:key="ungrouped-stages"
            x-bind:class="{ 'ui-kanban-column-dragover': isDragTarget(null) }"
        >
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Sem etapa</h3>
                <p class="text-sm text-slate-500">{{ $ungroupedTickets->count() }} de {{ $ungroupedTicketsTotal }} chamados sem etapa definida no quadro.</p>
            </div>

            <div class="divide-y divide-slate-100" data-board-drop-zone="true" data-board-group-id="__null__">
                @foreach ($ungroupedTickets as $ticket)
                    @php
                        $slaMeta = $this->slaMeta($ticket);
                    @endphp

                    <div
                        x-cloak
                        x-show="isDropIndicator(null, {{ $ticket->id }})"
                        class="ui-board-drop-indicator ui-board-drop-indicator-line"
                        data-board-drop-placement="before"
                        data-board-drop-before-ticket-id="{{ $ticket->id }}"
                    ></div>

                    <div
                        class="ui-row-interactive ui-row-zebra {{ $loop->even ? 'ui-row-zebra-alt' : '' }} flex flex-col items-stretch gap-4 px-4 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between"
                        wire:key="ticket-ungrouped-stages-{{ $ticket->id }}"
                        data-board-ticket-id="{{ $ticket->id }}"
                        x-on:pointerdown="if (! window.matchMedia('(max-width: 1023px)').matches) beginPointerDrag($event, {{ $ticket->id }}, null)"
                        x-bind:class="{
                            'ui-kanban-card-dragging': isDraggingTicket({{ $ticket->id }}),
                            'ui-kanban-card-lifted': isPointerCandidate({{ $ticket->id }})
                        }"
                    >
                        <div>
                            <p class="font-medium text-slate-900">{{ $ticket->title }}</p>
                            <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-700">{{ $ticket->fullReference() }}</p>
                            <p class="text-sm text-slate-500">{{ $ticket->requester?->name ?? 'Nao informado' }}</p>
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center" data-no-drag>
                            <span class="ui-tone-chip ui-tone-chip-neutral">Sem etapa</span>
                            <span class="ui-tone-chip" style="--ui-pill-color: {{ $slaMeta['color'] }}">
                                <span class="ui-tone-dot"></span>
                                {{ $slaMeta['label'] }}
                            </span>
                            <label class="ticket-mobile-inline-select lg:hidden">
                                <span>Etapa</span>
                                <select x-on:change="changeGroup({{ $ticket->id }}, $event.target.value)" class="ui-native-select w-full">
                                    <option value="">Sem etapa</option>
                                    @foreach ($board->groups as $boardGroup)
                                        <option value="{{ $boardGroup->id }}">{{ $boardGroup->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <a href="{{ route('tickets.show', $ticket) }}" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm" data-no-drag>Ver</a>
                            <button
                                type="button"
                                wire:click="deleteTicket({{ $ticket->id }})"
                                data-confirm
                                data-confirm-variant="danger"
                                data-confirm-title="Remover chamado?"
                                data-confirm-message="Tem certeza? O chamado e seus subelementos vao para a lixeira por 30 dias e podem ser restaurados nesse prazo."
                                data-confirm-label="Sim, remover"
                                wire:loading.attr="disabled"
                                wire:loading.class="ui-loading"
                                wire:target="deleteTicket"
                                class="ui-action ui-action-danger rounded-xl px-3 py-2 text-sm"
                                data-no-drag
                            >
                                <flux:icon.trash class="size-4" />
                                <span>Excluir</span>
                            </button>
                        </div>
                    </div>
                @endforeach

                <div
                    x-cloak
                    x-show="isDropAtEnd(null)"
                    class="ui-board-drop-indicator ui-board-drop-indicator-line"
                    data-board-drop-placement="end"
                ></div>
            </div>
            @if ($ungroupedTickets->count() < $ungroupedTicketsTotal)
                <div class="border-t border-slate-100 px-6 py-4 text-center">
                    <button type="button" wire:click="loadMoreColumn('none')" class="ui-action ui-action-secondary rounded-2xl px-4 py-2 text-sm">
                        Carregar mais chamados sem etapa
                    </button>
                </div>
            @endif
        </section>
    @endif
</div>
