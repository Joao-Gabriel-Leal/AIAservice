<x-layouts.portal title="Empresas" :show-header="false">
    <div class="space-y-6">
        <x-portal.section-hero
            compact
            eyebrow="Administracao"
            title="Empresas"
            description="Gerencie a estrutura juridica e operacional que organiza os setores do portal."
        >
            <x-slot:actions>
                <a href="{{ route('companies.create') }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Nova empresa</a>
                <a href="{{ route('companies.export', request()->query()) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Exportar Excel</a>
            </x-slot:actions>

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
        </x-portal.section-hero>

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
                                <p class="font-medium text-slate-900">{{ $company->name }}</p>
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
