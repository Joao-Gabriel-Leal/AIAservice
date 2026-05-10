@php($coverImageUrl = $article->coverImageUrl())

<x-layouts.portal :title="$article->title" :subtitle="$article->sector?->name ? 'Setor: '.$article->sector->name : null" header-variant="none">
    <div class="space-y-6">
        <x-portal.page-intro
            variant="detail"
            :eyebrow="$article->sector?->company?->name ?? 'Base de conhecimento'"
            :title="$article->title"
            description="Conteudo publicado para consulta operacional, com feedback, anexos e contexto detalhado logo abaixo."
        >
            <x-slot:actions>
                <a href="{{ route('knowledge-base.index') }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Voltar para consulta</a>
                @can('update', $article)
                    <a href="{{ route('knowledge-base.edit', $article) }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Editar artigo</a>
                @endcan
            </x-slot:actions>
            <x-slot:meta>
                <x-sector-badge :sector="$article->sector" mode="chip" />
                <span class="portal-chip">{{ $article->visibility->label() }}</span>
                <span class="portal-chip">{{ $article->editorial_status->label() }}</span>
                @if ($article->author)
                    <span class="portal-chip">Criado por {{ $article->author->name }}</span>
                @endif
                @if ($article->attachments->isNotEmpty())
                    <span class="portal-chip">{{ $article->attachments->count() }} anexo(s)</span>
                @endif
            </x-slot:meta>
        </x-portal.page-intro>

        @if ($coverImageUrl)
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <img src="{{ $coverImageUrl }}" alt="Capa do artigo" class="h-64 w-full object-cover lg:h-80">
            </section>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div class="space-y-6">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="rounded-2xl border border-sky-100 bg-sky-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Resumo</p>
                        <p class="mt-2 text-sm leading-7 text-slate-700">{{ $article->summary }}</p>
                    </div>

                    <div class="mt-6 rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Utilidade</p>
                        @php($helpfulVotes = $article->helpful_feedback_count ?? 0)
                        @php($notHelpfulVotes = $article->not_helpful_feedback_count ?? 0)
                        @php($ticketUsages = $article->ticket_usages_count ?? 0)
                        <p class="mt-2 text-sm leading-7 text-slate-700">
                            {{ trans_choice('ui.helpful_vote', $helpfulVotes, ['count' => $helpfulVotes]) }},
                            {{ trans_choice('ui.not_helpful_vote', $notHelpfulVotes, ['count' => $notHelpfulVotes]) }}
                            e {{ trans_choice('ui.ticket_usage', $ticketUsages, ['count' => $ticketUsages]) }}.
                        </p>

                        <form method="POST" action="{{ route('knowledge-base.feedback', $article) }}" class="mt-4 flex flex-wrap gap-3">
                            @csrf
                            <button type="submit" name="is_helpful" value="1" class="rounded-xl border px-4 py-2 text-sm font-medium {{ ($userFeedback?->is_helpful ?? null) === true ? 'border-emerald-300 bg-white text-emerald-700' : 'border-slate-300 bg-white text-slate-700' }}">
                                Foi util
                            </button>
                            <button type="submit" name="is_helpful" value="0" class="rounded-xl border px-4 py-2 text-sm font-medium {{ ($userFeedback && $userFeedback->is_helpful === false) ? 'border-rose-300 bg-white text-rose-700' : 'border-slate-300 bg-white text-slate-700' }}">
                                Nao ajudou
                            </button>
                        </form>
                    </div>

                    <div class="prose prose-slate mt-6 max-w-none whitespace-pre-line text-sm leading-7">
                        {{ $article->content }}
                    </div>
                </section>

                @if ($article->attachments->isNotEmpty())
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900">Anexos</h2>

                        <div class="mt-4 space-y-3">
                            @foreach ($article->attachments as $attachment)
                                <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 md:flex-row md:items-center md:justify-between">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-slate-900">{{ $attachment->original_name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ $attachment->humanSize() }}@if ($attachment->uploader) - enviado por {{ $attachment->uploader->name }}@endif
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
                        @if ($article->sourceTicket)
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Chamado de origem</p>
                                <p class="mt-1 text-slate-800">
                                    @can('view', $article->sourceTicket)
                                        <a href="{{ route('tickets.show', $article->sourceTicket) }}" class="text-sky-700 hover:text-sky-800">{{ $article->sourceTicket->fullReference() }} - {{ $article->sourceTicket->title }}</a>
                                    @else
                                        {{ $article->sourceTicket->fullReference() }}
                                    @endcan
                                </p>
                            </div>
                        @endif
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
    </div>
</x-layouts.portal>
