<x-layouts.portal title="Dashboard" subtitle="Indicadores operacionais e visao recente dos chamados no seu escopo." header-variant="none">
    @php
        $sectorQuery = array_filter(['period' => $period, 'sector_id' => $selectedSectorId]);
        $selectedSector = $availableSectors->firstWhere('id', $selectedSectorId);
        $alertToneClasses = [
            'rose' => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200',
            'amber' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200',
            'orange' => 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900/50 dark:bg-orange-950/30 dark:text-orange-200',
            'sky' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/50 dark:bg-sky-950/30 dark:text-sky-200',
            'slate' => 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-200',
        ];

        $summaryCards = [
            ['label' => 'Total de chamados', 'value' => $stats['tickets_total'], 'hint' => 'Volume visivel no escopo atual.'],
            ['label' => 'Criados no periodo', 'value' => $stats['created_in_period'], 'hint' => "Novos chamados nos ultimos {$period} dias."],
            ['label' => 'Resolvidos no periodo', 'value' => $stats['resolved_in_period'], 'hint' => "Encerrados nos ultimos {$period} dias."],
            ['label' => 'Taxa de resolucao', 'value' => $stats['resolution_rate'].'%', 'hint' => 'Resolvidos sobre criados no periodo.'],
            ['label' => 'Tempo aprovado', 'value' => $timeTrackingSummary['approved_human'], 'hint' => 'Apontamentos aprovados no periodo.'],
            ['label' => 'Com atividade recente', 'value' => $stats['active_in_period'], 'hint' => "Atualizados nos ultimos {$period} dias."],
        ];

        $ticketsListUrl = auth()->user()->hasOperationalAccess()
            ? route('tickets.index', array_filter(['sector' => $selectedSectorId, 'view' => 'list']))
            : route('tickets.central');
    @endphp

    <div class="space-y-6">
        <x-portal.page-intro
            eyebrow="Painel operacional"
            title="Dashboard operacional"
            description="Tickets, SLA, produtividade, licencas, ativos e conhecimento no mesmo recorte de periodo e setor."
        >
            <x-slot:actions>
                <a
                    href="{{ route('dashboard.export', $sectorQuery) }}"
                    class="ui-action ui-action-secondary rounded-2xl px-4 py-2 text-sm font-medium"
                >
                    Exportar Excel
                </a>
                @foreach ($periodOptions as $optionValue => $optionLabel)
                    <a
                        href="{{ route('dashboard', array_filter(['period' => $optionValue, 'sector_id' => $selectedSectorId])) }}"
                        class="{{ (int) $period === (int) $optionValue ? 'ui-action-primary text-white' : 'ui-action-secondary' }} ui-action rounded-2xl px-4 py-2 text-sm font-medium"
                    >
                        {{ $optionLabel }}
                    </a>
                @endforeach
            </x-slot:actions>
        </x-portal.page-intro>

        @if ($availableSectors->count() > 1)
            <x-portal.filter-bar title="Recorte do painel" description="Use o setor para afinar os indicadores antes de mergulhar nas tabelas e graficos.">
                <form method="GET" action="{{ route('dashboard') }}" class="flex max-w-xl gap-2">
                    <input type="hidden" name="period" value="{{ $period }}">
                    <select name="sector_id" class="ui-native-select min-w-0 flex-1 rounded-2xl text-sm">
                        <option value="">Todos os setores</option>
                        @foreach ($availableSectors as $sector)
                            <option value="{{ $sector->id }}" @selected((int) $selectedSectorId === (int) $sector->id)>
                                {{ $sector->name }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="ui-action ui-action-secondary rounded-2xl px-4 py-2 text-sm font-semibold">
                        Filtrar
                    </button>
                </form>
            </x-portal.filter-bar>
        @endif

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            @foreach ($alerts as $alert)
                @continue($alert['hidden'] ?? false)
                <article class="rounded-3xl border p-5 shadow-sm {{ $alertToneClasses[$alert['tone']] ?? $alertToneClasses['slate'] }}">
                    <p class="text-sm font-semibold">{{ $alert['label'] }}</p>
                    <p class="mt-3 text-4xl font-semibold">{{ $alert['value'] }}</p>
                    <p class="mt-2 text-sm opacity-80">{{ $alert['hint'] }}</p>
                </article>
            @endforeach
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            @foreach ($summaryCards as $card)
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

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(360px,0.65fr)]">
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

            <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                <h3 class="text-lg font-semibold text-slate-900">Saude operacional</h3>
                <p class="mt-1 text-sm text-slate-500">Leitura dos pontos que mais pressionam o time.</p>

                <div class="mt-6 h-80">
                    <canvas data-chart='@json($charts["health"])'></canvas>
                </div>
            </section>
        </div>

        <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
            <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                <h3 class="text-lg font-semibold text-slate-900">Distribuicao por status</h3>
                <p class="mt-1 text-sm text-slate-500">Estoque atual por etapa do fluxo.</p>
                <div class="mt-6 h-64">
                    <canvas data-chart='@json($charts["status"])'></canvas>
                </div>
            </section>

            <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                <h3 class="text-lg font-semibold text-slate-900">Prioridade</h3>
                <p class="mt-1 text-sm text-slate-500">Volume por urgencia declarada.</p>
                <div class="mt-6 h-64">
                    <canvas data-chart='@json($charts["priority"])'></canvas>
                </div>
            </section>

            <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                <h3 class="text-lg font-semibold text-slate-900">Backlog por responsavel</h3>
                <p class="mt-1 text-sm text-slate-500">Chamados abertos por operador ou triagem.</p>
                <div class="mt-6 h-64">
                    <canvas data-chart='@json($charts["workload"])'></canvas>
                </div>
            </section>

            <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                <h3 class="text-lg font-semibold text-slate-900">{{ $assetSummary['scope_label'] }}</h3>
                <p class="mt-1 text-sm text-slate-500">Ativos por estado operacional.</p>
                <div class="mt-6 h-64">
                    <canvas data-chart='@json($charts["assets"])'></canvas>
                </div>
            </section>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            @if ($licenseSummary['can_view'])
                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Licencas</h3>
                            <p class="mt-1 text-sm text-slate-500">Renovacoes, lotacao e assentos.</p>
                        </div>
                        <a href="{{ route('licenses.index') }}" class="ui-action ui-action-secondary rounded-2xl px-3 py-2 text-xs">Abrir</a>
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/60">
                            <p class="text-2xl font-semibold text-slate-900">{{ $licenseSummary['expired'] + $licenseSummary['expiring_soon'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">Vencidas ou vencendo</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/60">
                            <p class="text-2xl font-semibold text-slate-900">{{ $licenseSummary['full_count'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">Licencas lotadas</p>
                        </div>
                    </div>

                    <div class="mt-5 h-56">
                        <canvas data-chart='@json($charts["licenses"])'></canvas>
                    </div>
                </section>
            @endif

            <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                <h3 class="text-lg font-semibold text-slate-900">Base de conhecimento</h3>
                <p class="mt-1 text-sm text-slate-500">Uso, revisao e retorno dos artigos.</p>

                <div class="mt-5 grid grid-cols-3 gap-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/60">
                        <p class="text-2xl font-semibold text-slate-900">{{ $knowledgeBaseSummary['published'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">Publicados</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/60">
                        <p class="text-2xl font-semibold text-slate-900">{{ $knowledgeBaseSummary['drafts'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">Rascunhos</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/60">
                        <p class="text-2xl font-semibold text-slate-900">{{ $knowledgeBaseSummary['generated_from_tickets'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">De chamados</p>
                    </div>
                </div>

                <div class="mt-5 text-sm text-slate-500">
                    Feedback util: <span class="font-semibold text-slate-900">{{ $knowledgeBaseSummary['helpful_feedback'] }}</span>
                    <span class="mx-2 text-slate-300">/</span>
                    Nao util: <span class="font-semibold text-slate-900">{{ $knowledgeBaseSummary['not_helpful_feedback'] }}</span>
                </div>
            </section>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            @if ($licenseSummary['can_view'])
                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                    <h3 class="text-lg font-semibold text-slate-900">Proximas renovacoes</h3>
                    <p class="mt-1 text-sm text-slate-500">Licencas vencidas ou vencendo em ate 30 dias.</p>

                    <div class="mt-5 space-y-3">
                        @forelse ($licenseSummary['upcoming'] as $license)
                            @php
                                $dueDate = $license->dueDate();
                            @endphp
                            <a href="{{ route('licenses.show', $license) }}" class="ui-row-interactive block rounded-2xl border border-slate-200 p-4 hover:bg-slate-50 dark:border-slate-800">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ $license->displayName() }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $license->sector?->name ?? 'Sem setor' }}</p>
                                    </div>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $license->isExpired() ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700' }}">
                                        {{ $dueDate?->format('d/m/Y') ?? 'Sem data' }}
                                    </span>
                                </div>
                                <p class="mt-3 text-xs text-slate-500">{{ $license->seatsInUse() }}/{{ $license->seats_total }} assentos em uso</p>
                            </a>
                        @empty
                            <p class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-950/60">
                                Nenhuma renovacao critica encontrada.
                            </p>
                        @endforelse
                    </div>
                </section>
            @endif

            <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                <h3 class="text-lg font-semibold text-slate-900">Ativos pendentes</h3>
                <p class="mt-1 text-sm text-slate-500">Manutencao, extravio ou saneamento patrimonial.</p>

                <div class="mt-5 space-y-3">
                    @forelse ($assetSummary['attention_assets'] as $asset)
                        <a href="{{ route('assets.show', $asset) }}" class="ui-row-interactive block rounded-2xl border border-slate-200 p-4 hover:bg-slate-50 dark:border-slate-800">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $asset->name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $asset->asset_code ?? 'Sem codigo' }} - {{ $asset->currentSector?->name ?? 'Sem setor' }}</p>
                                </div>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $asset->status?->badgeClasses() ?? 'bg-slate-100 text-slate-700' }}">
                                    {{ $asset->statusLabel() }}
                                </span>
                            </div>
                            <p class="mt-3 text-xs text-slate-500">{{ $asset->allocationStatusLabel() }} - {{ $asset->currentUser?->name ?? 'Sem responsavel' }}</p>
                        </a>
                    @empty
                        <p class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-950/60">
                            Nenhum ativo pendente no escopo atual.
                        </p>
                    @endforelse
                </div>
            </section>

            <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ $knowledgeBaseSummary['can_manage'] ? 'Artigos para revisao' : 'Artigos mais usados' }}</h3>
                        <p class="mt-1 text-sm text-slate-500">Itens que ajudam a reduzir retrabalho.</p>
                    </div>
                    <a href="{{ $knowledgeBaseSummary['can_manage'] ? route('knowledge-base.manage') : route('knowledge-base.index') }}" class="ui-action ui-action-secondary rounded-2xl px-3 py-2 text-xs">Abrir</a>
                </div>

                <div class="mt-5 space-y-3">
                    @php
                        $articleList = $knowledgeBaseSummary['can_manage'] && $knowledgeBaseSummary['review_articles']->isNotEmpty()
                            ? $knowledgeBaseSummary['review_articles']
                            : $knowledgeBaseSummary['top_articles'];
                    @endphp

                    @forelse ($articleList as $article)
                        <a href="{{ route('knowledge-base.show', $article) }}" class="ui-row-interactive block rounded-2xl border border-slate-200 p-4 hover:bg-slate-50 dark:border-slate-800">
                            <p class="font-semibold text-slate-900">{{ $article->title }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                <span>{{ $article->sector?->name ?? 'Sem setor' }}</span>
                                @if (isset($article->ticket_usages_count))
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-slate-600">{{ $article->ticket_usages_count }} usos</span>
                                @endif
                                @if ($article->editorial_status?->value === 'draft')
                                    <span class="rounded-full bg-amber-100 px-2 py-1 text-amber-700">Rascunho</span>
                                @endif
                            </div>
                        </a>
                    @empty
                        <p class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-950/60">
                            Nenhum artigo para destacar agora.
                        </p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid gap-4 xl:grid-cols-[320px_minmax(0,1fr)]">
            <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                <p class="text-sm font-medium text-slate-500">Estrutura e operacao</p>

                <div class="mt-5 space-y-4">
                    @if (! is_null($stats['companies']))
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950/70">
                            <p class="text-2xl font-semibold text-slate-900">{{ $stats['companies'] }}</p>
                            <p class="mt-1 text-sm text-slate-500">Empresas no escopo atual</p>
                        </div>
                    @endif

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950/70">
                        <p class="text-2xl font-semibold text-slate-900">{{ $stats['sectors'] }}</p>
                        <p class="mt-1 text-sm text-slate-500">Setores no escopo atual</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950/70">
                        <p class="text-2xl font-semibold text-slate-900">{{ $stats['collaborators'] }}</p>
                        <p class="mt-1 text-sm text-slate-500">Colaboradores vinculados no seu escopo</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950/70">
                        <p class="text-2xl font-semibold text-slate-900">{{ $timeTrackingSummary['running_count'] }}</p>
                        <p class="mt-1 text-sm text-slate-500">Apontamentos rodando</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950/70">
                        <p class="text-2xl font-semibold text-slate-900">{{ $timeTrackingSummary['pending_manual_count'] }}</p>
                        <p class="mt-1 text-sm text-slate-500">Apontamentos manuais pendentes</p>
                    </div>
                </div>
            </section>

            <section class="ui-panel overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                <div class="flex flex-col gap-4 border-b border-slate-200 px-6 py-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Chamados recentes</h3>
                        <p class="text-sm text-slate-500">Ultimas movimentacoes visiveis no escopo atual.</p>
                    </div>
                    <a href="{{ $ticketsListUrl }}" class="ui-action ui-action-primary rounded-2xl px-4 py-2 text-sm">
                        Ver chamados
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="px-6 py-3 font-medium">Titulo</th>
                                <th class="ui-person-column-head">Solicitante</th>
                                <th class="px-6 py-3 font-medium">Status</th>
                                <th class="px-6 py-3 font-medium">Avaliacao</th>
                                <th class="ui-person-column-head">Responsavel</th>
                                <th class="px-6 py-3 font-medium">Atualizado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($recentTickets as $ticket)
                                <tr class="ui-row-interactive hover:bg-slate-50">
                                    <td class="px-6 py-4 font-medium text-slate-900">
                                        <a href="{{ route('tickets.show', $ticket) }}" class="hover:text-sky-700">{{ $ticket->title }}</a>
                                        <p class="mt-1 text-xs text-slate-500">{{ $ticket->sector?->name ?? 'Sem setor' }}</p>
                                    </td>
                                    <td class="ui-person-column-cell">
                                        <x-person-reference :user="$ticket->requester" empty-label="N/A" />
                                    </td>
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
                                    <td class="ui-person-column-cell">
                                        <x-person-reference :user="$ticket->assignee" empty-label="Nao atribuido" />
                                    </td>
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
