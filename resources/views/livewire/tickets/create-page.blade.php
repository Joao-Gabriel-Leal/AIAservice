<div class="mx-auto max-w-[820px]">
    <form wire:submit="submit" class="overflow-hidden rounded-[2rem] border border-white/60 bg-white shadow-[0_40px_120px_-56px_rgba(15,23,42,0.45)]">
        <div class="border-b border-slate-200/80 px-6 py-8 text-center sm:px-10 sm:py-10">
            <div class="mx-auto flex h-14 w-[8rem] items-center justify-center overflow-hidden rounded-[1.15rem] border border-slate-200/80 bg-white px-2 shadow-[0_18px_36px_-28px_rgba(47,51,214,0.4)]">
                <x-app-logo-icon class="h-full w-full" />
            </div>

            <h2 class="mt-6 text-[2rem] font-semibold tracking-[-0.03em] text-slate-950 sm:text-[2.35rem]">
                Abertura de Chamado
            </h2>

            <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-slate-500 sm:text-[0.95rem]">
                Preencha as informacoes abaixo para abrir o chamado com mais rapidez e menos retrabalho na triagem.
            </p>

            @if ($selectedSector || $selectedForm)
                <div class="mt-5 flex flex-wrap items-center justify-center gap-2.5">
                    @if ($selectedSector)
                        <span class="inline-flex items-center rounded-full px-3.5 py-1.5 text-xs font-medium" style="background-color: {{ $selectedSector->softColor() }}; color: {{ $selectedSector->displayColor() }};">
                            Setor: {{ $selectedSector->name }}
                        </span>
                    @endif

                    @if ($selectedForm)
                        <span class="inline-flex items-center rounded-full px-3.5 py-1.5 text-xs font-medium" style="background-color: {{ $selectedSector?->softColor() ?? '#EEF2FF' }}; color: {{ $selectedSector?->displayColor() ?? '#31428C' }};">
                            Formulario: {{ $selectedForm->name }}
                        </span>
                    @endif

                    @if ($selectedBoard)
                        <span class="inline-flex items-center rounded-full px-3.5 py-1.5 text-xs font-medium" style="background-color: {{ $selectedSector?->softColor() ?? '#EEF2FF' }}; color: {{ $selectedSector?->displayColor() ?? '#31428C' }};">
                            Quadro: {{ $selectedBoard->name }}
                        </span>
                    @endif
                </div>
            @endif
        </div>

        <div class="space-y-8 px-6 py-7 sm:px-10 sm:py-8">
            @unless ($contextSelectionLocked)
            <section class="space-y-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#4966d6]">Contexto</p>
                    <h3 class="mt-2 text-[1.35rem] font-semibold tracking-[-0.02em] text-slate-900">Escolha o setor e o formulario</h3>
                    <p class="mt-1.5 text-sm leading-6 text-slate-500">O formulario define os campos da abertura. Se houver catalogo vinculado, ele complementa a triagem automaticamente.</p>
                </div>

                <div class="grid gap-5 sm:grid-cols-3">
                    <label class="block">
                        <span class="mb-2 block text-[1rem] font-semibold text-slate-900">Setor <span class="text-rose-500">*</span></span>
                        <select wire:model.live="selectedSectorId" class="ui-native-select h-14 w-full rounded-2xl border-slate-200 text-[0.96rem] text-slate-800">
                            <option value="">Selecione um setor</option>
                            @foreach ($sectorOptions as $sectorOption)
                                <option value="{{ $sectorOption->id }}">{{ $sectorOption->name }}</option>
                            @endforeach
                        </select>
                        @error('selectedSectorId') <span class="mt-2 block text-xs font-medium text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-[1rem] font-semibold text-slate-900">Quadro <span class="text-rose-500">*</span></span>
                        <select wire:model.live="selectedBoardId" @disabled(! $selectedSectorId || $boardOptions->isEmpty()) class="ui-native-select h-14 w-full rounded-2xl border-slate-200 text-[0.96rem] text-slate-800 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400">
                            <option value="">
                                @if (! $selectedSectorId)
                                    Selecione primeiro um setor
                                @elseif ($boardOptions->isEmpty())
                                    Nenhum quadro disponivel
                                @else
                                    Selecione um quadro
                                @endif
                            </option>
                            @foreach ($boardOptions as $boardOption)
                                <option value="{{ $boardOption->id }}">{{ $boardOption->name }}</option>
                            @endforeach
                        </select>
                        @error('selectedBoardId') <span class="mt-2 block text-xs font-medium text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-[1rem] font-semibold text-slate-900">Formulario <span class="text-rose-500">*</span></span>
                        <select wire:model.live="selectedFormId" @disabled(! $selectedBoardId || $formOptions->isEmpty()) class="ui-native-select h-14 w-full rounded-2xl border-slate-200 text-[0.96rem] text-slate-800 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400">
                            <option value="">
                                @if (! $selectedBoardId)
                                    Selecione primeiro um quadro
                                @elseif ($formOptions->isEmpty())
                                    Nenhum formulario disponivel
                                @else
                                    Selecione um formulario
                                @endif
                            </option>
                            @foreach ($formOptions as $formOption)
                                <option value="{{ $formOption->id }}">{{ $formOption->name }}</option>
                            @endforeach
                        </select>
                        @if ($selectedSectorId && $formOptions->isEmpty())
                            <span class="mt-2 block text-xs font-medium text-amber-700">Este setor ainda nao possui formularios ativos para abertura.</span>
                        @endif
                        @error('selectedFormId') <span class="mt-2 block text-xs font-medium text-rose-600">{{ $message }}</span> @enderror
                        @if ($selectedForm)
                            <span class="mt-2 block text-xs font-medium text-slate-500">
                                @if ($selectedCatalog)
                                    Catalogo aplicado automaticamente: {{ $selectedCatalog->name }}.
                                @else
                                    Este formulario sera aberto diretamente com a etapa padrao do setor.
                                @endif
                            </span>
                        @endif
                    </label>
                </div>
            </section>
            @endunless

            <section class="space-y-5 border-t border-slate-200/90 pt-8">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#4966d6]">Detalhes</p>
                    <h3 class="mt-2 text-[1.35rem] font-semibold tracking-[-0.02em] text-slate-900">Explique o chamado</h3>
                    <p class="mt-1.5 text-sm leading-6 text-slate-500">Use um titulo curto e uma descricao clara para facilitar o atendimento.</p>
                </div>

                <label class="block">
                    <span class="mb-2 flex items-center justify-between gap-4">
                        <span class="text-[1rem] font-semibold text-slate-900">Titulo <span class="text-rose-500">*</span></span>
                        <span class="text-xs font-medium text-slate-400">{{ mb_strlen($title) }}/160</span>
                    </span>
                    <input
                        type="text"
                        wire:model.live.debounce.500ms="title"
                        maxlength="160"
                        class="ui-input h-14 w-full rounded-2xl border-slate-200 text-[0.96rem] text-slate-800"
                        placeholder="Ex.: Erro ao acessar o sistema financeiro"
                    />
                    @error('title') <span class="mt-2 block text-xs font-medium text-rose-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="mb-2 flex items-center justify-between gap-4">
                        <span class="text-[1rem] font-semibold text-slate-900">Descricao</span>
                        <span class="text-xs font-medium text-slate-400">{{ mb_strlen($description) }}/2000</span>
                    </span>
                    <textarea
                        wire:model.live.debounce.500ms="description"
                        rows="7"
                        maxlength="2000"
                        class="ui-input min-h-[180px] w-full rounded-2xl border-slate-200 px-4 py-4 text-[0.96rem] leading-6 text-slate-800"
                        placeholder="Descreva o que aconteceu, quando comecou, quem foi impactado e qualquer detalhe importante."
                    ></textarea>
                    @error('description') <span class="mt-2 block text-xs font-medium text-rose-600">{{ $message }}</span> @enderror
                </label>

                <label class="block max-w-[280px]">
                    <span class="mb-2 block text-[1rem] font-semibold text-slate-900">Prioridade</span>
                    <select wire:model="priority" class="ui-native-select h-14 w-full rounded-2xl border-slate-200 text-[0.96rem] text-slate-800">
                        @foreach ($priorities as $priorityOption)
                            <option value="{{ $priorityOption->value }}">{{ $priorityOption->label() }}</option>
                        @endforeach
                    </select>
                    @error('priority') <span class="mt-2 block text-xs font-medium text-rose-600">{{ $message }}</span> @enderror
                </label>
            </section>

            @php($hasSuggestions = collect($suggestions)->contains(fn ($items) => filled($items)))

            @if ($selectedSectorId && ($hasSuggestions || filled($title) || filled($description)))
                <section class="space-y-5 border-t border-slate-200/90 pt-8">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#4966d6]">Sugestoes</p>
                            <h3 class="mt-2 text-[1.35rem] font-semibold tracking-[-0.02em] text-slate-900">Veja antes de enviar</h3>
                            <p class="mt-1.5 text-sm leading-6 text-slate-500">Enquanto voce descreve o problema, buscamos referencias para evitar chamados repetidos.</p>
                        </div>

                        <div wire:loading.flex wire:target="title,description,selectedSectorId" class="hidden items-center gap-2 rounded-full bg-[#eef2ff] px-3 py-1.5 text-xs font-medium text-[#31428c]">
                            <span class="size-2 animate-pulse rounded-full bg-[#4966d6]"></span>
                            Atualizando sugestoes...
                        </div>
                    </div>

                    @if ($hasSuggestions)
                        <div class="grid gap-4 xl:grid-cols-3">
                            <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-5">
                                <p class="text-sm font-semibold text-slate-900">Artigos da base</p>
                                <div class="mt-4 space-y-3">
                                    @forelse ($suggestions['articles'] as $article)
                                        <a href="{{ $article['url'] }}" class="block rounded-2xl border border-white bg-white p-4 transition hover:border-[#cfd8ff] hover:shadow-sm">
                                            <p class="text-sm font-semibold text-slate-900">{{ $article['title'] }}</p>
                                            @if ($article['sector_name'])
                                                <p class="mt-1 text-xs font-medium uppercase tracking-[0.18em] text-slate-400">{{ $article['sector_name'] }}</p>
                                            @endif
                                            @if ($article['matched_excerpt'])
                                                <p class="mt-2 text-sm leading-6 text-slate-500">{{ $article['matched_excerpt'] }}</p>
                                            @endif
                                        </a>
                                    @empty
                                        <p class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-5 text-sm leading-6 text-slate-500">Nenhum artigo relacionado encontrado ainda.</p>
                                    @endforelse
                                </div>
                            </div>

                            <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-5">
                                <p class="text-sm font-semibold text-slate-900">Chamados parecidos</p>
                                <div class="mt-4 space-y-3">
                                    @forelse ($suggestions['similar_tickets'] as $ticketSuggestion)
                                        <a href="{{ $ticketSuggestion['url'] }}" class="block rounded-2xl border border-white bg-white p-4 transition hover:border-[#cfd8ff] hover:shadow-sm">
                                            <p class="text-sm font-semibold text-slate-900">{{ $ticketSuggestion['title'] }}</p>
                                            <p class="mt-1 text-xs font-medium uppercase tracking-[0.18em] text-slate-400">
                                                {{ $ticketSuggestion['sector_name'] ?? 'Sem setor' }}
                                                @if ($ticketSuggestion['resolved_at'])
                                                    · Resolvido em {{ $ticketSuggestion['resolved_at'] }}
                                                @endif
                                            </p>
                                            @if ($ticketSuggestion['matched_excerpt'])
                                                <p class="mt-2 text-sm leading-6 text-slate-500">{{ $ticketSuggestion['matched_excerpt'] }}</p>
                                            @endif
                                        </a>
                                    @empty
                                        <p class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-5 text-sm leading-6 text-slate-500">Nenhum chamado parecido apareceu com o texto atual.</p>
                                    @endforelse
                                </div>
                            </div>

                            <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-5">
                                <p class="text-sm font-semibold text-slate-900">Solucoes anteriores</p>
                                <div class="mt-4 space-y-3">
                                    @forelse ($suggestions['previous_solutions'] as $solutionSuggestion)
                                        <a href="{{ $solutionSuggestion['url'] }}" class="block rounded-2xl border border-white bg-white p-4 transition hover:border-[#cfd8ff] hover:shadow-sm">
                                            <p class="text-sm font-semibold text-slate-900">{{ $solutionSuggestion['title'] }}</p>
                                            <p class="mt-1 text-xs font-medium uppercase tracking-[0.18em] text-slate-400">
                                                {{ $solutionSuggestion['sector_name'] ?? 'Sem setor' }}
                                                @if ($solutionSuggestion['resolved_at'])
                                                    · Encerrado em {{ $solutionSuggestion['resolved_at'] }}
                                                @endif
                                            </p>
                                            @if ($solutionSuggestion['solution_excerpt'])
                                                <p class="mt-2 rounded-xl bg-[#f8faff] px-3 py-3 text-sm leading-6 text-slate-600">{{ $solutionSuggestion['solution_excerpt'] }}</p>
                                            @elseif ($solutionSuggestion['matched_excerpt'])
                                                <p class="mt-2 text-sm leading-6 text-slate-500">{{ $solutionSuggestion['matched_excerpt'] }}</p>
                                            @else
                                                <p class="mt-2 text-sm leading-6 text-slate-500">Chamado encerrado semelhante sem resumo de resolucao disponivel.</p>
                                            @endif
                                        </a>
                                    @empty
                                        <p class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-5 text-sm leading-6 text-slate-500">Ainda nao encontramos solucoes anteriores para esse contexto.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    @elseif ($selectedSectorId && mb_strlen((string) preg_replace('/[^\pL\pN]+/u', '', $title.' '.$description)) >= 4)
                        <p class="rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50/80 px-5 py-4 text-sm leading-6 text-slate-500">Nenhuma sugestao apareceu para o texto atual, mas voce pode seguir com a abertura normalmente.</p>
                    @endif
                </section>
            @endif

            @if ($formFields->isNotEmpty())
                <section class="space-y-5 border-t border-slate-200/90 pt-8">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#4966d6]">Campos extras</p>
                        <h3 class="mt-2 text-[1.35rem] font-semibold tracking-[-0.02em] text-slate-900">Informacoes adicionais</h3>
                        <p class="mt-1.5 text-sm leading-6 text-slate-500">Esses campos ajudam o setor a entender o contexto logo na abertura.</p>
                    </div>

                <div class="grid gap-5 sm:grid-cols-2">
                        @foreach ($formFields as $field)
                            @php($isCompactField = in_array($field->type->value, ['select', 'status', 'checkbox', 'date', 'number', 'user'], true))
                            <label class="block {{ $isCompactField ? '' : 'sm:col-span-2' }}" wire:key="create-field-{{ $field->id }}">
                                <span class="mb-2 block text-[1rem] font-semibold text-slate-900">
                                    {{ $field->name }}
                                    @if ($field->pivot?->is_required || $field->is_required)
                                        <span class="text-rose-500">*</span>
                                    @endif
                                </span>

                                @if ($field->help_text)
                                    <span class="mb-2 block text-sm leading-6 text-slate-500">{{ $field->help_text }}</span>
                                @endif

                                @if (in_array($field->type->value, ['select', 'status'], true))
                                    <select wire:model="dynamicValues.{{ $field->id }}" class="ui-native-select h-14 w-full rounded-2xl border-slate-200 text-[0.96rem] text-slate-800">
                                        <option value="">Selecione</option>
                                        @foreach ($field->options as $option)
                                            <option value="{{ $option->value }}">{{ $option->label }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($field->type->value === 'checkbox')
                                    <span class="inline-flex min-h-14 w-full items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-[0.95rem] text-slate-700">
                                        <input type="checkbox" wire:model="dynamicValues.{{ $field->id }}" class="rounded border-slate-300 text-[#31428c] focus:ring-[#4966d6]" />
                                        Marcar este campo
                                    </span>
                                @elseif ($field->type->value === 'date')
                                    <input type="date" wire:model="dynamicValues.{{ $field->id }}" class="ui-input h-14 w-full rounded-2xl border-slate-200 text-[0.96rem] text-slate-800" />
                                @elseif ($field->type->value === 'number')
                                    <input type="number" wire:model="dynamicValues.{{ $field->id }}" class="ui-input h-14 w-full rounded-2xl border-slate-200 text-[0.96rem] text-slate-800" placeholder="{{ $field->placeholder }}" />
                                @elseif ($field->type->value === 'user')
                                    <select wire:model="dynamicValues.{{ $field->id }}" class="ui-native-select h-14 w-full rounded-2xl border-slate-200 text-[0.96rem] text-slate-800">
                                        <option value="">Selecione</option>
                                        @foreach ($sectorUsers as $sectorUser)
                                            <option value="{{ $sectorUser->id }}">{{ $sectorUser->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="text" wire:model="dynamicValues.{{ $field->id }}" data-mask="auto" data-mask-label="{{ $field->name }}" data-mask-placeholder="{{ $field->placeholder }}" class="ui-input h-14 w-full rounded-2xl border-slate-200 text-[0.96rem] text-slate-800" placeholder="{{ $field->placeholder }}" />
                                @endif

                                @error("dynamicValues.{$field->id}") <span class="mt-2 block text-xs font-medium text-rose-600">{{ $message }}</span> @enderror
                            </label>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="space-y-5 border-t border-slate-200/90 pt-8">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#4966d6]">Anexos</p>
                    <h3 class="mt-2 text-[1.35rem] font-semibold tracking-[-0.02em] text-slate-900">Envie evidencias</h3>
                    <p class="mt-1.5 text-sm leading-6 text-slate-500">Adicione prints, documentos ou qualquer arquivo que ajude no atendimento.</p>
                </div>

                <div class="rounded-[1.65rem] border border-dashed border-slate-300 bg-slate-50/80 p-3">
                    <label for="ticket-attachments" class="flex cursor-pointer flex-col items-center justify-center rounded-[1.35rem] border border-dashed border-slate-300 bg-white px-6 py-10 text-center transition hover:border-[#4966d6] hover:bg-[#f7f8ff]">
                        <span class="flex size-14 items-center justify-center rounded-full bg-[#eef2ff] text-[#31428c]">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-7">
                                <path d="M12 16V5m0 0-4 4m4-4 4 4" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M4 16.5v1A2.5 2.5 0 0 0 6.5 20h11a2.5 2.5 0 0 0 2.5-2.5v-1" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>

                        <span class="mt-4 text-[1rem] font-semibold text-slate-900">Escolha um arquivo para enviar</span>
                        <span class="mt-2 text-sm leading-6 text-slate-500">Clique aqui ou arraste os arquivos para esta area.</span>
                    </label>

                    <input id="ticket-attachments" type="file" wire:model="attachments" multiple class="sr-only" />

                    <div wire:loading wire:target="attachments" class="px-2 pt-4 text-sm font-medium text-slate-500">
                        Enviando arquivos...
                    </div>

                    @error('attachments.*') <span class="mt-4 block text-xs font-medium text-rose-600">{{ $message }}</span> @enderror

                    @if ($attachments)
                        <ul class="mt-4 flex flex-wrap gap-2">
                            @foreach ($attachments as $attachment)
                                <li class="inline-flex items-center rounded-full bg-[#eef2ff] px-3.5 py-2 text-xs font-medium text-[#31428c]">
                                    {{ $attachment->getClientOriginalName() }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        </div>

        <div class="flex flex-col gap-4 border-t border-slate-200/90 bg-white px-6 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-10">
            <p class="text-sm leading-6 text-slate-500">
                Revise os dados e envie. Depois voce acompanha tudo em <span class="font-medium text-slate-700">Meus chamados</span>.
            </p>

            <button
                type="submit"
                @disabled(! $selectedSectorId || ! $selectedBoardId || ! $selectedFormId)
                wire:loading.attr="disabled"
                wire:loading.class="ui-loading"
                wire:target="submit"
                class="ui-action ui-action-primary h-12 rounded-2xl px-6 text-sm font-semibold disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-300"
            >
                <span wire:loading.remove wire:target="submit">Enviar chamado</span>
                <span wire:loading wire:target="submit">Enviando...</span>
            </button>
        </div>
    </form>
</div>
