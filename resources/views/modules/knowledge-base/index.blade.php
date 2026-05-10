<x-layouts.portal title="Base de conhecimento" subtitle="Consulte orientacoes publicas e conteudos operacionais liberados para o seu acesso." header-variant="none">
    <div class="space-y-6">
        <x-portal.page-intro
            variant="compact"
            eyebrow="Conhecimento compartilhado"
            title="Base de conhecimento"
            description="Explore artigos por setor, encontre respostas rapidas e abra o conteudo certo com menos atrito."
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
        </x-portal.page-intro>

        <x-portal.filter-bar title="Busca por artigos" description="Procure por titulo, resumo ou conteudo antes de abrir o artigo certo.">
            <form method="GET" action="{{ route('knowledge-base.index') }}" class="grid w-full gap-3 md:grid-cols-[minmax(0,1fr)_auto_auto]">
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
        </x-portal.filter-bar>

        <div class="grid gap-6 lg:grid-cols-2">
            @forelse ($articles as $article)
                @php($helpfulVotes = $article->helpful_feedback_count ?? 0)
                @php($ticketUsages = $article->ticket_usages_count ?? 0)
                @php($isHighlighted = trim($search) === '' && $articles->currentPage() === 1 && $loop->iteration <= 3 && ($helpfulVotes > 0 || $ticketUsages > 0))
                @php($coverImageUrl = $article->coverImageUrl())

                <article class="group overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-xl hover:shadow-slate-200/70">
                    <a href="{{ route('knowledge-base.show', $article) }}" class="block focus:outline-none focus-visible:ring-4 focus-visible:ring-sky-200">
                        <div class="relative h-48 overflow-hidden bg-slate-900">
                            @if ($coverImageUrl)
                                <img src="{{ $coverImageUrl }}" alt="Capa do artigo" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                            @else
                                <div class="grid h-full place-items-center text-white" style="background-image: linear-gradient(135deg, #132238 0%, #256d85 52%, #37a28f 100%);">
                                    <div class="absolute inset-0 opacity-20" style="background-image: repeating-linear-gradient(135deg, rgba(255,255,255,.26) 0 1px, transparent 1px 12px);"></div>
                                    <div class="relative text-center">
                                        <div class="mx-auto grid size-16 place-items-center rounded-2xl bg-white/15 text-3xl font-semibold">
                                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($article->title, 0, 1)) }}
                                        </div>
                                        <p class="mt-3 text-xs font-semibold uppercase tracking-[0.16em] text-white/80">Artigo</p>
                                    </div>
                                </div>
                            @endif

                            <div class="absolute left-4 top-4 flex flex-wrap gap-2">
                                @if ($isHighlighted)
                                    <span class="rounded-full bg-amber-400 px-3 py-1 text-xs font-semibold text-amber-950 shadow-sm">Mais util</span>
                                @endif
                                <span class="rounded-full bg-white/95 px-3 py-1 text-xs font-semibold text-slate-800 shadow-sm">{{ $article->visibility->label() }}</span>
                            </div>
                        </div>
                    </a>

                    <div class="grid gap-5 p-5">
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <x-sector-badge :sector="$article->sector" mode="chip" />
                            @if (($article->attachments_count ?? 0) > 0)
                                <span class="rounded-full bg-sky-100 px-3 py-1 font-medium text-sky-700">{{ $article->attachments_count }} anexo(s)</span>
                            @endif
                            <span class="rounded-full bg-emerald-100 px-3 py-1 font-medium text-emerald-700">{{ trans_choice('ui.helpful_vote', $helpfulVotes, ['count' => $helpfulVotes]) }}</span>
                            <span class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-700">{{ trans_choice('ui.ticket_usage', $ticketUsages, ['count' => $ticketUsages]) }}</span>
                        </div>

                        <div class="space-y-2">
                            <h2 class="text-xl font-semibold leading-tight text-slate-950">{{ $article->title }}</h2>
                            <p class="text-sm leading-6 text-slate-600">{{ \Illuminate\Support\Str::limit($article->summary, 170) }}</p>
                            @if ($article->sourceTicket)
                                <p class="text-xs font-medium text-sky-700">Originado do chamado {{ $article->sourceTicket->fullReference() }}</p>
                            @endif
                        </div>

                        <div class="flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-xs text-slate-500">
                                Atualizado em {{ $article->updated_at?->format('d/m/Y H:i') }}
                                @if ($article->author)
                                    - por {{ $article->author->name }}
                                @endif
                            </p>

                            <a href="{{ route('knowledge-base.show', $article) }}" class="ui-action ui-action-secondary px-4 py-2 text-sm">Abrir artigo</a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-10 text-center lg:col-span-2">
                    <p class="text-base font-medium text-slate-700">Nenhum artigo encontrado.</p>
                    <p class="mt-2 text-sm text-slate-500">Cadastre topicos da base de conhecimento ou ajuste a busca para encontrar um artigo existente.</p>

                    @can('create', \App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle::class)
                        <div class="mt-5 flex flex-col justify-center gap-3 sm:flex-row">
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
