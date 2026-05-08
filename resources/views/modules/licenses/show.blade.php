<x-layouts.portal :title="$license->displayName()" subtitle="Gestao simples de quem esta com cada licenca desta linha, com transferencia direta e historico abaixo." header-variant="none">
    <div class="space-y-6">
        @if ($errors->any())
            <section class="rounded-3xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                <p class="font-semibold">Nao foi possivel salvar a operacao.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <x-portal.page-intro
            variant="detail"
            :eyebrow="$license->sector?->company?->name ?? 'Linha de licenca'"
            :title="$license->displayName()"
            description="Gerencie quem esta com cada assento, acompanhe a disponibilidade da linha e execute transferencias sem sair da pagina."
        >
            <x-slot:actions>
                <a href="{{ route('licenses.index') }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Voltar para licencas</a>
                <a href="{{ route('licenses.edit', $license) }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Editar cadastro</a>
            </x-slot:actions>
            <x-slot:meta>
                <x-sector-badge :sector="$license->sector" mode="chip" />
                <span class="portal-chip">{{ $license->plan_name ?: 'Sem tipo definido' }}</span>
                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $license->status?->badgeClasses() }}">{{ $license->status?->label() }}</span>
                @if ($license->isExpired())
                    <span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-medium text-rose-700">Expirada</span>
                @elseif ($license->isExpiringSoon())
                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700">Vence em breve</span>
                @endif
            </x-slot:meta>
        </x-portal.page-intro>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="border-b border-slate-200 pb-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Capacidade e contexto</h2>
                    <p class="mt-1 text-sm text-slate-500">Resumo rapido da linha para decidir disponibilidade, transferencia e alocacao do proximo assento.</p>
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Licencas totais</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $license->seats_total }}</p>
                </article>
                <article class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Em uso</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $license->seatsInUse() }}</p>
                </article>
                <article class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Disponiveis</p>
                    <p class="mt-2 text-2xl font-semibold {{ $license->seatsAvailable() > 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $license->seatsAvailable() }}</p>
                </article>
                <article class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Setor</p>
                    <div class="mt-2">
                        <x-sector-badge :sector="$license->sector" mode="chip" />
                    </div>
                </article>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-200 pb-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Licencas atribuidas</h3>
                    <p class="mt-1 text-sm text-slate-500">Aqui voce ve quem esta com cada licenca em uso e pode transferir ou desatribuir sem abrir formularios grandes.</p>
                </div>

                @if ($license->seatsAvailable() > 0)
                    <details class="w-full rounded-2xl border border-slate-200 bg-slate-50 p-4 lg:w-[420px]" {{ $errors->has('user_id') || $errors->has('assigned_email') || $errors->has('external_reference') ? 'open' : '' }}>
                        <summary class="cursor-pointer list-none text-sm font-medium text-slate-900">
                            <span class="inline-flex rounded-xl bg-slate-900 px-4 py-2 text-white">Atribuir licenca</span>
                        </summary>

                        <form method="POST" action="{{ route('licenses.assignments.store', $license) }}" class="mt-4 grid gap-3" data-license-assignment-form>
                            @csrf

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-slate-700">Colaborador</span>
                                <select name="user_id" class="w-full rounded-xl border border-slate-300 px-4 py-3" data-license-assignment-user-select>
                                    <option value="">Sem colaborador interno</option>
                                    @foreach ($collaborators as $collaboratorOption)
                                        <option value="{{ $collaboratorOption->id }}" @selected((string) old('user_id') === (string) $collaboratorOption->id)>{{ $collaboratorOption->name }} - {{ $collaboratorOption->email }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" data-license-assignment-auto-hint hidden>
                                O sistema usa automaticamente o nome e o e-mail do colaborador selecionado.
                            </div>

                            <div class="grid gap-3" data-license-assignment-manual-fields>
                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-slate-700">Email</span>
                                    <input type="email" name="assigned_email" value="{{ old('assigned_email') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3" placeholder="usuario@empresa.com">
                                </label>

                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-slate-700">Referencia</span>
                                    <input type="text" name="external_reference" value="{{ old('external_reference') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3" placeholder="Codigo SAP, tenant ou id externo">
                                </label>
                            </div>

                            <p class="text-xs text-slate-500">
                                Para conta externa, deixe "Sem colaborador interno" e preencha os campos manuais.
                            </p>

                            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Salvar atribuicao</button>
                        </form>
                    </details>
                @else
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Todas as licencas desta linha ja estao em uso. Desatribua ou transfira antes de criar uma nova atribuicao.
                    </div>
                @endif
            </div>

            <div class="overflow-x-auto pt-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="text-left text-slate-500">
                        <tr>
                            <th class="pb-3 pr-4 font-medium">Colaborador/Conta</th>
                            <th class="pb-3 pr-4 font-medium">Email</th>
                            <th class="pb-3 pr-4 font-medium">Referencia</th>
                            <th class="pb-3 text-right font-medium">Acoes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($assignments as $assignment)
                            <tr>
                                <td class="py-4 pr-4">
                                    <p class="font-medium text-slate-900">{{ $assignment->resolvedDisplayName() }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $assignment->user ? 'Colaborador interno' : 'Conta externa' }}
                                        @if ($assignment->assigned_at)
                                            - em uso desde {{ $assignment->assigned_at->format('d/m/Y H:i') }}
                                        @endif
                                    </p>
                                </td>
                                <td class="py-4 pr-4 text-slate-600">
                                    {{ $assignment->resolvedAssignedEmail() ?: 'Sem email' }}
                                </td>
                                <td class="py-4 pr-4 text-slate-600">
                                    {{ $assignment->external_reference ?: '-' }}
                                </td>
                                <td class="py-4 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <details class="inline-block text-left">
                                            <summary class="cursor-pointer rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700">
                                                Transferir
                                            </summary>

                                            <div class="mt-3 w-[320px] rounded-2xl border border-slate-200 bg-white p-4 shadow-lg">
                                                <form method="POST" action="{{ route('licenses.assignments.transfer', [$license, $assignment]) }}" class="grid gap-3">
                                                    @csrf

                                                    <label class="block">
                                                        <span class="mb-2 block text-sm font-medium text-slate-700">Novo colaborador</span>
                                                        <select name="user_id" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                                                            <option value="">Transferir sem colaborador interno</option>
                                                            @foreach ($collaborators as $collaboratorOption)
                                                                <option value="{{ $collaboratorOption->id }}">{{ $collaboratorOption->name }} - {{ $collaboratorOption->email }}</option>
                                                            @endforeach
                                                        </select>
                                                    </label>

                                                    <label class="block">
                                                        <span class="mb-2 block text-sm font-medium text-slate-700">Novo email</span>
                                                        <input type="email" name="assigned_email" value="{{ old('assigned_email') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3" placeholder="{{ $assignment->resolvedAssignedEmail() ?: 'usuario@empresa.com' }}">
                                                        <span class="mt-2 block text-xs text-slate-500">Se voce escolher um colaborador e deixar este campo em branco, o sistema usa o email atual dele.</span>
                                                    </label>

                                                    <label class="block">
                                                        <span class="mb-2 block text-sm font-medium text-slate-700">Referencia</span>
                                                        <input type="text" name="external_reference" value="{{ old('external_reference', $assignment->external_reference) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3" placeholder="Codigo SAP, tenant ou id externo">
                                                    </label>

                                                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Salvar transferencia</button>
                                                </form>
                                            </div>
                                        </details>

                                        <form method="POST" action="{{ route('licenses.assignments.release', [$license, $assignment]) }}">
                                            @csrf
                                            <button type="submit" class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-medium text-rose-600">Desatribuir licenca</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-10 text-center text-sm text-slate-500">
                                    Nenhuma licenca atribuida agora.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="border-b border-slate-200 pb-4">
                <h3 class="text-lg font-semibold text-slate-900">Detalhes da licenca</h3>
                <p class="mt-1 text-sm text-slate-500">Essas informacoes ficam aqui quando voce precisar olhar compra, renovacao, expiracao ou contexto da linha.</p>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Referencia</p>
                    <p class="mt-2 font-medium text-slate-900">{{ $license->license_reference ?: 'Nao informada' }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Fornecedor da compra</p>
                    <p class="mt-2 font-medium text-slate-900">{{ $license->supplier_name ?: 'Nao informado' }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Renovacao</p>
                    <p class="mt-2 font-medium text-slate-900">{{ $license->renewal_date?->format('d/m/Y') ?? 'Nao informada' }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Expiracao</p>
                    <p class="mt-2 font-medium text-slate-900">{{ $license->expires_at?->format('d/m/Y') ?? 'Nao informada' }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Custo</p>
                    <p class="mt-2 font-medium text-slate-900">
                        @if ($license->cost_amount !== null)
                            {{ $license->cost_currency ?: 'BRL' }} {{ number_format((float) $license->cost_amount, 2, ',', '.') }}
                        @else
                            Nao informado
                        @endif
                    </p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Compra</p>
                    <p class="mt-2 font-medium text-slate-900">{{ $license->purchased_at?->format('d/m/Y') ?? 'Nao informada' }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Criado por</p>
                    <p class="mt-2 font-medium text-slate-900">{{ $license->creator?->name ?? 'Sistema' }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Renovacao automatica</p>
                    <p class="mt-2 font-medium text-slate-900">{{ $license->auto_renew ? 'Sim' : 'Nao' }}</p>
                </article>
            </div>

            <article class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-sm text-slate-500">Observacoes</p>
                <p class="mt-2 text-sm text-slate-700">{{ $license->notes ?: 'Sem observacoes registradas.' }}</p>
            </article>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="border-b border-slate-200 pb-4">
                <h3 class="text-lg font-semibold text-slate-900">Historico</h3>
                <p class="mt-1 text-sm text-slate-500">Acompanhe atribuicoes, transferencias e desatribuicoes desta linha.</p>
            </div>

            <div class="space-y-4 pt-6">
                @forelse ($activityLogs as $activityLog)
                    @php($fromLabel = data_get($activityLog->properties, 'from.display_name') ?: data_get($activityLog->properties, 'from.assigned_email'))
                    @php($toLabel = data_get($activityLog->properties, 'to.display_name') ?: data_get($activityLog->properties, 'to.assigned_email'))
                    @php($assignmentLabel = data_get($activityLog->properties, 'assignment.display_name') ?: data_get($activityLog->properties, 'assignment.assigned_email'))
                    @php($reference = data_get($activityLog->properties, 'assignment.external_reference') ?: data_get($activityLog->properties, 'to.external_reference'))

                    <article class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="font-medium text-slate-900">{{ $activityLog->description ?: 'Evento registrado.' }}</p>

                                @if ($activityLog->event === 'license.assignment.transferred' && ($fromLabel || $toLabel))
                                    <p class="mt-1 text-sm text-slate-500">{{ $fromLabel ?: 'Sem identificacao' }} -> {{ $toLabel ?: 'Sem identificacao' }}</p>
                                @elseif ($assignmentLabel)
                                    <p class="mt-1 text-sm text-slate-500">{{ $assignmentLabel }}</p>
                                @endif

                                @if ($reference)
                                    <p class="mt-1 text-xs font-medium text-sky-700">Ref. {{ $reference }}</p>
                                @endif
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                                <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Responsavel</p>
                                <p class="mt-1 font-medium text-slate-900">{{ $activityLog->causer?->name ?? 'Sistema' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $activityLog->created_at?->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
                        Nenhum evento registrado ainda.
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    @push('scripts')
        <script>
            (() => {
                const initializeLicenseAssignmentForms = () => {
                    document.querySelectorAll('[data-license-assignment-form]').forEach((form) => {
                        if (form.dataset.licenseAssignmentBound === 'true') {
                            return;
                        }

                        const collaboratorSelect = form.querySelector('[data-license-assignment-user-select]');
                        const autoHint = form.querySelector('[data-license-assignment-auto-hint]');
                        const manualFields = form.querySelector('[data-license-assignment-manual-fields]');

                        if (!collaboratorSelect || !autoHint || !manualFields) {
                            return;
                        }

                        const manualInputs = manualFields.querySelectorAll('input, select, textarea');

                        const syncForm = () => {
                            const hasInternalCollaborator = collaboratorSelect.value !== '';

                            autoHint.hidden = !hasInternalCollaborator;
                            manualFields.hidden = hasInternalCollaborator;

                            manualInputs.forEach((input) => {
                                input.disabled = hasInternalCollaborator;
                            });
                        };

                        collaboratorSelect.addEventListener('change', syncForm);
                        form.dataset.licenseAssignmentBound = 'true';
                        syncForm();
                    });
                };

                document.addEventListener('DOMContentLoaded', initializeLicenseAssignmentForms);
            })();
        </script>
    @endpush
</x-layouts.portal>
