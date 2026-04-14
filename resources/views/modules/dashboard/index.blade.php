<x-layouts.portal title="Dashboard" subtitle="Indicadores operacionais e visao recente dos chamados no seu escopo.">
    @php
        $statCards = [
            'tickets_total' => ['label' => 'Total de chamados', 'hint' => 'Volume total visivel para o seu perfil.'],
            'open_tickets' => ['label' => 'Chamados abertos', 'hint' => 'Itens ainda sem resolucao.'],
            'created_in_period' => ['label' => 'Criados no periodo', 'hint' => "Novos chamados nos ultimos {$period} dias."],
            'resolved_in_period' => ['label' => 'Resolvidos no periodo', 'hint' => "Chamados encerrados nos ultimos {$period} dias."],
            'active_in_period' => ['label' => 'Com atividade recente', 'hint' => "Atualizados nos ultimos {$period} dias."],
            'unassigned_open_tickets' => ['label' => 'Abertos sem responsavel', 'hint' => 'Oportunidades de triagem imediata.'],
            'overdue_sla' => ['label' => 'SLA em atraso', 'hint' => 'Indicador ja existente mantido no dashboard atual.'],
        ];
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-medium text-slate-900">Janela de analise</p>
                <p class="mt-1 text-sm text-slate-500">As metricas de volume recente consideram o periodo selecionado.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach ($periodOptions as $optionValue => $optionLabel)
                    <a
                        href="{{ route('dashboard', ['period' => $optionValue]) }}"
                        class="{{ (int) $period === (int) $optionValue ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900' }} rounded-xl px-4 py-2 text-sm font-medium transition"
                    >
                        {{ $optionLabel }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($statCards as $key => $card)
                @if (array_key_exists($key, $stats) && ! is_null($stats[$key]))
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">{{ $card['label'] }}</p>
                        <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $stats[$key] }}</p>
                        <p class="mt-2 text-sm text-slate-500">{{ $card['hint'] }}</p>
                    </div>
                @endif
            @endforeach
        </div>

        @if ($ratingSummary)
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-slate-500">Media de avaliacao</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $ratingSummary['average'] }}/5</p>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-slate-500">Chamados avaliados</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $ratingSummary['rated_count'] }}</p>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-slate-500">Encerrados sem avaliacao</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $ratingSummary['pending_count'] }}</p>
                </div>
            </div>
        @endif

        <div class="grid gap-4 xl:grid-cols-3">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Estrutura visivel</p>
                <div class="mt-4 space-y-4">
                    @if (! is_null($stats['companies']))
                        <div>
                            <p class="text-2xl font-semibold text-slate-900">{{ $stats['companies'] }}</p>
                            <p class="text-sm text-slate-500">Empresas no escopo atual</p>
                        </div>
                    @endif
                    <div>
                        <p class="text-2xl font-semibold text-slate-900">{{ $stats['sectors'] }}</p>
                        <p class="text-sm text-slate-500">Setores no seu escopo</p>
                    </div>
                    <div>
                        <p class="text-2xl font-semibold text-slate-900">{{ $stats['collaborators'] }}</p>
                        <p class="text-sm text-slate-500">Colaboradores vinculados no seu escopo</p>
                    </div>
                </div>
            </div>

            <div class="xl:col-span-2 rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Chamados recentes</h2>
                        <p class="text-sm text-slate-500">Visao rapida das ultimas movimentacoes que voce pode acompanhar.</p>
                    </div>
                    <a href="{{ route('tickets.board') }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Abrir quadro</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="px-6 py-3 font-medium">Titulo</th>
                                <th class="px-6 py-3 font-medium">Solicitante</th>
                                <th class="px-6 py-3 font-medium">Status</th>
                                <th class="px-6 py-3 font-medium">Avaliacao</th>
                                <th class="px-6 py-3 font-medium">Responsavel</th>
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
                                        <div class="flex flex-wrap gap-2">
                                            <span class="rounded-full px-3 py-1 text-xs font-medium text-white" style="background-color: {{ $ticket->status?->color ?? '#64748b' }}">
                                                {{ $ticket->status?->name ?? 'Sem status' }}
                                            </span>
                                            @php
                                                $slaState = $ticket->overallSlaState();
                                                $slaBadge = match ($slaState) {
                                                    'breached' => 'bg-rose-100 text-rose-700',
                                                    'warning' => 'bg-amber-100 text-amber-700',
                                                    default => 'bg-emerald-100 text-emerald-700',
                                                };
                                                $slaLabel = match ($slaState) {
                                                    'breached' => 'SLA estourado',
                                                    'warning' => 'SLA perto do prazo',
                                                    default => 'SLA em dia',
                                                };
                                            @endphp
                                            <span class="rounded-full px-3 py-1 text-xs font-medium {{ $slaBadge }}">{{ $slaLabel }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600">
                                        @if ($ticket->rating)
                                            <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700">{{ $ticket->rating->rating }}/5</span>
                                        @elseif ($ticket->isClosed())
                                            <span class="text-xs text-slate-500">Sem avaliacao</span>
                                        @else
                                            <span class="text-xs text-slate-400">Aguardando encerramento</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-slate-600">{{ $ticket->assignee?->name ?? 'Nao atribuido' }}</td>
                                    <td class="px-6 py-4 text-slate-500">{{ $ticket->updated_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-slate-500">Nenhum chamado disponivel ainda.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.portal>
