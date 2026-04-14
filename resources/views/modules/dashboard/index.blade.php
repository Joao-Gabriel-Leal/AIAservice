<x-layouts.portal title="Dashboard">
    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($stats as $label => $value)
                @if (! is_null($value))
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm text-slate-500">{{ str($label)->replace('_', ' ')->title() }}</p>
                        <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $value }}</p>
                    </div>
                @endif
            @endforeach
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Chamados recentes</h2>
                    <p class="text-sm text-slate-500">Visão rápida das últimas movimentações que você pode acompanhar.</p>
                </div>
                <a href="{{ route('tickets.board') }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Abrir quadro</a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-6 py-3 font-medium">Título</th>
                            <th class="px-6 py-3 font-medium">Solicitante</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                            <th class="px-6 py-3 font-medium">Responsável</th>
                            <th class="px-6 py-3 font-medium">Atualizado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($recentTickets as $ticket)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 font-medium text-slate-900">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="hover:text-sky-700">{{ $ticket->title }}</a>
                                </td>
                                <td class="px-6 py-4 text-slate-600">{{ $ticket->requester?->name ?? 'N/A' }}</td>
                                <td class="px-6 py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-medium text-white" style="background-color: {{ $ticket->status?->color ?? '#64748b' }}">
                                        {{ $ticket->status?->name ?? 'Sem status' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-600">{{ $ticket->assignee?->name ?? 'Não atribuído' }}</td>
                                <td class="px-6 py-4 text-slate-500">{{ $ticket->updated_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-slate-500">Nenhum chamado disponível ainda.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.portal>
