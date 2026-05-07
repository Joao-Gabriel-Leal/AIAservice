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
        title="Quadros"
        description="Filtre tudo em um so lugar e alterne entre lista, etapas e kanban sem perder o contexto."
    >
        <x-slot:actions>
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
            <a href="{{ route('tickets.central') }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">
                Central de formularios
            </a>
            <a href="{{ $manualCreateUrl }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">
                Abrir demanda manualmente
            </a>
            <a href="{{ route('tickets.export', $exportParams) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">
                Exportar Excel
            </a>
            @if ($board && auth()->user()->can('update', $board))
                <a href="{{ route('tickets.settings', $board) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">
                    Configurar
                </a>
            @endif
        </x-slot:actions>

        <div class="portal-toolbar">
            <div>
                <p class="text-sm font-semibold text-slate-900">Filtros compartilhados</p>
                <p class="mt-1 text-sm text-slate-500">Os mesmos filtros abastecem lista, etapas e kanban.</p>
            </div>

            @if ($board)
                <x-sector-badge :sector="$board->sector" mode="chip">{{ $board->sector->company?->name }}</x-sector-badge>
            @endif
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <label class="text-sm text-slate-600">
                <span class="mb-1 block font-medium">Titulo</span>
                <input wire:model.live.debounce.400ms="titleFilter" type="text" class="ui-input w-full" placeholder="Buscar por titulo">
            </label>

            @if ($sectorOptions->isNotEmpty())
                <label class="text-sm text-slate-600">
                    <span class="mb-1 block font-medium">Setor</span>
                    <select wire:model.live="selectedSectorId" class="ui-native-select w-full">
                        <option value="">Todos os setores</option>
                        @foreach ($sectorOptions as $sectorOption)
                            <option value="{{ $sectorOption->id }}">{{ $sectorOption->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            @if ($boardOptions->isNotEmpty())
                <label class="text-sm text-slate-600">
                    <span class="mb-1 block font-medium">Quadro</span>
                    <select wire:model.live="selectedBoardId" class="ui-native-select w-full">
                        <option value="">Todos os quadros</option>
                        @foreach ($boardOptions as $boardOption)
                            <option value="{{ $boardOption->id }}">
                                {{ $boardOption->name }}
                                @if (! $selectedSectorId)
                                    - {{ $boardOption->sector?->name }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                </label>
            @endif

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
</div>

@include('livewire.tickets.partials.board-script')
