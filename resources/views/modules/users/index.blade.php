<x-layouts.portal title="Usuarios" header-variant="none">
    <div class="space-y-6">
        <x-portal.page-intro
            variant="compact"
            eyebrow="Administracao"
            title="Usuarios"
            description="Controle perfis globais, acessos por setor e o status operacional de cada conta."
        >
            <x-slot:actions>
                <a href="{{ route('users.create') }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Novo usuario</a>
                <a href="{{ route('users.export', request()->query()) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Exportar Excel</a>
            </x-slot:actions>
        </x-portal.page-intro>

        <x-portal.filter-bar title="Busca e acesso" description="Refine por nome, perfil global, setor ou status sem poluir a leitura da tabela.">
            <form method="GET" action="{{ route('users.index') }}" class="grid gap-2 md:grid-cols-2 xl:grid-cols-[minmax(260px,1.4fr)_180px_180px_minmax(220px,1fr)_auto_auto]">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Buscar por nome ou email" class="ui-input w-full">
                <select name="global_role" class="ui-native-select w-full text-sm text-slate-700">
                    <option value="">Perfil global</option>
                    @foreach ($globalRoles as $roleValue => $roleLabel)
                        <option value="{{ $roleValue }}" @selected(($filters['global_role'] ?? '') === $roleValue)>{{ $roleLabel }}</option>
                    @endforeach
                </select>
                <select name="status" class="ui-native-select w-full text-sm text-slate-700">
                    <option value="">Status</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Ativo</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inativo</option>
                </select>
                <select name="sector_id" class="ui-native-select w-full text-sm text-slate-700">
                    <option value="">Setor</option>
                    @foreach ($sectors as $sector)
                        <option value="{{ $sector->id }}" @selected((string) ($filters['sector_id'] ?? '') === (string) $sector->id)>{{ $sector->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Filtrar</button>
                <a href="{{ route('users.index') }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Limpar</a>
            </form>
        </x-portal.filter-bar>

        <div class="portal-table-surface">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="portal-table-head text-left text-slate-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Usuario</th>
                        <th class="px-6 py-3 font-medium">Perfil global</th>
                        <th class="px-6 py-3 font-medium">Acessos por setor</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $listedUser)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="font-medium text-slate-900">{{ $listedUser->name }}</p>
                                <p class="text-xs text-slate-500">{{ $listedUser->email }}</p>
                            </td>
                            <td class="px-6 py-4 text-slate-600">{{ $listedUser->global_role->label() }}</td>
                            <td class="px-6 py-4 text-slate-600">
                                <div class="flex flex-wrap gap-2">
                                    @forelse ($listedUser->sectorAccesses as $sectorAccess)
                                        <x-sector-badge :sector="$sectorAccess->sector" mode="chip">
                                            {{ $sectorAccess->access_level->label() }}
                                        </x-sector-badge>
                                    @empty
                                        <span class="text-xs text-slate-500">Sem vinculos setoriais</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <span class="rounded-full px-3 py-1 text-xs font-medium {{ $listedUser->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                        {{ $listedUser->is_active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                    @if ($listedUser->must_change_password)
                                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700">Troca de senha pendente</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('users.edit', $listedUser) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700">Editar</a>
                                    @can('delete', $listedUser)
                                        <form method="POST" action="{{ route('users.destroy', $listedUser) }}" onsubmit="return confirm('Remover usuario?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-medium text-rose-600">Excluir</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-slate-500">Nenhum usuario cadastrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $users->links() }}
    </div>
</x-layouts.portal>
