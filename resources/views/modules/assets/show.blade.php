<x-layouts.portal :title="$asset->name" subtitle="Detalhe completo do patrimonio, localizacao atual, QR Code e historico cronologico.">
    <div class="space-y-6">
        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5 flex flex-wrap gap-2 border-b border-slate-200 pb-4 text-sm">
                <a href="{{ route('assets.index') }}" class="text-slate-500 hover:text-slate-900">Patrimonios</a>
                <span class="text-slate-300">/</span>
                <span class="font-medium text-slate-900">{{ $asset->asset_code }}</span>
            </div>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_280px]">
                <div class="space-y-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Codigo patrimonial</p>
                            <h2 class="mt-2 text-3xl font-semibold text-slate-900">{{ $asset->asset_code }}</h2>
                            <p class="mt-2 text-lg font-medium text-slate-900">{{ $asset->name }}</p>
                            <p class="mt-2 text-sm text-slate-500">{{ $asset->description ?: 'Sem descricao cadastrada.' }}</p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <span class="rounded-full px-3 py-1 text-xs font-medium {{ $asset->status?->badgeClasses() }}">{{ $asset->statusLabel() }}</span>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">{{ $asset->operationalStateLabel() }}</span>
                            @can('update', $asset)
                                <a href="{{ route('assets.edit', $asset) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Editar cadastro</a>
                            @endcan
                            @can('move', $asset)
                                <a href="{{ route('assets.movement.create', $asset) }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Movimentar</a>
                            @endcan
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <article class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-sm text-slate-500">Setor atual</p>
                            <div class="mt-2">
                                <x-sector-badge :sector="$asset->currentSector" mode="chip" />
                            </div>
                        </article>
                        <article class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-sm text-slate-500">Sala atual</p>
                            <p class="mt-2 font-semibold text-slate-900">{{ $asset->currentRoom?->name ?? 'Sem sala' }}</p>
                        </article>
                        <article class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-sm text-slate-500">Colaborador atual</p>
                            <p class="mt-2 font-semibold text-slate-900">{{ $asset->currentUser?->name ?? 'Sem colaborador vinculado' }}</p>
                        </article>
                        <article class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-sm text-slate-500">Criado por</p>
                            <p class="mt-2 font-semibold text-slate-900">{{ $asset->creator?->name ?? 'Sistema' }}</p>
                        </article>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <article class="rounded-3xl border border-slate-200 bg-white p-4">
                            <p class="text-sm text-slate-500">Numero de serie</p>
                            <p class="mt-2 font-medium text-slate-900">{{ $asset->serial_number ?: 'Nao informado' }}</p>
                        </article>
                        <article class="rounded-3xl border border-slate-200 bg-white p-4">
                            <p class="text-sm text-slate-500">Marca</p>
                            <p class="mt-2 font-medium text-slate-900">{{ $asset->brand ?: 'Nao informada' }}</p>
                        </article>
                        <article class="rounded-3xl border border-slate-200 bg-white p-4">
                            <p class="text-sm text-slate-500">Modelo</p>
                            <p class="mt-2 font-medium text-slate-900">{{ $asset->model ?: 'Nao informado' }}</p>
                        </article>
                    </div>
                </div>

                <aside class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                    <p class="text-sm font-medium text-slate-500">Etiqueta digital</p>
                    <a href="{{ route('assets.show', $asset) }}" class="mt-4 block rounded-3xl border-2 border-dashed border-slate-300 bg-white p-4 text-center" data-qr-target="{{ $asset->detailUrl() }}">
                        {!! $asset->qrCodeSvg(220) !!}
                        <span class="mt-3 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $asset->asset_code }}</span>
                    </a>
                    <p class="mt-4 text-xs text-slate-500">O QR direciona para a pagina autenticada deste patrimonio.</p>
                    <p class="mt-3 break-all text-xs text-slate-500">{{ $asset->detailUrl() }}</p>
                </aside>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="border-b border-slate-200 pb-4">
                <h3 class="text-lg font-semibold text-slate-900">Historico de movimentacoes</h3>
                <p class="mt-1 text-sm text-slate-500">A trilha abaixo registra lotacao, status e atribuicoes do patrimonio.</p>
            </div>

            <div class="space-y-4 pt-6">
                @forelse ($asset->movements as $movement)
                    <article class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-slate-900 px-3 py-1 text-xs font-medium text-white">{{ $movement->type?->label() ?? 'Movimentacao' }}</span>
                                    <span class="text-xs text-slate-500">{{ $movement->moved_at?->format('d/m/Y H:i') }}</span>
                                </div>
                                <p class="mt-3 text-sm font-medium text-slate-900">{{ $movement->reason ?: 'Sem motivo informado' }}</p>
                                @if ($movement->notes)
                                    <p class="mt-1 text-sm text-slate-500">{{ $movement->notes }}</p>
                                @endif
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                                <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Responsavel pela acao</p>
                                <p class="mt-1 font-medium text-slate-900">{{ $movement->movedBy?->name ?? 'Sistema' }}</p>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 lg:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm text-slate-600">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Origem</p>
                                @if ($movement->from_sector_id || $movement->from_room_id || $movement->from_user_id || $movement->from_status)
                                    <div class="mt-2">
                                        <x-sector-badge :sector="$movement->fromSector" mode="chip" />
                                    </div>
                                    <p class="mt-1">{{ $movement->fromRoom?->name ?? 'Sem sala' }}</p>
                                    <p class="mt-1">{{ $movement->fromUser?->name ?? 'Sem colaborador' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $movement->from_status?->label() ?? 'Sem status' }}</p>
                                @else
                                    <p class="mt-2 text-slate-500">Cadastro inicial do patrimonio.</p>
                                @endif
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm text-slate-600">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Destino</p>
                                <div class="mt-2">
                                    <x-sector-badge :sector="$movement->toSector" mode="chip" />
                                </div>
                                <p class="mt-1">{{ $movement->toRoom?->name ?? 'Sem sala' }}</p>
                                <p class="mt-1">{{ $movement->toUser?->name ?? 'Sem colaborador' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $movement->to_status?->label() ?? 'Sem status' }}</p>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
                        Nenhuma movimentacao registrada.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.portal>
