<x-layouts.portal title="Usuarios">
    <div class="space-y-6">
        <div class="flex justify-end">
            <a href="{{ route('users.create') }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Novo usuario</a>
        </div>

        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
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
                                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                                            {{ $sectorAccess->sector?->name ?? 'Setor removido' }}: {{ $sectorAccess->access_level->label() }}
                                        </span>
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
