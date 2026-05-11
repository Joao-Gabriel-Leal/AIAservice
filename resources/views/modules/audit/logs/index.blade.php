@php($presenter = app(\App\Modules\Audit\Support\ActivityLogPresenter::class))
@php($hasActiveFilters = collect($filters)->contains(fn ($value) => filled($value)))

<x-layouts.portal title="Logs" header-variant="none">
    <div class="space-y-6">
        <x-portal.page-intro
            variant="compact"
            eyebrow="Administracao"
            title="Logs"
            description="Auditoria global de eventos administrativos, operacionais e de seguranca."
        />

        <form method="GET" action="{{ route('admin.logs.index') }}" class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-4 lg:grid-cols-[minmax(220px,1fr)_minmax(180px,260px)_160px_160px_auto] lg:items-end">
                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-slate-700">Buscar</span>
                    <input
                        type="search"
                        name="search"
                        value="{{ $filters['search'] }}"
                        class="ui-input w-full"
                        placeholder="Evento, responsavel ou setor"
                    >
                </label>

                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-slate-700">Evento</span>
                    <select name="event" class="ui-native-select w-full">
                        <option value="">Todos</option>
                        @foreach ($events as $event)
                            <option value="{{ $event }}" @selected($filters['event'] === $event)>
                                {{ $presenter->eventLabelFromString($event) }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-slate-700">De</span>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="ui-input w-full">
                </label>

                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-slate-700">Ate</span>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="ui-input w-full">
                </label>

                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Filtrar</button>
                    @if ($hasActiveFilters)
                        <a href="{{ route('admin.logs.index') }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Limpar</a>
                    @endif
                </div>
            </div>
        </form>

        <section class="space-y-3">
            @forelse ($logs as $log)
                @php($changes = $presenter->changes($log))
                @php($subjectUrl = $presenter->subjectUrl($log))

                <article class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                    {{ $presenter->subjectTypeLabel($log) }}
                                </span>
                                @if ($log->sector)
                                    <x-sector-badge :sector="$log->sector" mode="chip" />
                                @endif
                            </div>

                            <h2 class="mt-3 text-base font-semibold text-slate-950">
                                {{ $presenter->eventLabel($log) }}
                            </h2>
                            <p class="mt-1 text-sm text-slate-600">{{ $log->description ?: $log->event }}</p>

                            <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-slate-500">
                                <span>
                                    Alvo:
                                    @if ($subjectUrl)
                                        <a href="{{ $subjectUrl }}" class="font-medium text-sky-700 hover:text-sky-800">
                                            {{ $presenter->subjectName($log) }}
                                        </a>
                                    @else
                                        <span class="font-medium text-slate-700">{{ $presenter->subjectName($log) }}</span>
                                    @endif
                                </span>
                                <span>Responsavel: <span class="font-medium text-slate-700">{{ $log->causer?->name ?? 'Sistema' }}</span></span>
                            </div>
                        </div>

                        <div class="shrink-0 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            <p class="font-medium text-slate-900">{{ $log->created_at?->format('d/m/Y H:i') }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $log->created_at?->diffForHumans() }}</p>
                        </div>
                    </div>

                    @if ($changes !== [])
                        <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-left text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3 font-medium">Campo</th>
                                        <th class="px-4 py-3 font-medium">Antes</th>
                                        <th class="px-4 py-3 font-medium">Depois</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @foreach ($changes as $change)
                                        <tr>
                                            <td class="w-56 px-4 py-3 font-medium text-slate-700">{{ $change['label'] }}</td>
                                            <td class="max-w-md px-4 py-3 text-slate-600">
                                                <span class="break-words">{{ $change['before'] }}</span>
                                            </td>
                                            <td class="max-w-md px-4 py-3 text-slate-900">
                                                <span class="break-words font-medium">{{ $change['after'] }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                            Evento sem delta de campos registrado.
                        </p>
                    @endif
                </article>
            @empty
                <div class="rounded-[1.5rem] border border-dashed border-slate-300 bg-white px-5 py-10 text-center text-sm text-slate-500">
                    Nenhum log encontrado.
                </div>
            @endforelse
        </section>

        {{ $logs->links() }}
    </div>
</x-layouts.portal>
