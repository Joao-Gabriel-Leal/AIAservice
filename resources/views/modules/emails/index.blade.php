<x-layouts.portal title="E-mails" subtitle="Templates operacionais e envio por tipo." header-variant="none">
    @php
        $hasActiveOldInput = old('_type') === $activeTemplate['type'];
        $subjectValue = $hasActiveOldInput ? old('subject', $activeTemplate['subject']) : $activeTemplate['subject'];
        $htmlValue = $hasActiveOldInput ? old('html_body', $activeTemplate['html_body']) : $activeTemplate['html_body'];
        $isEnabledValue = $hasActiveOldInput ? old('is_enabled') === '1' : $activeTemplate['is_enabled'];
        $enabledCount = $templates->filter(fn (array $template): bool => $template['is_enabled'])->count();
    @endphp

    <div class="space-y-5" data-active-email-type="{{ $activeType }}">
        <x-portal.page-intro
            variant="compact"
            eyebrow="Administracao"
            title="E-mails operacionais"
            description="Controle os tipos enviados por e-mail e personalize apenas o template que precisar."
        >
            <x-slot:meta>
                <span class="portal-chip">Mailer: {{ config('mail.default') }}</span>
                <span class="portal-chip">Remetente: {{ config('mail.from.address') }}</span>
            </x-slot:meta>
        </x-portal.page-intro>

        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Tipos de envio</h2>
                    <p class="text-sm text-slate-500">Resumo rapido dos e-mails operacionais configurados.</p>
                </div>
                <span class="text-sm font-semibold text-slate-600">{{ $enabledCount }}/{{ $templates->count() }} habilitados</span>
            </div>

            <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
                @foreach ($templates as $template)
                    @php($isActive = $activeType === $template['type'])

                    <a
                        href="{{ route('admin.emails.index', ['type' => $template['type']]) }}"
                        @if ($isActive) aria-current="page" @endif
                        class="rounded-lg border px-3 py-2 transition {{ $isActive ? 'border-sky-300 bg-sky-50' : 'border-slate-200 bg-slate-50 hover:border-slate-300 hover:bg-white' }}"
                    >
                        <span class="flex items-center justify-between gap-2">
                            <span class="truncate text-sm font-semibold text-slate-800">{{ $template['label'] }}</span>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $template['is_enabled'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                {{ $template['is_enabled'] ? 'On' : 'Off' }}
                            </span>
                        </span>

                        <span class="mt-1 flex items-center gap-2 text-xs text-slate-500">
                            <span class="truncate">{{ $template['description'] }}</span>
                            @if ($template['has_override'])
                                <span class="shrink-0 rounded-full bg-sky-100 px-2 py-0.5 font-semibold text-sky-700">Custom</span>
                            @endif
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

        <article
            data-email-template
            data-preview-url="{{ route('admin.emails.preview', $activeTemplate['type']) }}"
            data-test-url="{{ route('admin.emails.test', $activeTemplate['type']) }}"
            class="rounded-lg border border-slate-200 bg-white shadow-sm"
        >
            <div class="flex flex-col gap-4 border-b border-slate-200 px-4 py-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-lg font-semibold text-slate-900">{{ $activeTemplate['label'] }}</h2>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $activeTemplate['is_enabled'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                            {{ $activeTemplate['is_enabled'] ? 'Habilitado' : 'Desabilitado' }}
                        </span>
                        @if ($activeTemplate['has_override'])
                            <span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">Customizado</span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-slate-500">{{ $activeTemplate['description'] }}</p>
                    @if ($activeTemplate['updated_at'])
                        <p class="mt-2 text-xs text-slate-400">
                            Atualizado em {{ $activeTemplate['updated_at']->format('d/m/Y H:i') }}
                            @if ($activeTemplate['updated_by'])
                                por {{ $activeTemplate['updated_by']->name }}
                            @endif
                        </p>
                    @endif
                </div>

                <div class="flex flex-col gap-2 sm:min-w-72">
                    <label class="text-sm font-medium text-slate-700" for="email-template-select">Tipo de e-mail</label>
                    <select id="email-template-select" data-email-type-select class="ui-input w-full">
                        @foreach ($templates as $template)
                            <option value="{{ $template['type'] }}" @selected($template['type'] === $activeType)>
                                {{ $template['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="p-4">
                <form method="POST" action="{{ route('admin.emails.update', $activeTemplate['type']) }}" data-email-form class="space-y-5">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="_type" value="{{ $activeTemplate['type'] }}">

                    <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <input type="checkbox" name="is_enabled" value="1" @checked($isEnabledValue) class="mt-0.5 size-4 rounded border-slate-300">
                        <span>
                            <span class="block text-sm font-semibold text-slate-800">Enviar por e-mail quando esse evento acontecer</span>
                            <span class="block text-xs text-slate-500">Ao desabilitar, a notificacao no banco continua funcionando.</span>
                        </span>
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-sm font-medium text-slate-700">Assunto</span>
                        <input type="text" name="subject" value="{{ $subjectValue }}" class="ui-input w-full" maxlength="180" required>
                    </label>

                    <div>
                        <p class="mb-2 text-sm font-medium text-slate-700">Variaveis disponiveis</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($activeTemplate['variables'] as $variable => $label)
                                <span title="{{ $label }}" class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                                    {{ chr(123).chr(123).' '.$variable.' '.chr(125).chr(125) }}
                                </span>
                            @endforeach
                        </div>
                    </div>

                    <details class="rounded-lg border border-slate-200 bg-slate-50" @if ($hasActiveOldInput && $errors->any()) open @endif>
                        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-slate-800">Editar HTML avancado</summary>
                        <div class="border-t border-slate-200 p-4">
                            <textarea name="html_body" rows="12" spellcheck="false" class="ui-input min-h-72 w-full resize-y font-mono text-sm leading-6" required>{{ $htmlValue }}</textarea>
                        </div>
                    </details>

                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" class="ui-action ui-action-primary rounded-lg px-4 py-3 text-sm">Salvar</button>
                        <button type="button" data-email-preview class="ui-action ui-action-secondary rounded-lg px-4 py-3 text-sm">Preview</button>
                        <button type="button" data-email-test class="ui-action ui-action-secondary rounded-lg px-4 py-3 text-sm">Enviar teste</button>
                        <span data-email-status class="text-sm font-medium text-slate-500" aria-live="polite"></span>
                    </div>
                </form>

                <div class="mt-3 flex flex-wrap gap-3">
                    @if ($activeTemplate['has_override'])
                        <form method="POST" action="{{ route('admin.emails.restore', $activeTemplate['type']) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="ui-action ui-action-secondary rounded-lg px-4 py-3 text-sm">
                                Restaurar padrao
                            </button>
                        </form>
                    @endif
                </div>

                <section data-email-preview-panel class="mt-5 rounded-lg border border-slate-200 bg-slate-100 p-4" hidden>
                    <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm font-semibold text-slate-700">Preview</p>
                        <p data-email-preview-subject class="truncate text-xs text-slate-500">{{ $activePreview['subject'] }}</p>
                    </div>

                    <iframe
                        title="Preview {{ $activeTemplate['label'] }}"
                        data-email-preview-frame
                        sandbox
                        srcdoc="{{ $activePreview['document'] }}"
                        class="h-[520px] w-full rounded-lg border border-slate-200 bg-white"
                    ></iframe>
                </section>
            </div>
        </article>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const select = document.querySelector('[data-email-type-select]');
                const indexUrl = @js(route('admin.emails.index'));

                select?.addEventListener('change', () => {
                    const url = new URL(indexUrl, window.location.origin);
                    url.searchParams.set('type', select.value);
                    window.location.href = url.toString();
                });

                const card = document.querySelector('[data-email-template]');
                const form = card?.querySelector('[data-email-form]');
                const previewButton = card?.querySelector('[data-email-preview]');
                const testButton = card?.querySelector('[data-email-test]');
                const status = card?.querySelector('[data-email-status]');
                const frame = card?.querySelector('[data-email-preview-frame]');
                const previewPanel = card?.querySelector('[data-email-preview-panel]');
                const previewSubject = card?.querySelector('[data-email-preview-subject]');
                const token = form?.querySelector('input[name="_token"]')?.value;

                if (!card || !form || !previewButton || !testButton || !status || !frame || !previewPanel || !token) {
                    return;
                }

                const payload = () => ({
                    subject: form.querySelector('[name="subject"]').value,
                    html_body: form.querySelector('[name="html_body"]').value,
                });

                const setStatus = (message, tone = 'slate') => {
                    status.textContent = message;
                    status.className = `text-sm font-medium ${tone === 'rose' ? 'text-rose-600' : tone === 'emerald' ? 'text-emerald-700' : 'text-slate-500'}`;
                };

                const postJson = async (url) => {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                        },
                        body: JSON.stringify(payload()),
                    });

                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const message = data.message || Object.values(data.errors || {})[0]?.[0] || 'Nao foi possivel concluir a acao.';
                        throw new Error(message);
                    }

                    return data;
                };

                previewButton.addEventListener('click', async () => {
                    setStatus('Gerando preview...');

                    try {
                        const data = await postJson(card.dataset.previewUrl);
                        frame.srcdoc = data.document || data.html || '';
                        previewPanel.hidden = false;

                        if (previewSubject) {
                            previewSubject.textContent = data.subject || '';
                        }

                        setStatus('Preview atualizado.', 'emerald');
                    } catch (error) {
                        setStatus(error.message, 'rose');
                    }
                });

                testButton.addEventListener('click', async () => {
                    setStatus('Enviando teste...');

                    try {
                        const data = await postJson(card.dataset.testUrl);
                        setStatus(data.message || 'Teste enviado.', 'emerald');
                    } catch (error) {
                        setStatus(error.message, 'rose');
                    }
                });
            });
        </script>
    @endpush
</x-layouts.portal>
