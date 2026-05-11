<div class="space-y-5">
    <x-portal.page-intro
        variant="compact"
        eyebrow="Quadros operacionais"
        title="Quadros"
        description="Abra rapidamente o quadro de trabalho."
    >
        <x-slot:meta>
            <span class="portal-chip">{{ $boards->count() }} quadro(s)</span>
            <span class="portal-chip">{{ $favoriteCount }} favorito(s)</span>
            <span class="portal-chip">{{ $recentCount }} recente(s)</span>
        </x-slot:meta>
    </x-portal.page-intro>

    <x-portal.filter-bar compact title="Encontrar quadro" description="Busque por nome, setor ou empresa.">
        <x-slot:actions>
            <div class="portal-toolbar-group">
                <button type="button" wire:click="setScope('all')" class="ui-action rounded-2xl px-3 py-2 text-sm {{ $scope === 'all' ? 'ui-action-primary' : 'ui-action-secondary' }}">Todos</button>
                <button type="button" wire:click="setScope('favorites')" class="ui-action rounded-2xl px-3 py-2 text-sm {{ $scope === 'favorites' ? 'ui-action-primary' : 'ui-action-secondary' }}">Favoritos</button>
                <button type="button" wire:click="setScope('recent')" class="ui-action rounded-2xl px-3 py-2 text-sm {{ $scope === 'recent' ? 'ui-action-primary' : 'ui-action-secondary' }}">Recentes</button>
            </div>
        </x-slot:actions>
        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
            <label class="text-sm text-slate-600">
                <span class="sr-only">Buscar quadro</span>
                <input wire:model.live.debounce.350ms="search" type="text" class="ui-input h-11 w-full px-3" placeholder="Nome, setor ou empresa">
            </label>

            <div class="flex items-center gap-2">
                <span class="portal-chip">Escopo {{ $scope === 'all' ? 'total' : ($scope === 'favorites' ? 'favoritos' : 'recentes') }}</span>
            </div>
        </div>
    </x-portal.filter-bar>

    @if ($boards->isEmpty())
        <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-slate-500">
            Nenhum quadro disponivel para o seu usuario.
        </div>
    @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach ($boards as $board)
                <article class="ui-panel flex min-h-[150px] flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $board->sector?->company?->name ?? 'Sem empresa' }}</p>
                            <h2 class="mt-1 line-clamp-2 text-base font-semibold text-slate-950">{{ $board->name }}</h2>
                        </div>

                        <span class="shrink-0 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600">
                            {{ $board->open_tickets_count }} abertas
                        </span>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if ($board->sector)
                            <x-sector-badge :sector="$board->sector" mode="chip">{{ $board->sector->company?->name }}</x-sector-badge>
                        @endif

                        @if ($board->is_default)
                            <span class="ui-tone-chip ui-tone-chip-neutral">Padrao</span>
                        @endif
                    </div>

                    @if ($board->userPreferences->first()?->last_opened_at)
                        <p class="mt-3 text-xs font-medium text-slate-400">Recente {{ $board->userPreferences->first()->last_opened_at->diffForHumans() }}</p>
                    @endif

                    <div class="mt-auto flex items-center justify-between gap-3 pt-4">
                        <button
                            type="button"
                            wire:click="toggleFavorite({{ $board->id }})"
                            class="inline-flex size-9 items-center justify-center rounded-full border transition {{ $board->userPreferences->first()?->is_favorite ? 'border-rose-200 bg-rose-50 text-rose-600' : 'border-slate-200 bg-slate-50 text-slate-500 hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600' }}"
                            title="{{ $board->userPreferences->first()?->is_favorite ? 'Remover dos favoritos' : 'Favoritar quadro' }}"
                            aria-label="{{ $board->userPreferences->first()?->is_favorite ? 'Remover dos favoritos' : 'Favoritar quadro' }}"
                        >
                            @if ($board->userPreferences->first()?->is_favorite)
                                <svg viewBox="0 0 24 24" class="size-4.5" fill="currentColor" aria-hidden="true">
                                    <path d="M12 21.2 10.7 20C5.4 15.2 2 12.1 2 8.2 2 5.1 4.4 2.8 7.5 2.8c1.7 0 3.4.8 4.5 2.1 1.1-1.3 2.8-2.1 4.5-2.1 3.1 0 5.5 2.3 5.5 5.4 0 3.9-3.4 7-8.7 11.8L12 21.2Z" />
                                </svg>
                            @else
                                <svg viewBox="0 0 24 24" class="size-4.5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21.2l7.8-7.8 1-1a5.5 5.5 0 0 0 0-7.8Z" />
                                </svg>
                            @endif
                        </button>

                        <a href="{{ route('tickets.board.show', $board) }}" class="ui-action ui-action-primary rounded-2xl px-4 py-2.5 text-sm">
                            Abrir
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
