<div class="space-y-6">
    <div class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">Acompanhamento de chamados</h2>
                <p class="mt-2 text-sm text-slate-500">Veja seus chamados e, quando tiver atuacao operacional, acompanhe tambem os setores em que voce trabalha.</p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                @if ($sectorOptions->isNotEmpty())
                    <label class="text-sm text-slate-600">
                        <span class="mb-1 block font-medium">Filtrar por setor</span>
                        <select wire:model.live="selectedSectorId" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                            <option value="">Todos os setores</option>
                            @foreach ($sectorOptions as $sectorOption)
                                <option value="{{ $sectorOption->id }}">{{ $sectorOption->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <a href="{{ route('tickets.central') }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">
                    Central de formularios
                </a>

                @if ($canAccessBoard)
                    <a href="{{ route('tickets.board') }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">
                        Abrir quadro
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="ui-panel overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-6 py-3 font-medium">Titulo</th>
                    <th class="px-6 py-3 font-medium">Setor</th>
                    <th class="px-6 py-3 font-medium">Status</th>
                    <th class="px-6 py-3 font-medium">Solicitante</th>
                    <th class="px-6 py-3 font-medium">Responsavel</th>
                    <th class="px-6 py-3 font-medium">Atualizado</th>
                    <th class="px-6 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($tickets as $ticket)
                    <tr class="ui-row-interactive hover:bg-slate-50">
                        <td class="px-6 py-4">
                            <p class="font-medium text-slate-900">{{ $ticket->title }}</p>
                            <p class="text-xs text-slate-500">{{ $ticket->catalogItem?->name ?? 'Chamado geral' }}</p>
                        </td>
                        <td class="px-6 py-4 text-slate-600">
                            <p>{{ $ticket->sector?->name ?? 'Sem setor' }}</p>
                            <p class="text-xs text-slate-500">{{ $ticket->sector?->company?->name }}</p>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-2">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium text-white" style="background-color: {{ $ticket->status?->color ?? '#64748b' }}">
                                    {{ $ticket->status?->name ?? 'Sem status' }}
                                </span>
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $ticket->priority?->badgeColor() }}">
                                    {{ $ticket->priority?->label() }}
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-slate-600">{{ $ticket->requester?->name ?? 'Nao informado' }}</td>
                        <td class="px-6 py-4 text-slate-600">{{ $ticket->assignee?->name ?? 'Nao atribuido' }}</td>
                        <td class="px-6 py-4 text-slate-500">{{ $ticket->updated_at?->diffForHumans() }}</td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('tickets.show', $ticket) }}" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm">Abrir</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-10 text-center text-slate-500">
                            Nenhum chamado encontrado. Use a central para abrir a primeira solicitacao.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $tickets->links() }}
</div>
