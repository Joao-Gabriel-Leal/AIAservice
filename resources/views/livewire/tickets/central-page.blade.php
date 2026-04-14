<div class="space-y-6">
    <div class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">Escolha o setor do chamado</h2>
                <p class="mt-2 text-sm text-slate-500">Todos os colaboradores autenticados podem abrir solicitacoes para qualquer setor ativo.</p>
            </div>

            <a href="{{ route('tickets.index') }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">
                Ver meus chamados
            </a>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        @forelse ($sectors as $sector)
            @php($catalogItems = $sector->board?->catalogItems ?? collect())

            <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 pb-5">
                    <p class="text-sm text-slate-500">{{ $sector->company?->name }}</p>
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h3 class="text-xl font-semibold text-slate-900">{{ $sector->name }}</h3>
                            <p class="mt-2 text-sm text-slate-500">{{ $sector->description ?: 'Setor pronto para receber chamados pela central.' }}</p>
                        </div>

                        <a href="{{ route('tickets.create', ['catalogItem' => null, 'sector' => $sector->id]) }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">
                            Abrir chamado
                        </a>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($catalogItems as $catalogItem)
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $catalogItem->name }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $catalogItem->description ?: 'Formulario configurado para este tipo de solicitacao.' }}</p>
                                    <p class="mt-2 text-xs text-slate-500">Formulario: {{ $catalogItem->form?->name ?? 'Abertura padrao' }}</p>
                                </div>

                                <a href="{{ route('tickets.create', $catalogItem) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">
                                    Usar formulario
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                            Este setor ainda nao publicou itens de catalogo ativos. Voce ainda pode abrir um chamado geral.
                        </div>
                    @endforelse
                </div>
            </section>
        @empty
            <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-slate-500 xl:col-span-2">
                Nenhum setor ativo disponivel para abertura de chamados.
            </div>
        @endforelse
    </div>
</div>
