<x-layouts.portal title="Empresas" header-variant="none">
    <div class="space-y-6">
        <x-portal.page-intro
            variant="compact"
            eyebrow="Administracao"
            title="Empresas"
            description="Gerencie a estrutura juridica e operacional que organiza os setores do portal."
        >
            <x-slot:actions>
                <a href="{{ route('companies.create') }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Nova empresa</a>
                <a href="{{ route('companies.export', request()->query()) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Exportar Excel</a>
            </x-slot:actions>
        </x-portal.page-intro>

        <x-portal.filter-bar title="Busca e status" description="Refine por nome, documento, contato ou situacao operacional.">
            <form method="GET" action="{{ route('companies.index') }}" class="grid gap-2 md:grid-cols-[minmax(260px,1.5fr)_180px_auto_auto]">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Buscar por nome, documento ou email" class="ui-input w-full">
                <select name="status" class="ui-native-select w-full text-sm text-slate-700">
                    <option value="">Status</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Ativa</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inativa</option>
                </select>
                <button type="submit" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Filtrar</button>
                <a href="{{ route('companies.index') }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Limpar</a>
            </form>
        </x-portal.filter-bar>

        <div class="portal-table-surface">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="portal-table-head text-left text-slate-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Nome</th>
                        <th class="px-6 py-3 font-medium">Documento</th>
                        <th class="px-6 py-3 font-medium">Contato</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($companies as $company)
                        <tr>
                            <td class="px-6 py-4">
                                <a href="{{ route('companies.show', $company) }}" class="font-medium text-slate-900 hover:text-[#3468b7]">{{ $company->name }}</a>
                                <p class="text-xs text-slate-500">{{ $company->legal_name }}</p>
                            </td>
                            <td class="px-6 py-4 text-slate-600">{{ $company->document ?: 'N/A' }}</td>
                            <td class="px-6 py-4 text-slate-600">
                                <p>{{ $company->email ?: 'Sem email' }}</p>
                                <p class="text-xs text-slate-500">{{ $company->phone ?: 'Sem telefone' }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $company->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $company->is_active ? 'Ativa' : 'Inativa' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('companies.show', $company) }}" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-medium text-white">Abrir</a>
                                    <a href="{{ route('companies.edit', $company) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700">Editar</a>
                                    <form method="POST" action="{{ route('companies.destroy', $company) }}" onsubmit="return confirm('Remover empresa?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-medium text-rose-600">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-slate-500">Nenhuma empresa cadastrada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $companies->links() }}
    </div>
</x-layouts.portal>
