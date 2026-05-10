<div class="space-y-6">
    <x-portal.page-intro
        eyebrow="Recuperacao operacional"
        title="Lixeira de chamados"
        description="Chamados e subelementos excluidos ficam guardados por {{ $retentionDays }} dias antes da remocao definitiva."
    >
        <x-slot:meta>
            <span class="portal-chip">{{ $tickets->total() }} item(ns) na lixeira</span>
            <span class="portal-chip">Retencao de {{ $retentionDays }} dias</span>
        </x-slot:meta>

        <x-slot:actions>
            <a href="{{ route('tickets.index') }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">
                Voltar aos quadros
            </a>
        </x-slot:actions>
    </x-portal.page-intro>

    <x-portal.filter-bar title="Itens excluidos" description="Filtre, confira os dados essenciais e restaure quando necessario.">
        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_220px]">
            <label class="text-sm text-slate-600">
                <span class="mb-1 block font-medium">Buscar na lixeira</span>
                <input wire:model.live.debounce.400ms="search" type="text" class="ui-input w-full" placeholder="Codigo, titulo, solicitante ou responsavel">
            </label>

            <label class="text-sm text-slate-600">
                <span class="mb-1 block font-medium">Tipo</span>
                <select wire:model.live="type" class="ui-native-select w-full">
                    @foreach ($typeOptions as $typeValue => $typeLabel)
                        <option value="{{ $typeValue }}">{{ $typeLabel }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </x-portal.filter-bar>

    <div class="rounded-3xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
        Antes de excluir, o sistema sempre pede confirmacao. Depois da exclusao, o item fica aqui por {{ $retentionDays }} dias para restauracao ou consulta dos dados principais.
    </div>

    <div class="portal-table-surface">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="portal-table-head text-left text-slate-500">
                <tr>
                    <th class="px-6 py-3 font-medium">Item</th>
                    <th class="px-6 py-3 font-medium">Quadro</th>
                    <th class="ui-person-column-head">Solicitante</th>
                    <th class="ui-person-column-head">Responsavel</th>
                    <th class="px-6 py-3 font-medium">Excluido em</th>
                    <th class="px-6 py-3 font-medium">Expira em</th>
                    <th class="px-6 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($tickets as $ticket)
                    @php
                        $expiresAt = $ticket->deleted_at?->copy()->addDays($retentionDays);
                        $daysLeft = $expiresAt ? max(0, (int) now()->diffInDays($expiresAt, false)) : 0;
                    @endphp
                    <tr class="ui-row-interactive hover:bg-slate-50">
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-medium text-slate-900">{{ $ticket->title }}</p>
                                @if ($ticket->isSubelement())
                                    <span class="inline-flex rounded-full bg-cyan-50 px-2.5 py-1 text-[11px] font-medium text-cyan-700 ring-1 ring-inset ring-cyan-200">
                                        Subelemento
                                    </span>
                                @elseif (($ticket->trashed_sub_tickets_count ?? 0) > 0)
                                    <span class="inline-flex rounded-full bg-cyan-50 px-2.5 py-1 text-[11px] font-medium text-cyan-700 ring-1 ring-inset ring-cyan-200">
                                        {{ $ticket->trashed_sub_tickets_count }} subelemento(s)
                                    </span>
                                @endif
                            </div>

                            <p class="mt-1 text-xs font-semibold uppercase tracking-[0.16em] text-sky-700">{{ $ticket->fullReference() }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $ticket->catalogItem?->name ?? 'Formulario padrao' }}</p>
                            @if ($ticket->isSubelement())
                                <p class="mt-1 text-xs text-slate-500">Pai: {{ $ticket->parentTicketWithTrashed?->publicReference() ?? '-' }} - {{ $ticket->parentTicketWithTrashed?->title ?? 'Nao informado' }}</p>
                            @endif

                            <details class="mt-3 rounded-2xl border border-slate-200 bg-white px-3 py-2">
                                <summary class="cursor-pointer text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Ver dados</summary>
                                <div class="mt-2 grid gap-2 text-sm text-slate-600">
                                    <p><span class="font-medium text-slate-800">Descricao:</span> {{ $ticket->description ?: 'Sem descricao.' }}</p>
                                    <p><span class="font-medium text-slate-800">Etapa:</span> {{ $ticket->group?->name ?? $ticket->status?->name ?? 'Sem etapa' }}</p>
                                    <p><span class="font-medium text-slate-800">Prioridade:</span> {{ $ticket->priority?->label() ?? 'Sem prioridade' }}</p>
                                </div>
                            </details>
                        </td>
                        <td class="px-6 py-4 text-slate-600">
                            <p class="font-medium text-slate-700">{{ $ticket->board?->name ?? 'Sem quadro' }}</p>
                            <x-sector-badge :sector="$ticket->sector" mode="dot" />
                        </td>
                        <td class="ui-person-column-cell">
                            <x-person-reference :user="$ticket->requester" empty-label="Nao informado" />
                        </td>
                        <td class="ui-person-column-cell">
                            <x-person-reference :user="$ticket->assignee" empty-label="Nao atribuido" />
                        </td>
                        <td class="px-6 py-4 text-slate-500">
                            <p>{{ $ticket->deleted_at?->format('d/m/Y H:i') ?? '-' }}</p>
                            <p class="text-xs">{{ $ticket->deleted_at?->diffForHumans() }}</p>
                        </td>
                        <td class="px-6 py-4 text-slate-500">
                            <p>{{ $expiresAt?->format('d/m/Y H:i') ?? '-' }}</p>
                            <p class="text-xs">{{ $daysLeft }} dia(s) restante(s)</p>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end">
                                <button
                                    type="button"
                                    wire:click="restoreTicket({{ $ticket->id }})"
                                    data-confirm
                                    data-confirm-variant="warning"
                                    data-confirm-title="Restaurar {{ $ticket->isSubelement() ? 'subelemento' : 'chamado' }}?"
                                    data-confirm-message="Tem certeza que deseja restaurar este item? Ele voltara a aparecer para a operacao."
                                    data-confirm-label="Sim, restaurar"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="ui-loading"
                                    wire:target="restoreTicket"
                                    class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm"
                                >
                                    Restaurar
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-10 text-center text-slate-500">
                            Nenhum chamado na lixeira dentro do prazo de {{ $retentionDays }} dias.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $tickets->links() }}
</div>
