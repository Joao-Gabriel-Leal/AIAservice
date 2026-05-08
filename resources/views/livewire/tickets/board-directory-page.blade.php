<div class="space-y-6">
    <x-portal.page-intro
        eyebrow="Quadros operacionais"
        title="Quadros"
        description="Abra o quadro certo para trabalhar as demandas em lista, etapas ou kanban."
    >
        <x-slot:meta>
            <span class="portal-chip">{{ $boards->count() }} quadro(s)</span>
            <span class="portal-chip">{{ $favoriteCount }} favorito(s)</span>
            <span class="portal-chip">{{ $recentCount }} recente(s)</span>
        </x-slot:meta>
    </x-portal.page-intro>

    <x-portal.filter-bar title="Encontrar um quadro" description="Busque, favorite e reabra rapidamente os quadros que voce usa todos os dias.">
        <x-slot:actions>
            <div class="portal-toolbar-group">
                <button type="button" wire:click="setScope('all')" class="ui-action rounded-2xl px-4 py-2 text-sm {{ $scope === 'all' ? 'ui-action-primary' : 'ui-action-secondary' }}">Todos</button>
                <button type="button" wire:click="setScope('favorites')" class="ui-action rounded-2xl px-4 py-2 text-sm {{ $scope === 'favorites' ? 'ui-action-primary' : 'ui-action-secondary' }}">Favoritos</button>
                <button type="button" wire:click="setScope('recent')" class="ui-action rounded-2xl px-4 py-2 text-sm {{ $scope === 'recent' ? 'ui-action-primary' : 'ui-action-secondary' }}">Recentes</button>
            </div>
        </x-slot:actions>
        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
            <label class="text-sm text-slate-600">
                <span class="mb-1 block font-medium">Buscar quadro</span>
                <input wire:model.live.debounce.350ms="search" type="text" class="ui-input w-full" placeholder="Nome, setor ou empresa">
            </label>

            <div class="flex items-end gap-2">
                <span class="portal-chip">Escopo {{ $scope === 'all' ? 'total' : ($scope === 'favorites' ? 'favoritos' : 'recentes') }}</span>
            </div>
        </div>
    </x-portal.filter-bar>

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

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    wire:click="toggleFavorite({{ $board->id }})"
                                    class="rounded-full border px-3 py-1 text-xs font-semibold transition {{ $board->userPreferences->first()?->is_favorite ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-slate-200 bg-slate-50 text-slate-500 hover:text-slate-900' }}"
                                    title="{{ $board->userPreferences->first()?->is_favorite ? 'Remover dos favoritos' : 'Favoritar quadro' }}"
                                >
                                    {{ $board->userPreferences->first()?->is_favorite ? 'Favorito' : 'Favoritar' }}
                                </button>

                                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                                    {{ $board->open_tickets_count }} abertas
                                </span>
                            </div>
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

                        @if ($board->userPreferences->first()?->last_opened_at)
                            <p class="text-xs font-medium text-slate-400">Aberto recentemente {{ $board->userPreferences->first()->last_opened_at->diffForHumans() }}</p>
                        @endif
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
