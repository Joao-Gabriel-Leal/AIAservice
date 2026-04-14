<div class="space-y-6">
    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="space-y-2">
                <p class="text-sm text-slate-500">
                    @if ($board)
                        {{ $board->sector->company?->name }} / {{ $board->sector->name }}
                    @else
                        Nenhum setor disponivel para exibir o quadro.
                    @endif
                </p>
                <h2 class="text-2xl font-semibold text-slate-900">{{ $board?->name ?? 'Quadro de chamados' }}</h2>
                <p class="text-sm text-slate-500">{{ $board?->description ?: 'Acompanhe os chamados agrupados por etapa.' }}</p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                @if ($sectorOptions->count() > 1)
                    <label class="text-sm text-slate-600">
                        <span class="mb-1 block font-medium">Setor</span>
                        <select wire:model.live="selectedSectorId" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">
                            @foreach ($sectorOptions as $sectorOption)
                                <option value="{{ $sectorOption->id }}">{{ $sectorOption->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <a href="{{ route('tickets.create') }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-3 text-sm font-medium text-white">
                    Abrir chamado
                </a>

                @if (auth()->user()->isSuperAdmin() || auth()->user()->isSectorAdmin())
                    <a href="{{ route('tickets.settings', $board?->sector_id) }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-3 text-sm font-medium text-slate-700">
                        Configurar quadro
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if (! $board)
        <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-slate-500">
            Crie um setor para comecar a organizar o quadro de chamados.
        </div>
    @else
        @foreach ($groups as $group)
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm" wire:key="group-{{ $group->id }}">
                <button type="button" wire:click="toggleGroup({{ $group->id }})" class="flex w-full items-center justify-between gap-4 border-b border-slate-200 px-6 py-4 text-left">
                    <div class="flex items-center gap-3">
                        <span class="size-3 rounded-full" style="background-color: {{ $group->color ?: '#2563eb' }}"></span>
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">{{ $group->name }}</h3>
                            <p class="text-sm text-slate-500">{{ $ticketsByGroup->get($group->id)?->count() ?? 0 }} chamados</p>
                        </div>
                    </div>
                    <span class="text-sm text-slate-500">{{ ($collapsedGroups[$group->id] ?? false) ? 'Expandir' : 'Recolher' }}</span>
                </button>

                @if (! ($collapsedGroups[$group->id] ?? false))
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Titulo</th>
                                    <th class="px-4 py-3 font-medium">Solicitante</th>
                                    <th class="px-4 py-3 font-medium">Responsavel</th>
                                    <th class="px-4 py-3 font-medium">Prioridade</th>
                                    <th class="px-4 py-3 font-medium">Status</th>
                                    @foreach ($fields as $field)
                                        <th class="px-4 py-3 font-medium">{{ $field->name }}</th>
                                    @endforeach
                                    <th class="px-4 py-3 font-medium">Abrir</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($ticketsByGroup->get($group->id, collect()) as $ticket)
                                    <tr class="align-top hover:bg-slate-50" wire:key="ticket-row-{{ $ticket->id }}">
                                        <td class="px-4 py-4">
                                            @if ($canUpdate)
                                                <input type="text" value="{{ $ticket->title }}" wire:change="updateFixedField({{ $ticket->id }}, 'title', $event.target.value)" class="w-72 rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none" />
                                            @else
                                                <p class="font-medium text-slate-900">{{ $ticket->title }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-slate-600">{{ $ticket->requester?->name ?? 'Nao informado' }}</td>
                                        <td class="px-4 py-4">
                                            @if ($canUpdate)
                                                <select wire:change="updateFixedField({{ $ticket->id }}, 'assignee_id', $event.target.value)" class="w-48 rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                                                    <option value="">Nao atribuido</option>
                                                    @foreach ($assignees as $assignee)
                                                        <option value="{{ $assignee->id }}" @selected($ticket->assignee_id === $assignee->id)>{{ $assignee->name }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <span class="text-slate-600">{{ $ticket->assignee?->name ?? 'Nao atribuido' }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4">
                                            @if ($canUpdate)
                                                <select wire:change="updateFixedField({{ $ticket->id }}, 'priority', $event.target.value)" class="w-40 rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                                                    @foreach ($priorities as $priority)
                                                        <option value="{{ $priority->value }}" @selected($ticket->priority === $priority)>{{ $priority->label() }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $ticket->priority?->badgeColor() }}">{{ $ticket->priority?->label() }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4">
                                            @if ($canUpdate)
                                                <div class="space-y-2">
                                                    <select wire:change="updateFixedField({{ $ticket->id }}, 'ticket_status_id', $event.target.value)" class="w-44 rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                                                        @foreach ($board->statuses as $status)
                                                            <option value="{{ $status->id }}" @selected($ticket->ticket_status_id === $status->id)>{{ $status->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <select wire:change="updateFixedField({{ $ticket->id }}, 'ticket_group_id', $event.target.value)" class="w-44 rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                                                        <option value="">Sem grupo</option>
                                                        @foreach ($board->groups as $boardGroup)
                                                            <option value="{{ $boardGroup->id }}" @selected($ticket->ticket_group_id === $boardGroup->id)>{{ $boardGroup->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @else
                                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium text-white" style="background-color: {{ $ticket->status?->color ?: '#64748b' }}">
                                                    {{ $ticket->status?->name ?? 'Sem status' }}
                                                </span>
                                            @endif
                                        </td>

                                        @foreach ($fields as $field)
                                            @php
                                                $value = $this->fieldValue($ticket, $field);
                                            @endphp
                                            <td class="px-4 py-4">
                                                @if (! $canUpdate)
                                                    <span class="text-slate-600">{{ $field->type->value === 'checkbox' ? ($value ? 'Sim' : 'Nao') : ($value ?: '-') }}</span>
                                                @elseif (in_array($field->type->value, ['select', 'status'], true))
                                                    <select wire:change="updateDynamicField({{ $ticket->id }}, {{ $field->id }}, $event.target.value)" class="w-44 rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                                                        <option value="">Selecione</option>
                                                        @foreach ($field->options as $option)
                                                            <option value="{{ $option->value }}" @selected((string) $value === (string) $option->value)>{{ $option->label }}</option>
                                                        @endforeach
                                                    </select>
                                                @elseif ($field->type->value === 'checkbox')
                                                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                                                        <input type="checkbox" @checked((bool) $value) wire:change="updateDynamicField({{ $ticket->id }}, {{ $field->id }}, $event.target.checked)" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                                                        Ativo
                                                    </label>
                                                @elseif ($field->type->value === 'date')
                                                    <input type="date" value="{{ $value }}" wire:change="updateDynamicField({{ $ticket->id }}, {{ $field->id }}, $event.target.value)" class="w-40 rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none" />
                                                @elseif ($field->type->value === 'number')
                                                    <input type="number" value="{{ $value }}" wire:change="updateDynamicField({{ $ticket->id }}, {{ $field->id }}, $event.target.value)" class="w-32 rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none" />
                                                @elseif ($field->type->value === 'user')
                                                    <select wire:change="updateDynamicField({{ $ticket->id }}, {{ $field->id }}, $event.target.value)" class="w-44 rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                                                        <option value="">Selecione</option>
                                                        @foreach ($sectorUsers as $sectorUser)
                                                            <option value="{{ $sectorUser->id }}" @selected((string) $value === (string) $sectorUser->id)>{{ $sectorUser->name }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input type="text" value="{{ $value }}" wire:change="updateDynamicField({{ $ticket->id }}, {{ $field->id }}, $event.target.value)" class="w-48 rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none" />
                                                @endif
                                            </td>
                                        @endforeach

                                        <td class="px-4 py-4">
                                            <a href="{{ route('tickets.show', $ticket) }}" class="inline-flex rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Ver</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ 6 + $fields->count() }}" class="px-4 py-8 text-center text-slate-500">Nenhum chamado neste grupo ainda.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endforeach

        @if ($ungroupedTickets->isNotEmpty())
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-slate-900">Sem grupo</h3>
                    <p class="text-sm text-slate-500">Chamados sem etapa definida no quadro.</p>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach ($ungroupedTickets as $ticket)
                        <a href="{{ route('tickets.show', $ticket) }}" class="flex items-center justify-between gap-4 px-6 py-4 hover:bg-slate-50">
                            <div>
                                <p class="font-medium text-slate-900">{{ $ticket->title }}</p>
                                <p class="text-sm text-slate-500">{{ $ticket->requester?->name ?? 'Nao informado' }}</p>
                            </div>
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium text-white" style="background-color: {{ $ticket->status?->color ?: '#64748b' }}">
                                {{ $ticket->status?->name ?? 'Sem status' }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    @endif
</div>
