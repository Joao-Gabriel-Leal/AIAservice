<div class="space-y-6">
    <x-portal.page-intro
        eyebrow="Busca global"
        title="Encontre o contexto certo em segundos"
        description="Digite texto, serial, usuario, setor, SLA, artigo, licenca ou trecho operacional para procurar em todo o sistema."
    />

    <x-portal.filter-bar title="Busca em tudo" description="Combine texto livre, tipos, setor e periodo para enxugar o resultado sem pesar o topo da tela.">
        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_290px]">
            <div class="space-y-4">
                <label class="block text-sm text-slate-600">
                    <span class="mb-2 block font-medium">Buscar em tudo</span>
                    <input
                        wire:model.live.debounce.350ms="query"
                        type="text"
                        class="ui-input h-12 w-full"
                        placeholder="Ex.: TI-AB7K9, 123, serial, setor, artigo, SLA, licenca ou mensagem"
                    >
                </label>

                <div class="flex flex-wrap gap-2">
                    @foreach ($typeOptions as $typeOption)
                        @php($active = in_array($typeOption['key'], $types, true))
                        <button
                            type="button"
                            wire:click="toggleType('{{ $typeOption['key'] }}')"
                            class="rounded-full border px-4 py-2 text-sm font-medium transition {{ $active ? 'border-sky-500 bg-sky-50 text-sky-700' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50' }}"
                        >
                            {{ $typeOption['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Filtros rapidos</p>
                        <p class="mt-1 text-xs text-slate-500">Setor e periodo ajudam a afinar chamados, mensagens, artigos, licencas e cadastros.</p>
                    </div>

                    @if ($hasActiveFilters)
                        <button type="button" wire:click="clearFilters" class="text-xs font-medium text-sky-700">
                            Limpar
                        </button>
                    @endif
                </div>

                <div class="mt-4 space-y-3">
                    @if ($sectorOptions->isNotEmpty())
                        <label class="block text-sm text-slate-600">
                            <span class="mb-1 block font-medium">Setor</span>
                            <select wire:model.live="sectorId" class="ui-native-select w-full">
                                <option value="">Todos os setores</option>
                                @foreach ($sectorOptions as $sectorOption)
                                    <option value="{{ $sectorOption->id }}">{{ $sectorOption->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    <label class="block text-sm text-slate-600">
                        <span class="mb-1 block font-medium">De</span>
                        <input wire:model.live="dateFrom" type="date" class="ui-input w-full">
                    </label>

                    <label class="block text-sm text-slate-600">
                        <span class="mb-1 block font-medium">Ate</span>
                        <input wire:model.live="dateTo" type="date" class="ui-input w-full">
                    </label>
                </div>
            </div>
        </div>
    </x-portal.filter-bar>

    @if (trim($results['query']) === '')
        <section class="ui-panel rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center shadow-sm">
            <p class="text-base font-semibold text-slate-900">Comece pela consulta principal</p>
            <p class="mt-2 text-sm text-slate-500">A busca agrupa por tipo e tenta priorizar correspondencias exatas, prefixos e resultados recentes.</p>
        </section>
    @elseif ($results['total'] === 0)
        <section class="ui-panel rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center shadow-sm">
            <p class="text-base font-semibold text-slate-900">Nenhum resultado encontrado</p>
            <p class="mt-2 text-sm text-slate-500">Tente outro texto, amplie o periodo ou remova um filtro rapido.</p>
        </section>
    @else
        <div class="space-y-5">
            @foreach ($results['groups'] as $group)
                @continue(count($group['items']) === 0)

                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $group['label'] }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ count($group['items']) }} resultado(s) exibido(s)</p>
                        </div>

                        @if ($group['has_more'])
                            <button
                                type="button"
                                wire:click="loadMore('{{ $group['key'] }}')"
                                class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm"
                            >
                                Ver mais {{ strtolower($group['label']) }}
                            </button>
                        @endif
                    </div>

                    <div class="mt-5 space-y-3">
                        @foreach ($group['items'] as $item)
                            <a href="{{ $item['url'] }}" class="ui-row-interactive block rounded-2xl border border-slate-200 px-4 py-4 hover:bg-slate-50">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-medium text-slate-900">{{ $item['title'] }}</p>
                                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-600">
                                                {{ $group['label'] }}
                                            </span>
                                        </div>

                                        @if (! empty($item['subtitle']))
                                            <p class="mt-1 text-sm text-slate-600">{{ $item['subtitle'] }}</p>
                                        @endif

                                        <p class="mt-2 text-sm text-slate-500">{{ $item['snippet'] }}</p>
                                    </div>

                                    @if (! empty($item['meta']))
                                        <div class="flex flex-wrap gap-2 lg:max-w-[280px] lg:justify-end">
                                            @foreach ($item['meta'] as $value)
                                                @continue(blank($value))
                                                <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                                                    {{ $value }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</div>
