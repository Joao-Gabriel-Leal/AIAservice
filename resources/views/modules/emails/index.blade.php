<x-layouts.portal title="E-mails" subtitle="Templates operacionais e envio por tipo." header-variant="none">
    @php($activeType = old('_type', request()->string('type')->toString()))

    <div class="space-y-6">
        <x-portal.page-intro
            variant="compact"
            eyebrow="Administracao"
            title="E-mails operacionais"
            description="Controle os tipos enviados por e-mail e personalize o HTML usado nas mensagens."
        >
            <x-slot:meta>
                <span class="portal-chip">Mailer: {{ config('mail.default') }}</span>
                <span class="portal-chip">Remetente: {{ config('mail.from.address') }}</span>
            </x-slot:meta>
        </x-portal.page-intro>

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="space-y-5">
            @foreach ($templates as $template)
                @php($isActive = $activeType === $template['type'])
                @php($subjectValue = $isActive ? old('subject', $template['subject']) : $template['subject'])
                @php($htmlValue = $isActive ? old('html_body', $template['html_body']) : $template['html_body'])

                <article
                    id="email-template-{{ $template['type'] }}"
                    data-email-template
                    data-preview-url="{{ route('admin.emails.preview', $template['type']) }}"
                    data-test-url="{{ route('admin.emails.test', $template['type']) }}"
                    class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"
                >
                    <div class="flex flex-col gap-4 border-b border-slate-200 bg-slate-50 px-5 py-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-semibold text-slate-900">{{ $template['label'] }}</h2>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $template['is_enabled'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $template['is_enabled'] ? 'Habilitado' : 'Desabilitado' }}
                                </span>
                                @if ($template['has_override'])
                                    <span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">Customizado</span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-slate-500">{{ $template['description'] }}</p>
                            @if ($template['updated_at'])
                                <p class="mt-2 text-xs text-slate-400">
                                    Atualizado em {{ $template['updated_at']->format('d/m/Y H:i') }}
                                    @if ($template['updated_by'])
                                        por {{ $template['updated_by']->name }}
                                    @endif
                                </p>
                            @endif
                        </div>

                        @if ($template['has_override'])
                            <form method="POST" action="{{ route('admin.emails.restore', $template['type']) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">
                                    Restaurar padrao
                                </button>
                            </form>
                        @endif
                    </div>

                    <div class="grid gap-0 xl:grid-cols-[minmax(0,1fr)_minmax(360px,0.75fr)]">
                        <form method="POST" action="{{ route('admin.emails.update', $template['type']) }}" data-email-form class="space-y-5 border-b border-slate-200 p-5 xl:border-b-0 xl:border-r">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="_type" value="{{ $template['type'] }}">

                            <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <input type="checkbox" name="is_enabled" value="1" @checked($template['is_enabled']) class="size-4 rounded border-slate-300">
                                <span class="text-sm font-medium text-slate-700">Enviar por e-mail quando esse evento acontecer</span>
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-slate-700">Assunto</span>
                                <input type="text" name="subject" value="{{ $subjectValue }}" class="ui-input w-full" maxlength="180" required>
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-slate-700">HTML</span>
                                <textarea name="html_body" rows="16" spellcheck="false" class="ui-input min-h-[380px] w-full resize-y font-mono text-sm leading-6" required>{{ $htmlValue }}</textarea>
                            </label>

                            <div>
                                <p class="mb-2 text-sm font-medium text-slate-700">Variaveis disponiveis</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($template['variables'] as $variable => $label)
                                        <span title="{{ $label }}" class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                                            {{ chr(123).chr(123).' '.$variable.' '.chr(125).chr(125) }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <button type="submit" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Salvar</button>
                                <button type="button" data-email-preview class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Preview</button>
                                <button type="button" data-email-test class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Enviar teste</button>
                                <span data-email-status class="text-sm font-medium text-slate-500" aria-live="polite"></span>
                            </div>
                        </form>

                        <section class="bg-slate-100 p-5">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold text-slate-700">Preview</p>
                                <p data-email-preview-subject class="truncate text-xs text-slate-500">{{ $template['preview']['subject'] }}</p>
                            </div>

                            <iframe
                                title="Preview {{ $template['label'] }}"
                                data-email-preview-frame
                                sandbox
                                srcdoc="{{ $template['preview']['document'] }}"
                                class="h-[620px] w-full rounded-2xl border border-slate-200 bg-white"
                            ></iframe>
                        </section>
                    </div>
                </article>
            @endforeach
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('[data-email-template]').forEach((card) => {
                    const form = card.querySelector('[data-email-form]');
                    const previewButton = card.querySelector('[data-email-preview]');
                    const testButton = card.querySelector('[data-email-test]');
                    const status = card.querySelector('[data-email-status]');
                    const frame = card.querySelector('[data-email-preview-frame]');
                    const previewSubject = card.querySelector('[data-email-preview-subject]');
                    const token = form?.querySelector('input[name="_token"]')?.value;

                    if (!form || !previewButton || !testButton || !status || !frame || !token) {
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
            });
        </script>
    @endpush
</x-layouts.portal>
