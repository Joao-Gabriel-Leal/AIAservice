<div
    class="space-y-6"
    x-data="{
        openSection: $wire.entangle('openSection').live,
        selectSection(section) {
            this.openSection = section;
            $wire.setOpenSection(section);
        },
    }"
>
    @if (session('status'))
        <div class="ui-panel rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="ui-panel rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
    @endif

    <x-portal.section-hero
        compact
        eyebrow="Configuracao operacional"
        :title="$board?->name ?? 'Configurar quadro'"
        :description="$board?->description ?: 'Ajuste etapas, SLA, campos e formularios do setor.'"
        :badge="$board ? ($board->groups->count().' etapa(s)') : 'Sem setor'"
    >
        @if ($sectorOptions->count() > 1 || $boardOptions->count() > 1 || $board)
            <div class="portal-toolbar">
                <div>
                    @if ($board)
                        <div class="mb-2">
                            <x-sector-badge :sector="$board->sector" mode="chip">{{ $board->sector->company?->name }}</x-sector-badge>
                        </div>
                    @endif
                    <p class="text-sm font-semibold text-slate-900">Escopo da configuracao</p>
                    <p class="mt-1 text-sm text-slate-500">Mantenha a estrutura do quadro alinhada com o setor antes de publicar formularios.</p>
                </div>

                <div class="portal-toolbar-group">
                    @if ($sectorOptions->count() > 1)
                        <label class="text-sm text-slate-600">
                            <span class="mb-1 block font-medium">Setor</span>
                            <select wire:model.live="selectedSectorId" class="ui-native-select min-w-[240px]">
                                @foreach ($sectorOptions as $sectorOption)
                                    <option value="{{ $sectorOption->id }}">{{ $sectorOption->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    @if ($boardOptions->isNotEmpty())
                        <label class="text-sm text-slate-600">
                            <span class="mb-1 block font-medium">Quadro</span>
                            <select wire:model.live="selectedBoardId" class="ui-native-select min-w-[240px]">
                                @foreach ($boardOptions as $boardOption)
                                    <option value="{{ $boardOption->id }}">{{ $boardOption->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    @if ($board)
                        <a href="{{ route('tickets.index', ['view' => 'stages', 'sector' => $board->sector_id, 'board' => $board->id]) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Voltar aos quadros</a>
                    @endif
                </div>
            </div>
        @endif
    </x-portal.section-hero>

    @if (! $board)
        <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-slate-500">
            Nenhum quadro disponivel para configuracao.
        </div>
    @else
        <div class="grid gap-6 xl:grid-cols-[260px_1fr]">
            <aside class="ui-panel h-max rounded-3xl border border-slate-200 bg-white p-4 shadow-sm xl:sticky xl:top-6">
                <p class="px-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Configuracao</p>
                <div class="mt-3 space-y-2">
                    @foreach ($settingsSections as $sectionKey => $sectionLabel)
                        <button
                            type="button"
                            x-on:click="selectSection('{{ $sectionKey }}')"
                            class="flex w-full items-center justify-between rounded-2xl px-4 py-3 text-left text-sm font-semibold transition"
                            :class="openSection === '{{ $sectionKey }}' ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'"
                        >
                            <span>{{ $sectionLabel }}</span>
                            <span class="text-xs opacity-70">Abrir</span>
                        </button>
                    @endforeach

                    @unless ($automationsUiEnabled)
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                            <p class="font-semibold">Automacoes em manutencao</p>
                            <p class="mt-1">A criacao e edicao de automacoes esta temporariamente oculta.</p>
                        </div>
                    @endunless
                </div>
            </aside>

            <div class="min-w-0 space-y-6">
        <section x-show="openSection === 'board'" x-cloak class="ui-panel overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Dados do quadro</h3>
                    <p class="text-sm text-slate-500">Resumo do ambiente: {{ $board->groups->count() }} etapas, {{ $board->fields->count() }} campos, {{ $board->forms->count() }} formularios.</p>
                </div>
                <span class="text-sm text-slate-500">Aba ativa</span>
            </div>

            <div x-show="openSection === 'board'" x-transition.opacity.duration.150ms class="border-t border-slate-200 px-6 py-6">
                    <div class="grid gap-4 lg:grid-cols-4">
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs uppercase tracking-[0.22em] text-slate-500">Etapa inicial</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $board->groups->firstWhere('is_default', true)?->name ?? 'Nao definida' }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs uppercase tracking-[0.22em] text-slate-500">Etapa final</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $board->groups->firstWhere('is_closed', true)?->name ?? 'Nao definida' }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs uppercase tracking-[0.22em] text-slate-500">Campos no quadro</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $board->fields->where('show_on_board', true)->count() }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs uppercase tracking-[0.22em] text-slate-500">Automacoes</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $automationsUiEnabled ? $board->automationRules->count() : 'Em manutencao' }}</p>
                        </div>
                    </div>

                    <form wire:submit="saveBoardMeta" class="mt-6 grid gap-4 md:grid-cols-2">
                        <label class="text-sm text-slate-600">
                            <span class="mb-2 block font-medium">Nome do quadro</span>
                            <input type="text" wire:model="boardName" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" />
                            @error('boardName') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="text-sm text-slate-600">
                            <span class="mb-2 block font-medium">Descricao</span>
                            <textarea wire:model="boardDescription" rows="3" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none"></textarea>
                            @error('boardDescription') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <div class="md:col-span-2">
                            <button type="submit" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Salvar quadro</button>
                        </div>
                    </form>

                    <div class="mt-6 grid gap-4 lg:grid-cols-2">
                        <form wire:submit="saveBoardOperators" class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                            <div>
                                <h4 class="text-base font-semibold text-slate-900">Operadores destacados</h4>
                                <p class="mt-1 text-sm text-slate-500">Gestores e operadores do setor podem abrir este quadro. Marque aqui apenas quem atua diretamente nele.</p>
                            </div>

                            <div class="mt-4 grid max-h-64 gap-2 overflow-y-auto pr-1">
                                @forelse ($boardOperatorOptions as $operator)
                                    <label class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                                        <span class="min-w-0 truncate">{{ $operator->name }}</span>
                                        <input type="checkbox" wire:model="boardOperatorIds" value="{{ $operator->id }}" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                                    </label>
                                @empty
                                    <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-5 text-sm text-slate-500">Nenhum operador ativo neste setor.</p>
                                @endforelse
                            </div>

                            <button type="submit" class="ui-action ui-action-primary mt-4 rounded-2xl px-4 py-3 text-sm">Salvar operadores</button>
                        </form>

                        <form wire:submit="createBoard" class="rounded-3xl border border-slate-200 bg-white p-5">
                            <div>
                                <h4 class="text-base font-semibold text-slate-900">Novo quadro neste setor</h4>
                                <p class="mt-1 text-sm text-slate-500">Crie fluxos separados para tipos diferentes de formulario, como Desenvolvimento e Suporte.</p>
                            </div>

                            <div class="mt-4 space-y-3">
                                <input type="text" wire:model="newBoardName" class="ui-input w-full" placeholder="Nome do novo quadro">
                                @error('newBoardName') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror
                                <textarea wire:model="newBoardDescription" rows="3" class="ui-textarea w-full" placeholder="Descricao opcional"></textarea>
                            </div>

                            @if ($boardOperatorOptions->isNotEmpty())
                                <div class="mt-4 grid max-h-44 gap-2 overflow-y-auto pr-1">
                                    @foreach ($boardOperatorOptions as $operator)
                                        <label class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                            <span class="min-w-0 truncate">{{ $operator->name }}</span>
                                            <input type="checkbox" wire:model="newBoardOperatorIds" value="{{ $operator->id }}" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                                        </label>
                                    @endforeach
                                </div>
                            @endif

                            <button type="submit" class="ui-action ui-action-secondary mt-4 rounded-2xl px-4 py-3 text-sm">Criar quadro</button>
                        </form>
                    </div>
                </div>
        </section>

        <section x-show="openSection === 'groups'" x-cloak class="ui-panel overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Etapas</h3>
                    <p class="text-sm text-slate-500">Fluxo principal do quadro. O ticket fecha sozinho ao entrar na etapa final.</p>
                </div>
                <span class="text-sm text-slate-500">Aba ativa</span>
            </div>

            <div x-show="openSection === 'groups'" x-transition.opacity.duration.150ms class="border-t border-slate-200 px-6 py-6">
                    <div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
                        <section class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                            <div class="mb-4">
                                <h4 class="text-base font-semibold text-slate-900">{{ $editingGroupId ? 'Editar etapa' : 'Nova etapa' }}</h4>
                                <p class="text-sm text-slate-500">Escolha nome, cor e se ela sera a etapa inicial ou final.</p>
                            </div>

                            <form wire:submit="{{ $editingGroupId ? 'updateGroup' : 'addGroup' }}" class="space-y-4">
                                <input type="text" wire:model="{{ $editingGroupId ? 'editGroupForm.name' : 'groupForm.name' }}" placeholder="Nome da etapa" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" />
                                <input type="text" wire:model="{{ $editingGroupId ? 'editGroupForm.color' : 'groupForm.color' }}" placeholder="#2563eb" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" />

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" wire:model="{{ $editingGroupId ? 'editGroupForm.is_default' : 'groupForm.is_default' }}" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" /> Etapa inicial</label>
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" wire:model="{{ $editingGroupId ? 'editGroupForm.is_closed' : 'groupForm.is_closed' }}" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" /> Etapa final</label>
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" wire:model="{{ $editingGroupId ? 'editGroupForm.is_collapsed_by_default' : 'groupForm.is_collapsed_by_default' }}" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" /> Inicia recolhida</label>
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" wire:model="{{ $editingGroupId ? 'editGroupForm.is_active' : 'groupForm.is_active' }}" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" /> Ativa</label>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="submit"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="ui-loading"
                                        wire:target="{{ $editingGroupId ? 'updateGroup' : 'addGroup' }}"
                                        class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm"
                                    >
                                        <span wire:loading.remove wire:target="{{ $editingGroupId ? 'updateGroup' : 'addGroup' }}">{{ $editingGroupId ? 'Salvar etapa' : 'Criar etapa' }}</span>
                                        <span wire:loading wire:target="{{ $editingGroupId ? 'updateGroup' : 'addGroup' }}">Salvando...</span>
                                    </button>
                                    @if ($editingGroupId)
                                        <button type="button" wire:click="cancelEditingGroup" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Cancelar</button>
                                    @endif
                                </div>
                            </form>
                        </section>

                        <div class="space-y-3">
                            @foreach ($board->groups as $group)
                                @php($impact = $groupImpacts[$group->id] ?? ['tickets_count' => 0, 'catalog_count' => 0])
                                <div class="rounded-2xl border border-slate-200 p-4">
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="size-3 rounded-full" style="background-color: {{ $group->color ?: '#2563eb' }}"></span>
                                                <p class="font-medium text-slate-900">{{ $group->name }}</p>
                                                @if ($group->is_default)
                                                    <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-700">Padrao</span>
                                                @endif
                                                @if ($group->is_closed)
                                                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700">Grupo final</span>
                                                @endif
                                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $group->is_active ? 'bg-slate-100 text-slate-700' : 'bg-rose-100 text-rose-700' }}">{{ $group->is_active ? 'Ativa' : 'Inativa' }}</span>
                                            </div>
                                            <p class="mt-2 text-sm text-slate-500">{{ $impact['tickets_count'] }} tickets e {{ $impact['catalog_count'] }} itens de catalogo vinculados.</p>
                                        </div>

                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" wire:click="moveGroupUp({{ $group->id }})" wire:loading.attr="disabled" wire:loading.class="ui-loading" wire:target="moveGroupUp({{ $group->id }})" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm" @disabled($loop->first)>Subir</button>
                                            <button type="button" wire:click="moveGroupDown({{ $group->id }})" wire:loading.attr="disabled" wire:loading.class="ui-loading" wire:target="moveGroupDown({{ $group->id }})" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm" @disabled($loop->last)>Descer</button>
                                            <button type="button" wire:click="startEditingGroup({{ $group->id }})" wire:loading.attr="disabled" wire:loading.class="ui-loading" wire:target="startEditingGroup({{ $group->id }})" class="ui-action rounded-xl border border-sky-200 px-3 py-2 text-sm text-sky-700 hover:bg-sky-50">Editar</button>
                                            <button type="button" wire:click="confirmDeleteGroup({{ $group->id }})" wire:loading.attr="disabled" wire:loading.class="ui-loading" wire:target="confirmDeleteGroup({{ $group->id }})" class="ui-action ui-action-danger rounded-xl px-3 py-2 text-sm">Excluir</button>
                                        </div>
                                    </div>

                                    @if ($pendingDeletionType === 'group' && $pendingDeletionId === $group->id)
                                        <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                                            <p class="font-medium">Remover etapa</p>
                                            <div class="mt-4 grid gap-3 md:grid-cols-2">
                                                <select wire:model="replacementSelection.group_ticket_group_id" class="rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none">
                                                    <option value="">Tickets ficam sem etapa</option>
                                                    @foreach ($deletionContext['replacement_groups'] ?? [] as $replacementGroup)
                                                        <option value="{{ $replacementGroup['id'] }}">{{ $replacementGroup['name'] }}</option>
                                                    @endforeach
                                                </select>
                                                <select wire:model="replacementSelection.group_catalog_group_id" class="rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none">
                                                    <option value="">Catalogo fica sem etapa</option>
                                                    @foreach ($deletionContext['replacement_groups'] ?? [] as $replacementGroup)
                                                        <option value="{{ $replacementGroup['id'] }}">{{ $replacementGroup['name'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="mt-4 flex flex-wrap gap-2">
                                                <button type="button" wire:click="deleteGroup" wire:loading.attr="disabled" wire:loading.class="ui-loading" wire:target="deleteGroup" class="ui-action rounded-2xl bg-rose-600 px-4 py-3 text-sm font-medium text-white hover:bg-rose-500">Confirmar exclusao</button>
                                                <button type="button" wire:click="cancelDeletion" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Cancelar</button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
        </section>

        <section x-show="openSection === 'statuses'" x-cloak class="ui-panel overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Status</h3>
                    <p class="text-sm text-slate-500">Status tecnico usado em filtros, automacoes legadas e compatibilidade dos chamados.</p>
                </div>
                <span class="text-sm text-slate-500">Aba ativa</span>
            </div>

            <div x-show="openSection === 'statuses'" x-transition.opacity.duration.150ms class="border-t border-slate-200 px-6 py-6">
                <div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
                    <section class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                        <div class="mb-4">
                            <h4 class="text-base font-semibold text-slate-900">{{ $editingStatusId ? 'Editar status' : 'Novo status' }}</h4>
                            <p class="text-sm text-slate-500">Mantenha um status padrao ativo para entrada do fluxo.</p>
                        </div>

                        <form wire:submit="{{ $editingStatusId ? 'updateStatus' : 'addStatus' }}" class="space-y-4">
                            <input type="text" wire:model="{{ $editingStatusId ? 'editStatusForm.name' : 'statusForm.name' }}" placeholder="Nome do status" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" />
                            <input type="text" wire:model="{{ $editingStatusId ? 'editStatusForm.color' : 'statusForm.color' }}" placeholder="#2563eb" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" />

                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="inline-flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" wire:model="{{ $editingStatusId ? 'editStatusForm.is_default' : 'statusForm.is_default' }}" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" /> Padrao</label>
                                <label class="inline-flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" wire:model="{{ $editingStatusId ? 'editStatusForm.is_closed' : 'statusForm.is_closed' }}" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" /> Fechamento</label>
                                <label class="inline-flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" wire:model="{{ $editingStatusId ? 'editStatusForm.is_active' : 'statusForm.is_active' }}" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" /> Ativo</label>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">{{ $editingStatusId ? 'Salvar status' : 'Criar status' }}</button>
                                @if ($editingStatusId)
                                    <button type="button" wire:click="cancelEditingStatus" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Cancelar</button>
                                @endif
                            </div>
                        </form>
                    </section>

                    <div class="space-y-3">
                        @foreach ($board->statuses as $status)
                            <div class="rounded-2xl border border-slate-200 p-4">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="size-3 rounded-full" style="background-color: {{ $status->color ?: '#2563eb' }}"></span>
                                            <p class="font-medium text-slate-900">{{ $status->name }}</p>
                                            @if ($status->is_default)
                                                <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-700">Padrao</span>
                                            @endif
                                            @if ($status->is_closed)
                                                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700">Fechamento</span>
                                            @endif
                                            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $status->is_active ? 'bg-slate-100 text-slate-700' : 'bg-rose-100 text-rose-700' }}">{{ $status->is_active ? 'Ativo' : 'Inativo' }}</span>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" wire:click="moveStatusUp({{ $status->id }})" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm" @disabled($loop->first)>Subir</button>
                                        <button type="button" wire:click="moveStatusDown({{ $status->id }})" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm" @disabled($loop->last)>Descer</button>
                                        <button type="button" wire:click="startEditingStatus({{ $status->id }})" class="ui-action rounded-xl border border-sky-200 px-3 py-2 text-sm text-sky-700 hover:bg-sky-50">Editar</button>
                                        <button type="button" wire:click="confirmDeleteStatus({{ $status->id }})" class="ui-action ui-action-danger rounded-xl px-3 py-2 text-sm">Excluir</button>
                                    </div>
                                </div>

                                @if ($pendingDeletionType === 'status' && $pendingDeletionId === $status->id)
                                    <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                                        <p class="font-medium">Remover status</p>
                                        <p class="mt-1 text-xs">Escolha para onde os chamados existentes devem apontar.</p>
                                        <div class="mt-4">
                                            <select wire:model="replacementSelection.status_replacement_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none">
                                                <option value="">Selecione um substituto ativo</option>
                                                @foreach ($deletionContext['replacement_statuses'] ?? [] as $replacementStatus)
                                                    <option value="{{ $replacementStatus['id'] }}">{{ $replacementStatus['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <button type="button" wire:click="deleteStatus" class="ui-action rounded-2xl bg-rose-600 px-4 py-3 text-sm font-medium text-white hover:bg-rose-500">Confirmar exclusao</button>
                                            <button type="button" wire:click="cancelDeletion" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Cancelar</button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section x-show="openSection === 'sla'" x-cloak class="ui-panel overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">SLA</h3>
                    <p class="text-sm text-slate-500">{{ $slaIsActive ? 'Ativo no quadro.' : 'Desativado no quadro.' }} Configure tempos por prioridade.</p>
                </div>
                <span class="text-sm text-slate-500">Aba ativa</span>
            </div>

            <div x-show="openSection === 'sla'" x-transition.opacity.duration.150ms class="border-t border-slate-200 px-6 py-6">
                    <form wire:submit="saveSlaPolicy" class="space-y-5">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" wire:model="slaIsActive" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" /> SLA ativo neste quadro</label>
                        <div class="grid gap-3 lg:grid-cols-2">
                            @foreach ($priorities as $priority)
                                <div class="rounded-2xl border border-slate-200 p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="font-medium text-slate-900">{{ $priority->label() }}</p>
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $priority->badgeColor() }}">{{ $priority->label() }}</span>
                                    </div>
                                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                        <label class="text-sm text-slate-600">
                                            <span class="mb-2 block font-medium">Primeira resposta (horas)</span>
                                            <input type="number" min="1" wire:model="slaTargets.{{ $priority->value }}.first_response_hours" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" />
                                        </label>
                                        <label class="text-sm text-slate-600">
                                            <span class="mb-2 block font-medium">Resolucao (horas)</span>
                                            <input type="number" min="1" wire:model="slaTargets.{{ $priority->value }}.resolution_hours" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" />
                                        </label>
                                    </div>
                                    <p class="mt-3 text-xs text-slate-500">O motor interno continua em minutos, mas a configuracao agora e feita em horas inteiras.</p>
                                </div>
                            @endforeach
                        </div>
                        <button type="submit" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Salvar SLA</button>
                    </form>
                </div>
        </section>

        @if ($automationsUiEnabled)
        <section x-show="openSection === 'automations'" x-cloak class="ui-panel overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Automacoes</h3>
                    <p class="text-sm text-slate-500">Regras no formato Quando / Se / Entao usando etapa, prioridade, responsavel e fechamento.</p>
                </div>
                <span class="text-sm text-slate-500">Aba ativa</span>
            </div>

            <div x-show="openSection === 'automations'" x-transition.opacity.duration.150ms class="border-t border-slate-200 px-6 py-6">
                    <div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
                        <section class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                            <div class="mb-4 flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-base font-semibold text-slate-900">{{ $editingAutomationRuleId ? 'Editar regra' : 'Nova regra' }}</h4>
                                    <p class="text-sm text-slate-500">Abra o editor so quando precisar criar ou ajustar uma automacao.</p>
                                </div>
                                <button type="button" wire:click="cancelAutomationEditing" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm">Nova regra</button>
                            </div>

                            <form wire:submit="saveAutomation" class="space-y-5">
                                <div class="grid gap-3 md:grid-cols-2">
                                    <label class="text-sm text-slate-600 md:col-span-2">
                                        <span class="mb-2 block font-medium">Nome da regra</span>
                                        <input type="text" wire:model="automationForm.name" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" />
                                    </label>
                                    <label class="text-sm text-slate-600 md:col-span-2">
                                        <span class="mb-2 block font-medium">Descricao opcional</span>
                                        <textarea wire:model="automationForm.description" rows="3" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none"></textarea>
                                    </label>
                                    <label class="text-sm text-slate-600">
                                        <span class="mb-2 block font-medium">Quando</span>
                                        <select wire:model.live="automationForm.trigger" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none">
                                            @foreach ($automationTriggers as $trigger)
                                                <option value="{{ $trigger->value }}">{{ $trigger->label() }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="text-sm text-slate-600">
                                        <span class="mb-2 block font-medium">Ordem</span>
                                        <input type="number" min="1" wire:model="automationForm.sort_order" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" />
                                    </label>
                                    @if ($automationForm['trigger'] === \App\Enums\TicketAutomationTrigger::TICKET_INACTIVE->value)
                                        <label class="text-sm text-slate-600">
                                            <span class="mb-2 block font-medium">Inatividade (min)</span>
                                            <input type="number" min="1" wire:model="automationForm.inactive_for_minutes" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" />
                                        </label>
                                    @endif
                                    <label class="text-sm text-slate-600">
                                        <span class="mb-2 block font-medium">Cooldown (min)</span>
                                        <input type="number" min="1" wire:model="automationForm.cooldown_minutes" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" />
                                    </label>
                                </div>

                                <label class="inline-flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" wire:model="automationForm.is_active" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" /> Regra ativa</label>

                                <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div><p class="font-medium text-slate-900">Se</p><p class="text-xs text-slate-500">Condicoes que precisam bater.</p></div>
                                        <button type="button" wire:click="addAutomationCondition" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm">Adicionar</button>
                                    </div>

                                    <?php foreach ($automationConditions as $automationConditionIndex => $condition): ?>
                                        <?php
                                            $conditionField = data_get($condition, 'field');
                                            $conditionOperator = data_get($condition, 'operator');
                                            $isBooleanField = in_array($conditionField, [
                                                \App\Enums\TicketAutomationConditionField::HAS_ASSIGNEE->value,
                                                \App\Enums\TicketAutomationConditionField::IS_CLOSED->value,
                                            ], true);
                                            $isMultiValue = in_array($conditionOperator, [
                                                \App\Enums\TicketAutomationConditionOperator::IN->value,
                                                \App\Enums\TicketAutomationConditionOperator::NOT_IN->value,
                                            ], true);
                                            $currentValues = data_get($condition, 'value', []);
                                            $currentValues = is_array($currentValues) ? $currentValues : [$currentValues];
                                            $conditionOptions = match ($conditionField) {
                                                \App\Enums\TicketAutomationConditionField::PRIORITY->value => collect($priorities)->map(fn ($priority) => ['value' => $priority->value, 'label' => $priority->label()])->all(),
                                                \App\Enums\TicketAutomationConditionField::GROUP_ID->value => $board->groups->map(fn ($group) => ['value' => $group->id, 'label' => $group->name])->all(),
                                                default => [],
                                            };
                                        ?>

                                        <div class="rounded-2xl border border-slate-200 p-3" wire:key="automation-condition-{{ $automationConditionIndex }}">
                                            <div class="grid gap-3 md:grid-cols-[1fr_1fr_auto]">
                                                <select wire:model.live="automationConditions.{{ $automationConditionIndex }}.field" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                                    @foreach ($automationConditionFields as $field)
                                                        <option value="{{ $field->value }}">{{ $field->label() }}</option>
                                                    @endforeach
                                                </select>
                                                <select wire:model.live="automationConditions.{{ $automationConditionIndex }}.operator" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                                    @if ($isBooleanField)
                                                        <option value="{{ \App\Enums\TicketAutomationConditionOperator::IS_TRUE->value }}">{{ \App\Enums\TicketAutomationConditionOperator::IS_TRUE->label() }}</option>
                                                        <option value="{{ \App\Enums\TicketAutomationConditionOperator::IS_FALSE->value }}">{{ \App\Enums\TicketAutomationConditionOperator::IS_FALSE->label() }}</option>
                                                    @else
                                                        <option value="{{ \App\Enums\TicketAutomationConditionOperator::IN->value }}">{{ \App\Enums\TicketAutomationConditionOperator::IN->label() }}</option>
                                                        <option value="{{ \App\Enums\TicketAutomationConditionOperator::NOT_IN->value }}">{{ \App\Enums\TicketAutomationConditionOperator::NOT_IN->label() }}</option>
                                                        <option value="{{ \App\Enums\TicketAutomationConditionOperator::EQUALS->value }}">{{ \App\Enums\TicketAutomationConditionOperator::EQUALS->label() }}</option>
                                                        <option value="{{ \App\Enums\TicketAutomationConditionOperator::NOT_EQUALS->value }}">{{ \App\Enums\TicketAutomationConditionOperator::NOT_EQUALS->label() }}</option>
                                                    @endif
                                                </select>
                                                <button type="button" wire:click="removeAutomationCondition({{ $automationConditionIndex }})" class="ui-action ui-action-danger rounded-xl px-3 py-2 text-sm">Remover</button>
                                            </div>

                                            @if (! $isBooleanField)
                                                <div class="mt-3">
                                                    @if ($isMultiValue)
                                                        <select multiple wire:model="automationConditions.{{ $automationConditionIndex }}.value" class="min-h-28 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                                            @foreach ($conditionOptions as $option)
                                                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <select wire:model="automationConditions.{{ $automationConditionIndex }}.value" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                                            <option value="">Selecione</option>
                                                            @foreach ($conditionOptions as $option)
                                                                <option value="{{ $option['value'] }}" @selected((string) ($currentValues[0] ?? '') === (string) $option['value'])>{{ $option['label'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    <?php endforeach; ?>

                                    @error('automationConditions') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror
                                </div>

                                <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div><p class="font-medium text-slate-900">Entao</p><p class="text-xs text-slate-500">Acoes executadas quando a regra dispara.</p></div>
                                        <button type="button" wire:click="addAutomationAction" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm">Adicionar</button>
                                    </div>

                                    <?php foreach ($automationActions as $automationActionIndex => $action): ?>
                                        @php($actionType = data_get($action, 'action'))
                                        <div class="rounded-2xl border border-slate-200 p-3" wire:key="automation-action-{{ $automationActionIndex }}">
                                            <div class="grid gap-3 md:grid-cols-[1fr_auto]">
                                                <select wire:model.live="automationActions.{{ $automationActionIndex }}.action" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                                    @foreach ($automationActionTypes as $type)
                                                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="button" wire:click="removeAutomationAction({{ $automationActionIndex }})" class="ui-action ui-action-danger rounded-xl px-3 py-2 text-sm">Remover</button>
                                            </div>

                                            <div class="mt-3">
                                                @if ($actionType === \App\Enums\TicketAutomationActionType::ASSIGN_FIXED_ASSIGNEE->value)
                                                    <select wire:model="automationActions.{{ $automationActionIndex }}.payload.assignee_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none"><option value="">Selecione o responsavel</option>@foreach ($automationAssignees as $assignee)<option value="{{ $assignee->id }}">{{ $assignee->name }}</option>@endforeach</select>
                                                @elseif ($actionType === \App\Enums\TicketAutomationActionType::CHANGE_GROUP->value)
                                                    <select wire:model="automationActions.{{ $automationActionIndex }}.payload.group_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none"><option value="">Selecione a etapa</option>@foreach ($board->groups as $group)<option value="{{ $group->id }}">{{ $group->name }}</option>@endforeach</select>
                                                @elseif ($actionType === \App\Enums\TicketAutomationActionType::CHANGE_PRIORITY->value)
                                                    <select wire:model="automationActions.{{ $automationActionIndex }}.payload.priority" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none"><option value="">Selecione a prioridade</option>@foreach ($priorities as $priority)<option value="{{ $priority->value }}">{{ $priority->label() }}</option>@endforeach</select>
                                                @elseif ($actionType === \App\Enums\TicketAutomationActionType::ADD_SYSTEM_MESSAGE->value)
                                                    <textarea wire:model="automationActions.{{ $automationActionIndex }}.payload.message" rows="3" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" placeholder="Mensagem que sera registrada no ticket"></textarea>
                                                @elseif ($actionType === \App\Enums\TicketAutomationActionType::SEND_NOTIFICATION->value)
                                                    <div class="grid gap-3">
                                                        <input type="text" wire:model="automationActions.{{ $automationActionIndex }}.payload.title" placeholder="Titulo da notificacao" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                                                        <textarea wire:model="automationActions.{{ $automationActionIndex }}.payload.message" rows="3" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" placeholder="Mensagem da notificacao"></textarea>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    @error('automationActions') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">{{ $editingAutomationRuleId ? 'Salvar automacao' : 'Criar automacao' }}</button>
                                    @if ($editingAutomationRuleId)
                                        <button type="button" wire:click="cancelAutomationEditing" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Cancelar</button>
                                    @endif
                                </div>
                            </form>
                        </section>

                        <section class="space-y-3">
                            @forelse ($board->automationRules as $rule)
                                <div class="rounded-2xl border border-slate-200 p-4">
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="font-medium text-slate-900">{{ $rule->name }}</p>
                                                <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-700">{{ $rule->trigger->label() }}</span>
                                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $rule->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $rule->is_active ? 'Ativa' : 'Inativa' }}</span>
                                            </div>
                                            @if ($rule->description)
                                                <p class="mt-2 text-sm text-slate-500">{{ $rule->description }}</p>
                                            @endif
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                @foreach ($rule->conditions as $condition)
                                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">{{ $this->describeAutomationCondition($condition) }}</span>
                                                @endforeach
                                                @foreach ($rule->actions as $action)
                                                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs text-amber-800">{{ $this->describeAutomationAction($action) }}</span>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" wire:click="startEditingAutomation({{ $rule->id }})" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm">Editar</button>
                                            <button type="button" wire:click="toggleAutomationActive({{ $rule->id }})" class="ui-action rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ $rule->is_active ? 'Desativar' : 'Ativar' }}</button>
                                            <button type="button" wire:click="deleteAutomation({{ $rule->id }})" class="ui-action ui-action-danger rounded-xl px-3 py-2 text-sm">Excluir</button>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-slate-500">Nenhuma automacao configurada ainda.</div>
                            @endforelse
                        </section>
                    </div>
                </div>
        </section>
        @endif

        <section x-show="openSection === 'fields'" x-cloak class="ui-panel overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Campos do chamado</h3>
                    <p class="text-sm text-slate-500">Cada campo pode aparecer no quadro e tambem ser perguntado, ou nao, em cada formulario.</p>
                </div>
                <span class="text-sm text-slate-500">Aba ativa</span>
            </div>

            <div x-show="openSection === 'fields'" x-transition.opacity.duration.150ms class="border-t border-slate-200 px-6 py-6">
                    <div class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
                        <section class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                            <div class="mb-4">
                                <h4 class="text-base font-semibold text-slate-900">Novo campo</h4>
                                <p class="text-sm text-slate-500">Estrutura reutilizada no quadro e nos formularios.</p>
                            </div>

                            <form wire:submit="addField" class="space-y-4">
                                <div class="grid gap-3 md:grid-cols-2">
                                    <input type="text" wire:model="fieldForm.name" placeholder="Nome do campo" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                                    <select wire:model="fieldForm.type" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                        @foreach ($fieldTypes as $fieldType)
                                            <option value="{{ $fieldType->value }}">{{ $fieldType->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="grid gap-3 md:grid-cols-2">
                                    <input type="text" wire:model="fieldForm.placeholder" placeholder="Placeholder" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                                    <input type="text" wire:model="fieldForm.help_text" placeholder="Ajuda para o usuario" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                                </div>

                                <textarea wire:model="fieldForm.options_text" rows="4" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" placeholder="Opcoes, uma por linha. Use Label|#cor quando fizer sentido."></textarea>

                                <div class="flex flex-wrap gap-4">
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" wire:model="fieldForm.is_required" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" /> Obrigatorio por padrao</label>
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" wire:model="fieldForm.show_on_board" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" /> Mostrar no quadro</label>
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" wire:model="fieldForm.is_active" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" /> Ativo</label>
                                </div>

                                <button type="submit" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Adicionar campo</button>
                            </form>
                        </section>

                        <section class="space-y-3">
                            @forelse ($board->fields as $field)
                                <div class="rounded-2xl border border-slate-200 p-4">
                                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="font-medium text-slate-900">{{ $field->name }}</p>
                                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ $field->type->label() }}</span>
                                                @if ($field->show_on_board)
                                                    <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-700">No quadro</span>
                                                @endif
                                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $field->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $field->is_active ? 'Ativo' : 'Inativo' }}</span>
                                            </div>
                                            <p class="mt-2 text-sm text-slate-500">{{ $field->help_text ?: 'Sem texto de ajuda.' }}</p>
                                        </div>

                                        <button type="button" wire:click="deleteField({{ $field->id }})" class="ui-action ui-action-danger rounded-xl px-3 py-2 text-sm">Excluir</button>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-slate-500">Nenhum campo configurado ainda.</div>
                            @endforelse
                        </section>
                    </div>
                </div>
        </section>

        <section x-show="openSection === 'forms'" x-cloak class="ui-panel overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Formularios e catalogo</h3>
                    <p class="text-sm text-slate-500">Defina quais perguntas entram em cada formulario e qual etapa o ticket recebe ao nascer.</p>
                </div>
                <span class="text-sm text-slate-500">Aba ativa</span>
            </div>

            <div x-show="openSection === 'forms'" x-transition.opacity.duration.150ms class="border-t border-slate-200 px-6 py-6">
                    <div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
                        <section class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                            <div class="mb-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-base font-semibold text-slate-900">{{ $editingFormId ? 'Editando: '.$formForm['name'] : 'Novo formulario' }}</h4>
                                    @if ($editingFormId)
                                        <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-700">Registro existente</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm text-slate-500">Escolha em quais formularios cada campo aparece e se ele sera obrigatorio.</p>
                            </div>

                            <form wire:submit="saveForm" class="space-y-4">
                                <input type="text" wire:model="formForm.name" placeholder="Nome do formulario" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                                <textarea wire:model="formForm.description" rows="3" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" placeholder="Descricao do formulario"></textarea>
                                <label class="block text-sm text-slate-600">
                                    <span class="mb-2 block font-medium">Nivel minimo para abrir este formulario</span>
                                    <select wire:model="formForm.opening_access_level" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                        @foreach ($openingAccessLevels as $accessLevel)
                                            <option value="{{ $accessLevel->value }}">{{ $accessLevel->label() }}</option>
                                        @endforeach
                                    </select>
                                    <span class="mt-2 block text-xs text-slate-500">Publico libera para qualquer colaborador autenticado. Operador e Gestor seguem o nivel setorial.</span>
                                </label>

                                <div class="space-y-2 rounded-2xl border border-slate-200 bg-white p-4">
                                    @if ($board->fields->isEmpty())
                                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">Crie campos primeiro para montar formularios.</div>
                                    @else
                                        <?php foreach ($board->fields as $formField): ?>
                                            <?php
                                                $visibleFieldIds = array_map('strval', $formForm['field_ids'] ?? []);
                                                $isVisibleInForm = in_array((string) $formField->id, $visibleFieldIds, true);
                                                $visibilityCondition = data_get($formForm, "visibility_conditions.{$formField->id}", []);
                                                $hasVisibilityCondition = (bool) data_get($visibilityCondition, 'enabled', false);
                                                $selectedParentFieldId = data_get($visibilityCondition, 'parent_field_id');
                                                $selectedParentField = $board->fields->firstWhere('id', (int) $selectedParentFieldId);
                                                $availableParentFields = $board->fields->filter(function ($candidateField) use ($visibleFieldIds, $formField) {
                                                    return in_array((string) $candidateField->id, $visibleFieldIds, true)
                                                        && $candidateField->id !== $formField->id;
                                                })->values();
                                            ?>
                                            <div class="rounded-2xl border border-slate-200 p-3">
                                                <div class="flex flex-col gap-3">
                                                    <div>
                                                        <p class="font-medium text-slate-900">{{ $formField->name }}</p>
                                                        <p class="text-xs text-slate-500">{{ $formField->type->label() }}</p>
                                                    </div>

                                                    <div class="flex flex-wrap gap-4">
                                                        <label class="inline-flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" value="{{ $formField->id }}" wire:model="formForm.field_ids" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" /> Perguntar neste formulario</label>
                                                        <label class="inline-flex items-center gap-2 text-sm text-slate-600 {{ $isVisibleInForm ? '' : 'opacity-50' }}"><input type="checkbox" value="{{ $formField->id }}" wire:model="formForm.required_field_ids" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" @disabled(! $isVisibleInForm) /> Obrigatorio</label>
                                                        <label class="inline-flex items-center gap-2 text-sm text-slate-600 {{ $isVisibleInForm ? '' : 'opacity-50' }}"><input type="checkbox" wire:model="formForm.visibility_conditions.{{ $formField->id }}.enabled" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" @disabled(! $isVisibleInForm) /> Campo inteligente</label>
                                                    </div>
                                                </div>

                                                @if ($isVisibleInForm && $hasVisibilityCondition)
                                                    <div class="mt-4 grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-3">
                                                        <label class="text-sm text-slate-600">
                                                            <span class="mb-2 block font-medium">Campo base</span>
                                                            <select wire:model.live="formForm.visibility_conditions.{{ $formField->id }}.parent_field_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                                                <option value="">Selecione</option>
                                                                @foreach ($availableParentFields as $availableParentField)
                                                                    <option value="{{ $availableParentField->id }}">{{ $availableParentField->name }}</option>
                                                                @endforeach
                                                            </select>
                                                            @error("formForm.visibility_conditions.{$formField->id}.parent_field_id") <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                                        </label>

                                                        <label class="text-sm text-slate-600">
                                                            <span class="mb-2 block font-medium">Operador</span>
                                                            <select wire:model="formForm.visibility_conditions.{{ $formField->id }}.operator" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                                                <option value="equals">Igual a</option>
                                                            </select>
                                                            @error("formForm.visibility_conditions.{$formField->id}.operator") <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                                        </label>

                                                        <label class="text-sm text-slate-600">
                                                            <span class="mb-2 block font-medium">Valor esperado</span>
                                                            @if ($selectedParentField?->type?->value === 'select' || $selectedParentField?->type?->value === 'status')
                                                                <select wire:model="formForm.visibility_conditions.{{ $formField->id }}.expected_value" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                                                    <option value="">Selecione</option>
                                                                    @foreach ($selectedParentField->options as $option)
                                                                        <option value="{{ $option->value }}">{{ $option->label }}</option>
                                                                    @endforeach
                                                                </select>
                                                            @elseif ($selectedParentField?->type?->value === 'checkbox')
                                                                <select wire:model="formForm.visibility_conditions.{{ $formField->id }}.expected_value" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                                                    <option value="">Selecione</option>
                                                                    <option value="1">Marcado</option>
                                                                    <option value="0">Desmarcado</option>
                                                                </select>
                                                            @elseif ($selectedParentField?->type?->value === 'user')
                                                                <select wire:model="formForm.visibility_conditions.{{ $formField->id }}.expected_value" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                                                                    <option value="">Selecione</option>
                                                                    @foreach ($formConditionUsers as $conditionUser)
                                                                        <option value="{{ $conditionUser->id }}">{{ $conditionUser->name }}</option>
                                                                    @endforeach
                                                                </select>
                                                            @else
                                                                <input type="text" wire:model="formForm.visibility_conditions.{{ $formField->id }}.expected_value" placeholder="Valor que libera o campo" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                                                            @endif
                                                            @error("formForm.visibility_conditions.{$formField->id}.expected_value") <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                                        </label>
                                                    </div>
                                                @endif
                                            </div>
                                        <?php endforeach; ?>
                                    @endif
                                </div>

                                <div class="flex flex-wrap gap-4">
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" wire:model="formForm.is_default" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" /> Formulario padrao</label>
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" wire:model="formForm.is_active" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" /> Ativo</label>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">{{ $editingFormId ? 'Salvar formulario' : 'Criar formulario' }}</button>
                                    @if ($editingFormId)
                                        <button type="button" wire:click="cancelEditingForm" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Cancelar</button>
                                    @endif
                                </div>
                            </form>
                        </section>

                        <section class="space-y-6">
                            <div class="rounded-3xl border border-slate-200 bg-white p-5">
                                <div class="mb-4"><h4 class="text-base font-semibold text-slate-900">Formularios existentes</h4><p class="text-sm text-slate-500">Formulario ativo nao aparece na central sozinho: ele precisa estar vinculado a um item de catalogo ativo.</p></div>
                                <div class="space-y-3">
                                    @forelse ($board->forms as $form)
                                        @php($publishedCatalogCount = $board->catalogItems->where('ticket_form_id', $form->id)->where('is_active', true)->count())
                                        <div class="rounded-2xl border border-slate-200 p-4">
                                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                                <div>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <p class="font-medium text-slate-900">{{ $form->name }}</p>
                                                        @if ($form->is_default)
                                                            <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-700">Padrao</span>
                                                        @endif
                                                        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $form->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $form->is_active ? 'Ativo' : 'Inativo' }}</span>
                                                        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $publishedCatalogCount > 0 ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-800' }}">{{ $publishedCatalogCount > 0 ? 'Publicado na central' : 'Nao publicado na central' }}</span>
                                                        <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-medium text-violet-700">Abertura: {{ $form->opening_access_level?->label() ?? 'Publico' }}</span>
                                                    </div>
                                                    <p class="mt-2 text-sm text-slate-500">{{ $form->fields->pluck('name')->join(', ') ?: 'Sem campos vinculados' }}</p>
                                                    <p class="mt-2 text-xs text-slate-400">{{ $publishedCatalogCount }} item(ns) de catalogo ativo(s) usando este formulario.</p>
                                                </div>
                                                <div class="flex flex-wrap gap-2">
                                                    <button type="button" wire:click="startEditingForm({{ $form->id }})" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm">Editar</button>
                                                    <button type="button" wire:click="deleteForm({{ $form->id }})" class="ui-action ui-action-danger rounded-xl px-3 py-2 text-sm">Excluir</button>
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">Nenhum formulario criado ainda.</div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-white p-5">
                                <div class="mb-4">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="text-base font-semibold text-slate-900">{{ $editingCatalogItemId ? 'Editando item: '.$catalogForm['name'] : 'Catalogo de servicos' }}</h4>
                                        @if ($editingCatalogItemId)
                                            <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-700">Registro existente</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-sm text-slate-500">Cada item pode apontar para um formulario e para a etapa inicial desejada.</p>
                                </div>
                                <form wire:submit="saveCatalogItem" class="space-y-3">
                                    <input type="text" wire:model="catalogForm.name" placeholder="Nome do item de catalogo" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                                    <textarea wire:model="catalogForm.description" rows="3" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" placeholder="Descricao do item"></textarea>
                                    <div class="grid gap-3 md:grid-cols-3">
                                        <select wire:model="catalogForm.ticket_form_id" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none"><option value="">Sem formulario</option>@foreach ($board->forms as $form)<option value="{{ $form->id }}">{{ $form->name }}</option>@endforeach</select>
                                        <select wire:model="catalogForm.default_ticket_group_id" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none"><option value="">Sem etapa</option>@foreach ($board->groups as $group)<option value="{{ $group->id }}">{{ $group->name }}</option>@endforeach</select>
                                        <select wire:model="catalogForm.default_priority" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">@foreach ($priorities as $priority)<option value="{{ $priority->value }}">{{ $priority->label() }}</option>@endforeach</select>
                                    </div>
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" wire:model="catalogForm.is_active" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" /> Ativo</label>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="submit" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">{{ $editingCatalogItemId ? 'Salvar item' : 'Criar item' }}</button>
                                        @if ($editingCatalogItemId)
                                            <button type="button" wire:click="cancelEditingCatalogItem" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Cancelar</button>
                                        @endif
                                    </div>
                                </form>

                                <div class="mt-6 space-y-3">
                                    @forelse ($board->catalogItems as $catalogItem)
                                        <div class="rounded-2xl border border-slate-200 p-4">
                                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                                <div>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <p class="font-medium text-slate-900">{{ $catalogItem->name }}</p>
                                                        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $catalogItem->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $catalogItem->is_active ? 'Ativo' : 'Inativo' }}</span>
                                                    </div>
                                                    <p class="mt-2 text-sm text-slate-500">Formulario: {{ $catalogItem->form?->name ?? 'Sem formulario' }} / Etapa inicial: {{ $catalogItem->defaultGroup?->name ?? 'Livre' }}</p>
                                                </div>
                                                <div class="flex flex-wrap gap-2">
                                                    <button type="button" wire:click="startEditingCatalogItem({{ $catalogItem->id }})" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm">Editar</button>
                                                    <button type="button" wire:click="deleteCatalogItem({{ $catalogItem->id }})" class="ui-action ui-action-danger rounded-xl px-3 py-2 text-sm">Excluir</button>
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">Nenhum item de catalogo criado ainda.</div>
                                    @endforelse
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
        </section>
            </div>
        </div>
    @endif
</div>
