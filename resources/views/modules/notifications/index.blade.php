<x-layouts.portal title="Notificacoes" subtitle="Inbox interna com atualizacoes dos chamados no seu contexto." header-variant="none">
    @php
        $filterLabels = [
            'all' => 'Todas',
            'unread' => 'Nao lidas',
            'read' => 'Lidas',
        ];
    @endphp

    <div class="space-y-5">
        <x-portal.page-intro
            variant="compact"
            eyebrow="Inbox interna"
            title="Central de notificacoes"
            description="Acompanhe as atualizacoes mais recentes e destaque o que ainda precisa de leitura."
        >
            <x-slot:actions>
                @if (($counts['unread'] ?? 0) > 0)
                    <form method="POST" action="{{ route('notifications.mark-all-read') }}">
                        @csrf
                        <button type="submit" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Ler tudo</button>
                    </form>
                @endif
            </x-slot:actions>
        </x-portal.page-intro>

        <x-portal.filter-bar title="Recorte da inbox" description="Alterne entre lidas e nao lidas sem competir com o titulo da pagina.">
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($filterLabels as $filterValue => $filterLabel)
                    <a
                        href="{{ route('notifications.index', ['filter' => $filterValue]) }}"
                        class="{{ $filter === $filterValue ? 'bg-[#16253f] text-white' : 'border border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900' }} rounded-xl px-3 py-2 text-sm font-medium transition"
                    >
                        {{ $filterLabel }} ({{ $counts[$filterValue] ?? 0 }})
                    </a>
                @endforeach
            </div>
        </x-portal.filter-bar>

        <div class="space-y-3">
            @forelse ($notifications as $notification)
                @php
                    $notificationData = is_array($notification->data) ? $notification->data : [];
                    $notificationTitle = data_get($notificationData, 'title') ?: 'Atualizacao de chamado';
                    $notificationMessage = data_get($notificationData, 'message') ?: 'Sem detalhes adicionais.';
                    $notificationUrl = data_get($notificationData, 'url');
                @endphp
                <article class="rounded-2xl border {{ is_null($notification->read_at) ? 'border-[#d6e0ef] bg-[#f4f7fb]' : 'border-slate-200 bg-white' }} px-4 py-3 shadow-sm">
                    <div class="flex gap-3">
                        <div class="pt-1">
                            <span class="mt-1 block size-2.5 rounded-full {{ is_null($notification->read_at) ? 'bg-[#314c8c]' : 'bg-slate-300' }}"></span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-sm font-semibold text-slate-900">{{ $notificationTitle }}</h2>
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ is_null($notification->read_at) ? 'bg-[#e7eef9] text-[#314c8c]' : 'bg-slate-100 text-slate-600' }}">
                                            {{ is_null($notification->read_at) ? 'Nao lida' : 'Lida' }}
                                        </span>
                                    </div>

                                    <p class="mt-1 text-sm text-slate-600" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                                        {{ $notificationMessage }}
                                    </p>
                                    <p class="mt-2 text-xs text-slate-400">{{ $notification->created_at->format('d/m/Y H:i') }}</p>
                                </div>

                                <div class="flex shrink-0 flex-wrap gap-2">
                                    @if (filled($notificationUrl) || $notification->type === \App\Modules\Users\Notifications\AccountCreatedNotification::class)
                                        <a href="{{ route('notifications.open', $notification->id) }}" class="ui-action ui-action-primary rounded-xl px-3 py-2 text-sm">Abrir</a>
                                    @endif

                                    @if (is_null($notification->read_at))
                                        <form method="POST" action="{{ route('notifications.mark-read', $notification->id) }}">
                                            @csrf
                                            <button type="submit" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm">Lida</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
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
