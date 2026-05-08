@php($sectorDisplay = $selectedSector?->displayColor() ?? '#31428C')
@php($sectorSoft = $selectedSector?->softColor() ?? 'color-mix(in srgb, #31428C 14%, white)')
@php($sectorBorder = $selectedSector?->borderColor() ?? 'color-mix(in srgb, #31428C 26%, white)')
@php($hasSuggestions = collect($suggestions)->contains(fn ($items) => filled($items)))

<div
    class="ticket-form-shell"
    style="--ticket-sector-display: {{ $sectorDisplay }}; --ticket-sector-soft: {{ $sectorSoft }}; --ticket-sector-border: {{ $sectorBorder }};"
>
    <form wire:submit="submit" class="ticket-form-card">
        <div class="ticket-form-header">
            <div class="ticket-form-header-copy">
                <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-[1.35rem] border border-white/12 bg-white/10 p-2 shadow-[0_20px_36px_-26px_rgba(2,6,23,0.7)] backdrop-blur-sm">
                    <x-app-logo-icon class="h-full w-full" />
                </div>

                <p class="ticket-form-step">{{ $contextSelectionLocked ? 'Formulario selecionado' : 'Novo chamado' }}</p>
                <h2 class="ticket-form-title">Abertura de chamado</h2>

                @if ($selectedSector || $selectedForm)
                    <div class="mt-5 flex flex-wrap gap-2.5">
                        @if ($selectedSector)
                            <span class="inline-flex items-center gap-2 rounded-full border px-3.5 py-2 text-xs font-semibold text-white" style="border-color: color-mix(in srgb, var(--ticket-sector-display) 20%, white); background-color: rgba(255, 255, 255, 0.08);">
                                <span class="size-2 rounded-full" style="background-color: {{ $selectedSector->displayColor() }};"></span>
                                Setor: {{ $selectedSector->name }}
                            </span>
                        @endif

                        @if ($selectedForm)
                            <span class="inline-flex items-center gap-2 rounded-full border px-3.5 py-2 text-xs font-semibold text-white" style="border-color: color-mix(in srgb, var(--ticket-sector-display) 20%, white); background-color: rgba(255, 255, 255, 0.08);">
                                <span class="size-2 rounded-full" style="background-color: {{ $selectedSector?->displayColor() ?? '#A6B7E5' }};"></span>
                                Formulario: {{ $selectedForm->name }}
                            </span>
                        @endif

                        @if ($selectedBoard)
                            <span class="inline-flex items-center gap-2 rounded-full border px-3.5 py-2 text-xs font-semibold text-white" style="border-color: color-mix(in srgb, var(--ticket-sector-display) 20%, white); background-color: rgba(255, 255, 255, 0.08);">
                                <span class="size-2 rounded-full" style="background-color: {{ $selectedSector?->displayColor() ?? '#A6B7E5' }};"></span>
                                Quadro: {{ $selectedBoard->name }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            <div class="ticket-form-summary">
                <div class="ticket-form-summary-item">
                    <span class="ticket-form-summary-label">Setor</span>
                    <span class="ticket-form-summary-value">{{ $selectedSector?->name ?? 'Selecionar' }}</span>
                </div>

                <div class="ticket-form-summary-item">
                    <span class="ticket-form-summary-label">Quadro</span>
                    <span class="ticket-form-summary-value">{{ $selectedBoard?->name ?? 'Selecionar' }}</span>
                </div>

                <div class="ticket-form-summary-item">
                    <span class="ticket-form-summary-label">Formulario</span>
                    <span class="ticket-form-summary-value">{{ $selectedForm?->name ?? 'Selecionar' }}</span>
                </div>
            </div>
        </div>

        <div class="ticket-form-body">
            @unless ($contextSelectionLocked)
                <section class="ticket-form-section">
                    <div class="ticket-form-section-heading">
                        <p class="ticket-form-step" style="color: var(--ticket-sector-display);">Contexto</p>
                        <h3 class="ticket-form-section-title">Setor, quadro e formulario</h3>
                    </div>

                    <div class="ticket-form-grid ticket-form-grid-tight xl:grid-cols-3">
                        <label class="ticket-form-field">
                            <span class="ticket-form-label">Setor <span class="text-rose-500">*</span></span>
                            <select wire:model.live="selectedSectorId" class="ui-native-select ticket-form-control w-full">
                                <option value="">Selecione um setor</option>
                                @foreach ($sectorOptions as $sectorOption)
                                    <option value="{{ $sectorOption->id }}">{{ $sectorOption->name }}</option>
                                @endforeach
                            </select>
                            @error('selectedSectorId') <span class="ticket-form-error">{{ $message }}</span> @enderror
                        </label>

                        <label class="ticket-form-field">
                            <span class="ticket-form-label">Quadro <span class="text-rose-500">*</span></span>
                            <select wire:model.live="selectedBoardId" @disabled(! $selectedSectorId || $boardOptions->isEmpty()) class="ui-native-select ticket-form-control w-full disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400">
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
                            @error('selectedBoardId') <span class="ticket-form-error">{{ $message }}</span> @enderror
                        </label>

                        <label class="ticket-form-field">
                            <span class="ticket-form-label">Formulario <span class="text-rose-500">*</span></span>
                            <select wire:model.live="selectedFormId" @disabled(! $selectedBoardId || $formOptions->isEmpty()) class="ui-native-select ticket-form-control w-full disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400">
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
                                <span class="ticket-form-warning">Este setor ainda nao possui formularios ativos para abertura.</span>
                            @endif

                            @error('selectedFormId') <span class="ticket-form-error">{{ $message }}</span> @enderror

                            @if ($selectedForm)
                                <span class="ticket-form-help">
                                    @if ($selectedCatalog)
                                        Catalogo automatico: {{ $selectedCatalog->name }}.
                                    @else
                                        Abertura direta neste quadro.
                                    @endif
                                </span>
                            @endif
                        </label>
                    </div>
                </section>
            @endunless

            <section class="ticket-form-section">
                <div class="ticket-form-section-heading">
                    <p class="ticket-form-step" style="color: var(--ticket-sector-display);">Detalhes</p>
                    <h3 class="ticket-form-section-title">Explique o chamado</h3>
                </div>

                <div class="ticket-form-grid">
                    <label class="ticket-form-field ticket-form-field-full">
                        <span class="ticket-form-meta">
                            <span class="ticket-form-label">Titulo <span class="text-rose-500">*</span></span>
                            <span class="ticket-form-counter">{{ mb_strlen($title) }}/160</span>
                        </span>
                        <input
                            type="text"
                            wire:model.live.debounce.500ms="title"
                            maxlength="160"
                            class="ui-input ticket-form-control w-full"
                            placeholder="Ex.: Erro ao acessar o sistema financeiro"
                        />
                        @error('title') <span class="ticket-form-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="ticket-form-field ticket-form-field-full">
                        <span class="ticket-form-meta">
                            <span class="ticket-form-label">Descricao</span>
                            <span class="ticket-form-counter">{{ mb_strlen($description) }}/2000</span>
                        </span>
                        <textarea
                            wire:model.live.debounce.500ms="description"
                            rows="7"
                            maxlength="2000"
                            class="ui-input ticket-form-control ticket-form-textarea w-full"
                            placeholder="Descreva o que aconteceu, quando comecou, quem foi impactado e qualquer detalhe importante."
                        ></textarea>
                        @error('description') <span class="ticket-form-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="ticket-form-field max-w-[18rem]">
                        <span class="ticket-form-label">Prioridade</span>
                        <select wire:model="priority" class="ui-native-select ticket-form-control w-full">
                            @foreach ($priorities as $priorityOption)
                                <option value="{{ $priorityOption->value }}">{{ $priorityOption->label() }}</option>
                            @endforeach
                        </select>
                        @error('priority') <span class="ticket-form-error">{{ $message }}</span> @enderror
                    </label>
                </div>
            </section>

            @if ($selectedSectorId && ($hasSuggestions || filled($title) || filled($description)))
                <section class="ticket-form-section">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="ticket-form-section-heading mb-0">
                            <p class="ticket-form-step" style="color: var(--ticket-sector-display);">Sugestoes</p>
                            <h3 class="ticket-form-section-title">Veja antes de enviar</h3>
                        </div>

                        <div
                            wire:loading.flex
                            wire:target="title,description,selectedSectorId"
                            class="hidden items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold"
                            style="background-color: var(--ticket-sector-soft); color: var(--ticket-sector-display);"
                        >
                            <span class="size-2 animate-pulse rounded-full" style="background-color: var(--ticket-sector-display);"></span>
                            Atualizando sugestoes...
                        </div>
                    </div>

                    @if ($hasSuggestions)
                        <div class="grid gap-4 xl:grid-cols-3">
                            <div class="rounded-[1.35rem] border p-4" style="border-color: var(--ticket-sector-border); background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, color-mix(in srgb, var(--ticket-sector-display) 3%, white) 100%);">
                                <p class="text-sm font-semibold text-slate-900">Artigos da base</p>
                                <div class="mt-4 space-y-3">
                                    @forelse ($suggestions['articles'] as $article)
                                        <a href="{{ $article['url'] }}" class="block rounded-2xl border border-white bg-white p-4 transition hover:shadow-sm" style="border-color: color-mix(in srgb, var(--ticket-sector-display) 10%, white);">
                                            <p class="text-sm font-semibold text-slate-900">{{ $article['title'] }}</p>
                                            @if ($article['sector_name'])
                                                <p class="mt-1 text-xs font-medium uppercase tracking-[0.18em] text-slate-400">{{ $article['sector_name'] }}</p>
                                            @endif
                                            @if ($article['matched_excerpt'])
                                                <p class="mt-2 text-sm leading-6 text-slate-500">{{ $article['matched_excerpt'] }}</p>
                                            @endif
                                        </a>
                                    @empty
                                        <p class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-5 text-sm leading-6 text-slate-500">Nenhum artigo relacionado.</p>
                                    @endforelse
                                </div>
                            </div>

                            <div class="rounded-[1.35rem] border p-4" style="border-color: var(--ticket-sector-border); background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, color-mix(in srgb, var(--ticket-sector-display) 3%, white) 100%);">
                                <p class="text-sm font-semibold text-slate-900">Chamados parecidos</p>
                                <div class="mt-4 space-y-3">
                                    @forelse ($suggestions['similar_tickets'] as $ticketSuggestion)
                                        <a href="{{ $ticketSuggestion['url'] }}" class="block rounded-2xl border border-white bg-white p-4 transition hover:shadow-sm" style="border-color: color-mix(in srgb, var(--ticket-sector-display) 10%, white);">
                                            <p class="text-sm font-semibold text-slate-900">{{ $ticketSuggestion['title'] }}</p>
                                            <p class="mt-1 text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">{{ $ticketSuggestion['reference'] }}</p>
                                            <p class="mt-1 text-xs font-medium uppercase tracking-[0.18em] text-slate-400">
                                                {{ $ticketSuggestion['sector_name'] ?? 'Sem setor' }}
                                                @if ($ticketSuggestion['resolved_at'])
                                                    - Resolvido em {{ $ticketSuggestion['resolved_at'] }}
                                                @endif
                                            </p>
                                            @if ($ticketSuggestion['matched_excerpt'])
                                                <p class="mt-2 text-sm leading-6 text-slate-500">{{ $ticketSuggestion['matched_excerpt'] }}</p>
                                            @endif
                                        </a>
                                    @empty
                                        <p class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-5 text-sm leading-6 text-slate-500">Nenhum chamado parecido.</p>
                                    @endforelse
                                </div>
                            </div>

                            <div class="rounded-[1.35rem] border p-4" style="border-color: var(--ticket-sector-border); background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, color-mix(in srgb, var(--ticket-sector-display) 3%, white) 100%);">
                                <p class="text-sm font-semibold text-slate-900">Solucoes anteriores</p>
                                <div class="mt-4 space-y-3">
                                    @forelse ($suggestions['previous_solutions'] as $solutionSuggestion)
                                        <a href="{{ $solutionSuggestion['url'] }}" class="block rounded-2xl border border-white bg-white p-4 transition hover:shadow-sm" style="border-color: color-mix(in srgb, var(--ticket-sector-display) 10%, white);">
                                            <p class="text-sm font-semibold text-slate-900">{{ $solutionSuggestion['title'] }}</p>
                                            <p class="mt-1 text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">{{ $solutionSuggestion['reference'] }}</p>
                                            <p class="mt-1 text-xs font-medium uppercase tracking-[0.18em] text-slate-400">
                                                {{ $solutionSuggestion['sector_name'] ?? 'Sem setor' }}
                                                @if ($solutionSuggestion['resolved_at'])
                                                    - Encerrado em {{ $solutionSuggestion['resolved_at'] }}
                                                @endif
                                            </p>
                                            @if ($solutionSuggestion['solution_excerpt'])
                                                <p class="mt-2 rounded-xl px-3 py-3 text-sm leading-6 text-slate-600" style="background-color: color-mix(in srgb, var(--ticket-sector-display) 4%, white);">{{ $solutionSuggestion['solution_excerpt'] }}</p>
                                            @elseif ($solutionSuggestion['matched_excerpt'])
                                                <p class="mt-2 text-sm leading-6 text-slate-500">{{ $solutionSuggestion['matched_excerpt'] }}</p>
                                            @else
                                                <p class="mt-2 text-sm leading-6 text-slate-500">Sem resumo de resolucao.</p>
                                            @endif
                                        </a>
                                    @empty
                                        <p class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-5 text-sm leading-6 text-slate-500">Nenhuma solucao anterior.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    @elseif ($selectedSectorId && mb_strlen((string) preg_replace('/[^\pL\pN]+/u', '', $title.' '.$description)) >= 4)
                        <p class="ticket-form-help">Nenhuma sugestao encontrada.</p>
                    @endif
                </section>
            @endif

            @if ($formFields->isNotEmpty())
                <section class="ticket-form-section">
                    <div class="ticket-form-section-heading">
                        <p class="ticket-form-step" style="color: var(--ticket-sector-display);">Campos extras</p>
                        <h3 class="ticket-form-section-title">Informacoes adicionais</h3>
                    </div>

                    <div class="ticket-form-grid">
                        @foreach ($formFields as $field)
                            @php($isCompactField = in_array($field->type->value, ['select', 'status', 'checkbox', 'date', 'number', 'user'], true))
                            <label class="ticket-form-field {{ $isCompactField ? '' : 'ticket-form-field-full' }}" wire:key="create-field-{{ $field->id }}">
                                <span class="ticket-form-label">
                                    {{ $field->name }}
                                    @if ($field->pivot?->is_required || $field->is_required)
                                        <span class="text-rose-500">*</span>
                                    @endif
                                </span>

                                @if ($field->help_text)
                                    <span class="ticket-form-help">{{ $field->help_text }}</span>
                                @endif

                                @if (in_array($field->type->value, ['select', 'status'], true))
                                    <select wire:model="dynamicValues.{{ $field->id }}" class="ui-native-select ticket-form-control w-full">
                                        <option value="">Selecione</option>
                                        @foreach ($field->options as $option)
                                            <option value="{{ $option->value }}">{{ $option->label }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($field->type->value === 'checkbox')
                                    <span class="ticket-form-checkbox">
                                        <input type="checkbox" wire:model="dynamicValues.{{ $field->id }}" class="rounded border-slate-300 text-[#31428c] focus:ring-[#4966d6]" />
                                        Marcar este campo
                                    </span>
                                @elseif ($field->type->value === 'date')
                                    <input type="date" wire:model="dynamicValues.{{ $field->id }}" class="ui-input ticket-form-control w-full" />
                                @elseif ($field->type->value === 'number')
                                    <input type="number" wire:model="dynamicValues.{{ $field->id }}" class="ui-input ticket-form-control w-full" placeholder="{{ $field->placeholder }}" />
                                @elseif ($field->type->value === 'user')
                                    <select wire:model="dynamicValues.{{ $field->id }}" class="ui-native-select ticket-form-control w-full">
                                        <option value="">Selecione</option>
                                        @foreach ($sectorUsers as $sectorUser)
                                            <option value="{{ $sectorUser->id }}">{{ $sectorUser->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="text" wire:model="dynamicValues.{{ $field->id }}" data-mask="auto" data-mask-label="{{ $field->name }}" data-mask-placeholder="{{ $field->placeholder }}" class="ui-input ticket-form-control w-full" placeholder="{{ $field->placeholder }}" />
                                @endif

                                @error("dynamicValues.{$field->id}") <span class="ticket-form-error">{{ $message }}</span> @enderror
                            </label>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="ticket-form-section">
                <div class="ticket-form-section-heading">
                    <p class="ticket-form-step" style="color: var(--ticket-sector-display);">Anexos</p>
                    <h3 class="ticket-form-section-title">Arquivos</h3>
                </div>

                <div class="ticket-upload-card">
                    <label for="ticket-attachments" class="ticket-upload-dropzone transition hover:bg-slate-50" style="border-color: color-mix(in srgb, var(--ticket-sector-display) 18%, white);">
                        <span class="ticket-upload-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-7">
                                <path d="M12 16V5m0 0-4 4m4-4 4 4" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M4 16.5v1A2.5 2.5 0 0 0 6.5 20h11a2.5 2.5 0 0 0 2.5-2.5v-1" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>

                        <span class="ticket-upload-title">Selecionar arquivos</span>
                        <span class="ticket-upload-copy">Clique ou arraste aqui.</span>
                    </label>

                    <input id="ticket-attachments" type="file" wire:model="attachments" multiple class="sr-only" />

                    <div wire:loading wire:target="attachments" class="ticket-upload-loading px-2 pt-4">
                        Enviando arquivos...
                    </div>

                    @error('attachments.*') <span class="ticket-form-error mt-4 block">{{ $message }}</span> @enderror

                    @if ($attachments)
                        <ul class="ticket-upload-list">
                            @foreach ($attachments as $attachment)
                                <li class="ticket-upload-chip">
                                    {{ $attachment->getClientOriginalName() }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        </div>

        <div class="ticket-form-actions">
            <div>
                <p class="ticket-form-actions-title">Pronto para enviar?</p>
                <p class="ticket-form-actions-copy">Acompanhe tudo em Meus chamados.</p>
            </div>

            <button
                type="submit"
                @disabled(! $selectedSectorId || ! $selectedBoardId || ! $selectedFormId)
                wire:loading.attr="disabled"
                wire:loading.class="ui-loading"
                wire:target="submit"
                class="ui-action ui-action-primary ticket-form-submit text-sm font-semibold disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-300"
            >
                <span wire:loading.remove wire:target="submit">Enviar chamado</span>
                <span wire:loading wire:target="submit">Enviando...</span>
            </button>
        </div>
    </form>
</div>
