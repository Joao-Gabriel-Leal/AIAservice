<div class="space-y-6">
    <x-portal.section-hero
        eyebrow="Operacao diaria"
        title="Minha fila operacional"
        description="Priorize o que precisa de acao: seus chamados, demandas sem dono, SLA critico e apontamentos de tempo abertos."
    >
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            @foreach ($buckets as $bucketKey => $bucketLabel)
                <button
                    type="button"
                    wire:click="setBucket('{{ $bucketKey }}')"
                    class="rounded-3xl border px-4 py-4 text-left transition {{ $bucket === $bucketKey ? 'border-sky-200 bg-sky-50 text-sky-900' : 'border-slate-200 bg-white/70 text-slate-600 hover:bg-white' }}"
                >
                    <span class="block text-2xl font-semibold">{{ $stats[$bucketKey] ?? 0 }}</span>
                    <span class="mt-1 block text-xs font-semibold uppercase tracking-[0.16em]">{{ $bucketLabel }}</span>
                </button>
            @endforeach
        </div>

        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_260px]">
            <label class="text-sm text-slate-600">
                <span class="mb-1 block font-medium">Buscar na fila</span>
                <input wire:model.live.debounce.400ms="search" type="text" class="ui-input w-full" placeholder="Titulo, solicitante ou responsavel">
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
        </div>
    </x-portal.section-hero>

    <div class="portal-table-surface">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="portal-table-head text-left text-slate-500">
                <tr>
                    <th class="px-6 py-3 font-medium">Chamado</th>
                    <th class="px-6 py-3 font-medium">Quadro</th>
                    <th class="px-6 py-3 font-medium">Solicitante</th>
                    <th class="px-6 py-3 font-medium">Responsavel</th>
                    <th class="px-6 py-3 font-medium">SLA</th>
                    <th class="px-6 py-3 font-medium">Atualizado</th>
                    <th class="px-6 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($tickets as $ticket)
                    @php($slaMeta = $this->slaMeta($ticket))
                    <tr class="ui-row-interactive hover:bg-slate-50">
                        <td class="px-6 py-4">
                            <p class="font-medium text-slate-900">{{ $ticket->title }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $ticket->catalogItem?->name ?? 'Formulario padrao' }}</p>
                        </td>
                        <td class="px-6 py-4 text-slate-600">
                            <p class="font-medium text-slate-700">{{ $ticket->board?->name ?? 'Sem quadro' }}</p>
                            <x-sector-badge :sector="$ticket->sector" mode="dot" />
                        </td>
                        <td class="px-6 py-4 text-slate-600">{{ $ticket->requester?->name ?? 'Nao informado' }}</td>
                        <td class="px-6 py-4 text-slate-600">{{ $ticket->assignee?->name ?? 'Nao atribuido' }}</td>
                        <td class="px-6 py-4">
                            <span class="ui-tone-chip" style="--ui-pill-color: {{ $slaMeta['color'] }}">
                                <span class="ui-tone-dot"></span>
                                {{ $slaMeta['label'] }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-500">{{ $ticket->updated_at?->diffForHumans() }}</td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('tickets.show', $ticket) }}" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm">Abrir</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-10 text-center text-slate-500">
                            Nenhum chamado nesta fila agora.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $tickets->links() }}
</div>
