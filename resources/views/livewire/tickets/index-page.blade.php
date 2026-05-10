<div
    class="space-y-6"
    x-data="ticketBoard({
        selectedSectorId: $wire.entangle('selectedSectorId').live,
        selectedBoardId: $wire.entangle('selectedBoardId').live,
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
    @php
        if (! empty($fieldFiltersForExport)) {
            $exportParams['field_filters'] = $fieldFiltersForExport;
        }
    @endphp

    <x-portal.page-intro
        eyebrow="Quadro operacional"
        :title="$board?->name ?? 'Quadro'"
        :description="$board?->description ?: 'Acompanhe as demandas deste quadro em lista, etapas ou kanban.'"
    >
        <x-slot:actions>
            <a href="{{ route('tickets.index') }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">
                Voltar aos quadros
            </a>

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
                        x-on:click="$wire.setViewMode('stages')"
                        class="rounded-xl px-4 py-2 text-sm font-medium transition"
                        :class="viewMode === 'stages' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
                    >
                        Etapas
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

            @if (auth()->user()->isSuperAdmin())
                <a href="{{ route('search') }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">
                    Buscar em tudo
                </a>
            @endif
            @if ($manualBoard)
                <button type="button" wire:click="openManualTicketModal" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">
                    Nova demanda
                </button>
            @endif
            <a href="{{ route('tickets.export', $exportParams) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">
                Exportar Excel
            </a>
            @if ($configBoard && auth()->user()->can('update', $configBoard))
                <a href="{{ route('tickets.settings', $configBoard) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">
                    Configurar
                </a>
            @endif
        </x-slot:actions>
        <x-slot:meta>
            @if ($board)
                <x-sector-badge :sector="$board->sector" mode="chip">{{ $board->sector->company?->name }}</x-sector-badge>
                <span class="portal-chip">{{ $groups->count() }} etapa(s)</span>
                <span class="portal-chip">{{ $savedViews->count() }} view(s) salva(s)</span>
            @endif
        </x-slot:meta>
    </x-portal.page-intro>

    <x-portal.filter-bar
        title="Operacao do quadro"
        description="Use atalhos, views salvas e filtros sem perder o foco no quadro aberto."
        :collapsible="true"
        persist-key="tickets-board-filter-bar"
        :default-collapsed="false"
    >
        <x-slot:actions>
            <div class="portal-filter-summary">
                <span class="portal-filter-summary-chip {{ $hasActiveFilters ? 'portal-filter-summary-chip-active' : '' }}">
                    {{ $hasActiveFilters ? $activeFilterCount.' '.($activeFilterCount === 1 ? 'filtro ativo' : 'filtros ativos') : 'Sem filtros ativos' }}
                </span>

                @if ($hasActiveFilters)
                    <button type="button" wire:click="resetTicketFilters" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-xs">
                        Limpar filtros
                    </button>
                @endif
            </div>
        </x-slot:actions>
        @if ($board)
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]">
                <section class="portal-filter-block portal-filter-block--accent">
                    <div class="portal-filter-block-header">
                        <div>
                            <p class="portal-filter-block-title">Views rapidas</p>
                            <p class="portal-filter-block-copy">Recortes do dia.</p>
                        </div>

                        <span class="portal-filter-summary-chip">Atalhos</span>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($quickViews as $quickViewKey => $quickViewLabel)
                            <button type="button" wire:click="applyQuickView('{{ $quickViewKey }}')" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-xs">
                                {{ $quickViewLabel }}
                            </button>
                        @endforeach
                    </div>
                </section>

                <section class="portal-filter-block">
                    <div class="portal-filter-block-header">
                        <div>
                            <p class="portal-filter-block-title">Views salvas</p>
                            <p class="portal-filter-block-copy">Salve combinacoes de filtros como "SLA critico" ou "Minha triagem".</p>
                    </div>

                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="portal-filter-summary-chip">{{ $savedViews->count() }} {{ $savedViews->count() === 1 ? 'view salva' : 'views salvas' }}</span>

                        @forelse ($savedViews as $savedView)
                            <div class="inline-flex items-center overflow-hidden rounded-xl border border-slate-200 bg-white text-xs font-medium text-slate-600">
                                <button type="button" wire:click="applySavedView({{ $savedView->id }})" class="px-3 py-2 hover:bg-slate-50">
                                    {{ $savedView->name }}{{ $savedView->is_default ? ' · padrao' : '' }}
                                </button>
                                <button type="button" wire:click="deleteSavedView({{ $savedView->id }})" class="border-l border-slate-200 px-2 py-2 text-rose-500 hover:bg-rose-50" title="Excluir view salva">
                                    Excluir
                                </button>
                            </div>
                        @empty
                            <span class="text-xs text-slate-500">Nenhuma view salva ainda.</span>
                        @endforelse
                    </div>

                    <form wire:submit.prevent="saveCurrentView" class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto]">
                        <label class="text-xs text-slate-600">
                            <span class="mb-1 block font-medium">Nome da view</span>
                            <input wire:model="savedViewName" type="text" class="ui-input w-full" placeholder="Ex.: Sem responsavel urgente">
                            @error('savedViewName') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <div class="flex items-end gap-2">
                            <label class="mb-2 inline-flex items-center gap-2 text-xs text-slate-600">
                                <input type="checkbox" wire:model="saveViewAsDefault" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                                Padrao
                            </label>
                            <button type="submit" class="ui-action ui-action-primary rounded-xl px-3 py-2 text-xs">Salvar</button>
                        </div>
                    </form>
                </section>
            </div>
        @endif

        <div class="{{ $board ? 'portal-filter-section' : '' }}">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <label class="text-sm text-slate-600">
                <span class="mb-1 block font-medium">ID ou titulo</span>
                <input wire:model.live.debounce.400ms="titleFilter" type="text" class="ui-input w-full" placeholder="Buscar por codigo ou titulo">
            </label>

            <label class="text-sm text-slate-600">
                <span class="mb-1 block font-medium">Etapa</span>
                <select wire:model.live="selectedGroupId" class="ui-native-select w-full">
                    <option value="">Todas as etapas</option>
                    @foreach ($groupOptions as $groupOption)
                        <option value="{{ $groupOption->id }}">{{ $groupOption->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm text-slate-600">
                <span class="mb-1 block font-medium">Solicitante</span>
                <input wire:model.live.debounce.400ms="requesterFilter" type="text" class="ui-input w-full" placeholder="Nome ou email">
            </label>

            <label class="text-sm text-slate-600">
                <span class="mb-1 block font-medium">Responsavel</span>
                <input wire:model.live.debounce.400ms="assigneeFilter" type="text" class="ui-input w-full" placeholder="Nome ou email">
            </label>

            <label class="text-sm text-slate-600">
                <span class="mb-1 block font-medium">Fila</span>
                <select wire:model.live="assigneeStateFilter" class="ui-native-select w-full">
                    <option value="all">Todos</option>
                    <option value="me">Atribuidos a mim</option>
                    <option value="unassigned">Sem responsavel</option>
                    <option value="assigned">Com responsavel</option>
                </select>
            </label>

            <label class="text-sm text-slate-600">
                <span class="mb-1 block font-medium">Prioridade</span>
                <select wire:model.live="priorityFilter" class="ui-native-select w-full">
                    <option value="">Todas</option>
                    <option value="high_or_urgent">Alta ou urgente</option>
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm text-slate-600">
                <span class="mb-1 block font-medium">SLA</span>
                <select wire:model.live="slaFilter" class="ui-native-select w-full">
                    <option value="all">Todos</option>
                    <option value="critical">Critico</option>
                    <option value="breached">Estourado</option>
                    <option value="warning">A vencer</option>
                    <option value="ok">Em dia</option>
                </select>
            </label>

            <div class="grid gap-3 md:grid-cols-2">
                <label class="text-sm text-slate-600">
                    <span class="mb-1 block font-medium">Atualizado de</span>
                    <input wire:model.live="updatedFrom" type="date" class="ui-input w-full">
                </label>

                <label class="text-sm text-slate-600">
                    <span class="mb-1 block font-medium">Atualizado ate</span>
                    <input wire:model.live="updatedTo" type="date" class="ui-input w-full">
                </label>
            </div>
            </div>

            @if ($fieldOptions->isNotEmpty())
                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($fieldOptions as $field)
                    <label class="text-sm text-slate-600">
                        <span class="mb-1 block font-medium">{{ $field->name }}</span>

                        @if (in_array($field->type->value, ['select', 'status', 'user'], true) && $field->options->isNotEmpty())
                            <select wire:model.live="fieldFilters.{{ $field->id }}" class="ui-native-select w-full">
                                <option value="">Todos</option>
                                @foreach ($field->options as $option)
                                    <option value="{{ $option->value }}">{{ $option->label }}</option>
                                @endforeach
                            </select>
                        @elseif ($field->type->value === 'checkbox')
                            <select wire:model.live="fieldFilters.{{ $field->id }}" class="ui-native-select w-full">
                                <option value="">Todos</option>
                                <option value="1">Sim</option>
                                <option value="0">Nao</option>
                            </select>
                        @elseif ($field->type->value === 'date')
                            <input wire:model.live="fieldFilters.{{ $field->id }}" type="date" class="ui-input w-full">
                        @elseif ($field->type->value === 'number')
                            <input wire:model.live.debounce.400ms="fieldFilters.{{ $field->id }}" type="number" class="ui-input w-full" placeholder="Filtrar valor">
                        @else
                            <input wire:model.live.debounce.400ms="fieldFilters.{{ $field->id }}" type="text" class="ui-input w-full" placeholder="Filtrar {{ strtolower($field->name) }}">
                        @endif
                    </label>
                @endforeach
                </div>
            @endif
        </div>
    </x-portal.filter-bar>

    @if ($lastManualTicketId)
        <div class="ui-panel flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <span>
                Chamado {{ $lastManualTicketReferenceCode ?? ('#'.$lastManualTicketId) }} criado direto no quadro
                @if ($lastManualTicketReferenceCode)
                    (ID interno #{{ $lastManualTicketId }})
                @endif
                .
            </span>
            <a href="{{ route('tickets.show', $lastManualTicketId) }}" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm">
                Abrir chamado
            </a>
        </div>
    @endif

    @if ($this->viewMode === 'list')
        <div class="ticket-mobile-list lg:hidden">
            @forelse ($tickets as $ticket)
                @php
                    $slaMeta = $this->slaMeta($ticket);
                    $needsRating = $ticket->canBeRatedBy(auth()->user());
                @endphp

                <article class="ticket-mobile-card">
                    <div class="ticket-mobile-card-header">
                        <div class="min-w-0">
                            <p class="ticket-mobile-reference">{{ $ticket->fullReference() }}</p>
                            @if ($canUpdate)
                                <div
                                    wire:key="ticket-title-list-mobile-{{ $ticket->id }}"
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
                            @else
                                <h2 class="ticket-mobile-title">{{ $ticket->title }}</h2>
                            @endif
                            <p class="ticket-mobile-subtitle">{{ $ticket->catalogItem?->name ?? 'Formulario nao identificado' }}</p>
                            @if ($ticket->isSubelement())
                                <p class="ticket-mobile-subtitle">Subelemento de {{ $ticket->parentTicket?->publicReference() ?? 'chamado pai' }}</p>
                            @endif
                        </div>

                        <div class="flex shrink-0 flex-col gap-2">
                            <a href="{{ route('tickets.show', $ticket) }}" class="ui-action ui-action-secondary ticket-mobile-open-button">
                                Abrir
                            </a>
                            <button
                                type="button"
                                wire:click="deleteTicket({{ $ticket->id }})"
                                data-confirm
                                data-confirm-variant="danger"
                                data-confirm-title="Remover {{ $ticket->isSubelement() ? 'subelemento' : 'chamado' }}?"
                                data-confirm-message="Esta ação remove o item das listas operacionais. Esta ação não pode ser desfeita."
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
                        <span class="ticket-mobile-chip" style="--ticket-mobile-chip-color: {{ $ticket->group?->color ?? '#64748b' }}">
                            {{ $ticket->group?->name ?? 'Sem etapa' }}
                        </span>
                        <span class="ticket-mobile-chip ticket-mobile-chip-soft">
                            {{ $ticket->priority?->label() }}
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
                        @if ($ticket->isSubelement())
                            <span class="ticket-mobile-chip ticket-mobile-chip-info">
                                Subelemento
                            </span>
                        @endif
                    </div>

                    @if ($needsRating)
                        <div class="ticket-mobile-alert">Chamado encerrado. Avalie o atendimento.</div>
                    @endif

                    <dl class="ticket-mobile-meta-grid">
                        <div>
                            <dt>Setor</dt>
                            <dd>
                                <x-sector-badge :sector="$ticket->sector" mode="dot" />
                                <span>{{ $ticket->sector?->company?->name }}</span>
                            </dd>
                        </div>
                        <div>
                            <dt>Solicitante</dt>
                            <dd><x-person-reference :user="$ticket->requester" empty-label="Nao informado" /></dd>
                        </div>
                        <div>
                            <dt>Responsavel</dt>
                            <dd>
                                @if ($canUpdate)
                                    <div class="ui-native-pill-select ticket-mobile-inline-select w-full" style="--ui-pill-color: {{ $ticket->assignee_id ? '#3b82f6' : '#94a3b8' }}">
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
                            </dd>
                        </div>
                        <div>
                            <dt>Atualizado</dt>
                            <dd>{{ $ticket->updated_at?->diffForHumans() }}</dd>
                        </div>
                    </dl>

                    @if ($fieldOptions->isNotEmpty())
                        <details class="ticket-mobile-details">
                            <summary>Campos do quadro</summary>
                            <dl class="ticket-mobile-detail-grid">
                                @foreach ($fieldOptions as $field)
                                    <div>
                                        <dt>{{ $field->name }}</dt>
                                        <dd>{{ $this->displayFieldValue($ticket, $field) ?? '-' }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </details>
                    @endif
                </article>
            @empty
                <div class="ticket-mobile-empty">
                    Nenhum chamado encontrado. Use a central para abrir a primeira solicitacao.
                </div>
            @endforelse
        </div>

        <div class="portal-table-surface hidden lg:block">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="portal-table-head text-left text-slate-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Titulo</th>
                        <th class="px-6 py-3 font-medium">Setor</th>
                        <th class="px-6 py-3 font-medium">Etapa</th>
                        <th class="ui-person-column-head">Solicitante</th>
                        <th class="ui-person-column-head">Responsavel</th>
                        <th class="px-6 py-3 font-medium">Atualizado</th>
                        @foreach ($fieldOptions as $field)
                            <th class="px-6 py-3 font-medium">{{ $field->name }}</th>
                        @endforeach
                        <th class="px-6 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($tickets as $ticket)
                        <tr class="ui-row-interactive ui-row-zebra {{ $loop->even ? 'ui-row-zebra-alt' : '' }}">
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($canUpdate)
                                        <div
                                            wire:key="ticket-title-list-{{ $ticket->id }}"
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
                                    @else
                                        <p class="font-medium text-slate-900">{{ $ticket->title }}</p>
                                    @endif
                                    @if ($ticket->canBeRatedBy(auth()->user()))
                                        <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-medium text-amber-700 ring-1 ring-inset ring-amber-200">
                                            Chamado encerrado. Avalie o atendimento.
                                        </span>
                                    @endif
                                    @if ($ticket->is_major_incident)
                                        <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-medium text-rose-700 ring-1 ring-inset ring-rose-200">
                                            Incidente massivo - {{ $ticket->incident_children_count ?? 0 }}
                                        </span>
                                    @elseif ($ticket->major_incident_ticket_id)
                                        <span class="inline-flex rounded-full bg-sky-50 px-2.5 py-1 text-[11px] font-medium text-sky-700 ring-1 ring-inset ring-sky-200">
                                            Vinculado a incidente
                                        </span>
                                    @endif
                                    @if ($ticket->isSubelement())
                                        <span class="inline-flex rounded-full bg-cyan-50 px-2.5 py-1 text-[11px] font-medium text-cyan-700 ring-1 ring-inset ring-cyan-200">
                                            Subelemento
                                        </span>
                                    @elseif (($ticket->sub_tickets_count ?? 0) > 0)
                                        <span class="inline-flex rounded-full bg-cyan-50 px-2.5 py-1 text-[11px] font-medium text-cyan-700 ring-1 ring-inset ring-cyan-200">
                                            {{ $ticket->sub_tickets_count }} subelemento(s), {{ $ticket->open_sub_tickets_count ?? 0 }} aberto(s)
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs font-semibold uppercase tracking-[0.16em] text-sky-700">{{ $ticket->fullReference() }}</p>
                                <p class="text-xs text-slate-500">{{ $ticket->catalogItem?->name ?? 'Formulario nao identificado' }}</p>
                                @if ($ticket->isSubelement())
                                    <p class="text-xs text-slate-500">Pai: {{ $ticket->parentTicket?->publicReference() ?? '-' }} - {{ $ticket->parentTicket?->title ?? 'Nao informado' }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-600">
                                <x-sector-badge :sector="$ticket->sector" mode="dot" />
                                <p class="text-xs text-slate-500">{{ $ticket->sector?->company?->name }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium text-white" style="background-color: {{ $ticket->group?->color ?? '#64748b' }}">
                                        {{ $ticket->group?->name ?? 'Sem etapa' }}
                                    </span>
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $ticket->priority?->badgeColor() }}">
                                        {{ $ticket->priority?->label() }}
                                    </span>
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
                            <td class="px-6 py-4 text-slate-500">{{ $ticket->updated_at?->diffForHumans() }}</td>
                            @foreach ($fieldOptions as $field)
                                <td class="px-6 py-4 text-slate-600">{{ $this->displayFieldValue($ticket, $field) ?? '-' }}</td>
                            @endforeach
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm">Abrir</a>
                                    <button
                                        type="button"
                                        wire:click="deleteTicket({{ $ticket->id }})"
                                        data-confirm
                                        data-confirm-variant="danger"
                                        data-confirm-title="Remover {{ $ticket->isSubelement() ? 'subelemento' : 'chamado' }}?"
                                        data-confirm-message="Esta ação remove o item das listas operacionais. Esta ação não pode ser desfeita."
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
                    @empty
                        <tr>
                            <td colspan="{{ 7 + $fieldOptions->count() }}" class="px-6 py-10 text-center text-slate-500">
                                Nenhum chamado encontrado. Use a central para abrir a primeira solicitacao.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $tickets->links() }}
    @elseif ($this->viewMode === 'stages')
        @if (! $board)
            <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-slate-500">
                Selecione um setor em que voce atue como operador ou gestor para abrir as etapas.
            </div>
        @else
            @include('livewire.tickets.partials.stages-view')
        @endif
    @else
        @if (! $board)
            <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-slate-500">
                Selecione um setor em que voce atue como operador ou gestor para abrir o kanban.
            </div>
        @else
            @include('livewire.tickets.partials.kanban-view')
        @endif
    @endif

    <div x-ref="dragLayer" class="pointer-events-none fixed inset-0 z-[80] hidden"></div>

    @if ($showManualTicketModal && $manualBoard)
        <div class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/55 px-4 py-6 backdrop-blur-sm">
            <div class="w-full max-w-4xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl">
                <form wire:submit.prevent="createManualTicket" class="max-h-[88vh] overflow-y-auto">
                    <div class="border-b border-slate-100 px-6 py-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Criacao manual</p>
                                <h2 class="mt-2 text-xl font-semibold text-slate-950">{{ $manualBoard->name }}</h2>
                                <p class="mt-1 text-sm text-slate-500">{{ $manualBoard->sector?->name }} - sem formulario publico</p>
                            </div>

                            <button type="button" wire:click="closeManualTicketModal" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                                Fechar
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-5 px-6 py-6 md:grid-cols-2">
                        <label class="md:col-span-2 text-sm text-slate-600">
                            <span class="mb-1 block font-medium">Titulo</span>
                            <input wire:model="manualTicketForm.title" type="text" class="ui-input w-full" placeholder="Resumo curto da demanda">
                            @error('manualTicketForm.title') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="md:col-span-2 text-sm text-slate-600">
                            <span class="mb-1 block font-medium">Descricao</span>
                            <textarea wire:model="manualTicketForm.description" rows="4" class="ui-input w-full" placeholder="Contexto, impacto e qualquer detalhe util"></textarea>
                            @error('manualTicketForm.description') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="text-sm text-slate-600">
                            <span class="mb-1 block font-medium">Solicitante</span>
                            <select wire:model="manualTicketForm.requester_id" class="ui-native-select w-full">
                                @foreach ($manualRequesters as $requesterOption)
                                    <option value="{{ $requesterOption->id }}">{{ $requesterOption->name }} - {{ $requesterOption->email }}</option>
                                @endforeach
                            </select>
                            @error('manualTicketForm.requester_id') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="text-sm text-slate-600">
                            <span class="mb-1 block font-medium">Prioridade</span>
                            <select wire:model="manualTicketForm.priority" class="ui-native-select w-full">
                                @foreach ($priorities as $priority)
                                    <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                                @endforeach
                            </select>
                            @error('manualTicketForm.priority') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="text-sm text-slate-600">
                            <span class="mb-1 block font-medium">Etapa inicial</span>
                            <select wire:model="manualTicketForm.ticket_group_id" class="ui-native-select w-full">
                                <option value="">Sem etapa</option>
                                @foreach ($manualBoard->groups->where('is_active', true) as $groupOption)
                                    <option value="{{ $groupOption->id }}">{{ $groupOption->name }}</option>
                                @endforeach
                            </select>
                            @error('manualTicketForm.ticket_group_id') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="text-sm text-slate-600">
                            <span class="mb-1 block font-medium">Responsavel</span>
                            <select wire:model="manualTicketForm.assignee_id" class="ui-native-select w-full">
                                <option value="">Nao atribuido</option>
                                @foreach ($manualAssignees as $assigneeOption)
                                    <option value="{{ $assigneeOption->id }}">{{ $assigneeOption->name }}</option>
                                @endforeach
                            </select>
                            @error('manualTicketForm.assignee_id') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        @if ($manualFields->isNotEmpty())
                            <div class="md:col-span-2 grid gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <p class="text-sm font-semibold text-slate-900">Campos do quadro</p>
                                    <p class="mt-1 text-xs text-slate-500">Apenas campos ativos e marcados para aparecer no quadro entram aqui.</p>
                                </div>

                                @foreach ($manualFields as $field)
                                    <label wire:key="manual-field-{{ $field->id }}" class="{{ $field->type->value === 'text' ? 'md:col-span-2' : '' }} text-sm text-slate-600">
                                        <span class="mb-1 block font-medium">
                                            {{ $field->name }}
                                            @if ($field->is_required)
                                                <span class="text-rose-500">*</span>
                                            @endif
                                        </span>

                                        @if (in_array($field->type->value, ['select', 'status'], true) && $field->options->isNotEmpty())
                                            <select wire:model="manualTicketForm.dynamic_values.{{ $field->id }}" class="ui-native-select w-full">
                                                <option value="">Selecione</option>
                                                @foreach ($field->options as $option)
                                                    <option value="{{ $option->value }}">{{ $option->label }}</option>
                                                @endforeach
                                            </select>
                                        @elseif ($field->type->value === 'user')
                                            <select wire:model="manualTicketForm.dynamic_values.{{ $field->id }}" class="ui-native-select w-full">
                                                <option value="">Selecione</option>
                                                @foreach ($manualRequesters as $requesterOption)
                                                    <option value="{{ $requesterOption->id }}">{{ $requesterOption->name }}</option>
                                                @endforeach
                                            </select>
                                        @elseif ($field->type->value === 'checkbox')
                                            <span class="flex min-h-[44px] items-center gap-3 rounded-2xl border border-slate-300 bg-white px-4 py-3">
                                                <input wire:model="manualTicketForm.dynamic_values.{{ $field->id }}" type="checkbox" class="size-4 rounded border-slate-300">
                                                <span>Sim</span>
                                            </span>
                                        @elseif ($field->type->value === 'date')
                                            <input wire:model="manualTicketForm.dynamic_values.{{ $field->id }}" type="date" class="ui-input w-full">
                                        @elseif ($field->type->value === 'number')
                                            <input wire:model="manualTicketForm.dynamic_values.{{ $field->id }}" type="number" class="ui-input w-full" placeholder="{{ $field->placeholder ?: 'Valor' }}">
                                        @else
                                            <textarea wire:model="manualTicketForm.dynamic_values.{{ $field->id }}" rows="3" class="ui-input w-full" placeholder="{{ $field->placeholder ?: 'Informe o valor' }}"></textarea>
                                        @endif

                                        @if ($field->help_text)
                                            <span class="mt-1 block text-xs text-slate-400">{{ $field->help_text }}</span>
                                        @endif

                                        @error("manualTicketForm.dynamic_values.{$field->id}") <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
                        <button type="button" wire:click="closeManualTicketModal" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">
                            Cancelar
                        </button>
                        <button type="submit" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">
                            Criar demanda
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

@include('livewire.tickets.partials.board-script')
