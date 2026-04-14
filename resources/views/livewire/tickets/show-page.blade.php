<div class="space-y-6">
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_420px]">
        <div class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="space-y-2">
                        <p class="text-sm text-slate-500">{{ $ticket->sector?->company?->name }} / {{ $ticket->sector?->name }}</p>
                        <h2 class="text-2xl font-semibold text-slate-900">{{ $ticket->title }}</h2>
                        <p class="text-sm text-slate-500">{{ $ticket->description ?: 'Sem descricao adicional.' }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $ticket->priority?->badgeColor() }}">{{ $ticket->priority?->label() }}</span>
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium text-white" style="background-color: {{ $ticket->status?->color ?: '#64748b' }}">
                            {{ $ticket->status?->name ?? 'Sem status' }}
                        </span>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Solicitante</p>
                        <p class="mt-2 text-sm font-medium text-slate-900">{{ $ticket->requester?->name ?? 'Nao informado' }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Responsavel</p>
                        <p class="mt-2 text-sm font-medium text-slate-900">{{ $ticket->assignee?->name ?? 'Nao atribuido' }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Sala</p>
                        <p class="mt-2 text-sm font-medium text-slate-900">{{ $ticket->room?->name ?? 'Nao informada' }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Catalogo</p>
                        <p class="mt-2 text-sm font-medium text-slate-900">{{ $ticket->catalogItem?->name ?? 'Nao vinculado' }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-slate-900">Atualizacoes operacionais</h3>
                    <p class="text-sm text-slate-500">Tecnicos e admins de setor podem ajustar o fluxo sem sair da tela.</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <label class="text-sm text-slate-600">
                        <span class="mb-2 block font-medium">Responsavel</span>
                        @can('update', $ticket)
                            <select wire:change="updateFixedField('assignee_id', $event.target.value)" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none">
                                <option value="">Nao atribuido</option>
                                @foreach ($assignees as $assignee)
                                    <option value="{{ $assignee->id }}" @selected($ticket->assignee_id === $assignee->id)>{{ $assignee->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700">{{ $ticket->assignee?->name ?? 'Nao atribuido' }}</div>
                        @endcan
                    </label>

                    <label class="text-sm text-slate-600">
                        <span class="mb-2 block font-medium">Prioridade</span>
                        @can('update', $ticket)
                            <select wire:change="updateFixedField('priority', $event.target.value)" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none">
                                @foreach ($priorities as $priority)
                                    <option value="{{ $priority->value }}" @selected($ticket->priority === $priority)>{{ $priority->label() }}</option>
                                @endforeach
                            </select>
                        @else
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700">{{ $ticket->priority?->label() }}</div>
                        @endcan
                    </label>

                    <label class="text-sm text-slate-600">
                        <span class="mb-2 block font-medium">Status</span>
                        @can('update', $ticket)
                            <select wire:change="updateFixedField('ticket_status_id', $event.target.value)" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->id }}" @selected($ticket->ticket_status_id === $status->id)>{{ $status->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700">{{ $ticket->status?->name ?? 'Sem status' }}</div>
                        @endcan
                    </label>

                    <label class="text-sm text-slate-600">
                        <span class="mb-2 block font-medium">Grupo</span>
                        @can('update', $ticket)
                            <select wire:change="updateFixedField('ticket_group_id', $event.target.value)" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none">
                                <option value="">Sem grupo</option>
                                @foreach ($groups as $group)
                                    <option value="{{ $group->id }}" @selected($ticket->ticket_group_id === $group->id)>{{ $group->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700">{{ $ticket->group?->name ?? 'Sem grupo' }}</div>
                        @endcan
                    </label>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-slate-900">Campos dinamicos</h3>
                    <p class="text-sm text-slate-500">Valores adicionais configurados pelo setor.</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    @forelse ($fields as $field)
                        @php
                            $value = $ticket->fieldValues->firstWhere('ticket_field_id', $field->id)?->primitive_value;
                        @endphp
                        <label class="text-sm text-slate-600" wire:key="show-field-{{ $field->id }}">
                            <span class="mb-2 block font-medium">{{ $field->name }}</span>

                            @can('update', $ticket)
                                @if (in_array($field->type->value, ['select', 'status'], true))
                                    <select wire:change="updateDynamicField({{ $field->id }}, $event.target.value)" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none">
                                        <option value="">Selecione</option>
                                        @foreach ($field->options as $option)
                                            <option value="{{ $option->value }}" @selected((string) $value === (string) $option->value)>{{ $option->label }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($field->type->value === 'checkbox')
                                    <label class="inline-flex items-center gap-2 rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-700">
                                        <input type="checkbox" @checked((bool) $value) wire:change="updateDynamicField({{ $field->id }}, $event.target.checked)" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                                        Campo marcado
                                    </label>
                                @elseif ($field->type->value === 'date')
                                    <input type="date" value="{{ $value }}" wire:change="updateDynamicField({{ $field->id }}, $event.target.value)" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" />
                                @elseif ($field->type->value === 'number')
                                    <input type="number" value="{{ $value }}" wire:change="updateDynamicField({{ $field->id }}, $event.target.value)" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" />
                                @elseif ($field->type->value === 'user')
                                    <select wire:change="updateDynamicField({{ $field->id }}, $event.target.value)" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none">
                                        <option value="">Selecione</option>
                                        @foreach ($sectorUsers as $sectorUser)
                                            <option value="{{ $sectorUser->id }}" @selected((string) $value === (string) $sectorUser->id)>{{ $sectorUser->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="text" value="{{ $value }}" wire:change="updateDynamicField({{ $field->id }}, $event.target.value)" class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none" />
                                @endif
                            @else
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700">
                                    {{ $field->type->value === 'checkbox' ? ($value ? 'Sim' : 'Nao') : ($value ?: '-') }}
                                </div>
                            @endcan

                            @if ($field->help_text)
                                <span class="mt-1 block text-xs text-slate-500">{{ $field->help_text }}</span>
                            @endif
                        </label>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                            O setor ainda nao configurou campos dinamicos neste board.
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-slate-900">Historico</h3>
                    <p class="text-sm text-slate-500">Log de acoes relevantes para auditoria.</p>
                </div>

                <div class="space-y-3">
                    @forelse ($ticket->activityLogs as $log)
                        <div class="rounded-2xl border border-slate-200 px-4 py-3">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $log->description ?: $log->event }}</p>
                                    <p class="text-xs text-slate-500">{{ $log->causer?->name ?? 'Sistema' }}</p>
                                </div>
                                <span class="text-xs text-slate-500">{{ $log->created_at?->diffForHumans() }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                            Ainda nao ha itens de historico registrados.
                        </div>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            <section
                class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"
                wire:poll.5s
                x-data="{
                    boot() {
                        if (! window.Echo) {
                            return;
                        }

                        window.Echo.private('tickets.{{ $ticket->id }}')
                            .listen('.ticket.message.created', () => {
                                $wire.$refresh();
                            });
                    }
                }"
                x-init="boot()"
            >
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-slate-900">Chat interno</h3>
                    <p class="text-sm text-slate-500">Conversa entre solicitante, tecnico e administradores do setor.</p>
                </div>

                <div class="max-h-[480px] space-y-3 overflow-y-auto rounded-3xl bg-slate-50 p-4">
                    @forelse ($ticket->messages as $ticketMessage)
                        <div class="flex {{ $ticketMessage->user_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[85%] rounded-3xl px-4 py-3 {{ $ticketMessage->user_id === auth()->id() ? 'bg-slate-900 text-white' : 'bg-white text-slate-900' }}">
                                <div class="mb-1 flex items-center justify-between gap-4 text-xs {{ $ticketMessage->user_id === auth()->id() ? 'text-slate-300' : 'text-slate-500' }}">
                                    <span>{{ $ticketMessage->user?->name ?? 'Sistema' }}</span>
                                    <span>{{ $ticketMessage->created_at?->format('d/m H:i') }}</span>
                                </div>
                                <p class="whitespace-pre-line text-sm">{{ $ticketMessage->message }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-6 text-center text-sm text-slate-500">
                            Nenhuma mensagem ainda.
                        </div>
                    @endforelse
                </div>

                <form wire:submit="sendMessage" class="mt-4 space-y-3">
                    <textarea wire:model="message" rows="4" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none" placeholder="Escreva uma mensagem para o time"></textarea>
                    @error('message') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror

                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-sky-500 px-4 py-3 text-sm font-medium text-white hover:bg-sky-400">
                        Enviar mensagem
                    </button>
                </form>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-slate-900">Anexos</h3>
                    <p class="text-sm text-slate-500">Arquivos enviados na abertura do chamado.</p>
                </div>

                <div class="space-y-3">
                    @forelse ($ticket->attachments as $attachment)
                        <a href="{{ route('tickets.attachments.show', $attachment) }}" class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 px-4 py-3 hover:bg-slate-50">
                            <div>
                                <p class="font-medium text-slate-900">{{ $attachment->original_name }}</p>
                                <p class="text-xs text-slate-500">{{ number_format(($attachment->size ?? 0) / 1024, 1) }} KB</p>
                            </div>
                            <span class="text-sm text-sky-700">Baixar</span>
                        </a>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                            Nenhum anexo neste chamado.
                        </div>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</div>
