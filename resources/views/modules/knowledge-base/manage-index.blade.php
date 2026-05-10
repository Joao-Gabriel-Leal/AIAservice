<x-layouts.portal title="Gerenciar base de conhecimento" subtitle="Cadastre e mantenha os artigos por setor com controle de visibilidade." header-variant="none">
    <div class="space-y-6">
        <x-portal.page-intro
            variant="compact"
            eyebrow="Gestao editorial"
            title="Gerenciar base de conhecimento"
            description="Cadastre, revise e mantenha os artigos por setor com controle de visibilidade."
        >
            <x-slot:actions>
                <a href="{{ route('knowledge-base.create') }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Novo artigo</a>
                <a href="{{ route('knowledge-base.manage.export', request()->query()) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Exportar Excel</a>
            </x-slot:actions>
        </x-portal.page-intro>

        <x-portal.filter-bar title="Busca editorial" description="Procure por titulo, resumo ou conteudo antes de ajustar a linha na tabela.">
            <form method="GET" action="{{ route('knowledge-base.manage') }}" class="flex w-full max-w-2xl gap-3">
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Buscar por titulo, resumo ou conteudo"
                    class="ui-input w-full"
                >
                <button type="submit" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Buscar</button>
                <a href="{{ route('knowledge-base.manage') }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Limpar</a>
            </form>
        </x-portal.filter-bar>

        <div class="portal-table-surface">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="portal-table-head text-left text-slate-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Artigo</th>
                        <th class="px-6 py-3 font-medium">Setor</th>
                        <th class="px-6 py-3 font-medium">Visibilidade</th>
                        <th class="px-6 py-3 font-medium">Editorial</th>
                        <th class="px-6 py-3 font-medium">Anexos</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($articles as $article)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="font-medium text-slate-900">{{ $article->title }}</p>
                                <p class="text-xs text-slate-500">{{ $article->summary }}</p>
                            </td>
                            <td class="px-6 py-4 text-slate-600">
                                <x-sector-badge :sector="$article->sector" mode="dot" />
                                <p class="text-xs text-slate-500">{{ $article->sector?->company?->name }}</p>
                            </td>
                            <td class="px-6 py-4 text-slate-600">{{ $article->visibility->label() }}</td>
                            <td class="px-6 py-4 text-slate-600">
                                {{ $article->editorial_status->label() }}
                                @if ($article->sourceTicket)
                                    <p class="text-xs text-sky-700">{{ $article->sourceTicket->fullReference() }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-600">{{ $article->attachments_count ?? 0 }}</td>
                            <td class="px-6 py-4">
                                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $article->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $article->is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('knowledge-base.show', $article) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700">Visualizar</a>
                                    <a href="{{ route('knowledge-base.edit', $article) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700">Editar</a>
                                    <form method="POST" action="{{ route('knowledge-base.destroy', $article) }}" data-confirm data-confirm-variant="danger" data-confirm-title="Remover artigo?" data-confirm-message="Tem certeza que deseja remover este artigo? Esta ação não pode ser desfeita." data-confirm-label="Sim, remover">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-medium text-rose-600">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-10 text-center text-slate-500">Nenhum artigo encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $articles->links() }}
    </div>
</x-layouts.portal>
