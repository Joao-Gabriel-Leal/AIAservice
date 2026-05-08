<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-slate-900">{{ $board->name }}</p>
            <p class="text-sm text-slate-500">{{ $board->description ?: 'Arraste cards entre as etapas e edite prioridade, responsavel e campos direto no quadro.' }}</p>
        </div>

        <x-sector-badge :sector="$board->sector" mode="chip">{{ $board->sector->company?->name }}</x-sector-badge>
    </div>

    <div class="ui-kanban-grid">
        @foreach ($kanbanColumns as $column)
            @php
                $columnGroup = $column['group'];
                $columnTickets = $column['tickets'];
                $targetGroupId = $column['targetGroupId'];
                $laneColor = $columnGroup?->color ?: '#94a3b8';
                $totalTickets = $column['total'] ?? $columnTickets->count();
            @endphp

            <section
                class="ui-panel ui-kanban-column"
                style="--ui-lane-color: {{ $laneColor }}"
                wire:key="kanban-column-{{ $column['key'] }}"
                data-kanban-column="true"
                x-bind:class="{ 'ui-kanban-column-dragover': isDragTarget({{ $targetGroupId ?? 'null' }}) }"
            >
                <div class="ui-kanban-column-header">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-semibold text-slate-900">{{ $columnGroup?->name ?? 'Sem etapa' }}</h3>

                                @if ($columnGroup?->is_default)
                                    <span class="ui-tone-chip ui-tone-chip-neutral">Entrada</span>
                                @endif

                                @if ($columnGroup?->is_closed)
                                    <span class="ui-tone-chip" style="--ui-pill-color: {{ $laneColor }}">
                                        <span class="ui-tone-dot"></span>
                                        Final
                                    </span>
                                @endif
                            </div>

                            <p class="mt-1 text-sm text-slate-500">{{ $columnTickets->count() }} de {{ $totalTickets }} chamado(s)</p>
                        </div>

                        <span class="ui-tone-chip" style="--ui-pill-color: {{ $laneColor }}">
                            <span class="ui-tone-dot"></span>
                            {{ $totalTickets }}
                        </span>
                    </div>
                </div>

                <div
                    class="ui-kanban-card-list"
                    data-board-drop-zone="true"
                    data-board-group-id="{{ $targetGroupId === null ? '__null__' : $targetGroupId }}"
                >
                    @forelse ($columnTickets as $ticket)
                        @php
                            $slaMeta = $this->slaMeta($ticket);
                        @endphp

                        <div
                            x-cloak
                            x-show="isDropIndicator({{ $targetGroupId ?? 'null' }}, {{ $ticket->id }})"
                            class="ui-board-drop-indicator ui-board-drop-indicator-card"
                            data-board-drop-placement="before"
                            data-board-drop-before-ticket-id="{{ $ticket->id }}"
                        ></div>

                        <article
                            class="ui-panel ui-kanban-card rounded-[1.6rem] border border-slate-200 bg-white p-4 shadow-sm"
                            wire:key="ticket-card-kanban-{{ $ticket->id }}"
                            data-board-ticket-id="{{ $ticket->id }}"
                            x-on:pointerdown="beginPointerDrag($event, {{ $ticket->id }}, {{ $ticket->ticket_group_id ?? 'null' }})"
                            x-bind:class="{
                                'ui-kanban-card-dragging': isDraggingTicket({{ $ticket->id }}),
                                'ui-kanban-card-lifted': isPointerCandidate({{ $ticket->id }})
                            }"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div wire:key="ticket-title-kanban-{{ $ticket->id }}">
                                        <input type="text" value="{{ $ticket->title }}" wire:change="updateFixedField({{ $ticket->id }}, 'title', $event.target.value)" class="ui-input w-full text-sm font-medium" />
                                    </div>

                                    <div class="mt-3 flex flex-wrap items-center gap-2">
                                        <span class="ui-tone-chip ui-tone-chip-neutral">{{ $ticket->requester?->name ?? 'Nao informado' }}</span>
                                        <span class="ui-tone-chip" style="--ui-pill-color: {{ $slaMeta['color'] }}">
                                            <span class="ui-tone-dot"></span>
                                            {{ $slaMeta['label'] }}
                                        </span>
                                    </div>
                                </div>

                                <a href="{{ route('tickets.show', $ticket) }}" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm" data-no-drag>
                                    Ver
                                </a>
                            </div>

                            <div class="mt-3 grid gap-2 text-xs text-slate-500">
                                <div>Catalogo: {{ $ticket->catalogItem?->name ?? 'Formulario padrao' }}</div>
                                <div>1a resp.: {{ $ticket->first_response_due_at?->format('d/m H:i') ?? '-' }}</div>
                                <div>Resol.: {{ $ticket->resolution_due_at?->format('d/m H:i') ?? '-' }}</div>
                            </div>

                            <div class="mt-4 grid gap-3" wire:key="ticket-controls-kanban-{{ $ticket->id }}" data-no-drag>
                                <div class="ui-native-pill-select" style="--ui-pill-color: {{ $ticket->group?->color ?: '#94a3b8' }}">
                                    <span class="ui-native-pill-dot"></span>
                                    <select x-on:change="changeGroup({{ $ticket->id }}, $event.target.value)" class="ui-native-select ui-native-select-pill w-full">
                                        <option value="">Sem etapa</option>
                                        @foreach ($board->groups as $boardGroup)
                                            <option value="{{ $boardGroup->id }}" @selected($ticket->ticket_group_id === $boardGroup->id)>{{ $boardGroup->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="ui-native-pill-select" style="--ui-pill-color: {{ $ticket->assignee_id ? '#3b82f6' : '#94a3b8' }}">
                                    <span class="ui-native-pill-dot"></span>
                                    <select wire:change="updateFixedField({{ $ticket->id }}, 'assignee_id', $event.target.value)" class="ui-native-select ui-native-select-pill w-full">
                                        <option value="">Nao atribuido</option>
                                        @foreach ($assignees as $assignee)
                                            <option value="{{ $assignee->id }}" @selected($ticket->assignee_id === $assignee->id)>{{ $assignee->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="ui-native-pill-select" style="--ui-pill-color: {{ $this->priorityColor($ticket->priority) }}">
                                    <span class="ui-native-pill-dot"></span>
                                    <select wire:change="updateFixedField({{ $ticket->id }}, 'priority', $event.target.value)" class="ui-native-select ui-native-select-pill w-full">
                                        @foreach ($priorities as $priority)
                                            <option value="{{ $priority->value }}" @selected($ticket->priority === $priority)>{{ $priority->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            @if ($fields->isNotEmpty())
                                <div class="mt-4 space-y-3 border-t border-slate-100 pt-4" data-no-drag>
                                    @foreach ($fields as $field)
                                        @php
                                            $value = $this->fieldValue($ticket, $field);
                                            $selectedOption = $this->fieldOption($field, $value);
                                        @endphp

                                        <label class="block text-sm text-slate-600" wire:key="ticket-field-kanban-{{ $ticket->id }}-{{ $field->id }}">
                                            <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">{{ $field->name }}</span>

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
                                                <label class="inline-flex items-center gap-2 rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-700">
                                                    <input type="checkbox" @checked((bool) $value) wire:change="updateDynamicField({{ $ticket->id }}, {{ $field->id }}, $event.target.checked)" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                                                    Campo marcado
                                                </label>
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
                            @endif

                            <p class="mt-4 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                                Arraste o card para mover de coluna
                            </p>
                        </article>
                    @empty
                        <div
                            x-cloak
                            x-show="isDropAtEmpty({{ $targetGroupId ?? 'null' }})"
                            class="ui-board-drop-indicator ui-board-drop-indicator-card ui-board-drop-indicator-empty"
                            data-board-drop-placement="top"
                        ></div>

                        <div class="rounded-[1.45rem] border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                            Nenhum chamado nesta coluna.
                        </div>
                    @endforelse

                    <div
                        x-cloak
                        x-show="isDropAtEnd({{ $targetGroupId ?? 'null' }})"
                        class="ui-board-drop-indicator ui-board-drop-indicator-card"
                        data-board-drop-placement="end"
                    ></div>

                    @if ($column['hasMore'] ?? false)
                        <button
                            type="button"
                            wire:click="loadMoreColumn('{{ $targetGroupId === null ? 'none' : $targetGroupId }}')"
                            class="ui-action ui-action-secondary w-full rounded-2xl px-4 py-3 text-sm"
                        >
                            Carregar mais
                        </button>
                    @endif
                </div>
            </section>
        @endforeach
    </div>
</div>
