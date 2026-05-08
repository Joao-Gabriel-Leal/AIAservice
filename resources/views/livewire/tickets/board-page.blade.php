<div
    class="space-y-6"
    x-data="ticketBoard({
        selectedSectorId: $wire.entangle('selectedSectorId').live,
        viewMode: $wire.entangle('viewMode').live,
    })"
    x-init="init()"
    x-bind:class="{ 'ui-kanban-drag-active': drag.active }"
    x-on:pointermove.window="updatePointerDrag($event)"
    x-on:pointerup.window="endPointerDrag($event)"
    x-on:pointercancel.window="cancelPointerDrag()"
    x-on:scroll.window="refreshDragTarget()"
    x-on:resize.window="refreshDragTarget()"
    x-on:keydown.escape.window="cancelPointerDrag()"
>
    <x-portal.page-intro
        eyebrow="Quadro operacional"
        :title="$board?->name ?? 'Quadro de chamados'"
        :description="$board?->description ?: 'Acompanhe os chamados agrupados por etapa.'"
    >
        <x-slot:actions>
            <a href="{{ route('tickets.central') }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">
                Central de formularios
            </a>

            @if ($board && (auth()->user()->isSuperAdmin() || auth()->user()->isSectorAdmin($board->sector_id)))
                <a href="{{ route('tickets.settings', $board) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">
                    Configurar quadro
                </a>
            @endif
        </x-slot:actions>
        <x-slot:meta>
            @if ($board)
                <x-sector-badge :sector="$board->sector" mode="chip">{{ $board->sector->company?->name }}</x-sector-badge>
                <span class="portal-chip">{{ $groups->count() }} etapa(s)</span>
            @else
                <span class="portal-chip">Sem setor disponivel</span>
            @endif
        </x-slot:meta>
    </x-portal.page-intro>

    <x-portal.filter-bar title="Visualizacao do quadro" description="Alterne entre lista e kanban sem sair da mesma tela operacional.">
        @if (! $board)
            <p class="text-sm font-semibold text-slate-900">Nenhum setor disponivel para exibir o quadro.</p>
        @endif
        
        <div class="{{ $board ? '' : 'mt-4' }} portal-toolbar-group sm:justify-end">
            @if ($board)
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-1">
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            x-on:click="$wire.setViewMode('list')"
                            class="rounded-xl px-4 py-2 text-sm font-medium transition"
                            :class="viewMode === 'list' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
                        >
                            Lista
                        </button>
                        <button
                            type="button"
                            x-on:click="$wire.setViewMode('kanban')"
                            class="rounded-xl px-4 py-2 text-sm font-medium transition"
                            :class="viewMode === 'kanban' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
                        >
                            Kanban
                        </button>
                    </div>
                </div>
            @endif

            @if ($sectorOptions->count() > 1)
                <label class="text-sm text-slate-600">
                    <span class="mb-1 block font-medium">Setor</span>
                    <select wire:model.live="selectedSectorId" class="ui-native-select min-w-[240px]">
                        @foreach ($sectorOptions as $sectorOption)
                            <option value="{{ $sectorOption->id }}">{{ $sectorOption->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
        </div>
    </x-portal.filter-bar>

    @if (! $board)
        <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-slate-500">
            Crie um setor para comecar a organizar o quadro de chamados.
        </div>
    @else
        <div x-show="viewMode === 'list'" x-cloak class="space-y-6">
            @foreach ($groups as $group)
                <section class="ui-panel ui-board-lane" style="--ui-lane-color: {{ $group->color ?: '#2563eb' }}" wire:key="group-list-{{ $group->id }}">
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
                                        <th class="ui-person-column-head">Solicitante</th>
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

                                        <tr class="ui-row-interactive align-top hover:bg-slate-50" wire:key="ticket-row-list-{{ $ticket->id }}">
                                            <td>
                                                <div class="space-y-1">
                                                    @if ($canUpdate)
                                                        <div wire:key="ticket-title-list-{{ $ticket->id }}">
                                                            <input type="text" value="{{ $ticket->title }}" wire:change="updateFixedField({{ $ticket->id }}, 'title', $event.target.value)" class="ui-input w-72" />
                                                        </div>
                                                    @else
                                                        <p class="font-medium text-slate-900">{{ $ticket->title }}</p>
                                                    @endif

                                                    <p class="text-xs text-slate-400">{{ $ticket->catalogItem?->name ?? 'Formulario padrao' }}</p>
                                                </div>
                                            </td>

                                            <td class="ui-person-column-cell">
                                                <x-person-reference :user="$ticket->requester" empty-label="Nao informado" />
                                            </td>

                                            <td @class(['ui-person-column-cell' => ! $canUpdate])>
                                                @if ($canUpdate)
                                                    <div class="ui-native-pill-select w-52" style="--ui-pill-color: {{ $ticket->assignee_id ? '#3b82f6' : '#94a3b8' }}" wire:key="ticket-assignee-list-{{ $ticket->id }}">
                                                        <span class="ui-native-pill-dot"></span>
                                                        <select wire:change="updateFixedField({{ $ticket->id }}, 'assignee_id', $event.target.value)" class="ui-native-select ui-native-select-pill w-full">
                                                            <option value="">Nao atribuido</option>
                                                            @foreach ($assignees as $assignee)
                                                                <option value="{{ $assignee->id }}" @selected($ticket->assignee_id === $assignee->id)>{{ $assignee->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                @else
                                                    <x-person-reference :user="$ticket->assignee" empty-label="Nao atribuido" />
                                                @endif
                                            </td>

                                            <td>
                                                @if ($canUpdate)
                                                    <div class="ui-native-pill-select w-44" style="--ui-pill-color: {{ $this->priorityColor($ticket->priority) }}" wire:key="ticket-priority-list-{{ $ticket->id }}">
                                                        <span class="ui-native-pill-dot"></span>
                                                        <select wire:change="updateFixedField({{ $ticket->id }}, 'priority', $event.target.value)" class="ui-native-select ui-native-select-pill w-full">
                                                            @foreach ($priorities as $priority)
                                                                <option value="{{ $priority->value }}" @selected($ticket->priority === $priority)>{{ $priority->label() }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                @else
                                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $ticket->priority?->badgeColor() }}">{{ $ticket->priority?->label() }}</span>
                                                @endif
                                            </td>

                                            <td>
                                                @if ($canUpdate)
                                                    <div class="ui-native-pill-select w-48" style="--ui-pill-color: {{ $ticket->group?->color ?: '#94a3b8' }}" wire:key="ticket-group-list-{{ $ticket->id }}">
                                                        <span class="ui-native-pill-dot"></span>
                                                        <select x-on:change="changeGroup({{ $ticket->id }}, $event.target.value)" class="ui-native-select ui-native-select-pill w-full">
                                                            <option value="">Sem etapa</option>
                                                            @foreach ($board->groups as $boardGroup)
                                                                <option value="{{ $boardGroup->id }}" @selected($ticket->ticket_group_id === $boardGroup->id)>{{ $boardGroup->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                @else
                                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium text-white" style="background-color: {{ $ticket->group?->color ?: '#64748b' }}">
                                                        {{ $ticket->group?->name ?? 'Sem etapa' }}
                                                    </span>
                                                @endif
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

                                                <td wire:key="ticket-field-list-{{ $ticket->id }}-{{ $field->id }}">
                                                    @if (! $canUpdate)
                                                        <span class="text-slate-600">{{ $field->type->value === 'checkbox' ? ($value ? 'Sim' : 'Nao') : ($value ?: '-') }}</span>
                                                    @elseif (in_array($field->type->value, ['select', 'status'], true))
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
                                            <td colspan="{{ 7 + $fields->count() }}" class="px-4 py-8 text-center text-slate-500">Nenhum chamado neste grupo ainda.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endforeach

            @if ($ungroupedTickets->isNotEmpty())
                <section class="ui-panel rounded-3xl border border-slate-200 bg-white shadow-sm" wire:key="ungrouped-list">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h3 class="text-lg font-semibold text-slate-900">Sem etapa</h3>
                        <p class="text-sm text-slate-500">Chamados sem etapa definida no quadro.</p>
                    </div>

                    <div class="divide-y divide-slate-100">
                        @foreach ($ungroupedTickets as $ticket)
                            @php
                                $slaMeta = $this->slaMeta($ticket);
                            @endphp

                            <a href="{{ route('tickets.show', $ticket) }}" class="ui-row-interactive flex items-center justify-between gap-4 px-6 py-4 hover:bg-slate-50" wire:key="ticket-ungrouped-list-{{ $ticket->id }}">
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
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <div x-show="viewMode === 'kanban'" x-cloak class="space-y-6">
            <div class="ui-kanban-grid">
                @foreach ($kanbanColumns as $column)
                    @php
                        $columnGroup = $column['group'];
                        $columnTickets = $column['tickets'];
                        $targetGroupId = $column['targetGroupId'];
                        $laneColor = $columnGroup?->color ?: '#94a3b8';
                    @endphp

                    <section
                        class="ui-panel ui-kanban-column"
                        style="--ui-lane-color: {{ $laneColor }}"
                        wire:key="kanban-column-{{ $column['key'] }}"
                        data-kanban-column="true"
                        data-kanban-group-id="{{ $targetGroupId === null ? '__null__' : $targetGroupId }}"
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

                                    <p class="mt-1 text-sm text-slate-500">{{ $columnTickets->count() }} chamado(s)</p>
                                </div>

                                <span class="ui-tone-chip" style="--ui-pill-color: {{ $laneColor }}">
                                    <span class="ui-tone-dot"></span>
                                    {{ $columnTickets->count() }}
                                </span>
                            </div>
                        </div>

                        <div class="ui-kanban-card-list">
                            @forelse ($columnTickets as $ticket)
                                @php
                                    $slaMeta = $this->slaMeta($ticket);
                                @endphp

                                <article
                                    class="ui-panel ui-kanban-card rounded-[1.6rem] border border-slate-200 bg-white p-4 shadow-sm"
                                    wire:key="ticket-card-kanban-{{ $ticket->id }}"
                                    @if ($canUpdate)
                                        x-on:pointerdown="beginPointerDrag($event, {{ $ticket->id }}, {{ $ticket->ticket_group_id ?? 'null' }})"
                                    @endif
                                    x-bind:class="{
                                        'ui-kanban-card-dragging': isDraggingTicket({{ $ticket->id }}),
                                        'ui-kanban-card-lifted': isPointerCandidate({{ $ticket->id }})
                                    }"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            @if ($canUpdate)
                                                <div wire:key="ticket-title-kanban-{{ $ticket->id }}">
                                                    <input type="text" value="{{ $ticket->title }}" wire:change="updateFixedField({{ $ticket->id }}, 'title', $event.target.value)" class="ui-input w-full text-sm font-medium" />
                                                </div>
                                            @else
                                                <h4 class="text-sm font-semibold text-slate-900">{{ $ticket->title }}</h4>
                                            @endif

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

                                    @if ($canUpdate)
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
                                    @endif

                                    @if ($fields->isNotEmpty())
                                        <div class="mt-4 space-y-3 border-t border-slate-100 pt-4" data-no-drag>
                                            @foreach ($fields as $field)
                                                @php
                                                    $value = $this->fieldValue($ticket, $field);
                                                    $selectedOption = $this->fieldOption($field, $value);
                                                @endphp

                                                <label class="block text-sm text-slate-600" wire:key="ticket-field-kanban-{{ $ticket->id }}-{{ $field->id }}">
                                                    <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">{{ $field->name }}</span>

                                                    @if (! $canUpdate)
                                                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700">
                                                            {{ $field->type->value === 'checkbox' ? ($value ? 'Sim' : 'Nao') : ($value ?: '-') }}
                                                        </div>
                                                    @elseif (in_array($field->type->value, ['select', 'status'], true))
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

                                    @if ($canUpdate)
                                        <p class="mt-4 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                                            Arraste o card para mover de coluna
                                        </p>
                                    @endif
                                </article>
                            @empty
                                <div class="rounded-[1.45rem] border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                                    Nenhum chamado nesta coluna.
                                </div>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    @endif

    <div x-ref="dragLayer" class="pointer-events-none fixed inset-0 z-[80] hidden"></div>
</div>

@push('scripts')
    <script>
        if (! window.ticketBoard) {
            window.ticketBoard = function (config) {
                return {
                    selectedSectorId: config.selectedSectorId,
                    viewMode: config.viewMode,
                    dragThreshold: 10,
                    drag: {
                        active: false,
                        pending: false,
                        ticketId: null,
                        sourceGroupId: null,
                        targetGroupId: null,
                        pointerId: null,
                        startX: 0,
                        startY: 0,
                        currentX: 0,
                        currentY: 0,
                        offsetX: 0,
                        offsetY: 0,
                        cardEl: null,
                        previewEl: null,
                    },

                    init() {
                        this.restoreViewMode();

                        this.$watch('selectedSectorId', () => {
                            this.cancelPointerDrag();
                            this.restoreViewMode();
                        });

                        this.$watch('viewMode', (value) => {
                            if (value !== 'kanban') {
                                this.cancelPointerDrag();
                            }

                            this.persistViewMode(value);
                        });
                    },

                    storageKey() {
                        return this.selectedSectorId ? `tickets-board-view:${this.selectedSectorId}` : null;
                    },

                    restoreViewMode() {
                        const key = this.storageKey();

                        if (! key) {
                            return;
                        }

                        let storedMode = null;

                        try {
                            storedMode = window.localStorage.getItem(key);
                        } catch (error) {
                            return;
                        }

                        const nextMode = ['list', 'kanban'].includes(storedMode) ? storedMode : 'list';

                        if (this.viewMode !== nextMode) {
                            this.$wire.setViewMode(nextMode);
                        } else {
                            this.persistViewMode(nextMode);
                        }
                    },

                    persistViewMode(value) {
                        if (! ['list', 'kanban'].includes(value)) {
                            return;
                        }

                        const key = this.storageKey();

                        if (! key) {
                            return;
                        }

                        try {
                            window.localStorage.setItem(key, value);
                        } catch (error) {
                            return;
                        }
                    },

                    normalizeGroupValue(rawValue) {
                        if (rawValue === '' || rawValue === null || typeof rawValue === 'undefined') {
                            return null;
                        }

                        if (typeof rawValue === 'number' && Number.isInteger(rawValue)) {
                            return rawValue;
                        }

                        if (typeof rawValue === 'string' && /^\d+$/.test(rawValue)) {
                            return Number.parseInt(rawValue, 10);
                        }

                        return rawValue;
                    },

                    resetDragState() {
                        this.drag = {
                            active: false,
                            pending: false,
                            ticketId: null,
                            sourceGroupId: null,
                            targetGroupId: null,
                            pointerId: null,
                            startX: 0,
                            startY: 0,
                            currentX: 0,
                            currentY: 0,
                            offsetX: 0,
                            offsetY: 0,
                            cardEl: null,
                            previewEl: null,
                        };
                    },

                    changeGroup(ticketId, rawValue) {
                        this.cancelPointerDrag();
                        this.$wire.moveTicketToGroup(ticketId, this.normalizeGroupValue(rawValue));
                    },

                    areSameGroupId(left, right) {
                        return (left ?? null) === (right ?? null);
                    },

                    isInteractiveTarget(target) {
                        return Boolean(target?.closest('a, button, input, select, textarea, label, [data-no-drag]'));
                    },

                    beginPointerDrag(event, ticketId, groupId) {
                        if (this.viewMode !== 'kanban') {
                            return;
                        }

                        if (this.isInteractiveTarget(event.target)) {
                            return;
                        }

                        if (event.pointerType === 'mouse' && event.button !== 0) {
                            return;
                        }

                        this.cancelPointerDrag();

                        const cardEl = event.currentTarget;
                        const cardRect = cardEl.getBoundingClientRect();

                        this.drag.pending = true;
                        this.drag.ticketId = ticketId;
                        this.drag.sourceGroupId = this.normalizeGroupValue(groupId);
                        this.drag.targetGroupId = this.normalizeGroupValue(groupId);
                        this.drag.pointerId = event.pointerId ?? null;
                        this.drag.startX = event.clientX;
                        this.drag.startY = event.clientY;
                        this.drag.currentX = event.clientX;
                        this.drag.currentY = event.clientY;
                        this.drag.offsetX = event.clientX - cardRect.left;
                        this.drag.offsetY = event.clientY - cardRect.top;
                        this.drag.cardEl = cardEl;

                        if (typeof cardEl.setPointerCapture === 'function' && this.drag.pointerId !== null) {
                            try {
                                cardEl.setPointerCapture(this.drag.pointerId);
                            } catch (error) {
                                // Ignora falhas de captura em navegadores que tratam touch de forma diferente.
                            }
                        }
                    },

                    activatePointerDrag() {
                        if (! this.drag.pending || this.drag.active || ! this.drag.cardEl) {
                            return;
                        }

                        const previewEl = this.drag.cardEl.cloneNode(true);
                        const cardRect = this.drag.cardEl.getBoundingClientRect();

                        previewEl.classList.add('ui-kanban-drag-preview');
                        previewEl.classList.remove('ui-kanban-card-dragging', 'ui-kanban-card-lifted');
                        previewEl.setAttribute('aria-hidden', 'true');
                        previewEl.style.width = `${cardRect.width}px`;
                        previewEl.style.height = `${cardRect.height}px`;
                        previewEl.style.left = '0';
                        previewEl.style.top = '0';
                        previewEl.style.margin = '0';

                        this.$refs.dragLayer.replaceChildren(previewEl);
                        this.$refs.dragLayer.classList.remove('hidden');

                        this.drag.previewEl = previewEl;
                        this.drag.active = true;

                        document.body.classList.add('ui-kanban-drag-active');

                        this.updatePreviewPosition();
                        this.refreshDragTarget();
                    },

                    updatePointerDrag(event) {
                        if (! this.drag.pending && ! this.drag.active) {
                            return;
                        }

                        if (this.drag.pointerId !== null && event.pointerId !== this.drag.pointerId) {
                            return;
                        }

                        this.drag.currentX = event.clientX;
                        this.drag.currentY = event.clientY;

                        if (! this.drag.active) {
                            const deltaX = this.drag.currentX - this.drag.startX;
                            const deltaY = this.drag.currentY - this.drag.startY;

                            if (Math.hypot(deltaX, deltaY) < this.dragThreshold) {
                                return;
                            }

                            this.activatePointerDrag();
                        }

                        event.preventDefault?.();

                        this.updatePreviewPosition();
                        this.refreshDragTarget();
                    },

                    updatePreviewPosition() {
                        if (! this.drag.previewEl) {
                            return;
                        }

                        this.drag.previewEl.style.transform = `translate3d(${this.drag.currentX - this.drag.offsetX}px, ${this.drag.currentY - this.drag.offsetY}px, 0) rotate(1.5deg) scale(1.01)`;
                    },

                    resolveDropGroupFromPoint(x, y) {
                        if (! Number.isFinite(x) || ! Number.isFinite(y)) {
                            return undefined;
                        }

                        const columnEl = document.elementFromPoint(x, y)?.closest('[data-kanban-column]');

                        if (! columnEl) {
                            return undefined;
                        }

                        const rawGroupId = columnEl.dataset.kanbanGroupId;

                        return rawGroupId === '__null__'
                            ? null
                            : this.normalizeGroupValue(rawGroupId);
                    },

                    refreshDragTarget() {
                        if (! this.drag.active) {
                            return;
                        }

                        this.drag.targetGroupId = this.resolveDropGroupFromPoint(this.drag.currentX, this.drag.currentY);
                    },

                    isDragTarget(groupId) {
                        if (! this.drag.active || typeof this.drag.targetGroupId === 'undefined') {
                            return false;
                        }

                        return this.areSameGroupId(this.normalizeGroupValue(groupId), this.drag.targetGroupId);
                    },

                    isDraggingTicket(ticketId) {
                        return this.drag.active && this.drag.ticketId === ticketId;
                    },

                    isPointerCandidate(ticketId) {
                        return this.drag.pending && ! this.drag.active && this.drag.ticketId === ticketId;
                    },

                    cleanupPreview() {
                        if (this.drag.previewEl) {
                            this.drag.previewEl.remove();
                        }

                        if (this.$refs.dragLayer) {
                            this.$refs.dragLayer.replaceChildren();
                            this.$refs.dragLayer.classList.add('hidden');
                        }
                    },

                    endPointerDrag(event) {
                        if (! this.drag.pending && ! this.drag.active) {
                            return;
                        }

                        if (this.drag.pointerId !== null && event.pointerId !== this.drag.pointerId) {
                            return;
                        }

                        if (typeof event.clientX === 'number') {
                            this.drag.currentX = event.clientX;
                            this.drag.currentY = event.clientY;
                        }

                        if (! this.drag.active) {
                            this.cancelPointerDrag();

                            return;
                        }

                        const ticketId = this.drag.ticketId;
                        const targetGroupId = this.resolveDropGroupFromPoint(this.drag.currentX, this.drag.currentY);
                        const sourceGroupId = this.drag.sourceGroupId;

                        this.cancelPointerDrag();

                        if (ticketId === null || typeof targetGroupId === 'undefined' || this.areSameGroupId(targetGroupId, sourceGroupId)) {
                            return;
                        }

                        this.$wire.moveTicketToGroup(ticketId, targetGroupId);
                    },

                    cancelPointerDrag() {
                        if (this.drag.cardEl && this.drag.pointerId !== null && typeof this.drag.cardEl.hasPointerCapture === 'function' && this.drag.cardEl.hasPointerCapture(this.drag.pointerId)) {
                            try {
                                this.drag.cardEl.releasePointerCapture(this.drag.pointerId);
                            } catch (error) {
                                // Ignora erros de release em navegadores que limpam a captura automaticamente.
                            }
                        }

                        document.body.classList.remove('ui-kanban-drag-active');

                        this.cleanupPreview();
                        this.resetDragState();
                    },
                };
            };
        }
    </script>
@endpush
