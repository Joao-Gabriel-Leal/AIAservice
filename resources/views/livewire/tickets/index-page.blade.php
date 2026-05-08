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

    <x-portal.section-hero
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

        <div class="portal-toolbar">
            <div>
                <p class="text-sm font-semibold text-slate-900">Filtros do quadro</p>
                <p class="mt-1 text-sm text-slate-500">Este painel mostra somente demandas do quadro aberto.</p>
            </div>

            @if ($board)
                <x-sector-badge :sector="$board->sector" mode="chip">{{ $board->sector->company?->name }}</x-sector-badge>
            @endif
        </div>

        @if ($board)
            <div class="grid gap-4 xl:grid-cols-[1fr_1fr]">
                <div class="rounded-3xl border border-slate-200 bg-white/70 p-4">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Views rapidas</p>
                            <p class="mt-1 text-xs text-slate-500">Atalhos para o recorte operacional do dia.</p>
                        </div>
                        <button type="button" wire:click="resetTicketFilters" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-xs">Limpar filtros</button>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @foreach ($quickViews as $quickViewKey => $quickViewLabel)
                            <button type="button" wire:click="applyQuickView('{{ $quickViewKey }}')" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-xs">
                                {{ $quickViewLabel }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white/70 p-4">
                    <div class="mb-3">
                        <p class="text-sm font-semibold text-slate-900">Views salvas</p>
                        <p class="mt-1 text-xs text-slate-500">Salve combinacoes de filtros como “SLA critico” ou “Minha triagem”.</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
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

                    <form wire:submit.prevent="saveCurrentView" class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
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
                </div>
            </div>
        @endif

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <label class="text-sm text-slate-600">
                <span class="mb-1 block font-medium">Titulo</span>
                <input wire:model.live.debounce.400ms="titleFilter" type="text" class="ui-input w-full" placeholder="Buscar por titulo">
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
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
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
    </x-portal.section-hero>

    @if ($lastManualTicketId)
        <div class="ui-panel flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <span>Chamado #{{ $lastManualTicketId }} criado direto no quadro.</span>
            <a href="{{ route('tickets.show', $lastManualTicketId) }}" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm">
                Abrir chamado
            </a>
        </div>
    @endif

    @if ($this->viewMode === 'list')
        <div class="portal-table-surface">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="portal-table-head text-left text-slate-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Titulo</th>
                        <th class="px-6 py-3 font-medium">Setor</th>
                        <th class="px-6 py-3 font-medium">Etapa</th>
                        <th class="px-6 py-3 font-medium">Solicitante</th>
                        <th class="px-6 py-3 font-medium">Responsavel</th>
                        <th class="px-6 py-3 font-medium">Atualizado</th>
                        @foreach ($fieldOptions as $field)
                            <th class="px-6 py-3 font-medium">{{ $field->name }}</th>
                        @endforeach
                        <th class="px-6 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($tickets as $ticket)
                        <tr class="ui-row-interactive hover:bg-slate-50">
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-medium text-slate-900">{{ $ticket->title }}</p>
                                    @if ($ticket->canBeRatedBy(auth()->user()))
                                        <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-medium text-amber-700 ring-1 ring-inset ring-amber-200">
                                            Chamado encerrado. Avalie o atendimento.
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-500">{{ $ticket->catalogItem?->name ?? 'Formulario nao identificado' }}</p>
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
                            <td class="px-6 py-4 text-slate-600">{{ $ticket->requester?->name ?? 'Nao informado' }}</td>
                            <td class="px-6 py-4 text-slate-600">{{ $ticket->assignee?->name ?? 'Nao atribuido' }}</td>
                            <td class="px-6 py-4 text-slate-500">{{ $ticket->updated_at?->diffForHumans() }}</td>
                            @foreach ($fieldOptions as $field)
                                <td class="px-6 py-4 text-slate-600">{{ $this->displayFieldValue($ticket, $field) ?? '-' }}</td>
                            @endforeach
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('tickets.show', $ticket) }}" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm">Abrir</a>
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
