<div class="space-y-6">
    <x-portal.page-intro
        eyebrow="Acompanhamento pessoal"
        title="Meus chamados"
        description="Veja somente os chamados que foram abertos por voce, com status, responsavel e SLA no mesmo painel."
    >
        <x-slot:actions>
            <a href="{{ route('tickets.central') }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">
                Abrir novo chamado
            </a>

            @if (auth()->user()->hasOperationalAccess())
                <a href="{{ route('tickets.index') }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">
                    Quadro
                </a>
            @endif
        </x-slot:actions>
        <x-slot:meta>
            <span class="portal-chip">{{ $tickets->total() }} chamado(s)</span>
            <span class="portal-chip">Somente solicitacoes do seu usuario</span>
        </x-slot:meta>
    </x-portal.page-intro>

    <x-portal.filter-bar title="Filtros" description="Chamados abertos por voce.">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <label class="text-sm text-slate-600">
                <span class="mb-1 block font-medium">ID ou titulo</span>
                <input wire:model.live.debounce.400ms="titleFilter" type="text" class="ui-input w-full" placeholder="Buscar por codigo ou titulo">
            </label>

            <label class="text-sm text-slate-600">
                <span class="mb-1 block font-medium">Setor</span>
                <select wire:model.live="selectedSectorId" class="ui-native-select w-full">
                    <option value="">Todos os setores</option>
                    @foreach ($sectorOptions as $sectorOption)
                        <option value="{{ $sectorOption->id }}">{{ $sectorOption->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm text-slate-600">
                <span class="mb-1 block font-medium">Situacao</span>
                <select wire:model.live="statusFilter" class="ui-native-select w-full">
                    <option value="all">Todos</option>
                    <option value="open">Abertos</option>
                    <option value="closed">Finalizados</option>
                </select>
            </label>

            <label class="text-sm text-slate-600">
                <span class="mb-1 block font-medium">SLA</span>
                <select wire:model.live="slaFilter" class="ui-native-select w-full">
                    <option value="all">Todos</option>
                    <option value="ok">Em dia</option>
                    <option value="warning">A vencer</option>
                    <option value="breached">Estourado</option>
                </select>
            </label>

            <label class="text-sm text-slate-600">
                <span class="mb-1 block font-medium">Atualizado de</span>
                <input wire:model.live="updatedFrom" type="date" class="ui-input w-full">
            </label>

            <label class="text-sm text-slate-600">
                <span class="mb-1 block font-medium">Atualizado ate</span>
                <input wire:model.live="updatedTo" type="date" class="ui-input w-full">
            </label>
        </div>
    </x-portal.filter-bar>

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
                        <h2 class="ticket-mobile-title">{{ $ticket->title }}</h2>
                        <p class="ticket-mobile-subtitle">{{ $ticket->catalogItem?->name ?? 'Formulario nao identificado' }}</p>
                    </div>

                    <a href="{{ route('tickets.show', $ticket) }}" class="ui-action {{ $needsRating ? 'ui-action-primary' : 'ui-action-secondary' }} ticket-mobile-open-button">
                        {{ $needsRating ? 'Avaliar' : 'Abrir' }}
                    </a>
                </div>

                <div class="ticket-mobile-chip-row">
                    <span class="ticket-mobile-chip" style="--ticket-mobile-chip-color: {{ $ticket->group?->color ?? $ticket->status?->color ?? '#64748b' }}">
                        {{ $ticket->group?->name ?? $ticket->status?->name ?? 'Sem etapa' }}
                    </span>
                    <span class="ticket-mobile-chip ticket-mobile-chip-soft">
                        {{ $ticket->priority?->label() }}
                    </span>
                    @if ($ticket->isClosed())
                        <span class="ticket-mobile-chip ticket-mobile-chip-success">Finalizado</span>
                    @else
                        <span class="ticket-mobile-chip ticket-mobile-chip-info">Aberto</span>
                    @endif
                    <span class="ticket-mobile-chip" style="--ticket-mobile-chip-color: {{ $slaMeta['color'] }}">
                        {{ $slaMeta['label'] }}
                    </span>
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
                        <dt>Responsavel</dt>
                        <dd><x-person-reference :user="$ticket->assignee" empty-label="Nao atribuido" /></dd>
                    </div>
                    <div>
                        <dt>Atualizado</dt>
                        <dd>{{ $ticket->updated_at?->diffForHumans() }}</dd>
                    </div>
                </dl>

                <div class="ticket-mobile-deadlines">
                    <span>1a resposta: {{ $ticket->first_response_due_at?->format('d/m/Y H:i') ?? 'sem prazo' }}</span>
                    <span>Resolucao: {{ $ticket->resolution_due_at?->format('d/m/Y H:i') ?? 'sem prazo' }}</span>
                </div>
            </article>
        @empty
            <div class="ticket-mobile-empty">
                Nenhum chamado encontrado nos filtros atuais.
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
                    <th class="ui-person-column-head">Responsavel</th>
                    <th class="px-6 py-3 font-medium">Situacao</th>
                    <th class="px-6 py-3 font-medium">SLA</th>
                    <th class="px-6 py-3 font-medium">Atualizado</th>
                    <th class="px-6 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($tickets as $ticket)
                    @php
                        $slaMeta = $this->slaMeta($ticket);
                        $needsRating = $ticket->canBeRatedBy(auth()->user());
                    @endphp
                    <tr class="ui-row-interactive hover:bg-slate-50">
                        <td class="px-6 py-4">
                            <p class="font-medium text-slate-900">{{ $ticket->title }}</p>
                            <p class="mt-1 text-xs font-semibold uppercase tracking-[0.16em] text-sky-700">{{ $ticket->fullReference() }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $ticket->catalogItem?->name ?? 'Formulario nao identificado' }}</p>
                            @if ($needsRating)
                                <span class="mt-2 inline-flex rounded-full bg-yellow-300 px-3 py-1 text-xs font-semibold text-yellow-950 ring-1 ring-yellow-400/80 shadow-sm">Avalie o atendimento</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-slate-600">
                            <x-sector-badge :sector="$ticket->sector" mode="dot" />
                            <p class="text-xs text-slate-500">{{ $ticket->sector?->company?->name }}</p>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-2">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium text-white" style="background-color: {{ $ticket->group?->color ?? $ticket->status?->color ?? '#64748b' }}">
                                    {{ $ticket->group?->name ?? $ticket->status?->name ?? 'Sem etapa' }}
                                </span>
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $ticket->priority?->badgeColor() }}">
                                    {{ $ticket->priority?->label() }}
                                </span>
                            </div>
                        </td>
                        <td class="ui-person-column-cell">
                            <x-person-reference :user="$ticket->assignee" empty-label="Nao atribuido" />
                        </td>
                        <td class="px-6 py-4">
                            @if ($ticket->isClosed())
                                <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700">Finalizado</span>
                            @else
                                <span class="inline-flex rounded-full bg-sky-100 px-3 py-1 text-xs font-medium text-sky-700">Aberto</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium text-white" style="background-color: {{ $slaMeta['color'] }}">
                                {{ $slaMeta['label'] }}
                            </span>
                            <p class="mt-1 text-xs text-slate-500">
                                Primeira resposta: {{ $ticket->first_response_due_at?->format('d/m/Y H:i') ?? 'sem prazo' }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                Resolucao: {{ $ticket->resolution_due_at?->format('d/m/Y H:i') ?? 'sem prazo' }}
                            </p>
                        </td>
                        <td class="px-6 py-4 text-slate-500">{{ $ticket->updated_at?->diffForHumans() }}</td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('tickets.show', $ticket) }}" class="ui-action {{ $needsRating ? 'ui-action-primary' : 'ui-action-secondary' }} rounded-xl px-3 py-2 text-sm">
                                {{ $needsRating ? 'Avalie o atendimento' : 'Abrir' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-10 text-center text-slate-500">
                            Nenhum chamado encontrado nos filtros atuais.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $tickets->links() }}
</div>
