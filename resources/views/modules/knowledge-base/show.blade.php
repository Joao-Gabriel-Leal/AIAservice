<x-layouts.portal :title="$article->title" :subtitle="$article->sector?->name ? 'Setor: '.$article->sector->name : null">
    <div class="space-y-6">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div class="space-y-6">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <x-sector-badge :sector="$article->sector" mode="chip" />
                        <span class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-700">{{ $article->visibility->label() }}</span>
                        @if ($article->author)
                            <span class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-700">Criado por {{ $article->author->name }}</span>
                        @endif
                        @if ($article->attachments->isNotEmpty())
                            <span class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-700">{{ $article->attachments->count() }} anexo(s)</span>
                        @endif
                    </div>

                    <div class="mt-6 rounded-2xl border border-sky-100 bg-sky-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Resumo</p>
                        <p class="mt-2 text-sm leading-7 text-slate-700">{{ $article->summary }}</p>
                    </div>

                    <div class="prose prose-slate mt-6 max-w-none whitespace-pre-line text-sm leading-7">
                        {{ $article->content }}
                    </div>
                </section>

                @if ($article->attachments->isNotEmpty())
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900">Anexos</h2>
                        <p class="mt-1 text-sm text-slate-500">Arquivos complementares vinculados a este artigo.</p>

                        <div class="mt-4 space-y-3">
                            @foreach ($article->attachments as $attachment)
                                <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 md:flex-row md:items-center md:justify-between">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-slate-900">{{ $attachment->original_name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ $attachment->humanSize() }}
                                            @if ($attachment->uploader)
                                                • enviado por {{ $attachment->uploader->name }}
                                            @endif
                                        </p>
                                    </div>

                                    <a href="{{ route('knowledge-base.attachments.show', $attachment) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700">
                                        Baixar anexo
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            <aside class="space-y-6">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Detalhes</h2>
                    <div class="mt-4 space-y-4 text-sm">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Setor</p>
                            <div class="mt-1 text-slate-800">
                                <x-sector-badge :sector="$article->sector" mode="dot" />
                            </div>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Empresa</p>
                            <p class="mt-1 text-slate-800">{{ $article->sector?->company?->name ?? 'Nao informada' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Criado em</p>
                            <p class="mt-1 text-slate-800">{{ $article->created_at?->format('d/m/Y H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Atualizado em</p>
                            <p class="mt-1 text-slate-800">{{ $article->updated_at?->format('d/m/Y H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Status</p>
                            <p class="mt-1 text-slate-800">{{ $article->is_active ? 'Ativo' : 'Inativo' }}</p>
                        </div>
                    </div>
                </section>
            </aside>
        </div>

        <div class="flex gap-3">
            <a href="{{ route('knowledge-base.index') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700">Voltar para consulta</a>
            @can('update', $article)
                <a href="{{ route('knowledge-base.edit', $article) }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Editar artigo</a>
            @endcan
        </div>
    </div>
</x-layouts.portal>
