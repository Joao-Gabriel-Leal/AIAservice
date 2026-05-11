<x-layouts.portal title="Gerenciar base de conhecimento" subtitle="Cadastre e mantenha os artigos por setor com controle de visibilidade." header-variant="none">
    <div class="space-y-6">
        <x-portal.page-intro
            variant="compact"
            eyebrow="Gestao editorial"
            title="Gerenciar base de conhecimento"
            description="Organize os artigos por setor, destaque conteudos com capa e acompanhe status de publicacao."
        >
            <x-slot:actions>
                <a href="{{ route('knowledge-base.create') }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Novo artigo</a>
                <x-excel-export-action :href="route('knowledge-base.manage.export', request()->query())" />
            </x-slot:actions>
        </x-portal.page-intro>

        <x-portal.filter-bar title="Busca editorial" description="Procure por titulo, resumo ou conteudo antes de revisar os cards.">
            <form method="GET" action="{{ route('knowledge-base.manage') }}" class="grid w-full gap-3 md:grid-cols-[minmax(0,1fr)_auto_auto]">
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

        <div class="grid gap-6 xl:grid-cols-2">
            @forelse ($articles as $article)
                @php($coverImageUrl = $article->coverImageUrl())
                <article class="group overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-xl hover:shadow-slate-200/70">
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
                                    <p class="mt-3 text-xs font-semibold uppercase tracking-[0.16em] text-white/80">Base de conhecimento</p>
                                </div>
                            </div>
                        @endif

                        <div class="absolute left-4 top-4 flex flex-wrap gap-2">
                            <span class="rounded-full bg-white/95 px-3 py-1 text-xs font-semibold text-slate-800 shadow-sm">{{ $article->editorial_status->label() }}</span>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold shadow-sm {{ $article->is_active ? 'bg-emerald-500 text-white' : 'bg-slate-700 text-white' }}">
                                {{ $article->is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-5 p-5">
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <x-sector-badge :sector="$article->sector" mode="chip" />
                            <span class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-700">{{ $article->visibility->label() }}</span>
                            @if (($article->attachments_count ?? 0) > 0)
                                <span class="rounded-full bg-sky-100 px-3 py-1 font-medium text-sky-700">{{ $article->attachments_count }} anexo(s)</span>
                            @endif
                        </div>

                        <div class="space-y-2">
                            <h2 class="text-xl font-semibold leading-tight text-slate-950">{{ $article->title }}</h2>
                            <p class="text-sm leading-6 text-slate-600">{{ \Illuminate\Support\Str::limit($article->summary, 150) }}</p>
                        </div>

                        <div class="grid gap-3 border-t border-slate-100 pt-4 text-xs text-slate-500 sm:grid-cols-2">
                            <div>
                                <p class="font-semibold uppercase tracking-[0.14em] text-slate-400">Empresa</p>
                                <p class="mt-1 text-sm font-medium text-slate-700">{{ $article->sector?->company?->name ?? 'Nao informada' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold uppercase tracking-[0.14em] text-slate-400">Atualizado</p>
                                <p class="mt-1 text-sm font-medium text-slate-700">{{ $article->updated_at?->format('d/m/Y H:i') }}</p>
                            </div>
                            @if ($article->sourceTicket)
                                <div class="sm:col-span-2">
                                    <p class="font-semibold uppercase tracking-[0.14em] text-slate-400">Origem</p>
                                    <p class="mt-1 text-sm font-medium text-sky-700">{{ $article->sourceTicket->fullReference() }}</p>
                                </div>
                            @endif
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
                            <a href="{{ route('knowledge-base.show', $article) }}" class="ui-action ui-action-secondary px-4 py-2 text-sm">Visualizar</a>
                            <a href="{{ route('knowledge-base.edit', $article) }}" class="ui-action ui-action-secondary px-4 py-2 text-sm">Editar</a>
                            <form method="POST" action="{{ route('knowledge-base.destroy', $article) }}" data-confirm data-confirm-variant="danger" data-confirm-title="Remover artigo?" data-confirm-message="Tem certeza que deseja remover este artigo? Esta acao nao pode ser desfeita." data-confirm-label="Sim, remover">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ui-action ui-action-danger w-full px-4 py-2 text-sm sm:w-auto">Excluir</button>
                            </form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-10 text-center xl:col-span-2">
                    <p class="text-base font-medium text-slate-700">Nenhum artigo encontrado.</p>
                    <p class="mt-2 text-sm text-slate-500">Cadastre um artigo com foto de capa ou ajuste a busca para encontrar um conteudo existente.</p>
                    <div class="mt-5">
                        <a href="{{ route('knowledge-base.create') }}" class="ui-action ui-action-primary rounded-xl px-4 py-3 text-sm">Cadastrar primeiro artigo</a>
                    </div>
                </div>
            @endforelse
        </div>

        {{ $articles->links() }}
    </div>
</x-layouts.portal>
