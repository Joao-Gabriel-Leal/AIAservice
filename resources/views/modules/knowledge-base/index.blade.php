<x-layouts.portal title="Base de conhecimento" subtitle="Consulte orientacoes publicas e conteudos operacionais liberados para o seu acesso." :show-header="false">
    <div class="space-y-6">
        <x-portal.section-hero
            eyebrow="Conhecimento compartilhado"
            title="Base de conhecimento"
            description="Pesquise por titulo, resumo, conteudo ou abra o gerenciamento para cadastrar novos topicos."
        >
            @can('create', \App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle::class)
                <x-slot:actions>
                    <a href="{{ route('knowledge-base.create') }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Novo artigo</a>
                    <a href="{{ route('knowledge-base.manage') }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Gerenciar artigos</a>
                    <a href="{{ route('knowledge-base.export', request()->query()) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Exportar Excel</a>
                </x-slot:actions>
            @endcan

            @cannot('create', \App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle::class)
                <x-slot:actions>
                    <a href="{{ route('knowledge-base.export', request()->query()) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Exportar Excel</a>
                </x-slot:actions>
            @endcannot

            <form method="GET" action="{{ route('knowledge-base.index') }}" class="flex w-full max-w-3xl gap-3">
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Buscar por titulo, resumo ou conteudo"
                    class="ui-input w-full"
                >
                <button type="submit" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Buscar</button>
                <a href="{{ route('knowledge-base.index') }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Limpar</a>
            </form>
        </x-portal.section-hero>

        <div class="grid gap-6">
            @forelse ($articles as $article)
                <article class="portal-surface p-6">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <x-sector-badge :sector="$article->sector" mode="chip" />
                                <span class="rounded-full {{ $article->visibility->value === 'public' ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700' }} px-3 py-1 font-medium">
                                    {{ $article->visibility->label() }}
                                </span>
                                @if (($article->attachments_count ?? 0) > 0)
                                    <span class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-700">{{ $article->attachments_count }} anexo(s)</span>
                                @endif
                            </div>

                            <div>
                                <h2 class="text-xl font-semibold text-slate-900">{{ $article->title }}</h2>
                                <p class="mt-2 text-sm text-slate-600">{{ $article->summary }}</p>
                                <p class="mt-3 text-xs text-slate-500">
                                    Atualizado em {{ $article->updated_at?->format('d/m/Y H:i') }}
                                    @if ($article->author)
                                        - por {{ $article->author->name }}
                                    @endif
                                </p>
                            </div>
                        </div>

                        <a href="{{ route('knowledge-base.show', $article) }}" class="ui-action ui-action-secondary rounded-xl px-4 py-2 text-sm">Abrir artigo</a>
                    </div>
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-10 text-center">
                    <p class="text-base font-medium text-slate-700">Nenhum artigo encontrado.</p>
                    <p class="mt-2 text-sm text-slate-500">Cadastre topicos da base de conhecimento ou ajuste a busca para encontrar um artigo existente.</p>

                    @can('create', \App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle::class)
                        <div class="mt-5 flex justify-center gap-3">
                            <a href="{{ route('knowledge-base.create') }}" class="ui-action ui-action-primary rounded-xl px-4 py-3 text-sm">Cadastrar primeiro artigo</a>
                            <a href="{{ route('knowledge-base.manage') }}" class="ui-action ui-action-secondary rounded-xl px-4 py-3 text-sm">Abrir gerenciamento</a>
                        </div>
                    @endcan
                </div>
            @endforelse
        </div>

        {{ $articles->links() }}
    </div>
</x-layouts.portal>
