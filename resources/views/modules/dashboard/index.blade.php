<x-layouts.portal title="Dashboard" subtitle="Indicadores operacionais e visao recente dos chamados no seu escopo." :show-header="false">
    @php
        $highlightCards = [
            [
                'label' => 'Chamados abertos',
                'value' => $stats['open_tickets'],
                'hint' => 'Itens ainda sem resolucao no seu escopo.',
                'surface' => 'border-[#d7e3ff] bg-[#f8fbff] dark:border-[#1b315d] dark:bg-[#0d1832]',
                'chip' => 'Atual',
                'chipTone' => 'bg-[#e8eeff] text-[#243fc8] dark:bg-[#162955] dark:text-[#bed1ff]',
            ],
            [
                'label' => 'SLA em atraso',
                'value' => $stats['overdue_sla'],
                'hint' => 'Demandas que precisam de atencao imediata.',
                'surface' => 'border-[#ffd6df] bg-[#fff8fa] dark:border-[#4e2231] dark:bg-[#2a1420]',
                'chip' => 'Critico',
                'chipTone' => 'bg-[#ffe2e8] text-[#d44568] dark:bg-[#4a2230] dark:text-[#ffb0c3]',
            ],
            [
                'label' => 'Resolvidos no periodo',
                'value' => $stats['resolved_in_period'],
                'hint' => "Encerrados nos ultimos {$period} dias.",
                'surface' => 'border-[#d7f2e6] bg-[#f7fffb] dark:border-[#1e4a45] dark:bg-[#0d2626]',
                'chip' => 'Entrega',
                'chipTone' => 'bg-[#dff8ee] text-[#16996d] dark:bg-[#163c3a] dark:text-[#8de3c5]',
            ],
            [
                'label' => 'Sem responsavel',
                'value' => $stats['unassigned_open_tickets'],
                'hint' => 'Fila aberta para triagem ou distribuicao.',
                'surface' => 'border-[#ffe6be] bg-[#fffaf1] dark:border-[#5d4220] dark:bg-[#291d12]',
                'chip' => 'Triagem',
                'chipTone' => 'bg-[#fff0ce] text-[#c38108] dark:bg-[#4d3516] dark:text-[#ffd48a]',
            ],
        ];

        $supportCards = [
            ['label' => 'Total de chamados', 'value' => $stats['tickets_total'], 'hint' => 'Volume total visivel para o seu perfil.'],
            ['label' => 'Criados no periodo', 'value' => $stats['created_in_period'], 'hint' => "Novos chamados nos ultimos {$period} dias."],
            ['label' => 'Com atividade recente', 'value' => $stats['active_in_period'], 'hint' => "Atualizados nos ultimos {$period} dias."],
        ];
    @endphp

    <div class="space-y-6">
        <x-portal.section-hero
            eyebrow="Panorama operacional"
            title="Leitura rapida do volume, da saude e do ritmo do atendimento"
            description="Cards, graficos e fila recente respondem ao mesmo recorte de periodo para facilitar comparacao e decisao."
        >
            <x-slot:actions>
                <a
                    href="{{ route('dashboard.export', ['period' => $period]) }}"
                    class="border border-white/16 bg-white/6 text-white hover:bg-white/12 ui-action rounded-2xl px-4 py-2 text-sm font-medium"
                >
                    Exportar Excel
                </a>
                @foreach ($periodOptions as $optionValue => $optionLabel)
                    <a
                        href="{{ route('dashboard', ['period' => $optionValue]) }}"
                        class="{{ (int) $period === (int) $optionValue ? 'bg-white text-[#1f3152]' : 'border border-white/16 bg-white/6 text-white hover:bg-white/12' }} ui-action rounded-2xl px-4 py-2 text-sm font-medium"
                    >
                        {{ $optionLabel }}
                    </a>
                @endforeach
            </x-slot:actions>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($highlightCards as $card)
                    <article class="rounded-3xl border p-5 shadow-sm {{ $card['surface'] }}">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $card['label'] }}</p>
                            <span class="rounded-full px-3 py-1 text-[11px] font-semibold {{ $card['chipTone'] }}">{{ $card['chip'] }}</span>
                        </div>
                        <p class="mt-4 text-4xl font-semibold text-[#10235f] dark:text-white">{{ $card['value'] }}</p>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $card['hint'] }}</p>
                    </article>
                @endforeach
            </div>
        </x-portal.section-hero>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_360px]">
            <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Volume no periodo</h3>
                        <p class="mt-1 text-sm text-slate-500">Comparativo diario entre chamados criados e resolvidos.</p>
                    </div>
                    <span class="portal-chip dark:bg-slate-800 dark:text-slate-300">{{ $periodOptions[$period] }}</span>
                </div>

                <div class="mt-6 h-80">
                    <canvas data-chart='@json($charts["volume"])'></canvas>
                </div>
            </section>

            <div class="space-y-4">
                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                    <h3 class="text-lg font-semibold text-slate-900">Distribuicao por status</h3>
                    <p class="mt-1 text-sm text-slate-500">Panorama do estoque atual por etapa do fluxo.</p>

                    <div class="mt-6 h-64">
                        <canvas data-chart='@json($charts["status"])'></canvas>
                    </div>
                </section>

                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
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
                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                    <p class="text-sm font-medium text-slate-500">{{ $card['label'] }}</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $card['value'] }}</p>
                    <p class="mt-2 text-sm text-slate-500">{{ $card['hint'] }}</p>
                </section>
            @endforeach
        </div>

        @if ($ratingSummary)
            <div class="grid gap-4 md:grid-cols-3">
                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                    <p class="text-sm text-slate-500">Media de avaliacao</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $ratingSummary['average'] }}/5</p>
                </section>
                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                    <p class="text-sm text-slate-500">Chamados avaliados</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $ratingSummary['rated_count'] }}</p>
                </section>
                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                    <p class="text-sm text-slate-500">Encerrados sem avaliacao</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $ratingSummary['pending_count'] }}</p>
                </section>
            </div>
        @endif

        <div class="grid gap-4 xl:grid-cols-[320px_minmax(0,1fr)]">
            <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                <p class="text-sm font-medium text-slate-500">Estrutura visivel</p>

                <div class="mt-5 space-y-4">
                    @if (! is_null($stats['companies']))
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950/70">
                            <p class="text-2xl font-semibold text-slate-900">{{ $stats['companies'] }}</p>
                            <p class="mt-1 text-sm text-slate-500">Empresas no escopo atual</p>
                        </div>
                    @endif

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950/70">
                        <p class="text-2xl font-semibold text-slate-900">{{ $stats['sectors'] }}</p>
                        <p class="mt-1 text-sm text-slate-500">Setores no seu escopo</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950/70">
                        <p class="text-2xl font-semibold text-slate-900">{{ $stats['collaborators'] }}</p>
                        <p class="mt-1 text-sm text-slate-500">Colaboradores vinculados no seu escopo</p>
                    </div>
                </div>
            </section>

            <section class="ui-panel overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
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
                                <tr class="ui-row-interactive hover:bg-slate-50">
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
