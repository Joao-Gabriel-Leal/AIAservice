<div class="ticket-cockpit space-y-6">
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
        $internalMentionableUsersForJs = $internalMentionableUsers
            ->map(fn ($user) => ['id' => $user->id, 'name' => $user->name])
            ->values();
        $messageTemplateForJs = fn ($template) => [
            'id' => $template->id,
            'name' => $template->name,
            'body' => $template->body,
            'isPersonal' => $template->isPersonal(),
        ];
        $publicMessageTemplatesForJs = $publicMessageTemplates
            ->map($messageTemplateForJs)
            ->values();
        $internalMessageTemplatesForJs = $internalMessageTemplates
            ->map($messageTemplateForJs)
            ->values();
        $slaItems = $canViewOperationalHistory ? collect($ticket->slaSummary()) : collect();
        $activeSlaItems = $slaItems->reject(fn ($slaItem) => $slaItem['state'] === 'na')->values();
        $primarySlaItem = $activeSlaItems->first();
        $statusLabel = $ticket->group?->name ?? $ticket->status?->name ?? 'Sem etapa';
        $statusColor = $ticket->group?->color ?: ($ticket->status?->color ?: '#2563eb');
        $priorityLabel = $ticket->priority?->label() ?? 'Sem prioridade';
        $requesterName = $ticket->requester?->name ?? 'Nao informado';
        $assigneeName = $ticket->assignee?->name ?? 'Nao atribuido';
        $isOwnRequester = $ticket->requester_id === auth()->id();
        $requesterMetaLabel = $isOwnRequester ? 'Aberto por' : 'Solicitante';
        $requesterActionsLabel = $isOwnRequester ? 'Suas acoes' : 'Acoes do solicitante';

        if ($latestActivity) {
            $latestActivitySummary = $latestActivity->description ?: $latestActivity->event;

            if (strlen($latestActivitySummary) > 56) {
                $latestActivitySummary = substr($latestActivitySummary, 0, 53).'...';
            }
        }
    @endphp

    <section class="ticket-cockpit-hero">
        <div class="ticket-cockpit-hero-main">
            <div class="ticket-cockpit-reference-row">
                <button
                    type="button"
                    class="ticket-cockpit-reference"
                    data-copy-url="{{ route('tickets.show', $ticket) }}"
                    aria-label="Copiar link do chamado {{ $ticket->publicReference() }}"
                    title="Copiar link do chamado"
                    x-data="{ copied: false, timeoutId: null, async copy() { if (! navigator.clipboard) { return; } await navigator.clipboard.writeText(this.$el.dataset.copyUrl); this.copied = true; window.clearTimeout(this.timeoutId); this.timeoutId = window.setTimeout(() => this.copied = false, 1600); } }"
                    @click="copy()"
                >
                    <span>{{ $ticket->publicReference() }}</span>
                    <flux:icon.document-duplicate x-show="!copied" variant="outline" class="ticket-cockpit-reference-icon" />
                    <flux:icon.check x-cloak x-show="copied" variant="solid" class="ticket-cockpit-reference-icon ticket-cockpit-reference-icon-success" />
                    <small x-cloak x-show="copied">Link copiado</small>
                </button>
                <span class="ticket-cockpit-status" style="--ticket-status-color: {{ $statusColor }}">
                    <span></span>
                    {{ $statusLabel }}
                </span>
                <span class="ticket-cockpit-priority">{{ $priorityLabel }}</span>
            </div>

            <div>
                <p class="ticket-cockpit-kicker">{{ $ticket->sector?->company?->name ?? 'Sem empresa' }} / {{ $ticket->sector?->name ?? 'Sem setor' }}</p>
                <h1 class="ticket-cockpit-title">{{ $ticket->title }}</h1>
                <p class="ticket-cockpit-description">{{ $ticket->description ?: 'Sem descricao adicional.' }}</p>
            </div>

            <div class="ticket-cockpit-meta-grid">
                <div>
                    <span>{{ $requesterMetaLabel }}</span>
                    <strong>{{ $requesterName }}</strong>
                </div>
                <div>
                    <span>Responsavel</span>
                    <strong>{{ $assigneeName }}</strong>
                </div>
                <div>
                    <span>Catalogo</span>
                    <strong>{{ $ticket->catalogItem?->name ?? 'Nao vinculado' }}</strong>
                </div>
                <div>
                    <span>Ultima atividade</span>
                    <strong>{{ $ticket->last_activity_at?->diffForHumans() ?? $ticket->updated_at?->diffForHumans() ?? 'Agora' }}</strong>
                </div>
            </div>
        </div>

        <aside class="ticket-cockpit-hero-side">
            <div class="ticket-cockpit-score-grid">
                <div>
                    <strong>{{ $messageCount }}</strong>
                    <span>{{ $messageLabel }}</span>
                </div>
                @if ($canViewInternalUpdates)
                    <div>
                        <strong>{{ $internalMessageCount }}</strong>
                        <span>internas</span>
                    </div>
                @endif
                <div>
                    <strong>{{ $attachmentCount }}</strong>
                    <span>{{ $attachmentLabel }}</span>
                </div>
            </div>

            @if ($primarySlaItem)
                <div class="ticket-cockpit-sla-card">
                    <span>SLA em foco</span>
                    <strong>{{ $primarySlaItem['label'] }}</strong>
                    <small>{{ $primarySlaItem['due_at']?->format('d/m/Y H:i') ?? 'Sem prazo definido' }}</small>
                </div>
            @endif
        </aside>
    </section>

    @if (session('status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm" role="status">
            {{ session('status') }}
        </div>
    @endif

    <div class="ticket-cockpit-layout">
        <div class="ticket-cockpit-feed space-y-6">
            <div
                wire:poll.5s
                class="space-y-6"
                x-data="ticketConversation({{ $ticket->id }})"
                x-init="boot()"
            >
                <section class="ticket-channel-card ticket-channel-card-public">
                    <div class="ticket-conversation-surface">
                        <div class="ticket-chat-header">
                            <div class="ticket-channel-heading">
                                <div class="ticket-channel-icon ticket-channel-icon-public">
                                    <svg viewBox="0 0 24 24" fill="none" class="size-5" aria-hidden="true">
                                        <path d="M7 10.5h10M7 14h6m-8.2 5.2 2.3-2.1H17a4 4 0 0 0 4-4V8a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v5.1a4 4 0 0 0 1.8 3.4v2.7Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="ticket-panel-kicker">Conversa</p>
                                    <h3 class="ticket-panel-title ticket-chat-title">Chat do chamado</h3>
                                </div>
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
                                <div class="ticket-chat-message-row {{ $isOwnMessage ? 'ticket-chat-message-row-self' : 'ticket-chat-message-row-other' }}">
                                    @unless ($isOwnMessage)
                                        <x-user-avatar :user="$ticketMessage->user" size="xs" class="ticket-chat-avatar" />
                                    @endunless
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
                                    @if ($isOwnMessage)
                                        <x-user-avatar :user="$ticketMessage->user" size="xs" class="ticket-chat-avatar" />
                                    @endif
                                </div>
                            @empty
                                <div class="ticket-chat-empty">
                                    <p class="text-base font-medium text-slate-900">Nenhuma mensagem por aqui ainda.</p>
                                    <p class="mt-2 text-sm text-slate-500">Quando a conversa comecar, as atualizacoes do atendimento vao aparecer aqui.</p>
                                </div>
                            @endforelse
                        </div>

                        @if ($canComment)
                            <form
                                wire:submit="sendMessage"
                                class="ticket-chat-composer"
                                x-data="ticketComposerAutocomplete({
                                    templates: @js($publicMessageTemplatesForJs),
                                    message: $wire.entangle('message').live,
                                })"
                            >
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">Responder</p>
                                </div>

                                <div class="ticket-mention-composer">
                                    <textarea
                                        x-ref="composerTextarea"
                                        x-model="message"
                                        x-on:input="handleAutocompleteInput()"
                                        x-on:click="refreshAutocompleteMenu()"
                                        x-on:keyup="if (! ['ArrowDown', 'ArrowUp', 'Enter', 'Tab', 'Escape'].includes($event.key)) refreshAutocompleteMenu()"
                                        x-on:keydown.arrow-down.prevent="moveAutocomplete(1)"
                                        x-on:keydown.arrow-up.prevent="moveAutocomplete(-1)"
                                        x-on:keydown.enter="if (autocompleteOpen) { $event.preventDefault(); selectActiveAutocompleteItem(); }"
                                        x-on:keydown.tab="if (autocompleteOpen) { $event.preventDefault(); selectActiveAutocompleteItem(); }"
                                        x-on:keydown.escape="autocompleteOpen = false"
                                        rows="4"
                                        class="ui-input ticket-chat-input w-full"
                                        placeholder="Escreva sua mensagem. Use @ para templates"
                                    ></textarea>

                                    <div
                                        x-cloak
                                        x-show="autocompleteOpen && filteredItems.length > 0"
                                        x-transition.opacity.duration.120ms
                                        class="ticket-mention-menu"
                                        role="listbox"
                                    >
                                        <template x-for="(item, index) in filteredItems" :key="item.key">
                                            <button
                                                type="button"
                                                class="ticket-mention-option"
                                                :class="{ 'ticket-mention-option-active': index === activeAutocompleteIndex }"
                                                x-on:mousedown.prevent="selectAutocompleteItem(item)"
                                                x-on:click.prevent="selectAutocompleteItem(item)"
                                                role="option"
                                                :aria-selected="(index === activeAutocompleteIndex).toString()"
                                            >
                                                <span class="ticket-mention-avatar ticket-mention-avatar-template">T</span>
                                                <span class="ticket-mention-content">
                                                    <span class="ticket-mention-title-row">
                                                        <span class="ticket-mention-name" x-text="item.name"></span>
                                                        <span class="ticket-mention-type">Template</span>
                                                    </span>
                                                    <span class="ticket-mention-preview" x-text="item.preview"></span>
                                                </span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                                @error('message') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror

                                <div class="ticket-composer-file-row">
                                    <div wire:loading wire:target="chatFiles" class="ticket-upload-inline-status">
                                        Preparando anexos...
                                    </div>

                                    @error('chatFiles') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                    @error('chatFiles.*') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror

                                    @if (count($chatFiles) > 0)
                                        <div class="ticket-upload-chip-list">
                                            @foreach ($chatFiles as $index => $file)
                                                <span wire:key="chat-file-{{ $index }}" class="ticket-upload-mini-chip" title="{{ $file->getClientOriginalName() }}">
                                                    <span>{{ $file->getClientOriginalName() }}</span>
                                                    <small>{{ number_format(($file->getSize() ?? 0) / 1024, 1) }} KB</small>
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="ticket-chat-composer-footer">
                                    <div class="ticket-composer-actions">
                                        @if ($canUseMessageTemplates)
                                            <button type="button" wire:click="openPersonalTemplateForm('public')" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm font-medium">
                                                Salvar template
                                            </button>
                                        @endif

                                        <label class="ticket-attach-button" title="Anexar arquivos" aria-label="Anexar arquivos">
                                            <svg viewBox="0 0 24 24" fill="none" class="size-5" aria-hidden="true">
                                                <path d="m21.4 11.1-8.7 8.7a5.2 5.2 0 0 1-7.4-7.4l9.4-9.4a3.5 3.5 0 0 1 5 5l-9.4 9.4a1.8 1.8 0 0 1-2.5-2.5l8.7-8.7" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" />
                                            </svg>
                                            <span class="sr-only">Anexar arquivos</span>
                                            <input wire:model="chatFiles" type="file" multiple class="sr-only">
                                        </label>

                                        <button
                                            type="submit"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="ui-loading"
                                            wire:target="sendMessage,chatFiles"
                                            class="ui-action ui-action-primary rounded-2xl px-5 py-3 text-sm font-medium sm:w-auto"
                                        >
                                            <span wire:loading.remove wire:target="sendMessage,chatFiles">Enviar</span>
                                            <span wire:loading wire:target="sendMessage,chatFiles">Enviando...</span>
                                        </button>
                                    </div>
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
                    <section class="ticket-channel-card ticket-channel-card-internal">
                        <div class="ticket-conversation-surface">
                            <div class="ticket-chat-header">
                                <div class="ticket-channel-heading">
                                    <div class="ticket-channel-icon ticket-channel-icon-internal">
                                        <svg viewBox="0 0 24 24" fill="none" class="size-5" aria-hidden="true">
                                            <path d="M8 11V8a4 4 0 1 1 8 0v3m-9.2 0h10.4A1.8 1.8 0 0 1 19 12.8v5.4a1.8 1.8 0 0 1-1.8 1.8H6.8A1.8 1.8 0 0 1 5 18.2v-5.4A1.8 1.8 0 0 1 6.8 11Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="ticket-panel-kicker">Interno</p>
                                        <h3 class="ticket-panel-title ticket-chat-title">Atualizacoes internas</h3>
                                    </div>
                                </div>

                                <div class="ticket-chat-chip-group">
                                    <span class="ticket-visibility-chip ticket-visibility-chip-internal">Restrito a equipe</span>
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
                                        $currentUserWasMentioned = $mentionedUsers->contains(fn ($mentionedUser) => $mentionedUser->id === auth()->id());
                                    @endphp
                                    <div class="ticket-chat-message-row {{ $isOwnMessage ? 'ticket-chat-message-row-self' : 'ticket-chat-message-row-other' }}">
                                        @unless ($isOwnMessage)
                                            <x-user-avatar :user="$ticketMessage->user" size="xs" class="ticket-chat-avatar" />
                                        @endunless
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

                                            @if ($currentUserWasMentioned)
                                                <div class="mt-3 flex flex-wrap gap-2">
                                                    <span class="inline-flex rounded-full bg-slate-900/90 px-3 py-1 text-[11px] font-medium text-white">
                                                        Voce foi mencionado
                                                    </span>
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
                                        @if ($isOwnMessage)
                                            <x-user-avatar :user="$ticketMessage->user" size="xs" class="ticket-chat-avatar" />
                                        @endif
                                    </div>
                                @empty
                                    <div class="ticket-chat-empty">
                                        <p class="text-base font-medium text-slate-900">Nenhuma atualizacao interna registrada.</p>
                                    </div>
                                @endforelse
                            </div>

                            @if ($canCommentInternally)
                                <form
                                    wire:submit="sendInternalUpdate"
                                    class="ticket-chat-composer"
                                    x-data="ticketComposerAutocomplete({
                                        users: @js($internalMentionableUsersForJs),
                                        templates: @js($internalMessageTemplatesForJs),
                                        message: $wire.entangle('internalMessage').live,
                                        mentionedIds: $wire.entangle('internalMentionedUserIds').live,
                                    })"
                                >
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">Nova atualizacao interna</p>
                                    </div>

                                    <div class="ticket-mention-composer">
                                        <textarea
                                            x-ref="composerTextarea"
                                            x-model="message"
                                            x-on:input="handleAutocompleteInput()"
                                            x-on:click="refreshAutocompleteMenu()"
                                            x-on:keyup="if (! ['ArrowDown', 'ArrowUp', 'Enter', 'Tab', 'Escape'].includes($event.key)) refreshAutocompleteMenu()"
                                            x-on:keydown.arrow-down.prevent="moveAutocomplete(1)"
                                            x-on:keydown.arrow-up.prevent="moveAutocomplete(-1)"
                                            x-on:keydown.enter="if (autocompleteOpen) { $event.preventDefault(); selectActiveAutocompleteItem(); }"
                                            x-on:keydown.tab="if (autocompleteOpen) { $event.preventDefault(); selectActiveAutocompleteItem(); }"
                                            x-on:keydown.escape="autocompleteOpen = false"
                                            rows="4"
                                            class="ui-input ticket-chat-input w-full"
                                            placeholder="Atualizacao interna. Use @ para pessoas e templates"
                                        ></textarea>

                                        <div
                                            x-cloak
                                            x-show="autocompleteOpen && filteredItems.length > 0"
                                            x-transition.opacity.duration.120ms
                                            class="ticket-mention-menu"
                                            role="listbox"
                                        >
                                            <template x-for="(item, index) in filteredItems" :key="item.key">
                                                <button
                                                    type="button"
                                                    class="ticket-mention-option"
                                                    :class="{ 'ticket-mention-option-active': index === activeAutocompleteIndex }"
                                                    x-on:mousedown.prevent="selectAutocompleteItem(item)"
                                                    x-on:click.prevent="selectAutocompleteItem(item)"
                                                    role="option"
                                                    :aria-selected="(index === activeAutocompleteIndex).toString()"
                                                >
                                                    <span class="ticket-mention-avatar" :class="{ 'ticket-mention-avatar-template': item.type === 'template' }" x-text="item.type === 'template' ? 'T' : initials(item.name)"></span>
                                                    <span class="ticket-mention-content">
                                                        <span class="ticket-mention-title-row">
                                                            <span class="ticket-mention-name" x-text="item.name"></span>
                                                            <span class="ticket-mention-type" x-text="item.type === 'template' ? 'Template' : 'Pessoa'"></span>
                                                        </span>
                                                        <span x-show="item.type === 'template'" class="ticket-mention-preview" x-text="item.preview"></span>
                                                    </span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                    @error('internalMessage') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror

                                    @error('internalMentionedUserIds') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror
                                    @error('internalMentionedUserIds.*') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror

                                    <div class="ticket-composer-file-row">
                                        <div wire:loading wire:target="internalChatFiles" class="ticket-upload-inline-status">
                                            Preparando anexos...
                                        </div>

                                        @error('internalChatFiles') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                        @error('internalChatFiles.*') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror

                                        @if (count($internalChatFiles) > 0)
                                            <div class="ticket-upload-chip-list">
                                                @foreach ($internalChatFiles as $index => $file)
                                                    <span wire:key="internal-chat-file-{{ $index }}" class="ticket-upload-mini-chip" title="{{ $file->getClientOriginalName() }}">
                                                        <span>{{ $file->getClientOriginalName() }}</span>
                                                        <small>{{ number_format(($file->getSize() ?? 0) / 1024, 1) }} KB</small>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    <div class="ticket-chat-composer-footer">
                                        <div class="ticket-composer-actions">
                                            @if ($canUseMessageTemplates)
                                                <button type="button" wire:click="openPersonalTemplateForm('internal')" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm font-medium">
                                                    Salvar template
                                                </button>
                                            @endif

                                            <label class="ticket-attach-button" title="Anexar arquivos" aria-label="Anexar arquivos">
                                                <svg viewBox="0 0 24 24" fill="none" class="size-5" aria-hidden="true">
                                                    <path d="m21.4 11.1-8.7 8.7a5.2 5.2 0 0 1-7.4-7.4l9.4-9.4a3.5 3.5 0 0 1 5 5l-9.4 9.4a1.8 1.8 0 0 1-2.5-2.5l8.7-8.7" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" />
                                                </svg>
                                                <span class="sr-only">Anexar arquivos</span>
                                                <input wire:model="internalChatFiles" type="file" multiple class="sr-only">
                                            </label>

                                            <button
                                                type="submit"
                                                wire:loading.attr="disabled"
                                                wire:loading.class="ui-loading"
                                                wire:target="sendInternalUpdate,internalChatFiles"
                                                class="ui-action ui-action-primary rounded-2xl px-5 py-3 text-sm font-medium sm:w-auto"
                                            >
                                                <span wire:loading.remove wire:target="sendInternalUpdate,internalChatFiles">Publicar</span>
                                                <span wire:loading wire:target="sendInternalUpdate,internalChatFiles">Enviando...</span>
                                            </button>
                                        </div>
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

        <aside class="ticket-cockpit-aside space-y-5 xl:sticky xl:top-6">
            @if ($canUseMessageTemplates)
                <section class="ticket-side-card ticket-side-card-templates">
                    <div class="ticket-panel-heading ticket-panel-heading-spread">
                        <div>
                            <p class="ticket-panel-kicker">Atalhos</p>
                            <h3 class="ticket-panel-title text-[1.2rem]">Templates pessoais</h3>
                            <p class="ticket-panel-copy">Seus textos ficam disponiveis neste quadro.</p>
                        </div>

                        <button type="button" wire:click="openPersonalTemplateForm('public')" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-xs font-medium">
                            Novo
                        </button>
                    </div>

                    <div class="mt-4 space-y-2">
                        @forelse ($personalMessageTemplates as $template)
                            <div class="ticket-personal-template-row">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $template->name }}</p>
                                    <p class="text-xs text-slate-500">{{ \App\Modules\Tickets\Models\TicketMessageTemplate::channelLabel($template->channel) }}</p>
                                </div>
                                <div class="flex shrink-0 gap-1">
                                    <button type="button" wire:click="applyMessageTemplate({{ $template->id }})" class="ticket-mini-action">Usar</button>
                                    <button type="button" wire:click="startEditingPersonalTemplate({{ $template->id }})" class="ticket-mini-action">Editar</button>
                                    <button type="button" wire:click="deletePersonalTemplate({{ $template->id }})" class="ticket-mini-action ticket-mini-action-danger">Excluir</button>
                                </div>
                            </div>
                        @empty
                            <div class="ticket-side-empty">
                                Nenhum template pessoal ainda. Salve uma resposta frequente direto do composer.
                            </div>
                        @endforelse
                    </div>
                </section>
            @endif

            @if ($canManageMajorIncident)
                <section
                    class="ui-panel rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                    wire:loading.class="ui-loading"
                    wire:target="toggleMajorIncident,linkIncidentChild,unlinkIncidentChild,sendIncidentBulkMessage,closeIncidentChildren"
                >
                    <div class="ticket-panel-heading">
                        <p class="ticket-panel-kicker">Incidente massivo</p>
                        <h3 class="ticket-panel-title text-[1.35rem]">Agrupamento operacional</h3>
                        <p class="ticket-panel-copy">Use quando varios chamados representam a mesma queda, falha ou indisponibilidade.</p>
                    </div>

                    @error('incident') <span class="mt-3 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    @error('incidentSelectedChildIds') <span class="mt-3 block text-xs text-rose-600">{{ $message }}</span> @enderror

                    @if ($incidentParent)
                        <div class="mt-4 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3">
                            <p class="text-sm font-semibold text-sky-900">Vinculado ao incidente {{ $incidentParent->publicReference() }}</p>
                            <p class="mt-1 text-sm text-sky-700">{{ $incidentParent->title }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="{{ route('tickets.show', $incidentParent) }}" class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm">
                                    Abrir incidente
                                </a>
                                <button
                                    type="button"
                                    wire:click="unlinkIncidentChild({{ $ticket->id }})"
                                    wire:confirm="Remover este chamado do incidente massivo?"
                                    class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm"
                                >
                                    Desvincular
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ $ticket->is_major_incident ? 'Este chamado e o incidente principal.' : 'Este chamado ainda nao e um incidente massivo.' }}
                                </p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ $ticket->is_major_incident ? 'Os chamados vinculados podem receber comunicados e fechamento em massa.' : 'Marque como incidente para vincular chamados repetidos.' }}
                                </p>
                            </div>

                            <button
                                type="button"
                                wire:click="toggleMajorIncident"
                                wire:confirm="{{ $ticket->is_major_incident ? 'Desmarcar e remover os vinculos deste incidente?' : 'Marcar este chamado como incidente massivo?' }}"
                                class="ui-action {{ $ticket->is_major_incident ? 'ui-action-secondary' : 'ui-action-primary' }} rounded-2xl px-4 py-3 text-sm font-medium"
                            >
                                {{ $ticket->is_major_incident ? 'Desmarcar' : 'Marcar incidente' }}
                            </button>
                        </div>

                        @if ($ticket->is_major_incident)
                            <div class="mt-4 space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-slate-900">Chamados vinculados</p>
                                    <span class="portal-chip">{{ $incidentChildren->count() }} filho(s)</span>
                                </div>

                                <div class="space-y-2">
                                    @forelse ($incidentChildren as $childTicket)
                                        @php
                                            $childClosed = $childTicket->isClosed();
                                        @endphp

                                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                            <div class="flex items-start gap-3">
                                                <input
                                                    type="checkbox"
                                                    value="{{ $childTicket->id }}"
                                                    wire:model.live="incidentSelectedChildIds"
                                                    @disabled($childClosed)
                                                    class="mt-1 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                                                />
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <a href="{{ route('tickets.show', $childTicket) }}" class="truncate text-sm font-semibold text-slate-900">
                                                            {{ $childTicket->publicReference() }} - {{ $childTicket->title }}
                                                        </a>
                                                        @if ($childClosed)
                                                            <span class="inline-flex rounded-full bg-slate-200 px-2.5 py-1 text-[11px] font-medium text-slate-600">Encerrado</span>
                                                        @endif
                                                    </div>
                                                    <p class="mt-1 text-xs text-slate-500">
                                                        {{ $childTicket->requester?->name ?? 'Solicitante nao informado' }} - {{ $childTicket->group?->name ?? 'Sem etapa' }}
                                                    </p>
                                                </div>

                                                <button
                                                    type="button"
                                                    wire:click="unlinkIncidentChild({{ $childTicket->id }})"
                                                    wire:confirm="Remover este chamado do incidente massivo?"
                                                    class="ticket-mini-action ticket-mini-action-danger"
                                                >
                                                    Remover
                                                </button>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                            Nenhum chamado vinculado ainda. Use as sugestoes abaixo para agrupar duplicados.
                                        </div>
                                    @endforelse
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <p class="text-sm font-semibold text-slate-900">Atualizacao em massa</p>
                                    <p class="mt-1 text-xs text-slate-500">A mensagem sempre entra no incidente principal e tambem nos filhos selecionados.</p>

                                    <textarea
                                        wire:model="incidentBulkMessage"
                                        rows="3"
                                        class="ui-input mt-3 w-full"
                                        placeholder="Ex.: Estamos com instabilidade no link principal e o fornecedor ja foi acionado."
                                    ></textarea>
                                    @error('incidentBulkMessage') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror

                                    <button
                                        type="button"
                                        wire:click="sendIncidentBulkMessage"
                                        wire:loading.attr="disabled"
                                        wire:target="sendIncidentBulkMessage"
                                        class="ui-action ui-action-primary mt-3 rounded-2xl px-4 py-3 text-sm font-medium"
                                    >
                                        Publicar atualizacao
                                    </button>
                                </div>

                                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                                    <p class="text-sm font-semibold text-emerald-950">Fechar selecionados</p>
                                    <p class="mt-1 text-xs text-emerald-800">Somente os filhos marcados acima serao encerrados com a mesma mensagem.</p>

                                    <textarea
                                        wire:model="incidentResolutionMessage"
                                        rows="3"
                                        class="ui-input mt-3 w-full"
                                        placeholder="Ex.: Link restabelecido e servicos normalizados."
                                    ></textarea>
                                    @error('incidentResolutionMessage') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror

                                    <button
                                        type="button"
                                        wire:click="closeIncidentChildren"
                                        wire:confirm="Fechar os chamados vinculados selecionados com esta mensagem?"
                                        wire:loading.attr="disabled"
                                        wire:target="closeIncidentChildren"
                                        class="ui-action ui-action-primary mt-3 rounded-2xl px-4 py-3 text-sm font-medium"
                                    >
                                        Fechar chamados selecionados
                                    </button>
                                </div>
                            </div>
                        @endif

                        <div class="mt-5 space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900">Sugestoes de duplicidade</p>
                                <span class="portal-chip">{{ $incidentSuggestions->count() }} sugestao(oes)</span>
                            </div>

                            @forelse ($incidentSuggestions as $suggestedTicket)
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-900">
                                                {{ $suggestedTicket->publicReference() }} - {{ $suggestedTicket->title }}
                                            </p>
                                            <p class="mt-1 text-xs text-slate-500">
                                                {{ $suggestedTicket->requester?->name ?? 'Solicitante nao informado' }} - {{ $suggestedTicket->updated_at?->diffForHumans() }}
                                            </p>
                                        </div>

                                        <button
                                            type="button"
                                            wire:click="linkIncidentChild({{ $suggestedTicket->id }})"
                                            class="ui-action ui-action-secondary shrink-0 rounded-xl px-3 py-2 text-sm"
                                        >
                                            Vincular
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                    Nenhum chamado aberto parecido encontrado neste quadro nas ultimas horas.
                                </div>
                            @endforelse
                        </div>
                    @endif
                </section>
            @endif

            <section
                class="ui-panel rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                wire:loading.class="ui-loading"
                wire:target="updateFixedField,updateDynamicField"
            >
                <div class="ticket-panel-heading">
                    <p class="ticket-panel-kicker">Detalhes do chamado</p>
                    <h3 class="ticket-panel-title text-[1.45rem]">Painel operacional</h3>
                    <p class="ticket-panel-copy">O essencial para conduzir o atendimento sem perder contexto.</p>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="ticket-summary-label">{{ $requesterMetaLabel }}</p>
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
                        <p class="text-sm font-semibold text-slate-900">{{ $requesterActionsLabel }}</p>
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

    @if ($canUseMessageTemplates && $showPersonalTemplateForm)
        <div class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/45 px-4 py-8">
            <div class="ui-panel w-full max-w-2xl rounded-[2rem] border border-slate-200 bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">
                            {{ $editingPersonalTemplateId ? 'Editar template pessoal' : 'Salvar template pessoal' }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">Respostas recorrentes deste quadro.</p>
                    </div>

                    <button
                        type="button"
                        wire:click="cancelPersonalTemplateForm"
                        class="ui-action ui-action-secondary rounded-xl px-3 py-2 text-sm"
                    >
                        Fechar
                    </button>
                </div>

                <form wire:submit="savePersonalTemplate" class="mt-6 space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="text-sm text-slate-600">
                            <span class="mb-2 block font-medium">Canal</span>
                            <select wire:model="personalTemplateForm.channel" class="ui-native-select w-full">
                                <option value="public">Chat do chamado</option>
                                <option value="internal">Atualizacao interna</option>
                            </select>
                            @error('personalTemplateForm.channel') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="text-sm text-slate-600">
                            <span class="mb-2 block font-medium">Nome do template</span>
                            <input type="text" wire:model="personalTemplateForm.name" class="ui-input w-full" placeholder="Ex: Pedir mais detalhes">
                            @error('personalTemplateForm.name') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <label class="text-sm text-slate-600">
                        <span class="mb-2 block font-medium">Texto</span>
                        <textarea wire:model="personalTemplateForm.body" rows="8" class="ui-input w-full resize-y" placeholder="Texto reutilizavel"></textarea>
                        @error('personalTemplateForm.body') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
                        <p class="text-xs text-slate-500">O texto entra no composer atual.</p>

                        <div class="flex flex-wrap gap-3">
                            <button type="button" wire:click="cancelPersonalTemplateForm" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm font-medium">
                                Cancelar
                            </button>
                            <button type="submit" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm font-medium">
                                Salvar template
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

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

        if (! window.ticketComposerAutocomplete) {
            window.ticketComposerAutocomplete = function (config) {
                return {
                    users: Array.isArray(config.users) ? config.users : [],
                    templates: Array.isArray(config.templates) ? config.templates : [],
                    message: config.message,
                    mentionedIds: config.mentionedIds ?? [],
                    tracksMentions: Object.prototype.hasOwnProperty.call(config, 'mentionedIds'),
                    autocompleteOpen: false,
                    autocompleteQuery: '',
                    activeAutocompleteIndex: 0,
                    triggerStart: null,
                    suppressNextRefresh: false,

                    init() {
                        this.syncMentionIds();

                        this.$watch('message', () => {
                            this.syncMentionIds();

                            if (this.suppressNextRefresh) {
                                this.suppressNextRefresh = false;
                                return;
                            }

                            this.refreshAutocompleteMenu();
                        });
                    },

                    get filteredItems() {
                        const query = this.normalizeText(this.autocompleteQuery);
                        const selectedIds = new Set((this.mentionedIds ?? []).map((id) => Number(id)));
                        const users = this.users
                            .filter((user) => ! selectedIds.has(Number(user.id)))
                            .filter((user) => query === '' || this.normalizeText(user.name).includes(query))
                            .map((user) => ({
                                id: Number(user.id),
                                key: `user-${user.id}`,
                                type: 'user',
                                name: user.name,
                                preview: '',
                            }));
                        const templates = this.templates
                            .filter((template) => {
                                if (query === '') {
                                    return true;
                                }

                                return this.normalizeText(`${template.name} ${template.body}`).includes(query);
                            })
                            .map((template) => ({
                                id: Number(template.id),
                                key: `template-${template.id}`,
                                type: 'template',
                                name: template.name,
                                body: template.body,
                                isPersonal: Boolean(template.isPersonal),
                                preview: this.previewText(template.body),
                            }));

                        return [...users, ...templates].slice(0, 8);
                    },

                    handleAutocompleteInput() {
                        this.syncMentionIds();
                        this.refreshAutocompleteMenu();
                    },

                    refreshAutocompleteMenu() {
                        const textarea = this.$refs.composerTextarea;

                        if (! textarea) {
                            return;
                        }

                        const caret = textarea.selectionStart ?? String(this.message ?? '').length;
                        const beforeCaret = String(this.message ?? '').slice(0, caret);
                        const atIndex = beforeCaret.lastIndexOf('@');

                        if (atIndex < 0) {
                            this.closeAutocompleteMenu();
                            return;
                        }

                        const fragment = beforeCaret.slice(atIndex + 1);

                        if (fragment.includes('\n') || fragment.length > 64) {
                            this.closeAutocompleteMenu();
                            return;
                        }

                        this.triggerStart = atIndex;
                        this.autocompleteQuery = fragment;
                        this.autocompleteOpen = this.filteredItems.length > 0;
                        this.activeAutocompleteIndex = Math.min(this.activeAutocompleteIndex, Math.max(this.filteredItems.length - 1, 0));
                    },

                    moveAutocomplete(direction) {
                        if (! this.autocompleteOpen || this.filteredItems.length === 0) {
                            return;
                        }

                        const total = this.filteredItems.length;
                        this.activeAutocompleteIndex = (this.activeAutocompleteIndex + direction + total) % total;
                    },

                    selectActiveAutocompleteItem() {
                        if (! this.autocompleteOpen || this.filteredItems.length === 0) {
                            return;
                        }

                        this.selectAutocompleteItem(this.filteredItems[this.activeAutocompleteIndex]);
                    },

                    selectAutocompleteItem(item) {
                        if (item.type === 'template') {
                            this.selectTemplate(item);
                            return;
                        }

                        this.selectUser(item);
                    },

                    selectUser(user) {
                        const textarea = this.$refs.composerTextarea;

                        if (! textarea || this.triggerStart === null) {
                            return;
                        }

                        const caret = textarea.selectionStart ?? String(this.message ?? '').length;
                        const before = String(this.message ?? '').slice(0, this.triggerStart);
                        const after = String(this.message ?? '').slice(caret);
                        const mention = `@${user.name}`;
                        const spacer = after.startsWith(' ') || after.startsWith('\n') ? '' : ' ';
                        const nextMessage = `${before}${mention}${spacer}${after}`;
                        const nextCaret = before.length + mention.length + spacer.length;

                        this.suppressNextRefresh = true;
                        this.message = nextMessage;
                        this.mentionedIds = Array.from(new Set([...(this.mentionedIds ?? []).map((id) => Number(id)), Number(user.id)]));
                        this.closeAutocompleteMenu();

                        this.$nextTick(() => {
                            textarea.focus();
                            textarea.setSelectionRange(nextCaret, nextCaret);
                        });
                    },

                    selectTemplate(template) {
                        const textarea = this.$refs.composerTextarea;

                        if (! textarea || this.triggerStart === null) {
                            return;
                        }

                        const caret = textarea.selectionStart ?? String(this.message ?? '').length;
                        const before = String(this.message ?? '').slice(0, this.triggerStart);
                        const after = String(this.message ?? '').slice(caret);
                        const replacement = String(template.body ?? '').trim();
                        const spacer = after === '' || after.startsWith(' ') || after.startsWith('\n') ? '' : ' ';
                        const nextMessage = `${before}${replacement}${spacer}${after}`;
                        const nextCaret = before.length + replacement.length + spacer.length;

                        this.suppressNextRefresh = true;
                        this.message = nextMessage;
                        this.closeAutocompleteMenu();

                        this.$nextTick(() => {
                            textarea.focus();
                            textarea.setSelectionRange(nextCaret, nextCaret);
                        });
                    },

                    syncMentionIds() {
                        if (! this.tracksMentions) {
                            return;
                        }

                        const currentIds = (this.mentionedIds ?? []).map((id) => Number(id));
                        const nextIds = currentIds.filter((id) => {
                            const user = this.users.find((candidate) => Number(candidate.id) === id);

                            return user && this.messageIncludesUser(user);
                        });

                        if (nextIds.length !== currentIds.length || nextIds.some((id, index) => id !== currentIds[index])) {
                            this.mentionedIds = nextIds;
                        }
                    },

                    messageIncludesUser(user) {
                        return String(this.message ?? '').includes(`@${user.name}`);
                    },

                    closeAutocompleteMenu() {
                        this.autocompleteOpen = false;
                        this.autocompleteQuery = '';
                        this.activeAutocompleteIndex = 0;
                        this.triggerStart = null;
                    },

                    normalizeText(value) {
                        return String(value ?? '')
                            .normalize('NFD')
                            .replace(/[\u0300-\u036f]/g, '')
                            .toLowerCase()
                            .trim();
                    },

                    previewText(value) {
                        const text = String(value ?? '').replace(/\s+/g, ' ').trim();

                        return text.length > 90 ? `${text.slice(0, 87)}...` : text;
                    },

                    initials(name) {
                        return String(name ?? '')
                            .split(/\s+/)
                            .filter(Boolean)
                            .slice(0, 2)
                            .map((part) => part.charAt(0).toUpperCase())
                            .join('');
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
