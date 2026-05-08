<div class="space-y-6">
    @php
        $messageCount = $messages->count();
        $latestMessage = $messages->last();
        $internalMessageCount = $internalMessages->count();
        $latestInternalMessage = $internalMessages->last();
        $activityLogs = $ticket->activityLogs->sortByDesc('created_at')->values();
        $activityCount = $activityLogs->count();
        $latestActivity = $activityLogs->first();
        $attachmentCount = $visibleAttachments->count();
        $messageLabel = $messageCount === 1 ? 'mensagem' : 'mensagens';
        $internalMessageLabel = $internalMessageCount === 1 ? 'atualizacao interna' : 'atualizacoes internas';
        $attachmentLabel = $attachmentCount === 1 ? 'anexo' : 'anexos';
        $activityLabel = $activityCount === 1 ? 'evento' : 'eventos';
        $latestActivitySummary = null;
        $detailFields = $canViewOperationalHistory ? $fields : $requesterFields;
        $detailFieldsCount = $detailFields->count();
        $internalAudienceUsersById = $internalAudienceUsers->keyBy('id');

        if ($latestActivity) {
            $latestActivitySummary = $latestActivity->description ?: $latestActivity->event;

            if (strlen($latestActivitySummary) > 56) {
                $latestActivitySummary = substr($latestActivitySummary, 0, 53).'...';
            }
        }
    @endphp

    <x-portal.page-intro
        variant="detail"
        :eyebrow="($ticket->sector?->company?->name ?? 'Sem empresa').' / '.($ticket->sector?->name ?? 'Sem setor')"
        :title="$ticket->title"
        :description="$ticket->description ?: 'Sem descricao adicional.'"
    >
        <x-slot:meta>
            <span class="portal-chip">{{ $ticket->publicReference() }}</span>
            <span class="portal-chip">ID interno {{ $ticket->technicalReference() }}</span>
            <div
                x-data="{ copied: false, timeoutId: null, copy() { if (! navigator.clipboard) { return; } navigator.clipboard.writeText(@js($ticket->publicReference())); this.copied = true; window.clearTimeout(this.timeoutId); this.timeoutId = window.setTimeout(() => this.copied = false, 1600); } }"
                class="inline-flex items-center gap-2"
            >
                <button type="button" class="portal-chip transition hover:bg-slate-100" @click="copy()">Copiar codigo</button>
                <span x-cloak x-show="copied" class="text-xs font-medium text-emerald-700">Copiado</span>
            </div>
            <x-sector-badge :sector="$ticket->sector" mode="chip" />
            <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $ticket->priority?->badgeColor() }}">{{ $ticket->priority?->label() }}</span>
            <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium text-white" style="background-color: {{ $ticket->group?->color ?: '#64748b' }}">
                {{ $ticket->group?->name ?? 'Sem etapa' }}
            </span>
            <span class="portal-chip">Solicitante: {{ $ticket->requester?->name ?? 'Nao informado' }}</span>
            <span class="portal-chip">Responsavel: {{ $ticket->assignee?->name ?? 'Nao atribuido' }}</span>
            <span class="portal-chip">Catalogo: {{ $ticket->catalogItem?->name ?? 'Nao vinculado' }}</span>
            <span class="portal-chip">{{ $messageCount }} {{ $messageLabel }}</span>
            <span class="portal-chip">{{ $attachmentCount }} {{ $attachmentLabel }}</span>
            @if ($canViewInternalUpdates)
                <span class="portal-chip">{{ $internalMessageCount }} {{ $internalMessageLabel }}</span>
            @endif
            @if ($canViewOperationalHistory)
                <span class="portal-chip">{{ $activityCount }} {{ $activityLabel }}</span>
            @endif
        </x-slot:meta>
    </x-portal.page-intro>

    @if (session('status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm" role="status">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_360px] xl:items-start">
        <div class="space-y-6">
            <div
                wire:poll.5s
                class="space-y-6"
                x-data="ticketConversation({{ $ticket->id }})"
                x-init="boot()"
            >
                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="ticket-conversation-surface">
                        <div class="ticket-chat-header">
                            <div>
                                <p class="ticket-panel-kicker">Fluxo de conversa</p>
                                <h3 class="ticket-panel-title ticket-chat-title">Conversa</h3>
                                <p class="ticket-panel-copy">Mensagens do atendimento em tempo real, com foco em leitura e resposta rapida.</p>
                            </div>

                            <div class="ticket-chat-chip-group">
                                <span class="portal-chip">{{ $messageCount }} {{ $messageLabel }}</span>
                                @if ($latestMessage?->created_at)
                                    <span class="portal-chip">Ultima mensagem {{ $latestMessage->created_at->diffForHumans() }}</span>
                                @endif
                            </div>
                        </div>

                        <div x-ref="messageList" class="ticket-chat-list">
                            @forelse ($messages as $ticketMessage)
                                @php
                                    $isOwnMessage = $ticketMessage->user_id === auth()->id();
                                    $messageAttachments = $ticketMessage->attachments ?? collect();
                                    $hasMessageText = trim((string) $ticketMessage->message) !== '';
                                @endphp
                                <div class="flex {{ $isOwnMessage ? 'justify-end' : 'justify-start' }}">
                                    <article class="ticket-chat-bubble {{ $isOwnMessage ? 'ticket-chat-bubble-self' : 'ticket-chat-bubble-other' }}">
                                        <div class="ticket-chat-meta {{ $isOwnMessage ? 'ticket-chat-meta-self' : '' }}">
                                            <div class="ticket-chat-author-row">
                                                <span class="ticket-chat-author">{{ $ticketMessage->user?->name ?? 'Sistema' }}</span>
                                                @if ($isOwnMessage)
                                                    <span class="ticket-chat-author-pill">Voce</span>
                                                @endif
                                            </div>
                                            <span class="ticket-chat-time">{{ $ticketMessage->created_at?->format('d/m/Y H:i') }}</span>
                                        </div>

                                        @if ($hasMessageText)
                                            <p class="ticket-chat-message">{{ $ticketMessage->message }}</p>
                                        @endif

                                        @if ($messageAttachments->isNotEmpty())
                                            <div class="mt-3 grid gap-2">
                                                @foreach ($messageAttachments as $attachment)
                                                    @if ($attachment->isImage())
                                                        <a href="{{ route('tickets.attachments.show', $attachment) }}" class="block overflow-hidden rounded-2xl border border-white/40 bg-white/95">
                                                            <img
                                                                src="{{ route('tickets.attachments.inline', $attachment) }}"
                                                                alt="{{ $attachment->original_name }}"
                                                                class="max-h-80 w-full bg-slate-100 object-contain"
                                                            >
                                                        </a>
                                                    @elseif ($attachment->isVideo())
                                                        <div class="overflow-hidden rounded-2xl border border-white/40 bg-slate-950">
                                                            <video controls preload="metadata" class="max-h-80 w-full">
                                                                <source src="{{ route('tickets.attachments.inline', $attachment) }}" type="{{ $attachment->mime_type }}">
                                                            </video>
                                                        </div>
                                                    @else
                                                        <a href="{{ route('tickets.attachments.show', $attachment) }}" class="flex items-center justify-between gap-3 rounded-2xl border border-white/40 bg-white/95 px-4 py-3 text-slate-700">
                                                            <div class="min-w-0">
                                                                <p class="truncate text-sm font-semibold text-slate-900">{{ $attachment->original_name }}</p>
                                                                <p class="mt-1 text-xs text-slate-500">{{ $attachment->displaySize() }}</p>
                                                            </div>
                                                            <span class="shrink-0 text-sm font-medium text-sky-700">Baixar</span>
                                                        </a>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    </article>
                                </div>
                            @empty
                                <div class="ticket-chat-empty">
                                    <p class="text-base font-medium text-slate-900">Nenhuma mensagem por aqui ainda.</p>
                                    <p class="mt-2 text-sm text-slate-500">Quando a conversa comecar, as atualizacoes do atendimento vao aparecer aqui.</p>
                                </div>
                            @endforelse
                        </div>

                        @if ($canComment)
                            <form wire:submit="sendMessage" class="ticket-chat-composer">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">Responder</p>
                                    <p class="mt-1 text-sm text-slate-500">Sua mensagem fica registrada no atendimento para quem participa desta conversa.</p>
                                </div>

                                <textarea wire:model="message" rows="4" class="ui-input ticket-chat-input w-full" placeholder="Escreva sua mensagem"></textarea>
                                @error('message') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror

                                <div class="rounded-2xl border border-dashed border-slate-300 bg-white/80 px-4 py-3">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">Arquivos no chat</p>
                                            <p class="mt-1 text-xs text-slate-500">Ate 5 arquivos por mensagem, com limite de 25 MB cada.</p>
                                        </div>

                                        <label class="ui-action ui-action-secondary cursor-pointer rounded-xl px-4 py-2 text-sm">
                                            Selecionar arquivos
                                            <input wire:model="chatFiles" type="file" multiple class="sr-only">
                                        </label>
                                    </div>

                                    <div wire:loading wire:target="chatFiles" class="mt-3 text-xs text-slate-500">
                                        Preparando arquivos...
                                    </div>

                                    @if (count($chatFiles) > 0)
                                        <div class="mt-3 grid gap-2">
                                            @foreach ($chatFiles as $index => $file)
                                                <div wire:key="chat-file-{{ $index }}" class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                                    <p class="min-w-0 truncate text-sm font-medium text-slate-700">{{ $file->getClientOriginalName() }}</p>
                                                    <span class="shrink-0 text-xs text-slate-500">{{ number_format(($file->getSize() ?? 0) / 1024, 1) }} KB</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    @error('chatFiles') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                    @error('chatFiles.*') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                </div>

                                <div class="ticket-chat-composer-footer">
                                    <p class="text-xs text-slate-500">Atualizacao em tempo real sempre que uma nova mensagem chegar.</p>

                                    <button
                                        type="submit"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="ui-loading"
                                        wire:target="sendMessage,chatFiles"
                                        class="ui-action ui-action-primary rounded-2xl px-5 py-3 text-sm font-medium sm:w-auto"
                                    >
                                        <span wire:loading.remove wire:target="sendMessage,chatFiles">Enviar mensagem</span>
                                        <span wire:loading wire:target="sendMessage,chatFiles">Enviando...</span>
                                    </button>
                                </div>
                            </form>
                        @else
                            <div class="ticket-chat-composer">
                                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500">
                                    Este chamado esta finalizado. A conversa fica bloqueada e novas mensagens so serao liberadas se o chamado for reaberto.
                                </div>
                            </div>
                        @endif
                    </div>
                </section>

                @if ($canViewInternalUpdates)
                    <section class="ui-panel rounded-3xl border border-amber-200 bg-amber-50/70 p-6 shadow-sm">
                        <div class="ticket-conversation-surface">
                            <div class="ticket-chat-header">
                                <div>
                                    <p class="ticket-panel-kicker">Canal restrito</p>
                                    <h3 class="ticket-panel-title ticket-chat-title">Atualizacoes internas</h3>
                                    <p class="ticket-panel-copy">Espaco privado para operadores e gestores alinharem contexto do chamado sem expor o solicitante.</p>
                                </div>

                                <div class="ticket-chat-chip-group">
                                    <span class="portal-chip">{{ $internalMessageCount }} {{ $internalMessageLabel }}</span>
                                    @if ($latestInternalMessage?->created_at)
                                        <span class="portal-chip">Ultima atualizacao {{ $latestInternalMessage->created_at->diffForHumans() }}</span>
                                    @endif
                                </div>
                            </div>

                            <div x-ref="internalMessageList" class="ticket-chat-list">
                                @forelse ($internalMessages as $ticketMessage)
                                    @php
                                        $isOwnMessage = $ticketMessage->user_id === auth()->id();
                                        $messageAttachments = $ticketMessage->attachments ?? collect();
                                        $hasMessageText = trim((string) $ticketMessage->message) !== '';
                                        $mentionedUsers = collect($ticketMessage->mentioned_user_ids ?? [])
                                            ->map(fn ($mentionedUserId) => $internalAudienceUsersById->get($mentionedUserId))
                                            ->filter()
                                            ->values();
                                    @endphp
                                    <div class="flex {{ $isOwnMessage ? 'justify-end' : 'justify-start' }}">
                                        <article class="ticket-chat-bubble {{ $isOwnMessage ? 'ticket-chat-bubble-self' : 'ticket-chat-bubble-other' }}">
                                            <div class="ticket-chat-meta {{ $isOwnMessage ? 'ticket-chat-meta-self' : '' }}">
                                                <div class="ticket-chat-author-row">
                                                    <span class="ticket-chat-author">{{ $ticketMessage->user?->name ?? 'Sistema' }}</span>
                                                    @if ($isOwnMessage)
                                                        <span class="ticket-chat-author-pill">Voce</span>
                                                    @endif
                                                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-800">Interna</span>
                                                </div>
                                                <span class="ticket-chat-time">{{ $ticketMessage->created_at?->format('d/m/Y H:i') }}</span>
                                            </div>

                                            @if ($hasMessageText)
                                                <p class="ticket-chat-message">{{ $ticketMessage->message }}</p>
                                            @endif

                                            @if ($mentionedUsers->isNotEmpty())
                                                <div class="mt-3 flex flex-wrap gap-2">
                                                    @foreach ($mentionedUsers as $mentionedUser)
                                                        <span class="inline-flex rounded-full bg-slate-900/90 px-3 py-1 text-[11px] font-medium text-white">
                                                            {{ $mentionedUser->id === auth()->id() ? 'Voce foi marcado' : 'Marcado: '.$mentionedUser->name }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif

                                            @if ($messageAttachments->isNotEmpty())
                                                <div class="mt-3 grid gap-2">
                                                    @foreach ($messageAttachments as $attachment)
                                                        @if ($attachment->isImage())
                                                            <a href="{{ route('tickets.attachments.show', $attachment) }}" class="block overflow-hidden rounded-2xl border border-white/40 bg-white/95">
                                                                <img
                                                                    src="{{ route('tickets.attachments.inline', $attachment) }}"
                                                                    alt="{{ $attachment->original_name }}"
                                                                    class="max-h-80 w-full bg-slate-100 object-contain"
                                                                >
                                                            </a>
                                                        @elseif ($attachment->isVideo())
                                                            <div class="overflow-hidden rounded-2xl border border-white/40 bg-slate-950">
                                                                <video controls preload="metadata" class="max-h-80 w-full">
                                                                    <source src="{{ route('tickets.attachments.inline', $attachment) }}" type="{{ $attachment->mime_type }}">
                                                                </video>
                                                            </div>
                                                        @else
                                                            <a href="{{ route('tickets.attachments.show', $attachment) }}" class="flex items-center justify-between gap-3 rounded-2xl border border-white/40 bg-white/95 px-4 py-3 text-slate-700">
                                                                <div class="min-w-0">
                                                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $attachment->original_name }}</p>
                                                                    <p class="mt-1 text-xs text-slate-500">{{ $attachment->displaySize() }}</p>
                                                                </div>
                                                                <span class="shrink-0 text-sm font-medium text-sky-700">Baixar</span>
                                                            </a>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        </article>
                                    </div>
                                @empty
                                    <div class="ticket-chat-empty">
                                        <p class="text-base font-medium text-slate-900">Nenhuma atualizacao interna registrada.</p>
                                        <p class="mt-2 text-sm text-slate-500">Use este espaco para alinhar diagnostico, pedir apoio e marcar operadores ou gestores do quadro.</p>
                                    </div>
                                @endforelse
                            </div>

                            @if ($canCommentInternally)
                                <form wire:submit="sendInternalUpdate" class="ticket-chat-composer">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">Nova atualizacao interna</p>
                                        <p class="mt-1 text-sm text-slate-500">Somente operadores e gestores do quadro visualizam este bloco.</p>
                                    </div>

                                    <textarea wire:model="internalMessage" rows="4" class="ui-input ticket-chat-input w-full" placeholder="Registre uma atualizacao interna"></textarea>
                                    @error('internalMessage') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror

                                    @if ($internalMentionableUsers->isNotEmpty())
                                        <div class="rounded-2xl border border-dashed border-amber-300 bg-white/80 px-4 py-3">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">Marcar operadores ou gestores</p>
                                                <p class="mt-1 text-xs text-slate-500">Selecione quem deve receber a notificacao desta atualizacao.</p>
                                            </div>

                                            <div class="mt-3 flex flex-wrap gap-2">
                                                @foreach ($internalMentionableUsers as $mentionedUser)
                                                    <label class="cursor-pointer">
                                                        <input
                                                            type="checkbox"
                                                            value="{{ $mentionedUser->id }}"
                                                            wire:model="internalMentionedUserIds"
                                                            class="peer sr-only"
                                                        >
                                                        <span class="inline-flex rounded-full border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition peer-checked:border-slate-900 peer-checked:bg-slate-900 peer-checked:text-white">
                                                            {{ $mentionedUser->name }}
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>

                                            @error('internalMentionedUserIds') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                            @error('internalMentionedUserIds.*') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                        </div>
                                    @endif

                                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white/80 px-4 py-3">
                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">Arquivos internos</p>
                                                <p class="mt-1 text-xs text-slate-500">Ate 5 arquivos por atualizacao, com limite de 25 MB cada.</p>
                                            </div>

                                            <label class="ui-action ui-action-secondary cursor-pointer rounded-xl px-4 py-2 text-sm">
                                                Selecionar arquivos
                                                <input wire:model="internalChatFiles" type="file" multiple class="sr-only">
                                            </label>
                                        </div>

                                        <div wire:loading wire:target="internalChatFiles" class="mt-3 text-xs text-slate-500">
                                            Preparando arquivos...
                                        </div>

                                        @if (count($internalChatFiles) > 0)
                                            <div class="mt-3 grid gap-2">
                                                @foreach ($internalChatFiles as $index => $file)
                                                    <div wire:key="internal-chat-file-{{ $index }}" class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                                        <p class="min-w-0 truncate text-sm font-medium text-slate-700">{{ $file->getClientOriginalName() }}</p>
                                                        <span class="shrink-0 text-xs text-slate-500">{{ number_format(($file->getSize() ?? 0) / 1024, 1) }} KB</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        @error('internalChatFiles') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                        @error('internalChatFiles.*') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                    </div>

                                    <div class="ticket-chat-composer-footer">
                                        <p class="text-xs text-slate-500">As notificacoes vao somente para os operadores ou gestores que voce marcar.</p>

                                        <button
                                            type="submit"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="ui-loading"
                                            wire:target="sendInternalUpdate,internalChatFiles"
                                            class="ui-action ui-action-primary rounded-2xl px-5 py-3 text-sm font-medium sm:w-auto"
                                        >
                                            <span wire:loading.remove wire:target="sendInternalUpdate,internalChatFiles">Registrar atualizacao interna</span>
                                            <span wire:loading wire:target="sendInternalUpdate,internalChatFiles">Enviando...</span>
                                        </button>
                                    </div>
                                </form>
                            @else
                                <div class="ticket-chat-composer">
                                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500">
                                        Este chamado esta finalizado. As atualizacoes internas ficam bloqueadas e so voltam a aceitar novas mensagens se o chamado for reaberto.
                                    </div>
                                </div>
                            @endif
                        </div>
                    </section>
                @endif
            </div>

            <div class="grid gap-6 {{ $canViewOperationalHistory ? 'lg:grid-cols-2' : '' }}">
                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-5 shadow-sm {{ $attachmentCount === 0 ? 'ticket-attachments-panel-empty' : '' }}">
                    <div class="ticket-panel-heading ticket-panel-heading-spread">
                        <div>
                            <p class="ticket-panel-kicker">Arquivos do chamado</p>
                            <h3 class="ticket-panel-title text-[1.35rem]">Anexos</h3>
                            <p class="ticket-panel-copy">Arquivos enviados na abertura ou no chat do chamado.</p>
                        </div>

                        <span class="portal-chip">{{ $attachmentCount }} {{ $attachmentLabel }}</span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse ($visibleAttachments as $attachment)
                            <a href="{{ route('tickets.attachments.show', $attachment) }}" class="ui-row-interactive flex items-center justify-between gap-4 rounded-2xl border border-slate-200 px-4 py-3 hover:bg-slate-50">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate font-medium text-slate-900">{{ $attachment->original_name }}</p>
                                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                            {{ $attachment->message?->is_internal ? 'Interna' : ($attachment->source === 'chat' ? 'Chat' : 'Abertura') }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500">{{ $attachment->displaySize() }}</p>
                                </div>
                                <span class="text-sm text-sky-700">Baixar</span>
                            </a>
                        @empty
                            <div class="ticket-attachments-empty-state">
                                <p class="text-sm font-medium text-slate-900">Nenhum anexo neste chamado.</p>
                                <p class="mt-1 text-sm text-slate-500">Quando houver arquivos vinculados a este atendimento, eles aparecerao aqui.</p>
                            </div>
                        @endforelse
                    </div>
                </section>

                @if ($canViewOperationalHistory)
                    <section
                        class="ui-panel rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                        x-data="{ historyOpen: false }"
                    >
                        <button type="button" class="ticket-collapsible-toggle" @click="historyOpen = ! historyOpen" :aria-expanded="historyOpen.toString()">
                            <div class="ticket-panel-heading ticket-panel-heading-spread">
                                <div>
                                    <p class="ticket-panel-kicker">Auditoria</p>
                                    <h3 class="ticket-panel-title text-[1.35rem]">Historico</h3>
                                    <p class="ticket-panel-copy">Log de acoes relevantes. Fica recolhido para a tela respirar melhor.</p>
                                </div>

                                <div class="ticket-collapsible-summary">
                                    <span class="portal-chip">{{ $activityCount }} {{ $activityLabel }}</span>

                                    @if ($latestActivity)
                                        <div class="ticket-collapsible-highlight">
                                            <p class="ticket-collapsible-highlight-title">{{ $latestActivitySummary }}</p>
                                            <p class="ticket-collapsible-highlight-copy">{{ $latestActivity->created_at?->diffForHumans() }}</p>
                                        </div>
                                    @endif

                                    <span class="ticket-collapsible-icon" :class="{ 'ticket-collapsible-icon-open': historyOpen }" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" fill="none" class="size-5">
                                            <path d="M5.5 7.5 10 12l4.5-4.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" />
                                        </svg>
                                    </span>
                                </div>
                            </div>
                        </button>

                        <div x-cloak x-show="historyOpen" x-transition.opacity.duration.200ms class="mt-4 space-y-3">
                            @forelse ($activityLogs as $log)
                                <div class="ui-row-interactive rounded-2xl border border-slate-200 px-4 py-3">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="font-medium text-slate-900">{{ $log->description ?: $log->event }}</p>
                                            <p class="text-xs text-slate-500">{{ $log->causer?->name ?? 'Sistema' }}</p>
                                        </div>
                                        <span class="text-xs text-slate-500">{{ $log->created_at?->diffForHumans() }}</span>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                    Ainda nao ha itens de historico registrados.
                                </div>
                            @endforelse
                        </div>
                    </section>
                @endif
            </div>

            @if ($ticket->isClosed() && ($canCreateKnowledgeArticle || $knowledgeArticle || $helpfulKnowledgeArticles->isNotEmpty()))
                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="ticket-panel-heading">
                        <p class="ticket-panel-kicker">Base de conhecimento viva</p>
                        <h3 class="ticket-panel-title">Aproveitar a solucao deste chamado</h3>
                        <p class="ticket-panel-copy">Transforme o encerramento em artigo ou vincule um artigo util a este chamado resolvido.</p>
                    </div>

                    <div class="mt-5 space-y-4">
                        @if ($knowledgeArticle)
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                                <p class="text-sm font-semibold text-emerald-800">Este chamado ja originou um artigo</p>
                                <p class="mt-2 text-sm text-emerald-700">{{ $knowledgeArticle->title }}</p>
                                <div class="mt-4 flex flex-wrap gap-3">
                                    <a href="{{ route('knowledge-base.show', $knowledgeArticle) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm font-medium">Abrir artigo</a>
                                    @can('update', $knowledgeArticle)
                                        <a href="{{ route('knowledge-base.edit', $knowledgeArticle) }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm font-medium">Revisar artigo</a>
                                    @endcan
                                </div>
                            </div>
                        @elseif ($canCreateKnowledgeArticle)
                            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
                                <p class="text-sm font-semibold text-sky-900">Sugerir artigo a partir da solucao</p>
                                <p class="mt-2 text-sm text-sky-800">O sistema abre um formulario pre-preenchido com contexto, diagnostico e passos da resolucao.</p>
                                <div class="mt-4">
                                    <a href="{{ route('knowledge-base.from-ticket.create', $ticket) }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm font-medium">Transformar em artigo</a>
                                </div>
                            </div>
                        @endif

                        @if ($helpfulKnowledgeArticles->isNotEmpty())
                            <div class="grid gap-4 xl:grid-cols-2">
                                @foreach ($helpfulKnowledgeArticles as $helpfulArticle)
                                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-slate-900">{{ $helpfulArticle->title }}</p>
                                                <p class="mt-2 text-sm text-slate-600">{{ $helpfulArticle->summary }}</p>
                                                <p class="mt-3 text-xs text-slate-500">
                                                    {{ trans_choice('ui.helpful_vote', $helpfulArticle->helpful_feedback_count ?? 0, ['count' => $helpfulArticle->helpful_feedback_count ?? 0]) }}
                                                    - {{ trans_choice('ui.ticket_usage', $helpfulArticle->ticket_usages_count ?? 0, ['count' => $helpfulArticle->ticket_usages_count ?? 0]) }}
                                                </p>
                                            </div>
                                            <a href="{{ route('knowledge-base.show', $helpfulArticle) }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700">Abrir</a>
                                        </div>

                                        <div class="mt-4">
                                            @if (in_array($helpfulArticle->id, $knowledgeArticleIdsUsed, true))
                                                <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700">Ja vinculado a este chamado</span>
                                            @else
                                                <form method="POST" action="{{ route('knowledge-base.tickets.usage', [$helpfulArticle, $ticket]) }}">
                                                    @csrf
                                                    <button type="submit" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700">
                                                        Vincular ao chamado
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="ticket-panel-heading">
                    <p class="ticket-panel-kicker">Encerramento</p>
                    <h3 class="ticket-panel-title">Avaliacao do atendimento</h3>
                    <p class="ticket-panel-copy">Coleta simples de satisfacao apos o encerramento do chamado.</p>
                </div>

                <div class="mt-5">
                    @if ($ticket->rating)
                        <div class="space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-slate-900">Nota registrada</p>
                                    <p class="text-xs text-slate-500">
                                        Enviada por {{ $ticket->rating->user?->name ?? 'Solicitante' }}
                                        em {{ $ticket->rating->created_at?->format('d/m/Y H:i') }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-3 rounded-full bg-amber-50 px-4 py-2">
                                    <div class="flex items-center gap-1" aria-label="Nota {{ $ticket->rating->rating }} de 5">
                                        @for ($score = 1; $score <= 5; $score++)
                                            <span class="text-lg leading-none {{ $score <= $ticket->rating->rating ? 'text-amber-400' : 'text-slate-300' }}">&#9733;</span>
                                        @endfor
                                    </div>
                                    <span class="text-sm font-semibold text-amber-700">{{ $ticket->rating->rating }}/5</span>
                                </div>
                            </div>

                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Comentario</p>
                                <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $ticket->rating->comment ?: 'Nenhum comentario informado.' }}</p>
                            </div>
                        </div>
                    @elseif ($canRate)
                        <form wire:submit="submitRating" class="space-y-4">
                            <div>
                                <p class="mb-3 text-sm font-medium text-slate-900">Como voce avalia este atendimento?</p>
                                <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3">
                                    @for ($score = 1; $score <= 5; $score++)
                                        <label
                                            class="group flex cursor-pointer items-center"
                                            title="{{ $score }} de 5"
                                            aria-label="{{ $score }} de 5"
                                        >
                                            <input type="radio" wire:model.live="ratingValue" value="{{ $score }}" class="sr-only" />
                                            <span class="text-3xl leading-none transition {{ $ratingValue !== null && $score <= $ratingValue ? 'text-amber-400' : 'text-slate-300 group-hover:text-amber-300' }}">&#9733;</span>
                                        </label>
                                    @endfor

                                    <span class="ml-2 text-sm font-medium text-slate-600">
                                        {{ $ratingValue ? "{$ratingValue}/5" : 'Selecione uma nota' }}
                                    </span>
                                </div>
                                @error('ratingValue') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>

                            <label class="block text-sm text-slate-600">
                                <span class="mb-2 block font-medium">Comentario opcional</span>
                                <textarea wire:model="ratingComment" rows="4" class="ui-input w-full" placeholder="Conte como foi o atendimento, se quiser."></textarea>
                                @error('ratingComment') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>

                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="text-xs text-slate-500">A avaliacao nao podera ser editada depois de enviada.</p>
                                <button
                                    type="submit"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="ui-loading"
                                    wire:target="submitRating"
                                    class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm font-medium"
                                >
                                    <span wire:loading.remove wire:target="submitRating">Enviar avaliacao</span>
                                    <span wire:loading wire:target="submitRating">Enviando...</span>
                                </button>
                            </div>
                        </form>
                    @elseif ($ticket->isClosed())
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                            Ainda nao ha avaliacao registrada para este chamado.
                        </div>
                    @else
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                            A avaliacao sera liberada para o solicitante quando o chamado for encerrado.
                        </div>
                    @endif
                </div>
            </section>
        </div>

        <aside class="space-y-6 xl:sticky xl:top-24">
            <section
                class="ui-panel rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                wire:loading.class="ui-loading"
                wire:target="updateFixedField,updateDynamicField"
            >
                <div class="ticket-panel-heading">
                    <p class="ticket-panel-kicker">Visao rapida</p>
                    <h3 class="ticket-panel-title text-[1.45rem]">Painel do chamado</h3>
                    <p class="ticket-panel-copy">O essencial do chamado fica aqui, mais compacto e facil de bater o olho.</p>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="ticket-summary-label">Solicitante</p>
                        <p class="ticket-summary-value">{{ $ticket->requester?->name ?? 'Nao informado' }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="ticket-summary-label">Catalogo</p>
                        <p class="ticket-summary-value">{{ $ticket->catalogItem?->name ?? 'Nao vinculado' }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="ticket-summary-label">Atualizado</p>
                        <p class="ticket-summary-value">{{ $ticket->updated_at?->diffForHumans() ?? 'Agora' }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="ticket-summary-label">Criado em</p>
                        <p class="ticket-summary-value">{{ $ticket->created_at?->format('d/m/Y H:i') ?? 'Nao informado' }}</p>
                    </div>
                </div>

                @if ($canCloseOwn || $canReopenOwn)
                    <div class="mt-5 rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <p class="text-sm font-semibold text-slate-900">Acoes do solicitante</p>
                        <p class="mt-1 text-sm text-slate-500">Voce pode encerrar quando a demanda estiver resolvida ou reabrir se ainda precisar de atendimento.</p>

                        <div class="mt-3 flex flex-wrap gap-3">
                            @if ($canCloseOwn)
                                <button
                                    type="button"
                                    wire:click="closeOwnTicket"
                                    wire:confirm="Deseja finalizar este chamado?"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="ui-loading"
                                    wire:target="closeOwnTicket"
                                    class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm font-medium"
                                >
                                    <span wire:loading.remove wire:target="closeOwnTicket">Finalizar chamado</span>
                                    <span wire:loading wire:target="closeOwnTicket">Finalizando...</span>
                                </button>
                            @endif

                            @if ($canReopenOwn)
                                <button
                                    type="button"
                                    wire:click="reopenOwnTicket"
                                    wire:confirm="Deseja reabrir este chamado?"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="ui-loading"
                                    wire:target="reopenOwnTicket"
                                    class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm font-medium"
                                >
                                    <span wire:loading.remove wire:target="reopenOwnTicket">Reabrir chamado</span>
                                    <span wire:loading wire:target="reopenOwnTicket">Reabrindo...</span>
                                </button>
                            @endif
                        </div>

                        @error('ticketLifecycle') <span class="mt-3 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>
                @endif

                <div class="mt-5 space-y-3">
                    <div>
                        <p class="mb-2 text-sm font-semibold text-slate-900">{{ $canViewOperationalHistory ? 'Atualizacoes operacionais' : 'Resumo do atendimento' }}</p>
                        <p class="mb-3 text-sm text-slate-500">
                            {{ $canViewOperationalHistory ? 'Campos simples que antes estavam em cards grandes agora ficam em uma pilha mais enxuta.' : 'Acompanhe responsavel, prioridade e etapa atual do seu chamado.' }}
                        </p>
                    </div>

                    <label class="block text-sm text-slate-600">
                        <span class="mb-2 block font-medium">Responsavel</span>
                        @can('update', $ticket)
                            <select wire:change="updateFixedField('assignee_id', $event.target.value)" class="ui-native-select w-full">
                                <option value="">Nao atribuido</option>
                                @foreach ($assignees as $assignee)
                                    <option value="{{ $assignee->id }}" @selected($ticket->assignee_id === $assignee->id)>{{ $assignee->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700">{{ $ticket->assignee?->name ?? 'Nao atribuido' }}</div>
                        @endcan
                    </label>

                    <label class="block text-sm text-slate-600">
                        <span class="mb-2 block font-medium">Prioridade</span>
                        @can('update', $ticket)
                            <select wire:change="updateFixedField('priority', $event.target.value)" class="ui-native-select w-full">
                                @foreach ($priorities as $priority)
                                    <option value="{{ $priority->value }}" @selected($ticket->priority === $priority)>{{ $priority->label() }}</option>
                                @endforeach
                            </select>
                        @else
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700">{{ $ticket->priority?->label() }}</div>
                        @endcan
                    </label>

                    <label class="block text-sm text-slate-600">
                        <span class="mb-2 block font-medium">Etapa</span>
                        @can('update', $ticket)
                            <select wire:change="updateFixedField('ticket_group_id', $event.target.value)" class="ui-native-select w-full">
                                <option value="">Sem etapa</option>
                                @foreach ($groups as $group)
                                    <option value="{{ $group->id }}" @selected($ticket->ticket_group_id === $group->id)>{{ $group->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700">{{ $ticket->group?->name ?? 'Sem etapa' }}</div>
                        @endcan
                    </label>
                </div>

                @if ($canViewOperationalHistory)
                    <div class="mt-5 space-y-3">
                        <div>
                            <p class="mb-2 text-sm font-semibold text-slate-900">SLA</p>
                            <p class="mb-3 text-sm text-slate-500">Prazos em blocos menores, sem ocupar uma faixa inteira da tela.</p>
                        </div>

                        @foreach ($ticket->slaSummary() as $slaItem)
                            @php
                                $badgeClass = match ($slaItem['state']) {
                                    'breached' => 'bg-rose-100 text-rose-700',
                                    'warning' => 'bg-amber-100 text-amber-700',
                                    'na' => 'bg-slate-100 text-slate-600',
                                    default => 'bg-emerald-100 text-emerald-700',
                                };
                                $badgeLabel = match ($slaItem['state']) {
                                    'breached' => 'Estourado',
                                    'warning' => 'A vencer',
                                    'na' => 'Nao configurado',
                                    default => ($slaItem['completed_at'] ? 'Cumprido' : 'Em dia'),
                                };
                            @endphp

                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-900">{{ $slaItem['label'] }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Prazo: {{ $slaItem['due_at']?->format('d/m/Y H:i') ?? 'Nao definido' }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Concluido: {{ $slaItem['completed_at']?->format('d/m/Y H:i') ?? 'Ainda pendente' }}</p>
                                    </div>
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $badgeClass }}">{{ $badgeLabel }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            @if ($canViewOperationalHistory || $detailFields->isNotEmpty())
                <section
                    class="ui-panel rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                    x-data="{ fieldsOpen: false }"
                >
                    <button type="button" class="ticket-collapsible-toggle" @click="fieldsOpen = ! fieldsOpen" :aria-expanded="fieldsOpen.toString()">
                        <div class="ticket-panel-heading ticket-panel-heading-spread">
                            <div>
                                <p class="ticket-panel-kicker">Detalhamento</p>
                                <h3 class="ticket-panel-title text-[1.35rem]">{{ $canViewOperationalHistory ? 'Campos do chamado' : 'Informacoes da solicitacao' }}</h3>
                                <p class="ticket-panel-copy">
                                    {{ $canViewOperationalHistory ? 'Informacoes extras ficam mais escondidas e so aparecem quando voce precisar.' : 'Dados informados na abertura aparecem aqui em modo leitura.' }}
                                </p>
                            </div>

                            <div class="ticket-collapsible-summary">
                                <span class="portal-chip">{{ $detailFieldsCount }} campo(s)</span>
                                <span class="ticket-collapsible-icon" :class="{ 'ticket-collapsible-icon-open': fieldsOpen }" aria-hidden="true">
                                    <svg viewBox="0 0 20 20" fill="none" class="size-5">
                                        <path d="M5.5 7.5 10 12l4.5-4.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </button>

                    <div x-cloak x-show="fieldsOpen" x-transition.opacity.duration.200ms class="mt-4 grid gap-3">
                        @forelse ($detailFields as $field)
                            @php
                                $value = $ticket->fieldValues->firstWhere('ticket_field_id', $field->id)?->primitive_value;
                                $displayValue = $value;

                                if (in_array($field->type->value, ['select', 'status'], true)) {
                                    $displayValue = $field->options->firstWhere('value', (string) $value)?->label ?? $value;
                                } elseif ($field->type->value === 'user') {
                                    $displayValue = $sectorUsers->firstWhere('id', (int) $value)?->name ?? $value;
                                }
                            @endphp
                            <label class="block text-sm text-slate-600" wire:key="show-field-{{ $field->id }}">
                                <span class="mb-2 block font-medium">{{ $field->name }}</span>

                                @can('update', $ticket)
                                    @if (in_array($field->type->value, ['select', 'status'], true))
                                        <select wire:change="updateDynamicField({{ $field->id }}, $event.target.value)" class="ui-native-select w-full">
                                            <option value="">Selecione</option>
                                            @foreach ($field->options as $option)
                                                <option value="{{ $option->value }}" @selected((string) $value === (string) $option->value)>{{ $option->label }}</option>
                                            @endforeach
                                        </select>
                                    @elseif ($field->type->value === 'checkbox')
                                        <label class="inline-flex items-center gap-2 rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-700">
                                            <input type="checkbox" @checked((bool) $value) wire:change="updateDynamicField({{ $field->id }}, $event.target.checked)" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                                            Campo marcado
                                        </label>
                                    @elseif ($field->type->value === 'date')
                                        <input type="date" value="{{ $value }}" wire:change="updateDynamicField({{ $field->id }}, $event.target.value)" class="ui-input w-full" />
                                    @elseif ($field->type->value === 'number')
                                        <input type="number" value="{{ $value }}" wire:change="updateDynamicField({{ $field->id }}, $event.target.value)" class="ui-input w-full" />
                                    @elseif ($field->type->value === 'user')
                                        <select wire:change="updateDynamicField({{ $field->id }}, $event.target.value)" class="ui-native-select w-full">
                                            <option value="">Selecione</option>
                                            @foreach ($sectorUsers as $sectorUser)
                                                <option value="{{ $sectorUser->id }}" @selected((string) $value === (string) $sectorUser->id)>{{ $sectorUser->name }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" value="{{ $value }}" wire:change="updateDynamicField({{ $field->id }}, $event.target.value)" data-mask="auto" data-mask-label="{{ $field->name }}" data-mask-placeholder="{{ $field->placeholder }}" class="ui-input w-full" />
                                    @endif
                                @else
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700">
                                        {{ $field->type->value === 'checkbox' ? ($value ? 'Sim' : 'Nao') : ($displayValue ?: '-') }}
                                    </div>
                                @endcan

                                @if ($field->help_text)
                                    <span class="mt-1 block text-xs text-slate-500">{{ $field->help_text }}</span>
                                @endif
                            </label>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                O setor ainda nao configurou campos do chamado neste quadro.
                            </div>
                        @endforelse
                    </div>
                </section>
            @endif

            @if ($canViewTimeTracking)
                <section
                    wire:key="ticket-time-tracking-{{ $timeTrackingRenderKey }}"
                    class="ui-panel rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                    wire:poll.15s
                    x-data="Object.assign(ticketTimeTracking(@js($timeTrackingPayload)), { detailsOpen: false, timeOpen: false })"
                    x-init="init()"
                >
                    <button type="button" class="ticket-collapsible-toggle" @click="timeOpen = ! timeOpen" :aria-expanded="timeOpen.toString()">
                        <div class="ticket-panel-heading ticket-panel-heading-spread">
                            <div>
                                <p class="ticket-panel-kicker">Tempo operacional</p>
                                <h3 class="ticket-panel-title text-[1.35rem]">Controle de tempo</h3>
                                <p class="ticket-panel-copy">Sai do miolo da pagina e fica recolhido ate voce precisar abrir.</p>
                            </div>

                            <div class="ticket-collapsible-summary">
                                <span class="portal-chip" x-text="formatDuration(totalSeconds())">{{ $this->formatDuration($ticket->timeEntriesTotalSeconds()) }}</span>
                                <span class="portal-chip">{{ $timeEntries->count() }} sessao(oes)</span>
                                @if ($pendingTimeEntriesCount > 0)
                                    <span class="portal-chip !border-amber-200 !bg-amber-50 !text-amber-700">{{ $pendingTimeEntriesCount }} pendente(s)</span>
                                @endif
                                <span class="ticket-collapsible-icon" :class="{ 'ticket-collapsible-icon-open': timeOpen }" aria-hidden="true">
                                    <svg viewBox="0 0 20 20" fill="none" class="size-5">
                                        <path d="M5.5 7.5 10 12l4.5-4.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </button>

                    <div x-cloak x-show="timeOpen" x-transition.opacity.duration.200ms class="mt-4 space-y-4">
                        <div class="ticket-time-shell">
                            <div>
                                <p class="ticket-summary-label">Resumo rapido</p>
                                <p class="ticket-summary-helper">Acompanhe cronometros, sessoes manuais e distribuicao por operador.</p>
                            </div>

                            <div class="ticket-time-actions">
                                @if ($activeOwnTimeEntry)
                                    <button
                                        type="button"
                                        wire:click="stopTimeEntry({{ $activeOwnTimeEntry->id }})"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="ui-loading"
                                        wire:target="stopTimeEntry"
                                        class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm font-medium"
                                    >
                                        Parar cronometro
                                    </button>
                                @elseif ($canTrackTime)
                                    <button
                                        type="button"
                                        wire:click="startTimeEntry"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="ui-loading"
                                        wire:target="startTimeEntry"
                                        class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm font-medium"
                                        @disabled($ticket->isClosed())
                                    >
                                        Iniciar cronometro
                                    </button>
                                @endif

                                @if ($canTrackTime)
                                    <button
                                        type="button"
                                        wire:click="openTimeEntryForm"
                                        class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm font-medium"
                                    >
                                        + Adicionar sessao manualmente
                                    </button>
                                @endif
                            </div>
                        </div>

                        @error('timeTracking')
                            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                {{ $message }}
                            </div>
                        @enderror

                        <div class="ticket-time-summary-card">
                            <p class="ticket-summary-label">Total do chamado</p>
                            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <p class="ticket-time-total" x-text="formatDuration(totalSeconds())">{{ $this->formatDuration($ticket->timeEntriesTotalSeconds()) }}</p>
                                    <p class="ticket-summary-helper">{{ $timeEntries->count() }} sessao(oes) registrada(s)</p>
                                    @if ($pendingTimeEntriesCount > 0)
                                        <p class="mt-2 text-xs font-medium text-amber-700">Os apontamentos manuais pendentes ainda nao entram no total ate a revisao do gestor.</p>
                                    @endif
                                </div>

                                <button
                                    type="button"
                                    class="ticket-time-toggle"
                                    @click="detailsOpen = ! detailsOpen"
                                    :aria-expanded="detailsOpen.toString()"
                                >
                                    <span x-text="detailsOpen ? 'Ocultar detalhes' : 'Ver detalhes'">Ver detalhes</span>
                                    <span class="ticket-collapsible-icon size-10" :class="{ 'ticket-collapsible-icon-open': detailsOpen }" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" fill="none" class="size-5">
                                            <path d="M5.5 7.5 10 12l4.5-4.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" />
                                        </svg>
                                    </span>
                                </button>
                            </div>
                        </div>

                        <div x-cloak x-show="detailsOpen" x-transition.opacity.duration.200ms class="space-y-4">
                            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_300px]">
                                <div class="space-y-4">
                                    <div class="ticket-time-detail-card">
                                        <p class="ticket-summary-label">Sessao atual</p>
                                        @if ($activeOwnTimeEntry)
                                            <p
                                                class="ticket-time-detail-value"
                                                x-text="durationLabel(0, @js($activeOwnTimeEntry->started_at?->toIso8601String()))"
                                            >
                                                {{ $this->formatDuration($activeOwnTimeEntry->elapsedSeconds()) }}
                                            </p>
                                            <p class="ticket-summary-helper">Iniciada em {{ $activeOwnTimeEntry->started_at?->format('d/m/Y H:i') }}</p>
                                        @else
                                            <p class="ticket-time-detail-value">Nenhum cronometro ativo</p>
                                            <p class="ticket-summary-helper">
                                                {{ $ticket->isClosed() ? 'Chamado encerrado.' : 'Voce pode iniciar um cronometro ou lancar sessoes manuais.' }}
                                            </p>
                                        @endif
                                    </div>

                                    <div class="space-y-3">
                                        @forelse ($timeEntries as $timeEntry)
                                            @php
                                                $sourcePillColor = $timeEntry->source === \App\Enums\TicketTimeEntrySource::MANUAL ? '#0f766e' : '#1d4ed8';
                                                $approvalPill = match ($timeEntry->approval_status?->value ?? 'approved') {
                                                    'pending' => 'bg-amber-50 text-amber-700',
                                                    'rejected' => 'bg-rose-50 text-rose-700',
                                                    default => 'bg-emerald-50 text-emerald-700',
                                                };
                                            @endphp
                                            <div class="ticket-time-entry-card">
                                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                                    <div class="min-w-0 flex-1 space-y-3">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <span class="text-sm font-semibold text-slate-900">{{ $timeEntry->user?->name ?? 'Colaborador removido' }}</span>
                                                            <span class="ui-tone-chip" style="--ui-pill-color: {{ $sourcePillColor }}">
                                                                <span class="ui-tone-dot"></span>
                                                                {{ $this->sourceLabel($timeEntry->source) }}
                                                            </span>
                                                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $approvalPill }}">
                                                                {{ $this->approvalStatusLabel($timeEntry) }}
                                                            </span>
                                                            @if ($timeEntry->isRunning())
                                                                <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">Em andamento</span>
                                                            @endif
                                                        </div>

                                                        <div class="grid gap-3 text-sm text-slate-600 md:grid-cols-3">
                                                            <div class="ticket-time-meta-card">
                                                                <p class="ticket-time-meta-label">Data</p>
                                                                <p class="ticket-time-meta-value">
                                                                    {{ $timeEntry->started_at?->format('d/m/Y') ?? '-' }}
                                                                    @if ($timeEntry->ended_at && $timeEntry->started_at && ! $timeEntry->started_at->isSameDay($timeEntry->ended_at))
                                                                        ate {{ $timeEntry->ended_at->format('d/m/Y') }}
                                                                    @endif
                                                                </p>
                                                            </div>
                                                            <div class="ticket-time-meta-card">
                                                                <p class="ticket-time-meta-label">Horario</p>
                                                                <p class="ticket-time-meta-value">
                                                                    {{ $timeEntry->started_at?->format('H:i') ?? '--:--' }} -
                                                                    {{ $timeEntry->ended_at?->format('H:i') ?? 'Em andamento' }}
                                                                </p>
                                                            </div>
                                                            <div class="ticket-time-meta-card">
                                                                <p class="ticket-time-meta-label">Duracao</p>
                                                                <p
                                                                    class="ticket-time-meta-value"
                                                                    x-text="durationLabel({{ $timeEntry->isRunning() ? 0 : (int) ($timeEntry->duration_seconds ?? 0) }}, @js($timeEntry->isRunning() ? $timeEntry->started_at?->toIso8601String() : null))"
                                                                >
                                                                    {{ $this->formatDuration($timeEntry->elapsedSeconds()) }}
                                                                </p>
                                                            </div>
                                                        </div>

                                                        @if ($timeEntry->source === \App\Enums\TicketTimeEntrySource::MANUAL && ($timeEntry->reviewedBy || $timeEntry->reviewed_at || $timeEntry->review_note))
                                                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                                                <p class="font-medium text-slate-800">
                                                                    Revisao {{ $timeEntry->reviewedBy?->name ? 'por '.$timeEntry->reviewedBy->name : 'registrada' }}
                                                                    @if ($timeEntry->reviewed_at)
                                                                        em {{ $timeEntry->reviewed_at->format('d/m/Y H:i') }}
                                                                    @endif
                                                                </p>
                                                                @if ($timeEntry->review_note)
                                                                    <p class="mt-1 text-slate-500">{{ $timeEntry->review_note }}</p>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <div class="flex flex-wrap gap-2 lg:justify-end">
                                                        @if ($timeEntry->isRunning() && $this->canUpdateTimeEntry($timeEntry))
                                                            <button
                                                                type="button"
                                                                wire:click="stopTimeEntry({{ $timeEntry->id }})"
                                                                wire:loading.attr="disabled"
                                                                wire:loading.class="ui-loading"
                                                                wire:target="stopTimeEntry"
                                                                class="ui-action ui-action-primary rounded-xl px-3 py-2 text-sm"
                                                            >
                                                                Parar
                                                            </button>
                                                        @endif

                                                        @if (! $timeEntry->isRunning() && $this->canUpdateTimeEntry($timeEntry))
                                                            <button
                                                                type="button"
                                                                wire:click="editTimeEntry({{ $timeEntry->id }})"
                                                                class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm"
                                                            >
                                                                Editar
                                                            </button>
                                                        @endif

                                                        @if (! $timeEntry->isRunning() && $timeEntry->source === \App\Enums\TicketTimeEntrySource::MANUAL && ! $timeEntry->isApproved() && $this->canReviewTimeEntry($timeEntry))
                                                            <button
                                                                type="button"
                                                                wire:click="approveTimeEntry({{ $timeEntry->id }})"
                                                                class="ui-action ui-action-primary rounded-xl px-3 py-2 text-sm"
                                                            >
                                                                Aprovar
                                                            </button>
                                                            <button
                                                                type="button"
                                                                wire:click="rejectTimeEntry({{ $timeEntry->id }})"
                                                                class="ui-action ui-action-danger rounded-xl px-3 py-2 text-sm"
                                                            >
                                                                Rejeitar
                                                            </button>
                                                        @endif

                                                        @if ($this->canDeleteTimeEntry($timeEntry))
                                                            <button
                                                                type="button"
                                                                onclick="if (confirm('Deseja remover esta sessao de tempo?')) { $wire.deleteTimeEntry({{ $timeEntry->id }}) }"
                                                                class="ui-action ui-action-danger rounded-xl px-3 py-2 text-sm"
                                                            >
                                                                Excluir
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="ticket-time-empty-state">
                                                Ainda nao ha sessoes de tempo registradas neste chamado.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>

                                <aside class="space-y-4">
                                    <div class="ticket-time-side-card">
                                        <p class="ticket-summary-label">Horas por operador</p>
                                        <div class="mt-3 space-y-3">
                                            @forelse ($timeEntriesByUser as $timeEntrySummary)
                                                @php
                                                    $summaryBaseSeconds = $timeEntrySummary['active_started_at']
                                                        ? max(0, $timeEntrySummary['total_seconds'] - max(0, now()->getTimestamp() - $timeEntrySummary['active_started_at']->getTimestamp()))
                                                        : $timeEntrySummary['total_seconds'];
                                                @endphp
                                                <div class="ticket-time-tech-row">
                                                    <div class="min-w-0">
                                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $timeEntrySummary['user']?->name ?? 'Colaborador removido' }}</p>
                                                        <p class="ticket-summary-helper">
                                                            {{ $timeEntrySummary['active_started_at'] ? 'Cronometro em andamento' : 'Sem sessao ativa' }}
                                                        </p>
                                                    </div>
                                                    <span
                                                        class="text-sm font-semibold text-slate-900"
                                                        x-text="durationLabel({{ $summaryBaseSeconds }}, @js($timeEntrySummary['active_started_at']?->toIso8601String()))"
                                                    >
                                                        {{ $this->formatDuration($timeEntrySummary['total_seconds']) }}
                                                    </span>
                                                </div>
                                            @empty
                                                <div class="ticket-time-empty-state ticket-time-empty-state-soft">
                                                    Nenhum operador registrou tempo ainda.
                                                </div>
                                            @endforelse
                                        </div>
                                    </div>

                                    <div class="ticket-time-note">
                                        O solicitante nao visualiza este controle. As sessoes ficam restritas ao time operacional do setor.
                                    </div>
                                </aside>
                            </div>
                        </div>
                    </div>
                </section>
            @endif
        </aside>
    </div>

    @if ($canViewTimeTracking && $showTimeEntryForm)
        <div class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/45 px-4 py-8">
            <div class="ui-panel w-full max-w-xl rounded-[2rem] border border-slate-200 bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">
                            {{ $editingTimeEntryId ? 'Editar sessao de tempo' : 'Adicionar sessao manualmente' }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">Informe o intervalo em que voce trabalhou neste chamado.</p>
                    </div>

                    <button
                        type="button"
                        wire:click="cancelTimeEntryForm"
                        class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm"
                    >
                        Fechar
                    </button>
                </div>

                <form wire:submit="saveTimeEntry" class="mt-6 space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="text-sm text-slate-600">
                            <span class="mb-2 block font-medium">Inicio</span>
                            <input type="datetime-local" wire:model="timeEntryForm.started_at" class="ui-input w-full" />
                            @error('timeEntryForm.started_at') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="text-sm text-slate-600">
                            <span class="mb-2 block font-medium">Fim</span>
                            <input type="datetime-local" wire:model="timeEntryForm.ended_at" class="ui-input w-full" />
                            @error('timeEntryForm.ended_at') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
                        <p class="text-xs text-slate-500">Sessoes manuais aceitam intervalos atravessando a meia-noite, desde que o fim seja posterior ao inicio.</p>

                        <div class="flex flex-wrap gap-3">
                            <button
                                type="button"
                                wire:click="cancelTimeEntryForm"
                                class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm font-medium"
                            >
                                Cancelar
                            </button>

                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                wire:loading.class="ui-loading"
                                wire:target="saveTimeEntry"
                                class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm font-medium"
                            >
                                {{ $editingTimeEntryId ? 'Salvar alteracoes' : 'Registrar sessao' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

@push('scripts')
    <script>
        if (! window.ticketConversation) {
            window.ticketConversation = function (ticketId) {
                return {
                    started: false,
                    livewireId: null,
                    channelName: null,
                    refreshing: false,
                    refreshQueued: false,

                    boot() {
                        if (this.started) {
                            this.scrollToLatest();
                            return;
                        }

                        this.started = true;
                        this.livewireId = this.$root.closest('[wire\\:id]')?.getAttribute('wire:id') ?? null;
                        this.scrollToLatest();

                        if (! window.Echo) {
                            return;
                        }

                        this.channelName = `tickets.${ticketId}`;

                        window.Echo.private(this.channelName)
                            .listen('.ticket.message.created', () => {
                                this.refreshComponent();
                                window.setTimeout(() => this.scrollToLatest(), 80);
                            });
                    },

                    refreshComponent() {
                        if (! this.livewireId || ! window.Livewire) {
                            return;
                        }

                        if (this.refreshing) {
                            this.refreshQueued = true;
                            return;
                        }

                        const component = window.Livewire.find(this.livewireId);

                        if (! component) {
                            return;
                        }

                        this.refreshing = true;

                        Promise.resolve(component.$refresh())
                            .catch(() => {})
                            .finally(() => {
                                this.refreshing = false;

                                if (this.refreshQueued) {
                                    this.refreshQueued = false;
                                    this.refreshComponent();
                                }
                            });
                    },

                    scrollToLatest() {
                        this.scrollList('messageList');
                        this.scrollList('internalMessageList');
                    },

                    scrollList(refName) {
                        this.$nextTick(() => {
                            const container = this.$refs[refName];

                            if (! container) {
                                return;
                            }

                            container.scrollTop = container.scrollHeight;
                        });
                    },

                    destroy() {
                        if (! this.channelName || ! window.Echo) {
                            return;
                        }

                        window.Echo.private(this.channelName).stopListening('.ticket.message.created');
                    },
                };
            };
        }

        if (! window.ticketTimeTracking) {
            window.ticketTimeTracking = function (config) {
                return {
                    nowMs: Date.now(),
                    ticketBaseSeconds: Number(config.ticketBaseSeconds ?? 0),
                    ticketActiveStartedAts: Array.isArray(config.ticketActiveStartedAts) ? config.ticketActiveStartedAts : [],
                    intervalId: null,

                    init() {
                        if (this.intervalId) {
                            return;
                        }

                        this.nowMs = Date.now();
                        this.intervalId = window.setInterval(() => {
                            this.nowMs = Date.now();
                        }, 1000);
                    },

                    destroy() {
                        if (! this.intervalId) {
                            return;
                        }

                        window.clearInterval(this.intervalId);
                        this.intervalId = null;
                    },

                    secondsSince(startedAt) {
                        if (! startedAt) {
                            return 0;
                        }

                        const startedAtMs = Date.parse(startedAt);

                        if (Number.isNaN(startedAtMs)) {
                            return 0;
                        }

                        return Math.max(0, Math.floor((this.nowMs - startedAtMs) / 1000));
                    },

                    totalSeconds() {
                        return this.ticketBaseSeconds + this.ticketActiveStartedAts.reduce((sum, startedAt) => {
                            return sum + this.secondsSince(startedAt);
                        }, 0);
                    },

                    durationLabel(baseSeconds, activeStartedAt) {
                        return this.formatDuration(Number(baseSeconds || 0) + this.secondsSince(activeStartedAt));
                    },

                    formatDuration(totalSeconds) {
                        const normalizedSeconds = Math.max(0, Number(totalSeconds || 0));
                        const hours = Math.floor(normalizedSeconds / 3600);
                        const minutes = Math.floor((normalizedSeconds % 3600) / 60);
                        const seconds = normalizedSeconds % 60;

                        return [hours, minutes, seconds]
                            .map((part) => String(part).padStart(2, '0'))
                            .join(':');
                    },
                };
            };
        }
    </script>
@endpush
