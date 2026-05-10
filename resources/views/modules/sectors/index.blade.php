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

        @php($hasActiveFilters = collect([
            $filters['search'] ?? '',
            $filters['company_id'] ?? '',
            $filters['status'] ?? '',
        ])->contains(fn ($value) => filled($value)))

        <x-portal.table-search-bar
            form-id="sectors-filter-form"
            :action="route('sectors.index')"
            :search-value="$filters['search'] ?? ''"
            placeholder="Buscar por nome ou descricao"
            :clear-href="route('sectors.index')"
            :has-active-filters="$hasActiveFilters"
        />

        <div class="portal-table-surface">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="portal-table-head text-left text-slate-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Setor</th>
                        <th class="px-6 py-3 font-medium">
                            <x-portal.table-column-filter label="Empresa" form-id="sectors-filter-form" name="company_id" min-width="min-w-52">
                                @foreach ($companies as $company)
                                    <option value="{{ $company->id }}" @selected((string) ($filters['company_id'] ?? '') === (string) $company->id)>{{ $company->name }}</option>
                                @endforeach
                            </x-portal.table-column-filter>
                        </th>
                        <th class="px-6 py-3 font-medium">
                            <x-portal.table-column-filter label="Status" form-id="sectors-filter-form" name="status" min-width="min-w-36">
                                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Ativo</option>
                                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inativo</option>
                            </x-portal.table-column-filter>
                        </th>
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
                                        <form method="POST" action="{{ route('sectors.destroy', $sector) }}" data-confirm data-confirm-variant="danger" data-confirm-title="Remover setor?" data-confirm-message="Tem certeza que deseja remover este setor? Esta ação não pode ser desfeita." data-confirm-label="Sim, remover">
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
