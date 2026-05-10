<div class="space-y-6">
    @if ($sectors->isEmpty())
        <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-slate-500">
            Nenhum setor ativo disponivel para abertura de chamados.
        </div>
    @else
        <x-portal.page-intro
            eyebrow="Central de formularios"
            title="Escolha o setor e siga pelo formulario certo"
            description="Selecione uma area para ver os formularios ativos disponiveis naquele setor."
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
                @endif
            </x-slot:meta>
        </x-portal.page-intro>

        <x-portal.filter-bar title="Setores disponiveis" description="Busque por nome ou empresa.">
            <div class="space-y-4">
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
                    <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-sm text-slate-500">
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
                                class="ui-panel ui-panel-interactive flex min-h-[132px] flex-col items-start rounded-3xl border p-5 text-left transition"
                                style="{{ $selectedSector?->id === $sector->id
                                    ? 'border-color: '.$sector->displayColor().'; background-color: '.$sector->softColor().'; box-shadow: inset 0 0 0 1px '.$sector->borderColor().'; color: #0f172a;'
                                    : 'border-color: #e2e8f0; background-color: #ffffff; color: #334155;' }}"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="size-3 rounded-full" style="background-color: {{ $sector->displayColor() }}"></span>
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">{{ $sector->company?->name ?? 'Sem empresa' }}</p>
                                </div>
                                <p class="mt-3 text-lg font-semibold">{{ $sector->name }}</p>
                                <p class="mt-2 line-clamp-2 text-sm text-slate-500">{{ $sector->description ?: 'Setor ativo para abertura e acompanhamento de solicitacoes.' }}</p>
                                <div class="mt-auto pt-4 text-xs font-medium text-slate-500">
                                    {{ $formCount }} formulario(s) ativo(s)
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-portal.filter-bar>

        @if ($selectedSector)
            <section id="central-sector-results" class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_320px]">
                <div class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition" data-central-results-frame>
                    <div class="flex flex-col gap-5 border-b border-slate-200 pb-5 lg:flex-row lg:items-end lg:justify-between">
                        <div data-central-results-header>
                            <div class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em]" style="background-color: {{ $selectedSector->softColor() }}; color: {{ $selectedSector->displayColor() }};">
                                <span class="size-2.5 rounded-full" style="background-color: {{ $selectedSector->displayColor() }}"></span>
                                {{ $selectedSector->company?->name }}
                            </div>
                            <h3 class="mt-2 text-2xl font-semibold text-slate-900">Formularios de {{ $selectedSector->name }}</h3>
                            <p class="mt-2 max-w-3xl text-sm text-slate-500">
                                {{ $selectedSector->description ?: 'Este setor esta pronto para receber solicitacoes pela central.' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 lg:grid-cols-2">
                        @forelse ($forms as $form)
                            @php($formBoard = $form->board)
                            <article class="ui-panel ui-panel-interactive rounded-3xl border border-slate-200 bg-slate-50 p-5" style="border-color: {{ $selectedSector->borderColor() }}; background: linear-gradient(180deg, {{ $selectedSector->softColor() }} 0%, #ffffff 100%);">
                                <div class="flex h-full flex-col gap-4">
                                    <div class="space-y-2">
                                        <div class="flex items-start justify-between gap-3">
                                            <h4 class="text-lg font-semibold text-slate-900">{{ $form->name }}</h4>
                                            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold shadow-sm" style="color: {{ $selectedSector->displayColor() }}">
                                                @if ($form->catalogItems->isNotEmpty())
                                                    Publicado no catalogo
                                                @else
                                                    Abertura direta
                                                @endif
                                            </span>
                                        </div>

                                        <p class="text-sm text-slate-500">
                                            {{ $form->description ?: 'Formulario configurado para este tipo de solicitacao no setor selecionado.' }}
                                        </p>

                                        @if ($formBoard)
                                            <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-600 shadow-sm">
                                                Quadro: {{ $formBoard->name }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="mt-auto flex flex-col items-stretch gap-3 pt-3 sm:flex-row sm:items-center sm:justify-between">
                                        <p class="text-xs font-medium uppercase tracking-[0.2em] text-slate-400">
                                            Setor {{ $selectedSector->name }}
                                        </p>

                                        <a href="{{ route('tickets.create', ['sector' => $selectedSector->id, 'board' => $formBoard?->id, 'form' => $form->id]) }}" class="ui-action ui-action-secondary w-full rounded-2xl px-4 py-3 text-sm sm:w-auto" style="border-color: {{ $selectedSector->borderColor() }}; color: {{ $selectedSector->displayColor() }};">
                                            Usar formulario
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-sm text-slate-500 lg:col-span-2">
                                Este setor ainda nao possui formularios ativos para abertura. Ative ao menos um formulario neste setor.
                            </div>
                        @endforelse
                    </div>
                </div>

                <aside class="space-y-6">
                    <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h4 class="text-lg font-semibold text-slate-900">Resumo do setor</h4>

                        <div class="mt-5 grid gap-3">
                            <div class="rounded-2xl border border-slate-200 px-4 py-4" style="border-color: {{ $selectedSector->borderColor() }}; background-color: {{ $selectedSector->softColor() }};">
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Formularios ativos</p>
                                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $forms->count() }}</p>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Empresa</p>
                                <p class="mt-3 text-base font-semibold text-slate-900">{{ $selectedSector->company?->name ?? 'Sem empresa vinculada' }}</p>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Abertura pelo formulario</p>
                                <p class="mt-3 text-sm text-slate-600">Use um dos formularios abaixo para cair na fila certa.</p>
                            </div>
                        </div>
                    </section>

                    <section class="ui-panel rounded-3xl border border-slate-200 bg-[#15233e] p-6 text-white shadow-sm">
                        <h4 class="text-lg font-semibold">Nao encontrou o formulario certo?</h4>
                        <p class="mt-2 text-sm text-slate-300">Revise se ele esta ativo neste setor.</p>
                    </section>
                </aside>
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
                const resultsHeader = document.querySelector('[data-central-results-header]');
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
                highlightElement(resultsHeader, {
                    boxShadow: '0 0 0 3px rgba(37, 99, 235, 0.14)',
                });
                highlightElement(selectedChip, {
                    boxShadow: '0 0 0 3px rgba(37, 99, 235, 0.18)',
                });
            });
        })();
    </script>
@endpush
