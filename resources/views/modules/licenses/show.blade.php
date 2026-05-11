@php
    $seatsInUse = $license->seatsInUse();
    $seatsAvailable = $license->seatsAvailable();
    $seatsTotal = max(0, (int) $license->seats_total);
    $usagePercent = $seatsTotal > 0 ? min(100, (int) round(($seatsInUse / $seatsTotal) * 100)) : 0;
    $initialPanel = old('license_panel');

    if (! $initialPanel && $errors->any() && $seatsAvailable > 0) {
        $initialPanel = 'assign';
    }

    $latestActivityLog = $activityLogs->first();
    $costLabel = $license->cost_amount !== null
        ? ($license->cost_currency ?: 'BRL').' '.number_format((float) $license->cost_amount, 2, ',', '.')
        : null;
    $adminFacts = collect([
        ['label' => 'Referencia', 'value' => $license->license_reference],
        ['label' => 'Fornecedor da compra', 'value' => $license->supplier_name],
        ['label' => 'Renovacao', 'value' => $license->renewal_date?->format('d/m/Y')],
        ['label' => 'Expiracao', 'value' => $license->expires_at?->format('d/m/Y')],
        ['label' => 'Custo', 'value' => $costLabel],
        ['label' => 'Compra', 'value' => $license->purchased_at?->format('d/m/Y')],
        ['label' => 'Criado por', 'value' => $license->creator?->name ?? 'Sistema'],
        ['label' => 'Renovacao automatica', 'value' => $license->auto_renew ? 'Sim' : null],
    ])->filter(fn ($fact) => filled($fact['value']))->values();
@endphp

<x-layouts.portal :title="$license->displayName()" subtitle="Gestao das licencas desta linha." header-variant="none">
    <div
        x-data="{
            panel: @js($initialPanel),
            open(name) { this.panel = name },
            close() { this.panel = null },
            isOpen(name) { return this.panel === name },
        }"
        x-on:keydown.escape.window="close()"
        class="space-y-5"
    >
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

        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Licenca</p>
                    <h1 class="mt-2 text-2xl font-semibold leading-tight text-slate-950 lg:text-3xl">{{ $license->displayName() }}</h1>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <x-sector-badge :sector="$license->sector" mode="chip" />

                        @if ($license->plan_name)
                            <span class="portal-chip">{{ $license->plan_name }}</span>
                        @endif

                        <span class="rounded-full px-3 py-1 text-xs font-medium {{ $license->status?->badgeClasses() }}">{{ $license->status?->label() }}</span>

                        @if ($license->isExpired())
                            <span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-medium text-rose-700">Expirada</span>
                        @elseif ($license->isExpiringSoon())
                            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700">Vence em breve</span>
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('licenses.index') }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Voltar</a>
                    <a href="{{ route('licenses.edit', $license) }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Editar cadastro</a>

                    @if ($seatsAvailable > 0)
                        <button type="button" x-on:click="open('assign')" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">
                            Atribuir licenca
                        </button>
                    @else
                        <button type="button" class="ui-action rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800" disabled>
                            Sem licencas livres
                        </button>
                    @endif
                </div>
            </div>
        </section>

        <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid gap-4 lg:grid-cols-[190px_190px_minmax(0,1fr)] lg:items-center">
                <div>
                    <p class="text-sm font-medium text-slate-500">Disponiveis</p>
                    <p class="mt-1 text-3xl font-semibold {{ $seatsAvailable > 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $seatsAvailable }}</p>
                </div>

                <div>
                    <p class="text-sm font-medium text-slate-500">Em uso</p>
                    <p class="mt-1 text-3xl font-semibold text-slate-950">{{ $seatsInUse }} <span class="text-base font-medium text-slate-500">/ {{ $seatsTotal }}</span></p>
                </div>

                <div>
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="font-medium text-slate-700">Ocupacao</span>
                        <span class="text-slate-500">{{ $usagePercent }}%</span>
                    </div>
                    <div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full {{ $seatsAvailable > 0 ? 'bg-emerald-500' : 'bg-rose-500' }}" style="width: {{ $usagePercent }}%"></div>
                    </div>
                    @if ($seatsAvailable <= 0)
                        <p class="mt-3 text-sm text-amber-700">Todas as licencas desta linha estao em uso. Transfira ou libere uma licenca antes de atribuir outra.</p>
                    @endif
                </div>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
            <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 border-b border-slate-200 pb-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Licencas em uso</h2>
                        <p class="mt-1 text-sm text-slate-500">Quem esta usando esta licenca agora.</p>
                    </div>

                    @if ($seatsAvailable > 0)
                        <button type="button" x-on:click="open('assign')" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">
                            Atribuir licenca
                        </button>
                    @endif
                </div>

                @if ($assignments->isNotEmpty())
                    <div class="hidden pt-4 lg:block">
                        <div class="overflow-hidden rounded-2xl border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-left text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3 font-medium">Colaborador</th>
                                        <th class="px-4 py-3 font-medium">Email / referencia</th>
                                        <th class="px-4 py-3 font-medium">Atribuida em</th>
                                        <th class="px-4 py-3 text-right font-medium">Acoes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @foreach ($assignments as $assignment)
                                        <tr>
                                            <td class="px-4 py-4">
                                                <p class="font-medium text-slate-950">{{ $assignment->resolvedDisplayName() }}</p>
                                                <p class="mt-1 text-xs text-slate-500">{{ $assignment->user ? 'Usuario interno' : 'Registro legado sem usuario interno' }}</p>
                                            </td>
                                            <td class="px-4 py-4 text-slate-600">
                                                <p>{{ $assignment->resolvedAssignedEmail() ?: 'Sem email' }}</p>
                                                @if ($assignment->external_reference)
                                                    <p class="mt-1 text-xs font-medium text-sky-700">Ref. {{ $assignment->external_reference }}</p>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 text-slate-600">
                                                {{ $assignment->assigned_at?->format('d/m/Y H:i') ?? '-' }}
                                            </td>
                                            <td class="px-4 py-4 text-right">
                                                <div class="flex flex-wrap justify-end gap-2">
                                                    <button type="button" x-on:click="open('transfer-{{ $assignment->id }}')" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">
                                                        Transferir
                                                    </button>
                                                    <button type="button" x-on:click="open('release-{{ $assignment->id }}')" class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-medium text-rose-700 transition hover:border-rose-300 hover:bg-rose-50">
                                                        Liberar licenca
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="space-y-3 pt-4 lg:hidden">
                        @foreach ($assignments as $assignment)
                            <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-950">{{ $assignment->resolvedDisplayName() }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $assignment->user ? 'Usuario interno' : 'Registro legado sem usuario interno' }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-500">
                                        {{ $assignment->assigned_at?->format('d/m') ?? '--' }}
                                    </span>
                                </div>

                                <div class="mt-3 space-y-1 text-sm text-slate-600">
                                    <p>{{ $assignment->resolvedAssignedEmail() ?: 'Sem email' }}</p>
                                    @if ($assignment->external_reference)
                                        <p class="text-xs font-medium text-sky-700">Ref. {{ $assignment->external_reference }}</p>
                                    @endif
                                </div>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    <button type="button" x-on:click="open('transfer-{{ $assignment->id }}')" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700">
                                        Transferir
                                    </button>
                                    <button type="button" x-on:click="open('release-{{ $assignment->id }}')" class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-medium text-rose-700">
                                        Liberar licenca
                                    </button>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="pt-4">
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                            <p class="text-sm font-medium text-slate-700">Nenhuma licenca em uso agora.</p>
                            <p class="mt-1 text-sm text-slate-500">Atribua a primeira pessoa quando esta licenca entrar em operacao.</p>

                            @if ($seatsAvailable > 0)
                                <button type="button" x-on:click="open('assign')" class="ui-action ui-action-primary mt-4 rounded-2xl px-4 py-3 text-sm">
                                    Atribuir licenca
                                </button>
                            @endif
                        </div>
                    </div>
                @endif
            </section>

            <aside class="space-y-5">
                <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Resumo</h2>

                    <div class="mt-4 space-y-4 text-sm">
                        @if ($license->license_reference)
                            <div>
                                <p class="text-slate-500">Referencia</p>
                                <p class="mt-1 font-medium text-slate-900">{{ $license->license_reference }}</p>
                            </div>
                        @endif

                        @if ($license->renewal_date || $license->expires_at)
                            <div>
                                <p class="text-slate-500">{{ $license->expires_at ? 'Expiracao' : 'Renovacao' }}</p>
                                <p class="mt-1 font-medium text-slate-900">{{ ($license->expires_at ?? $license->renewal_date)?->format('d/m/Y') }}</p>
                            </div>
                        @endif

                        @if ($license->supplier_name)
                            <div>
                                <p class="text-slate-500">Fornecedor</p>
                                <p class="mt-1 font-medium text-slate-900">{{ $license->supplier_name }}</p>
                            </div>
                        @endif

                        @if (! $license->license_reference && ! $license->renewal_date && ! $license->expires_at && ! $license->supplier_name)
                            <p class="text-sm text-slate-500">Sem dados administrativos essenciais cadastrados.</p>
                        @endif
                    </div>
                </section>

                <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Ultimo evento</h2>

                    @if ($latestActivityLog)
                        @php
                            $latestFromLabel = data_get($latestActivityLog->properties, 'from.display_name') ?: data_get($latestActivityLog->properties, 'from.assigned_email');
                            $latestToLabel = data_get($latestActivityLog->properties, 'to.display_name') ?: data_get($latestActivityLog->properties, 'to.assigned_email');
                            $latestAssignmentLabel = data_get($latestActivityLog->properties, 'assignment.display_name') ?: data_get($latestActivityLog->properties, 'assignment.assigned_email');
                        @endphp

                        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                            <p class="font-medium text-slate-900">{{ $latestActivityLog->description ?: 'Evento registrado.' }}</p>

                            @if ($latestActivityLog->event === 'license.assignment.transferred' && ($latestFromLabel || $latestToLabel))
                                <p class="mt-1 text-slate-500">{{ $latestFromLabel ?: 'Sem identificacao' }} -> {{ $latestToLabel ?: 'Sem identificacao' }}</p>
                            @elseif ($latestAssignmentLabel)
                                <p class="mt-1 text-slate-500">{{ $latestAssignmentLabel }}</p>
                            @endif

                            <p class="mt-3 text-xs text-slate-500">{{ $latestActivityLog->created_at?->format('d/m/Y H:i') }} por {{ $latestActivityLog->causer?->name ?? 'Sistema' }}</p>
                        </div>
                    @else
                        <p class="mt-4 text-sm text-slate-500">Nenhum evento registrado ainda.</p>
                    @endif
                </section>
            </aside>
        </div>

        <details class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
            <summary class="cursor-pointer list-none text-lg font-semibold text-slate-950">
                Dados administrativos
                <span class="ml-2 text-sm font-medium text-slate-500">Compra, datas e observacoes</span>
            </summary>

            <div class="mt-5 border-t border-slate-200 pt-5">
                @if ($adminFacts->isNotEmpty())
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        @foreach ($adminFacts as $fact)
                            <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-sm text-slate-500">{{ $fact['label'] }}</p>
                                <p class="mt-2 font-medium text-slate-900">{{ $fact['value'] }}</p>
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-slate-500">Sem dados administrativos complementares.</p>
                @endif

                @if ($license->notes)
                    <article class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-sm text-slate-500">Observacoes</p>
                        <p class="mt-2 text-sm text-slate-700">{{ $license->notes }}</p>
                    </article>
                @endif
            </div>
        </details>

        <details class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
            <summary class="cursor-pointer list-none text-lg font-semibold text-slate-950">
                Historico
                <span class="ml-2 text-sm font-medium text-slate-500">Atribuicoes, transferencias e liberacoes</span>
            </summary>

            <div class="space-y-4 border-t border-slate-200 pt-5">
                @forelse ($activityLogs as $activityLog)
                    @php($fromLabel = data_get($activityLog->properties, 'from.display_name') ?: data_get($activityLog->properties, 'from.assigned_email'))
                    @php($toLabel = data_get($activityLog->properties, 'to.display_name') ?: data_get($activityLog->properties, 'to.assigned_email'))
                    @php($assignmentLabel = data_get($activityLog->properties, 'assignment.display_name') ?: data_get($activityLog->properties, 'assignment.assigned_email'))
                    @php($reference = data_get($activityLog->properties, 'assignment.external_reference') ?: data_get($activityLog->properties, 'to.external_reference'))

                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
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
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
                        Nenhum evento registrado ainda.
                    </div>
                @endforelse
            </div>
        </details>

        <div x-cloak x-show="panel" class="fixed inset-0 z-50" aria-modal="true" role="dialog">
            <div x-show="panel" x-transition.opacity class="absolute inset-0 bg-slate-950/35" x-on:click="close()"></div>

            <div class="absolute inset-y-0 right-0 flex w-full justify-end sm:pl-10">
                <div x-show="panel" x-transition class="h-full w-full max-w-lg overflow-y-auto border-l border-slate-200 bg-white p-6 shadow-2xl">
                    <div x-show="isOpen('assign')" class="space-y-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Nova licenca</p>
                                <h2 class="mt-2 text-xl font-semibold text-slate-950">Atribuir licenca</h2>
                                <p class="mt-1 text-sm text-slate-500">Escolha um usuario ativo e, se precisar, informe uma referencia externa.</p>
                            </div>
                            <button type="button" x-on:click="close()" class="rounded-full border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-500">Fechar</button>
                        </div>

                        <form method="POST" action="{{ route('licenses.assignments.store', $license) }}" class="grid gap-4" data-license-assignment-form>
                            @csrf
                            <input type="hidden" name="license_panel" value="assign">
                            <input type="hidden" name="status" value="{{ \App\Enums\LicenseAssignmentStatus::ACTIVE->value }}">

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-slate-700">Usuario ativo</span>
                                <select name="user_id" class="w-full rounded-xl border border-slate-300 px-4 py-3" data-license-assignment-user-select required>
                                    <option value="">Selecione um usuario</option>
                                    @foreach ($collaborators as $collaboratorOption)
                                        <option value="{{ $collaboratorOption->id }}" @selected((string) old('user_id') === (string) $collaboratorOption->id)>{{ $collaboratorOption->name }} - {{ $collaboratorOption->email }}</option>
                                    @endforeach
                                </select>
                                @error('user_id') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-slate-700">Referencia</span>
                                <input type="text" name="external_reference" value="{{ old('external_reference') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3" placeholder="Codigo SAP, tenant ou id externo">
                                @error('external_reference') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
                            </label>

                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                                O sistema usa automaticamente o nome e o e-mail do usuario selecionado.
                            </div>

                            <div class="flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-4">
                                <button type="button" x-on:click="close()" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Cancelar</button>
                                <button type="submit" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Salvar atribuicao</button>
                            </div>
                        </form>
                    </div>

                    @foreach ($assignments as $assignment)
                        <div x-show="isOpen('transfer-{{ $assignment->id }}')" class="space-y-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Transferencia</p>
                                    <h2 class="mt-2 text-xl font-semibold text-slate-950">Transferir licenca</h2>
                                    <p class="mt-1 text-sm text-slate-500">Atual: {{ $assignment->resolvedDisplayName() }}</p>
                                </div>
                                <button type="button" x-on:click="close()" class="rounded-full border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-500">Fechar</button>
                            </div>

                            <form method="POST" action="{{ route('licenses.assignments.transfer', [$license, $assignment]) }}" class="grid gap-4">
                                @csrf
                                <input type="hidden" name="license_panel" value="transfer-{{ $assignment->id }}">

                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-slate-700">Novo usuario ativo</span>
                                    <select name="user_id" class="w-full rounded-xl border border-slate-300 px-4 py-3" required>
                                        <option value="">Selecione um usuario</option>
                                        @foreach ($collaborators as $collaboratorOption)
                                            <option value="{{ $collaboratorOption->id }}" @selected((string) old('license_panel') === 'transfer-'.$assignment->id && (string) old('user_id') === (string) $collaboratorOption->id)>{{ $collaboratorOption->name }} - {{ $collaboratorOption->email }}</option>
                                        @endforeach
                                    </select>
                                    @if ((string) old('license_panel') === 'transfer-'.$assignment->id)
                                        @error('user_id') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
                                    @endif
                                </label>

                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-slate-700">Referencia</span>
                                    <input type="text" name="external_reference" value="{{ (string) old('license_panel') === 'transfer-'.$assignment->id ? old('external_reference', $assignment->external_reference) : $assignment->external_reference }}" class="w-full rounded-xl border border-slate-300 px-4 py-3" placeholder="Codigo SAP, tenant ou id externo">
                                    @if ((string) old('license_panel') === 'transfer-'.$assignment->id)
                                        @error('external_reference') <span class="mt-1 block text-sm text-rose-600">{{ $message }}</span> @enderror
                                    @endif
                                </label>

                                <p class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">A transferencia passa a usar o nome e o e-mail do novo usuario automaticamente.</p>

                                <div class="flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-4">
                                    <button type="button" x-on:click="close()" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Cancelar</button>
                                    <button type="submit" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Salvar transferencia</button>
                                </div>
                            </form>
                        </div>

                        <div x-show="isOpen('release-{{ $assignment->id }}')" class="space-y-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-500">Liberar licenca</p>
                                    <h2 class="mt-2 text-xl font-semibold text-slate-950">Liberar esta licenca?</h2>
                                    <p class="mt-1 text-sm text-slate-500">{{ $assignment->resolvedDisplayName() }} deixara de ocupar esta licenca.</p>
                                </div>
                                <button type="button" x-on:click="close()" class="rounded-full border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-500">Fechar</button>
                            </div>

                            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                Essa acao libera uma licenca para outra pessoa. O historico continua registrado.
                            </div>

                            <form method="POST" action="{{ route('licenses.assignments.release', [$license, $assignment]) }}" class="flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-4">
                                @csrf
                                <input type="hidden" name="license_panel" value="release-{{ $assignment->id }}">
                                <button type="button" x-on:click="close()" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Cancelar</button>
                                <button type="submit" class="ui-action ui-action-danger rounded-2xl px-4 py-3 text-sm">Liberar licenca</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-layouts.portal>
