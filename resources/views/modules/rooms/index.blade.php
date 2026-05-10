<x-layouts.portal title="Salas" header-variant="none">
    <div class="space-y-6">
        <x-portal.page-intro
            variant="compact"
            eyebrow="Administracao"
            title="Salas"
            description="Mantenha os ambientes fisicos vinculados aos setores para organizar patrimonio e fluxo operacional."
        >
            <x-slot:actions>
                <a href="{{ route('rooms.create') }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Nova sala</a>
                <a href="{{ route('rooms.export', request()->query()) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Exportar Excel</a>
            </x-slot:actions>
        </x-portal.page-intro>

        @php($hasActiveFilters = collect([
            $filters['search'] ?? '',
            $filters['sector_id'] ?? '',
            $filters['status'] ?? '',
        ])->contains(fn ($value) => filled($value)))

        <x-portal.table-search-bar
            form-id="rooms-filter-form"
            :action="route('rooms.index')"
            :search-value="$filters['search'] ?? ''"
            placeholder="Buscar por sala ou descricao"
            :clear-href="route('rooms.index')"
            :has-active-filters="$hasActiveFilters"
        />

        <div class="portal-table-surface">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="portal-table-head text-left text-slate-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Sala</th>
                        <th class="px-6 py-3 font-medium">
                            <x-portal.table-column-filter label="Setor" form-id="rooms-filter-form" name="sector_id" min-width="min-w-52">
                                @foreach ($sectors as $sector)
                                    <option value="{{ $sector->id }}" @selected((string) ($filters['sector_id'] ?? '') === (string) $sector->id)>{{ $sector->name }}</option>
                                @endforeach
                            </x-portal.table-column-filter>
                        </th>
                        <th class="px-6 py-3 font-medium">
                            <x-portal.table-column-filter label="Status" form-id="rooms-filter-form" name="status" min-width="min-w-36">
                                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Ativa</option>
                                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inativa</option>
                            </x-portal.table-column-filter>
                        </th>
                        <th class="px-6 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rooms as $room)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="font-medium text-slate-900">{{ $room->name }}</p>
                                <p class="text-xs text-slate-500">{{ $room->description ?: 'Sem descrição' }}</p>
                            </td>
                            <td class="px-6 py-4 text-slate-600">
                                <x-sector-badge :sector="$room->sector" mode="dot" />
                            </td>
                            <td class="px-6 py-4">
                                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $room->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $room->is_active ? 'Ativa' : 'Inativa' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('rooms.edit', $room) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700">Editar</a>
                                    <form method="POST" action="{{ route('rooms.destroy', $room) }}" data-confirm data-confirm-variant="danger" data-confirm-title="Remover sala?" data-confirm-message="Tem certeza que deseja remover esta sala? Esta ação não pode ser desfeita." data-confirm-label="Sim, remover">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-medium text-rose-600">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-slate-500">Nenhuma sala cadastrada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $rooms->links() }}
    </div>
</x-layouts.portal>
