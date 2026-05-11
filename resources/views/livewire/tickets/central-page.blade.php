<div class="space-y-6">
    @if ($sectors->isEmpty())
        <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-slate-500">
            Nenhum setor ativo disponivel para abertura de chamados.
        </div>
    @else
        <x-portal.page-intro
            variant="compact"
            eyebrow="Central de formularios"
            title="Central de formularios"
            description="Escolha um setor e abra o formulario certo."
        >
            <x-slot:actions>
                <a href="{{ route('tickets.index') }}" class="portal-layout-action">
                    Ver meus chamados
                </a>
            </x-slot:actions>
            <x-slot:meta>
                <span class="portal-chip">{{ $sectors->count() }} setor(es)</span>
                @if ($selectedSector)
                    <span class="portal-chip" data-central-selected-chip>Setor ativo: {{ $selectedSector->name }}</span>
                    <span class="portal-chip">{{ $forms->count() }} formulario(s)</span>
                    <span class="portal-chip">{{ $favoriteCount }} favorito(s)</span>
                @endif
            </x-slot:meta>
        </x-portal.page-intro>

        <x-portal.filter-bar compact title="Setores" description="Busque por setor ou empresa.">
            <div class="space-y-3">
                <label class="block">
                    <span class="sr-only">Buscar setor</span>
                    <input
                        type="text"
                        wire:model.live.debounce.250ms="sectorSearch"
                        class="ui-input h-11 w-full px-3"
                        placeholder="Buscar setor ou empresa"
                        autocomplete="off"
                    >
                </label>

                @if ($filteredSectors->isEmpty())
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-6 text-sm text-slate-500">
                        Nenhum setor encontrado para a busca informada.
                    </div>
                @else
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach ($filteredSectors as $sector)
                            @php($formCount = $sector->boards?->sum(fn ($board) => $board->forms?->count() ?? 0) ?? 0)

                            <button
                                type="button"
                                wire:click="selectSector({{ $sector->id }})"
                                wire:key="central-sector-{{ $sector->id }}"
                                class="ui-panel ui-panel-interactive central-sector-card {{ $selectedSector?->id === $sector->id ? 'central-sector-card-active' : '' }} flex min-h-[108px] flex-col items-start rounded-2xl border p-4 text-left transition"
                                style="--central-sector-color: {{ $sector->displayColor() }}; --central-sector-soft: {{ $sector->softColor() }}; --central-sector-border: {{ $sector->borderColor() }};"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="size-2.5 rounded-full" style="background-color: {{ $sector->displayColor() }}"></span>
                                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $sector->company?->name ?? 'Sem empresa' }}</p>
                                </div>
                                <p class="mt-2 text-base font-semibold">{{ $sector->name }}</p>
                                <p class="mt-1 line-clamp-1 text-sm text-slate-500">{{ $sector->description ?: 'Setor ativo para abertura e acompanhamento de solicitacoes.' }}</p>
                                <div class="mt-auto pt-3 text-xs font-medium text-slate-500">
                                    {{ $formCount }} formulario(s) ativo(s)
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-portal.filter-bar>

        @if ($selectedSector)
            <section id="central-sector-results" class="space-y-4">
                <div class="ui-panel rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition" data-central-results-frame>
                    <div class="flex flex-col gap-4 border-b border-slate-200 pb-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <div class="central-sector-pill inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-[0.68rem] font-semibold uppercase tracking-[0.18em]" style="--central-sector-color: {{ $selectedSector->displayColor() }}; --central-sector-soft: {{ $selectedSector->softColor() }}; --central-sector-border: {{ $selectedSector->borderColor() }};">
                                <span class="size-2.5 rounded-full" style="background-color: {{ $selectedSector->displayColor() }}"></span>
                                {{ $selectedSector->company?->name }}
                            </div>
                            <h3 class="mt-2 text-xl font-semibold text-slate-900">Formularios de {{ $selectedSector->name }}</h3>
                            <p class="mt-1 max-w-3xl text-sm text-slate-500">
                                {{ $selectedSector->description ?: 'Este setor esta pronto para receber solicitacoes pela central.' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                        @forelse ($forms as $form)
                            @php($formBoard = $form->board)
                            <article class="ui-panel ui-panel-interactive central-form-card rounded-2xl border border-slate-200 bg-slate-50 p-4" style="--central-sector-color: {{ $selectedSector->displayColor() }}; --central-sector-soft: {{ $selectedSector->softColor() }}; --central-sector-border: {{ $selectedSector->borderColor() }};">
                                <div class="flex h-full flex-col gap-3">
                                    <div class="space-y-2">
                                        <div class="flex items-start justify-between gap-3">
                                            <h4 class="text-base font-semibold text-slate-900">{{ $form->name }}</h4>
                                            <div class="flex shrink-0 items-center gap-2">
                                                <span class="central-form-chip rounded-full bg-white px-2.5 py-1 text-[0.68rem] font-semibold shadow-sm">
                                                    @if ($form->catalogItems->isNotEmpty())
                                                        Catalogo
                                                    @else
                                                        Direto
                                                    @endif
                                                </span>

                                                <button
                                                    type="button"
                                                    wire:click="toggleFavorite({{ $form->id }})"
                                                    class="inline-flex size-9 items-center justify-center rounded-full border transition {{ $form->userPreferences->first()?->is_favorite ? 'border-rose-200 bg-rose-50 text-rose-600' : 'border-slate-200 bg-white text-slate-500 hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600' }}"
                                                    title="{{ $form->userPreferences->first()?->is_favorite ? 'Remover dos favoritos' : 'Favoritar formulario' }}"
                                                    aria-label="{{ $form->userPreferences->first()?->is_favorite ? 'Remover dos favoritos' : 'Favoritar formulario' }}"
                                                >
                                                    @if ($form->userPreferences->first()?->is_favorite)
                                                        <svg viewBox="0 0 24 24" class="size-4.5" fill="currentColor" aria-hidden="true">
                                                            <path d="M12 21.2 10.7 20C5.4 15.2 2 12.1 2 8.2 2 5.1 4.4 2.8 7.5 2.8c1.7 0 3.4.8 4.5 2.1 1.1-1.3 2.8-2.1 4.5-2.1 3.1 0 5.5 2.3 5.5 5.4 0 3.9-3.4 7-8.7 11.8L12 21.2Z" />
                                                        </svg>
                                                    @else
                                                        <svg viewBox="0 0 24 24" class="size-4.5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                            <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21.2l7.8-7.8 1-1a5.5 5.5 0 0 0 0-7.8Z" />
                                                        </svg>
                                                    @endif
                                                </button>
                                            </div>
                                        </div>

                                        <p class="line-clamp-2 text-sm text-slate-500">
                                            {{ $form->description ?: 'Formulario configurado para este tipo de solicitacao no setor selecionado.' }}
                                        </p>

                                        @if ($formBoard)
                                            <span class="inline-flex rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-600 shadow-sm">
                                                Quadro: {{ $formBoard->name }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="mt-auto pt-2">
                                        <a href="{{ route('tickets.create', ['sector' => $selectedSector->id, 'board' => $formBoard?->id, 'form' => $form->id]) }}" class="ui-action ui-action-secondary central-form-action w-full rounded-2xl px-4 py-2.5 text-sm">
                                            Usar formulario
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-sm text-slate-500 sm:col-span-2 2xl:col-span-3">
                                Este setor ainda nao possui formularios ativos para abertura. Ative ao menos um formulario neste setor.
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>
        @endif
    @endif
</div>

@push('scripts')
    <script>
        (() => {
            const highlightElement = (element, styles, duration = 1800) => {
                if (!element) {
                    return;
                }

                const previousTransition = element.style.transition;
                const previousBoxShadow = element.style.boxShadow;
                const previousTransform = element.style.transform;

                element.style.transition = 'box-shadow 220ms ease, transform 220ms ease';

                if (styles.boxShadow) {
                    element.style.boxShadow = styles.boxShadow;
                }

                if (styles.transform) {
                    element.style.transform = styles.transform;
                }

                window.setTimeout(() => {
                    element.style.boxShadow = previousBoxShadow;
                    element.style.transform = previousTransform;
                    element.style.transition = previousTransition;
                }, duration);
            };

            window.addEventListener('central-sector-selected', () => {
                const results = document.getElementById('central-sector-results');
                const resultsFrame = document.querySelector('[data-central-results-frame]');
                const selectedChip = document.querySelector('[data-central-selected-chip]');

                if (!results) {
                    return;
                }

                const rect = results.getBoundingClientRect();
                const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
                const isOutOfView = rect.top < 96 || rect.bottom > viewportHeight;

                if (isOutOfView) {
                    const top = window.scrollY + rect.top - 90;
                    window.scrollTo({
                        top: Math.max(0, top),
                        behavior: 'smooth',
                    });
                }

                highlightElement(resultsFrame, {
                    boxShadow: '0 0 0 3px rgba(37, 99, 235, 0.18)',
                    transform: 'translateY(-2px)',
                });
                highlightElement(selectedChip, {
                    boxShadow: '0 0 0 3px rgba(37, 99, 235, 0.18)',
                });
            });
        })();
    </script>
@endpush
