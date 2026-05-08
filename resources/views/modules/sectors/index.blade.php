<x-layouts.portal title="Setores" header-variant="none">
    <div class="space-y-6">
        @php($canCreateSector = auth()->user()->can('create', \App\Modules\Sectors\Models\Sector::class))

        <x-portal.page-intro
            variant="compact"
            eyebrow="Administracao"
            title="Setores"
            description="Organize os times e fluxos do portal pelos setores ativos de cada empresa."
        >
            <x-slot:actions>
                @if ($canCreateSector)
                    <a href="{{ route('sectors.create') }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Novo setor</a>
                @endif
                <a href="{{ route('sectors.export', request()->query()) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Exportar Excel</a>
            </x-slot:actions>
        </x-portal.page-intro>

        <x-portal.filter-bar title="Busca e estrutura" description="Filtre por nome, empresa e status antes de abrir o setor para edicao.">
            <form method="GET" action="{{ route('sectors.index') }}" class="grid gap-2 md:grid-cols-[minmax(240px,1.4fr)_minmax(200px,1fr)_180px_auto_auto]">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Buscar por nome ou descricao" class="ui-input w-full">
                <select name="company_id" class="ui-native-select w-full text-sm text-slate-700">
                    <option value="">Empresa</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}" @selected((string) ($filters['company_id'] ?? '') === (string) $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="ui-native-select w-full text-sm text-slate-700">
                    <option value="">Status</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Ativo</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inativo</option>
                </select>
                <button type="submit" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Filtrar</button>
                <a href="{{ route('sectors.index') }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Limpar</a>
            </form>
        </x-portal.filter-bar>

        <div class="portal-table-surface">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="portal-table-head text-left text-slate-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Setor</th>
                        <th class="px-6 py-3 font-medium">Empresa</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($sectors as $sector)
                        <tr>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="size-3 rounded-full" style="background-color: {{ $sector->displayColor() }}"></span>
                                    <p class="font-medium text-slate-900">{{ $sector->name }}</p>
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" style="background-color: {{ $sector->softColor() }}; color: {{ $sector->displayColor() }}; border: 1px solid {{ $sector->borderColor() }};">
                                        {{ $sector->displayColor() }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500">{{ $sector->description ?: 'Sem descrição' }}</p>
                            </td>
                            <td class="px-6 py-4 text-slate-600">{{ $sector->company?->name }}</td>
                            <td class="px-6 py-4">
                                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $sector->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $sector->is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('sectors.edit', $sector) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700">Editar</a>
                                    @can('delete', $sector)
                                        <form method="POST" action="{{ route('sectors.destroy', $sector) }}" onsubmit="return confirm('Remover setor?');">
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
                            <td colspan="4" class="px-6 py-10 text-center text-slate-500">Nenhum setor cadastrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $sectors->links() }}
    </div>
</x-layouts.portal>
