<x-layouts.portal title="Setores">
    <div class="space-y-6">
        @can('create', \App\Modules\Sectors\Models\Sector::class)
            <div class="flex justify-end">
                <a href="{{ route('sectors.create') }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Novo setor</a>
            </div>
        @endcan

        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
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
                                <p class="font-medium text-slate-900">{{ $sector->name }}</p>
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
