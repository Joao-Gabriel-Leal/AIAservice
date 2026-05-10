<x-layouts.portal title="Templates de setor" header-variant="none">
    <div class="space-y-6">
        <x-portal.page-intro
            variant="compact"
            eyebrow="Administracao"
            title="Templates de setor"
            description="Salve um onboarding reutilizavel com formulario, catalogo, automacoes e SLA para acelerar a criacao de novos setores."
        >
            <x-slot:actions>
                <a href="{{ route('sector-templates.create') }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Novo template</a>
            </x-slot:actions>
        </x-portal.page-intro>

        <div class="portal-table-surface">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="portal-table-head text-left text-slate-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Template</th>
                        <th class="px-6 py-3 font-medium">Formulario</th>
                        <th class="px-6 py-3 font-medium">Uso</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($templates as $template)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="font-medium text-slate-900">{{ $template->name }}</p>
                                <p class="text-xs text-slate-500">{{ $template->description ?: 'Sem descricao' }}</p>
                            </td>
                            <td class="px-6 py-4 text-slate-600">{{ $template->defaultFormName() }}</td>
                            <td class="px-6 py-4 text-slate-600">{{ $template->sectors_count }} setor(es)</td>
                            <td class="px-6 py-4">
                                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $template->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $template->is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('sector-templates.edit', $template) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700">Editar</a>
                                    <form method="POST" action="{{ route('sector-templates.destroy', $template) }}" data-confirm data-confirm-variant="danger" data-confirm-title="Remover template?" data-confirm-message="Tem certeza que deseja remover este template? Esta ação não pode ser desfeita." data-confirm-label="Sim, remover">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-medium text-rose-600">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-slate-500">Nenhum template cadastrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $templates->links() }}
    </div>
</x-layouts.portal>
