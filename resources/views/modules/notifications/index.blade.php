<x-layouts.portal title="Notificacoes" subtitle="Inbox interna com atualizacoes dos chamados no seu contexto.">
    @php
        $filterLabels = [
            'all' => 'Todas',
            'unread' => 'Nao lidas',
            'read' => 'Lidas',
        ];
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-medium text-slate-900">Filtros</p>
                <p class="mt-1 text-sm text-slate-500">Acompanhe as notificacoes mais recentes e destaque o que ainda precisa de leitura.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach ($filterLabels as $filterValue => $filterLabel)
                    <a
                        href="{{ route('notifications.index', ['filter' => $filterValue]) }}"
                        class="{{ $filter === $filterValue ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900' }} rounded-xl px-4 py-2 text-sm font-medium transition"
                    >
                        {{ $filterLabel }} ({{ $counts[$filterValue] ?? 0 }})
                    </a>
                @endforeach
            </div>
        </div>

        <div class="space-y-4">
            @forelse ($notifications as $notification)
                <article class="rounded-3xl border {{ is_null($notification->read_at) ? 'border-sky-200 bg-sky-50/40' : 'border-slate-200 bg-white' }} p-5 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-base font-semibold text-slate-900">{{ $notification->data['title'] ?? 'Atualizacao de chamado' }}</h2>
                                <span class="rounded-full px-3 py-1 text-xs font-medium {{ is_null($notification->read_at) ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ is_null($notification->read_at) ? 'Nao lida' : 'Lida' }}
                                </span>
                            </div>

                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $notification->data['message'] ?? 'Sem detalhes adicionais.' }}</p>
                            <p class="mt-3 text-xs font-medium uppercase tracking-[0.18em] text-slate-400">{{ $notification->created_at->format('d/m/Y H:i') }}</p>
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-2">
                            @if (! empty($notification->data['url']))
                                <a href="{{ route('notifications.open', $notification->id) }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Abrir chamado</a>
                            @endif

                            @if (is_null($notification->read_at))
                                <form method="POST" action="{{ route('notifications.mark-read', $notification->id) }}">
                                    @csrf
                                    <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Marcar como lida</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm">
                    <p class="text-base font-semibold text-slate-900">Nenhuma notificacao encontrada.</p>
                    <p class="mt-2 text-sm text-slate-500">Quando houver novas atualizacoes de chamados, elas vao aparecer aqui.</p>
                </div>
            @endforelse
        </div>

        {{ $notifications->links() }}
    </div>
</x-layouts.portal>
