<x-layouts.portal title="Dashboard" subtitle="Indicadores operacionais e visao recente dos chamados no seu escopo.">
    @php
        $highlightCards = [
            [
                'label' => 'Chamados abertos',
                'value' => $stats['open_tickets'],
                'hint' => 'Itens ainda sem resolucao no seu escopo.',
                'tone' => 'bg-slate-950 text-white',
                'hintTone' => 'text-slate-300',
            ],
            [
                'label' => 'SLA em atraso',
                'value' => $stats['overdue_sla'],
                'hint' => 'Demandas que precisam de atencao imediata.',
                'tone' => 'bg-rose-600 text-white',
                'hintTone' => 'text-rose-100',
            ],
            [
                'label' => 'Resolvidos no periodo',
                'value' => $stats['resolved_in_period'],
                'hint' => "Encerrados nos ultimos {$period} dias.",
                'tone' => 'bg-emerald-600 text-white',
                'hintTone' => 'text-emerald-100',
            ],
            [
                'label' => 'Sem responsavel',
                'value' => $stats['unassigned_open_tickets'],
                'hint' => 'Fila aberta para triagem ou distribuicao.',
                'tone' => 'bg-amber-400 text-slate-950',
                'hintTone' => 'text-slate-700',
            ],
        ];

        $supportCards = [
            ['label' => 'Total de chamados', 'value' => $stats['tickets_total'], 'hint' => 'Volume total visivel para o seu perfil.'],
            ['label' => 'Criados no periodo', 'value' => $stats['created_in_period'], 'hint' => "Novos chamados nos ultimos {$period} dias."],
            ['label' => 'Com atividade recente', 'value' => $stats['active_in_period'], 'hint' => "Atualizados nos ultimos {$period} dias."],
        ];
    @endphp

    <div class="space-y-6">
        <section class="ui-panel overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.18),_transparent_38%),linear-gradient(135deg,_#0f172a,_#1e293b)] px-6 py-8 text-white">
                <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-sky-200">Panorama operacional</p>
                        <h2 class="mt-3 text-3xl font-semibold">Leitura rapida do volume, da saude e do ritmo do atendimento</h2>
                        <p class="mt-3 text-sm text-slate-300">
                            Cards, graficos e fila recente respondem ao mesmo recorte de periodo para facilitar comparacao e decisao.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @foreach ($periodOptions as $optionValue => $optionLabel)
                            <a
                                href="{{ route('dashboard', ['period' => $optionValue]) }}"
                                class="{{ (int) $period === (int) $optionValue ? 'bg-white text-slate-950' : 'border border-white/20 bg-white/10 text-white hover:bg-white/20' }} rounded-xl px-4 py-2 text-sm font-medium transition"
                            >
                                {{ $optionLabel }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="grid gap-4 p-6 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($highlightCards as $card)
                    <article class="rounded-3xl p-5 shadow-sm {{ $card['tone'] }}">
                        <p class="text-sm font-medium {{ str_contains($card['tone'], 'text-white') ? 'text-white/80' : 'text-slate-700' }}">{{ $card['label'] }}</p>
                        <p class="mt-3 text-4xl font-semibold">{{ $card['value'] }}</p>
                        <p class="mt-2 text-sm {{ $card['hintTone'] }}">{{ $card['hint'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_360px]">
            <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Volume no periodo</h3>
                        <p class="mt-1 text-sm text-slate-500">Comparativo diario entre chamados criados e resolvidos.</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $periodOptions[$period] }}</span>
                </div>

                <div class="mt-6 h-80">
                    <canvas data-chart='@json($charts["volume"])'></canvas>
                </div>
            </section>

            <div class="space-y-4">
                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-900">Distribuicao por status</h3>
                    <p class="mt-1 text-sm text-slate-500">Panorama do estoque atual por etapa do fluxo.</p>

                    <div class="mt-6 h-64">
                        <canvas data-chart='@json($charts["status"])'></canvas>
                    </div>
                </section>

                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-900">Saude operacional</h3>
                    <p class="mt-1 text-sm text-slate-500">Leitura rapida dos pontos que mais pressionam o time.</p>

                    <div class="mt-6 h-64">
                        <canvas data-chart='@json($charts["health"])'></canvas>
                    </div>
                </section>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            @foreach ($supportCards as $card)
                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">{{ $card['label'] }}</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $card['value'] }}</p>
                    <p class="mt-2 text-sm text-slate-500">{{ $card['hint'] }}</p>
                </section>
            @endforeach
        </div>

        @if ($ratingSummary)
            <div class="grid gap-4 md:grid-cols-3">
                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-slate-500">Media de avaliacao</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $ratingSummary['average'] }}/5</p>
                </section>
                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-slate-500">Chamados avaliados</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $ratingSummary['rated_count'] }}</p>
                </section>
                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-slate-500">Encerrados sem avaliacao</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $ratingSummary['pending_count'] }}</p>
                </section>
            </div>
        @endif

        <div class="grid gap-4 xl:grid-cols-[320px_minmax(0,1fr)]">
            <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Estrutura visivel</p>

                <div class="mt-5 space-y-4">
                    @if (! is_null($stats['companies']))
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                            <p class="text-2xl font-semibold text-slate-900">{{ $stats['companies'] }}</p>
                            <p class="mt-1 text-sm text-slate-500">Empresas no escopo atual</p>
                        </div>
                    @endif

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-2xl font-semibold text-slate-900">{{ $stats['sectors'] }}</p>
                        <p class="mt-1 text-sm text-slate-500">Setores no seu escopo</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-2xl font-semibold text-slate-900">{{ $stats['collaborators'] }}</p>
                        <p class="mt-1 text-sm text-slate-500">Colaboradores vinculados no seu escopo</p>
                    </div>
                </div>
            </section>

            <section class="ui-panel overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-4 border-b border-slate-200 px-6 py-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Chamados recentes</h3>
                        <p class="text-sm text-slate-500">Visao rapida das ultimas movimentacoes que voce pode acompanhar.</p>
                    </div>

                    @if (auth()->user()->hasOperationalAccess())
                        <a href="{{ route('tickets.board') }}" class="ui-action ui-action-primary rounded-2xl px-4 py-2 text-sm">
                            Abrir quadro
                        </a>
                    @else
                        <a href="{{ route('tickets.central') }}" class="ui-action ui-action-primary rounded-2xl px-4 py-2 text-sm">
                            Abrir central
                        </a>
                    @endif
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
            </section>
        </div>
    </div>
</x-layouts.portal>
