<div class="space-y-6">
    @if ($sectors->isEmpty())
        <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-slate-500">
            Nenhum setor ativo disponivel para abertura de chamados.
        </div>
    @else
        <section class="ui-panel overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.16),_transparent_42%),linear-gradient(135deg,_#0f172a,_#1e293b)] px-6 py-8 text-white">
                <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-sky-200">Central de formularios</p>
                        <h2 class="mt-3 text-3xl font-semibold">Escolha o setor e siga pelo formulario certo</h2>
                        <p class="mt-3 text-sm text-slate-300">
                            Selecione uma area para ver apenas os formularios e tipos de solicitacao disponiveis naquele setor.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('tickets.index') }}" class="ui-action rounded-2xl border border-white/20 bg-white/10 px-4 py-3 text-sm font-medium text-white hover:bg-white/20">
                            Ver meus chamados
                        </a>
                    </div>
                </div>
            </div>

            <div class="p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Setores disponiveis</p>
                        <p class="mt-1 text-sm text-slate-500">A selecao abaixo atualiza o conteudo da central sem trocar de rota.</p>
                    </div>

                    <div class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                        {{ $sectors->count() }} setor(es)
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($sectors as $sector)
                        @php($catalogCount = $sector->board?->catalogItems?->count() ?? 0)

                        <button
                            type="button"
                            wire:click="selectSector({{ $sector->id }})"
                            wire:key="central-sector-{{ $sector->id }}"
                            class="{{ $selectedSector?->id === $sector->id ? 'border-sky-400 bg-sky-50 text-slate-900 shadow-[inset_0_0_0_1px_rgba(14,165,233,0.18)]' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50' }} ui-panel ui-panel-interactive flex min-h-[132px] flex-col items-start rounded-3xl border p-5 text-left"
                        >
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">{{ $sector->company?->name ?? 'Sem empresa' }}</p>
                            <p class="mt-3 text-lg font-semibold">{{ $sector->name }}</p>
                            <p class="mt-2 line-clamp-2 text-sm text-slate-500">{{ $sector->description ?: 'Setor ativo para abertura e acompanhamento de solicitacoes.' }}</p>
                            <div class="mt-auto pt-4 text-xs font-medium text-slate-500">
                                {{ $catalogCount }} formulario(s) ativo(s)
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        </section>

        @if ($selectedSector)
            <section class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_320px]">
                <div class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-5 border-b border-slate-200 pb-5 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-500">{{ $selectedSector->company?->name }}</p>
                            <h3 class="mt-2 text-2xl font-semibold text-slate-900">Formularios de {{ $selectedSector->name }}</h3>
                            <p class="mt-2 max-w-3xl text-sm text-slate-500">
                                {{ $selectedSector->description ?: 'Este setor esta pronto para receber solicitacoes pela central.' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 lg:grid-cols-2">
                        @forelse ($catalogItems as $catalogItem)
                            <article class="ui-panel ui-panel-interactive rounded-3xl border border-slate-200 bg-slate-50 p-5">
                                <div class="flex h-full flex-col gap-4">
                                    <div class="space-y-2">
                                        <div class="flex items-start justify-between gap-3">
                                            <h4 class="text-lg font-semibold text-slate-900">{{ $catalogItem->name }}</h4>
                                            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-500 shadow-sm">
                                                {{ $catalogItem->form?->name ?? 'Abertura padrao' }}
                                            </span>
                                        </div>

                                        <p class="text-sm text-slate-500">
                                            {{ $catalogItem->description ?: 'Formulario configurado para este tipo de solicitacao no setor selecionado.' }}
                                        </p>
                                    </div>

                                    <div class="mt-auto flex items-center justify-between gap-3 pt-3">
                                        <p class="text-xs font-medium uppercase tracking-[0.2em] text-slate-400">
                                            Setor {{ $selectedSector->name }}
                                        </p>

                                        <a href="{{ route('tickets.create', $catalogItem) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">
                                            Usar formulario
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-sm text-slate-500 lg:col-span-2">
                                Este setor ainda nao publicou formularios ativos no catalogo. Para abrir chamados aqui, publique primeiro um formulario deste setor.
                            </div>
                        @endforelse
                    </div>
                </div>

                <aside class="space-y-6">
                    <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h4 class="text-lg font-semibold text-slate-900">Resumo do setor</h4>

                        <div class="mt-5 grid gap-3">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Formularios ativos</p>
                                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $catalogItems->count() }}</p>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Empresa</p>
                                <p class="mt-3 text-base font-semibold text-slate-900">{{ $selectedSector->company?->name ?? 'Sem empresa vinculada' }}</p>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Fluxo recomendado</p>
                                <p class="mt-3 text-sm text-slate-600">
                                    Todo chamado deve nascer com o formulario do setor para entrar na fila correta e reduzir retrabalho na triagem.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section class="ui-panel rounded-3xl border border-slate-200 bg-slate-950 p-6 text-white shadow-sm">
                        <h4 class="text-lg font-semibold">Nao encontrou o formulario certo?</h4>
                        <p class="mt-2 text-sm text-slate-300">
                            Toda abertura precisa de um formulario vinculado ao setor. Se faltar uma opcao aqui, o setor precisa publicar o formulario correto antes da abertura.
                        </p>
                    </section>
                </aside>
            </section>
        @endif
    @endif
</div>
