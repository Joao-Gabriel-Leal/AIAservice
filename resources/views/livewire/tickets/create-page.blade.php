<div class="space-y-6">
    <div class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-2">
            <h2 class="text-xl font-semibold text-slate-900">Novo chamado</h2>
            <p class="text-sm text-slate-500">Selecione o setor e o formulario publicado por ele antes de montar o chamado.</p>
        </div>
    </div>

    <form wire:submit="submit" class="space-y-6">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div class="space-y-6">
                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="text-sm text-slate-600">
                            <span class="mb-2 block font-medium">Setor</span>
                            <select wire:model.live="selectedSectorId" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none">
                                <option value="">Selecione um setor</option>
                                @foreach ($sectorOptions as $sectorOption)
                                    <option value="{{ $sectorOption->id }}">{{ $sectorOption->name }}</option>
                                @endforeach
                            </select>
                            @error('selectedSectorId') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="text-sm text-slate-600">
                            <span class="mb-2 block font-medium">Formulario do setor</span>
                            <select wire:model.live="selectedCatalogId" @disabled(! $selectedSectorId || $catalogItems->isEmpty()) class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400">
                                <option value="">
                                    @if (! $selectedSectorId)
                                        Selecione primeiro um setor
                                    @elseif ($catalogItems->isEmpty())
                                        Nenhum formulario disponivel
                                    @else
                                        Selecione um formulario
                                    @endif
                                </option>
                                @foreach ($catalogItems as $catalogItem)
                                    <option value="{{ $catalogItem->id }}">{{ $catalogItem->name }}</option>
                                @endforeach
                            </select>
                            @if ($selectedSectorId && $catalogItems->isEmpty())
                                <span class="mt-1 block text-xs text-amber-600">Este setor ainda nao possui formularios ativos para abertura.</span>
                            @endif
                            @error('selectedCatalogId') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="text-sm text-slate-600">
                            <span class="mb-2 block font-medium">Prioridade</span>
                            <select wire:model="priority" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none">
                                @foreach ($priorities as $priorityOption)
                                    <option value="{{ $priorityOption->value }}">{{ $priorityOption->label() }}</option>
                                @endforeach
                            </select>
                            @error('priority') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                    </div>
                </section>

                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="space-y-4">
                        <label class="block text-sm text-slate-600">
                            <span class="mb-2 block font-medium">Titulo</span>
                            <input type="text" wire:model="title" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" placeholder="Descreva o problema ou a solicitacao" />
                            @error('title') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="block text-sm text-slate-600">
                            <span class="mb-2 block font-medium">Descricao</span>
                            <textarea wire:model="description" rows="6" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" placeholder="Contexto, impacto e qualquer detalhe util"></textarea>
                            @error('description') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                    </div>
                </section>

                @if ($formFields->isNotEmpty())
                    <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="mb-4">
                            <h3 class="text-lg font-semibold text-slate-900">Campos personalizados</h3>
                            <p class="text-sm text-slate-500">Este formulario foi configurado pelo setor para capturar mais contexto.</p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            @foreach ($formFields as $field)
                                <label class="block text-sm text-slate-600" wire:key="create-field-{{ $field->id }}">
                                    <span class="mb-2 block font-medium">
                                        {{ $field->name }}
                                        @if ($field->pivot?->is_required || $field->is_required)
                                            <span class="text-rose-600">*</span>
                                        @endif
                                    </span>

                                    @if (in_array($field->type->value, ['select', 'status'], true))
                                        <select wire:model="dynamicValues.{{ $field->id }}" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none">
                                            <option value="">Selecione</option>
                                            @foreach ($field->options as $option)
                                                <option value="{{ $option->value }}">{{ $option->label }}</option>
                                            @endforeach
                                        </select>
                                    @elseif ($field->type->value === 'checkbox')
                                        <label class="inline-flex items-center gap-2 rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-700">
                                            <input type="checkbox" wire:model="dynamicValues.{{ $field->id }}" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                                            Marcar este campo
                                        </label>
                                    @elseif ($field->type->value === 'date')
                                        <input type="date" wire:model="dynamicValues.{{ $field->id }}" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" />
                                    @elseif ($field->type->value === 'number')
                                        <input type="number" wire:model="dynamicValues.{{ $field->id }}" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" placeholder="{{ $field->placeholder }}" />
                                    @elseif ($field->type->value === 'user')
                                        <select wire:model="dynamicValues.{{ $field->id }}" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none">
                                            <option value="">Selecione</option>
                                            @foreach ($sectorUsers as $sectorUser)
                                                <option value="{{ $sectorUser->id }}">{{ $sectorUser->name }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" wire:model="dynamicValues.{{ $field->id }}" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" placeholder="{{ $field->placeholder }}" />
                                    @endif

                                    @if ($field->help_text)
                                        <span class="mt-1 block text-xs text-slate-500">{{ $field->help_text }}</span>
                                    @endif
                                    @error("dynamicValues.{$field->id}") <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                </label>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            <aside class="space-y-6">
                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-900">Anexos</h3>
                    <p class="mt-1 text-sm text-slate-500">Arquivos ficam no storage privado e respeitam policy de acesso.</p>

                    <label class="mt-4 block text-sm text-slate-600">
                        <span class="mb-2 block font-medium">Arquivos</span>
                        <input type="file" wire:model="attachments" multiple class="block w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm" />
                    </label>

                    @error('attachments.*') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror

                    @if ($attachments)
                        <ul class="mt-4 space-y-2 text-sm text-slate-600">
                            @foreach ($attachments as $attachment)
                                <li class="rounded-2xl bg-slate-50 px-3 py-2">{{ $attachment->getClientOriginalName() }}</li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <section class="ui-panel rounded-3xl border border-slate-200 bg-slate-950 p-6 text-white shadow-sm">
                    <h3 class="text-lg font-semibold">Enviar para o quadro</h3>
                    <p class="mt-2 text-sm text-slate-300">Depois da abertura voce pode acompanhar historico, conversa e atualizacoes do chamado.</p>

                    <button type="submit" @disabled(! $selectedSectorId || ! $selectedCatalogId) wire:loading.attr="disabled" wire:loading.class="ui-loading" wire:target="submit" class="ui-action mt-6 w-full rounded-2xl bg-sky-500 px-4 py-3 text-sm font-medium text-white hover:bg-sky-400 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-300">
                        <span wire:loading.remove wire:target="submit">Criar chamado</span>
                        <span wire:loading wire:target="submit">Criando...</span>
                    </button>
                </section>
            </aside>
        </div>
    </form>
</div>
