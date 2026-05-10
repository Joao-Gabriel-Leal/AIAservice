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

        <x-portal.filter-bar title="Busca e recorte" description="Filtre por sala, setor e status para localizar os ambientes ativos com rapidez.">
            <form method="GET" action="{{ route('rooms.index') }}" class="grid gap-2 md:grid-cols-[minmax(240px,1.4fr)_minmax(220px,1fr)_180px_auto_auto]">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Buscar por sala ou descricao" class="ui-input w-full">
                <select name="sector_id" class="ui-native-select w-full text-sm text-slate-700">
                    <option value="">Setor</option>
                    @foreach ($sectors as $sector)
                        <option value="{{ $sector->id }}" @selected((string) ($filters['sector_id'] ?? '') === (string) $sector->id)>{{ $sector->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="ui-native-select w-full text-sm text-slate-700">
                    <option value="">Status</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Ativa</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inativa</option>
                </select>
                <button type="submit" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Filtrar</button>
                <a href="{{ route('rooms.index') }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Limpar</a>
            </form>
        </x-portal.filter-bar>

        <div class="portal-table-surface">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="portal-table-head text-left text-slate-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Sala</th>
                        <th class="px-6 py-3 font-medium">Setor</th>
                        <th class="px-6 py-3 font-medium">Status</th>
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
