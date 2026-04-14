<x-layouts.portal title="Empresas">
    <div class="space-y-6">
        <div class="flex justify-end">
            <a href="{{ route('companies.create') }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Nova empresa</a>
        </div>

        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
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
