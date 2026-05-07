<div class="space-y-6">
    <x-portal.section-hero
        eyebrow="Quadros operacionais"
        title="Quadros"
        description="Abra o quadro certo para trabalhar as demandas em lista, etapas ou kanban."
    >
        <div class="portal-toolbar">
            <div>
                <p class="text-sm font-semibold text-slate-900">{{ $boards->count() }} quadro(s) disponivel(is)</p>
                <p class="mt-1 text-sm text-slate-500">Aparecem aqui apenas os quadros atribuidos ao seu perfil.</p>
            </div>
        </div>
    </x-portal.section-hero>

    @if ($boards->isEmpty())
        <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-slate-500">
            Nenhum quadro disponivel para o seu usuario.
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($boards as $board)
                <article class="ui-panel flex min-h-[220px] flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="space-y-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $board->sector?->company?->name ?? 'Sem empresa' }}</p>
                                <h2 class="mt-2 text-lg font-semibold text-slate-950">{{ $board->name }}</h2>
                            </div>

                            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                                {{ $board->open_tickets_count }} abertas
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            @if ($board->sector)
                                <x-sector-badge :sector="$board->sector" mode="chip">{{ $board->sector->company?->name }}</x-sector-badge>
                            @endif

                            @if ($board->is_default)
                                <span class="ui-tone-chip ui-tone-chip-neutral">Padrao</span>
                            @endif
                        </div>

                        <p class="line-clamp-3 text-sm text-slate-500">
                            {{ $board->description ?: 'Quadro operacional para acompanhamento das demandas deste fluxo.' }}
                        </p>
                    </div>

                    <div class="mt-5 flex justify-end">
                        <a href="{{ route('tickets.board.show', $board) }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">
                            Abrir
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
