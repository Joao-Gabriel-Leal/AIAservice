<x-layouts.portal title="Usuarios" header-variant="none">
    <div class="space-y-6">
        @php($createdUserAccess = session('created_user_access'))

        @if (is_array($createdUserAccess))
            @php($copyRows = [
                ['label' => 'E-mail', 'value' => $createdUserAccess['email'] ?? ''],
                ['label' => 'Senha', 'value' => $createdUserAccess['password'] ?? ''],
                ['label' => 'URL de Acesso', 'value' => $createdUserAccess['login_url'] ?? ''],
            ])
            @php($copyText = collect($copyRows)->map(fn (array $row) => $row['label'].': '.$row['value'])->implode(PHP_EOL))

            <section data-created-user-access class="overflow-hidden rounded-[28px] border border-sky-200 bg-sky-50/85 shadow-sm">
                <div class="flex flex-col gap-5 px-5 py-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-sky-700">Novo colaborador</p>
                        <h2 class="mt-2 text-lg font-semibold text-slate-900">
                            Dados de acesso de {{ $createdUserAccess['name'] ?? 'usuario' }} prontos para copia
                        </h2>
                        <p class="mt-1 text-sm text-slate-600">
                            Compartilhe este bloco com o colaborador. A senha temporaria precisa ser trocada no primeiro acesso.
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        <button
                            type="button"
                            data-copy-created-user-access
                            data-copy-rows='@json($copyRows)'
                            class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm"
                        >
                            Copiar tabela
                        </button>
                        <span data-copy-feedback class="text-sm font-medium text-sky-700" aria-live="polite"></span>
                    </div>
                </div>

                <div class="grid gap-4 border-t border-sky-100 bg-white/75 px-5 py-5 xl:grid-cols-[minmax(0,1.2fr)_minmax(260px,0.8fr)]">
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($copyRows as $row)
                                    <tr>
                                        <th class="w-44 bg-slate-50 px-4 py-3 text-left font-semibold text-slate-700">{{ $row['label'] }}</th>
                                        <td class="px-4 py-3 text-slate-900">
                                            @if ($row['label'] === 'URL de Acesso')
                                                <a href="{{ $row['value'] }}" target="_blank" rel="noreferrer" class="break-all font-medium text-sky-700 hover:text-sky-800">
                                                    {{ $row['value'] }}
                                                </a>
                                            @else
                                                <span class="break-all font-medium">{{ $row['value'] }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <label class="block">
                        <span class="mb-2 block text-sm font-medium text-slate-700">Campo rapido para copia manual</span>
                        <textarea
                            readonly
                            rows="5"
                            spellcheck="false"
                            data-copy-source
                            class="ui-input min-h-[152px] w-full resize-none font-mono text-sm leading-6"
                        >{{ trim($copyText) }}</textarea>
                        <span class="mt-2 block text-xs text-slate-500">Se a copia automatica nao funcionar, selecione esse texto e copie manualmente.</span>
                    </label>
                </div>
            </section>
        @endif

        <x-portal.page-intro
            variant="compact"
            eyebrow="Administracao"
            title="Usuarios"
            description="Controle perfis globais, acessos por setor e o status operacional de cada conta."
        >
            <x-slot:actions>
                <a href="{{ route('users.create') }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Novo usuario</a>
                <x-excel-export-action :href="route('users.export', request()->query())" />
            </x-slot:actions>
        </x-portal.page-intro>

        @php($hasActiveFilters = collect([
            $filters['search'] ?? '',
            $filters['global_role'] ?? '',
            $filters['status'] ?? '',
            $filters['sector_id'] ?? null,
        ])->contains(fn ($value) => filled($value)))

        <x-portal.table-search-bar
            form-id="users-filter-form"
            input-id="users-search"
            :action="route('users.index')"
            :search-value="$filters['search'] ?? ''"
            placeholder="Buscar por nome ou email"
            :clear-href="route('users.index')"
            :has-active-filters="$hasActiveFilters"
        />

        <div class="portal-table-surface">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="portal-table-head text-left text-slate-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Usuario</th>
                        <th class="px-6 py-3 font-medium">
                            <x-portal.table-column-filter label="Perfil global" form-id="users-filter-form" name="global_role">
                                @foreach ($globalRoles as $roleValue => $roleLabel)
                                    <option value="{{ $roleValue }}" @selected(($filters['global_role'] ?? '') === $roleValue)>{{ $roleLabel }}</option>
                                @endforeach
                            </x-portal.table-column-filter>
                        </th>
                        <th class="px-6 py-3 font-medium">
                            <x-portal.table-column-filter label="Acessos por setor" form-id="users-filter-form" name="sector_id" min-width="min-w-56">
                                @foreach ($sectors as $sector)
                                    <option value="{{ $sector->id }}" @selected((string) ($filters['sector_id'] ?? '') === (string) $sector->id)>{{ $sector->name }}</option>
                                @endforeach
                            </x-portal.table-column-filter>
                        </th>
                        <th class="px-6 py-3 font-medium">
                            <x-portal.table-column-filter label="Status" form-id="users-filter-form" name="status" min-width="min-w-36">
                                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Ativo</option>
                                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inativo</option>
                            </x-portal.table-column-filter>
                        </th>
                        <th class="px-6 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $listedUser)
                        <tr class="transition hover:bg-slate-50">
                            <td class="px-6 py-4">
                                <a href="{{ route('users.show', $listedUser) }}" class="flex items-center gap-3 rounded-xl outline-none transition focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2">
                                    <x-user-avatar :user="$listedUser" size="sm" class="rounded-full" />
                                    <span class="min-w-0">
                                        <span class="block truncate font-medium text-slate-900">{{ $listedUser->name }}</span>
                                        <span class="block truncate text-xs text-slate-500">{{ $listedUser->email }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="px-6 py-4 text-slate-600">{{ $listedUser->global_role->label() }}</td>
                            <td class="px-6 py-4 text-slate-600">
                                <div class="flex flex-wrap gap-2">
                                    @forelse ($listedUser->sectorAccesses as $sectorAccess)
                                        <x-sector-badge :sector="$sectorAccess->sector" mode="chip">
                                            {{ $sectorAccess->access_level->label() }}
                                        </x-sector-badge>
                                    @empty
                                        <span class="text-xs text-slate-500">Sem vinculos setoriais</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <span class="rounded-full px-3 py-1 text-xs font-medium {{ $listedUser->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                        {{ $listedUser->is_active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                    @if ($listedUser->must_change_password)
                                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700">Troca de senha pendente</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('users.edit', $listedUser) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700">Editar</a>
                                    @can('delete', $listedUser)
                                        <form method="POST" action="{{ route('users.destroy', $listedUser) }}" data-confirm data-confirm-variant="danger" data-confirm-title="Remover usuário?" data-confirm-message="Tem certeza que deseja remover este usuário? Esta ação não pode ser desfeita." data-confirm-label="Sim, remover">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-medium text-rose-600">Excluir</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-slate-500">Nenhum usuario cadastrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $users->links() }}
    </div>

    @if (is_array($createdUserAccess))
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const card = document.querySelector('[data-created-user-access]');

                    if (!card) {
                        return;
                    }

                    const button = card.querySelector('[data-copy-created-user-access]');
                    const textarea = card.querySelector('[data-copy-source]');
                    const feedback = card.querySelector('[data-copy-feedback]');

                    if (!button || !textarea || !feedback) {
                        return;
                    }

                    const rows = JSON.parse(button.dataset.copyRows || '[]');
                    let feedbackTimeout = null;

                    const escapeHtml = (value) => String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');

                    const htmlTable = `
                        <table style="border-collapse:collapse;width:100%;max-width:720px;font-family:Arial,sans-serif;font-size:14px;line-height:1.5;color:#0f172a;">
                            <tbody>
                                ${rows.map((row, index) => `
                                    <tr>
                                        <td style="padding:10px 14px;font-weight:700;background:#f8fafc;width:180px;${index < rows.length - 1 ? 'border-bottom:1px solid #e2e8f0;' : ''}">${escapeHtml(row.label)}</td>
                                        <td style="padding:10px 14px;${index < rows.length - 1 ? 'border-bottom:1px solid #e2e8f0;' : ''}">${escapeHtml(row.value)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    `.trim();

                    const setFeedback = (message) => {
                        feedback.textContent = message;

                        if (feedbackTimeout) {
                            window.clearTimeout(feedbackTimeout);
                        }

                        feedbackTimeout = window.setTimeout(() => {
                            feedback.textContent = '';
                        }, 2600);
                    };

                    const fallbackCopy = () => {
                        textarea.focus();
                        textarea.select();
                        textarea.setSelectionRange(0, textarea.value.length);

                        return document.execCommand('copy');
                    };

                    button.addEventListener('click', async () => {
                        try {
                            if (navigator.clipboard?.write && window.ClipboardItem) {
                                await navigator.clipboard.write([
                                    new ClipboardItem({
                                        'text/plain': new Blob([textarea.value], { type: 'text/plain' }),
                                        'text/html': new Blob([htmlTable], { type: 'text/html' }),
                                    }),
                                ]);
                            } else if (navigator.clipboard?.writeText) {
                                await navigator.clipboard.writeText(textarea.value);
                            } else if (!fallbackCopy()) {
                                throw new Error('clipboard-unavailable');
                            }

                            setFeedback('Tabela copiada.');
                        } catch (error) {
                            if (fallbackCopy()) {
                                setFeedback('Tabela copiada.');

                                return;
                            }

                            setFeedback('Nao foi possivel copiar automaticamente.');
                        }
                    });
                });
            </script>
        @endpush
    @endif
</x-layouts.portal>
