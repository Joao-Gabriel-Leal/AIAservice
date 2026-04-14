<div class="space-y-6">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm text-slate-500">Configuracao do ambiente de chamados por setor.</p>
                <h2 class="mt-1 text-2xl font-semibold text-slate-900">{{ $board?->name ?? 'Quadro do setor' }}</h2>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                @if ($sectorOptions->count() > 1)
                    <label class="text-sm text-slate-600">
                        <span class="mb-1 block font-medium">Setor</span>
                        <select wire:model.live="selectedSectorId" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none">
                            @foreach ($sectorOptions as $sectorOption)
                                <option value="{{ $sectorOption->id }}">{{ $sectorOption->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                @if ($board)
                    <a href="{{ route('tickets.board', $board->sector_id) }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-3 text-sm font-medium text-slate-700">
                        Voltar ao quadro
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if (! $board)
        <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-slate-500">
            Nenhum quadro disponivel para configuracao.
        </div>
    @else
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-slate-900">Dados do quadro</h3>
                <p class="text-sm text-slate-500">Nome e descricao usados no ambiente do setor.</p>
            </div>

            <form wire:submit="saveBoardMeta" class="grid gap-4 md:grid-cols-2">
                <label class="text-sm text-slate-600">
                    <span class="mb-2 block font-medium">Nome</span>
                    <input type="text" wire:model="boardName" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" />
                    @error('boardName') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>

                <label class="text-sm text-slate-600">
                    <span class="mb-2 block font-medium">Descricao</span>
                    <textarea wire:model="boardDescription" rows="3" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none"></textarea>
                    @error('boardDescription') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>

                <div class="md:col-span-2">
                    <button type="submit" class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-medium text-white">Salvar quadro</button>
                </div>
            </form>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-slate-900">Grupos</h3>
                    <p class="text-sm text-slate-500">Faixas do board no estilo Monday.</p>
                </div>

                <form wire:submit="addGroup" class="grid gap-3 md:grid-cols-2">
                    <input type="text" wire:model="groupForm.name" placeholder="Nome do grupo" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                    <input type="text" wire:model="groupForm.color" placeholder="#2563eb" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" wire:model="groupForm.is_collapsed_by_default" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        Iniciar recolhido
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" wire:model="groupForm.is_active" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        Ativo
                    </label>
                    <div class="md:col-span-2">
                        <button type="submit" class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-medium text-white">Adicionar grupo</button>
                    </div>
                </form>

                <div class="mt-6 space-y-3">
                    @foreach ($board->groups as $group)
                        <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="size-3 rounded-full" style="background-color: {{ $group->color ?: '#2563eb' }}"></span>
                                <div>
                                    <p class="font-medium text-slate-900">{{ $group->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $group->slug }}</p>
                                </div>
                            </div>
                            <button type="button" wire:click="deleteGroup({{ $group->id }})" class="rounded-xl border border-rose-200 px-3 py-2 text-sm text-rose-700">Excluir</button>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-slate-900">Status</h3>
                    <p class="text-sm text-slate-500">Estados usados pelo fluxo do chamado.</p>
                </div>

                <form wire:submit="addStatus" class="grid gap-3 md:grid-cols-2">
                    <input type="text" wire:model="statusForm.name" placeholder="Nome do status" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                    <input type="text" wire:model="statusForm.color" placeholder="#2563eb" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" wire:model="statusForm.is_default" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        Padrao
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" wire:model="statusForm.is_closed" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        Fecha o chamado
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600 md:col-span-2">
                        <input type="checkbox" wire:model="statusForm.is_active" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        Ativo
                    </label>
                    <div class="md:col-span-2">
                        <button type="submit" class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-medium text-white">Adicionar status</button>
                    </div>
                </form>

                <div class="mt-6 space-y-3">
                    @foreach ($board->statuses as $status)
                        <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="size-3 rounded-full" style="background-color: {{ $status->color ?: '#2563eb' }}"></span>
                                <div>
                                    <p class="font-medium text-slate-900">{{ $status->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $status->is_default ? 'Padrao' : 'Opcional' }} / {{ $status->is_closed ? 'Fechado' : 'Aberto' }}</p>
                                </div>
                            </div>
                            <button type="button" wire:click="deleteStatus({{ $status->id }})" class="rounded-xl border border-rose-200 px-3 py-2 text-sm text-rose-700">Excluir</button>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-slate-900">Campos dinamicos</h3>
                    <p class="text-sm text-slate-500">Estrutura reaproveitada por tabela e formularios.</p>
                </div>

                <form wire:submit="addField" class="space-y-3">
                    <div class="grid gap-3 md:grid-cols-2">
                        <input type="text" wire:model="fieldForm.name" placeholder="Nome do campo" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                        <select wire:model.live="fieldForm.type" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                            @foreach ($fieldTypes as $fieldType)
                                <option value="{{ $fieldType->value }}">{{ $fieldType->label() }}</option>
                            @endforeach
                        </select>
                        <input type="text" wire:model="fieldForm.placeholder" placeholder="Placeholder" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                        <input type="text" wire:model="fieldForm.help_text" placeholder="Ajuda para o usuario" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                    </div>

                    <textarea wire:model="fieldForm.options_text" rows="4" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" placeholder="Opcoes, uma por linha. Use Label|#cor para definir cor quando fizer sentido."></textarea>

                    <div class="flex flex-wrap gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" wire:model="fieldForm.is_required" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                            Obrigatorio
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" wire:model="fieldForm.is_active" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                            Ativo
                        </label>
                    </div>

                    <button type="submit" class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-medium text-white">Adicionar campo</button>
                </form>

                <div class="mt-6 space-y-3">
                    @foreach ($board->fields as $field)
                        <div class="rounded-2xl border border-slate-200 px-4 py-3">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $field->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $field->type->label() }} / {{ $field->slug }}</p>
                                    @if ($field->options->isNotEmpty())
                                        <p class="mt-2 text-xs text-slate-500">{{ $field->options->pluck('label')->join(', ') }}</p>
                                    @endif
                                </div>
                                <button type="button" wire:click="deleteField({{ $field->id }})" class="rounded-xl border border-rose-200 px-3 py-2 text-sm text-rose-700">Excluir</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-slate-900">Formularios e catalogo</h3>
                    <p class="text-sm text-slate-500">Monte experiencias de abertura especificas por tipo de servico.</p>
                </div>

                <form wire:submit="addForm" class="space-y-3">
                    <input type="text" wire:model="formForm.name" placeholder="Nome do formulario" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                    <textarea wire:model="formForm.description" rows="3" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" placeholder="Descricao do formulario"></textarea>

                    <div class="space-y-2 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-medium text-slate-700">Campos incluidos</p>
                        @foreach ($board->fields as $field)
                            <label class="flex items-center justify-between gap-3 rounded-xl bg-white px-3 py-2 text-sm text-slate-700">
                                <span class="inline-flex items-center gap-2">
                                    <input type="checkbox" value="{{ $field->id }}" wire:model="formForm.field_ids" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                                    {{ $field->name }}
                                </span>
                                <span class="inline-flex items-center gap-2 text-xs text-slate-500">
                                    <input type="checkbox" value="{{ $field->id }}" wire:model="formForm.required_field_ids" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                                    Obrigatorio
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" wire:model="formForm.is_default" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                            Padrao
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" wire:model="formForm.is_active" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                            Ativo
                        </label>
                    </div>

                    <button type="submit" class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-medium text-white">Criar formulario</button>
                </form>

                <div class="mt-6 space-y-3">
                    @foreach ($board->forms as $form)
                        <div class="rounded-2xl border border-slate-200 px-4 py-3">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $form->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $form->fields->pluck('name')->join(', ') ?: 'Sem campos vinculados' }}</p>
                                </div>
                                <button type="button" wire:click="deleteForm({{ $form->id }})" class="rounded-xl border border-rose-200 px-3 py-2 text-sm text-rose-700">Excluir</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <form wire:submit="addCatalogItem" class="mt-8 space-y-3 border-t border-slate-200 pt-6">
                    <input type="text" wire:model="catalogForm.name" placeholder="Nome do item de catalogo" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
                    <textarea wire:model="catalogForm.description" rows="3" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" placeholder="Descricao do item"></textarea>

                    <div class="grid gap-3 md:grid-cols-3">
                        <select wire:model="catalogForm.ticket_form_id" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                            <option value="">Sem formulario</option>
                            @foreach ($board->forms as $form)
                                <option value="{{ $form->id }}">{{ $form->name }}</option>
                            @endforeach
                        </select>
                        <select wire:model="catalogForm.default_ticket_group_id" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                            <option value="">Sem grupo</option>
                            @foreach ($board->groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                        <select wire:model="catalogForm.default_priority" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" wire:model="catalogForm.is_active" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        Ativo
                    </label>

                    <button type="submit" class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-medium text-white">Criar item de catalogo</button>
                </form>

                <div class="mt-6 space-y-3">
                    @foreach ($board->catalogItems as $catalogItem)
                        <div class="rounded-2xl border border-slate-200 px-4 py-3">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $catalogItem->name }}</p>
                                    <p class="text-xs text-slate-500">Formulario: {{ $catalogItem->form?->name ?? 'Sem formulario' }} / Grupo: {{ $catalogItem->defaultGroup?->name ?? 'Livre' }}</p>
                                </div>
                                <button type="button" wire:click="deleteCatalogItem({{ $catalogItem->id }})" class="rounded-xl border border-rose-200 px-3 py-2 text-sm text-rose-700">Excluir</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    @endif
</div>
