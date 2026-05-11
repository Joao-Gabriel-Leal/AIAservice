@php
    $profileFacts = [
        ['label' => 'E-mail', 'value' => $profileUser->email],
        ['label' => 'Telefone', 'value' => $profileUser->phone ?: 'Adicione um telefone'],
        ['label' => 'Local', 'value' => $profileUser->location ?: 'Adicione um local'],
        ['label' => 'Data de nascimento', 'value' => $profileUser->birth_date?->format('d/m/Y') ?: 'Adicione uma data de nascimento'],
    ];

    $activeLicenseAssignments = $profileUser->licenseAssignments
        ->filter(fn ($assignment) => $assignment->status === \App\Enums\LicenseAssignmentStatus::ACTIVE);
@endphp

<x-layouts.portal :title="$profileUser->name" header-variant="none">
    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="bg-slate-950 px-6 py-7 text-white">
                <div class="grid gap-7 lg:grid-cols-[156px_minmax(0,1fr)_360px]">
                    <div class="flex justify-center lg:block">
                        <x-user-avatar :user="$profileUser" size="xl" class="size-32 rounded-full text-4xl ring-4 ring-white/10" />
                    </div>

                    <div class="min-w-0">
                        <h1 class="truncate text-3xl font-semibold text-white">{{ $profileUser->name }}</h1>
                        <p class="mt-3 text-sm uppercase tracking-[0.16em] text-slate-300">
                            {{ $profileUser->job_title ?: 'Adicione um cargo' }}
                        </p>

                        <div class="mt-5 flex flex-wrap items-start gap-2">
                            <span class="rounded-lg bg-blue-600 px-3 py-1 text-xs font-semibold text-white">
                                {{ $profileUser->global_role?->label() ?? 'Colaborador' }}
                            </span>
                            <span class="rounded-lg border border-white/10 bg-white/5 px-3 py-1 text-xs font-medium text-slate-200">
                                {{ $workStatus['label'] }}
                            </span>
                            <span class="rounded-lg border border-white/10 px-3 py-1 text-xs font-medium {{ $profileUser->is_active ? 'bg-emerald-400/10 text-emerald-100' : 'bg-slate-400/10 text-slate-300' }}">
                                {{ $profileUser->is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </div>
                    </div>

                    <div class="grid content-start gap-4 text-sm sm:grid-cols-2 lg:grid-cols-1">
                        @foreach ($profileFacts as $fact)
                            <div>
                                <p class="font-semibold text-slate-100">{{ $fact['label'] }}</p>
                                <p class="mt-1 break-words text-slate-300">{{ $fact['value'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-5">
                <div>
                    <p class="text-sm font-medium text-slate-900">Perfil administrativo</p>
                    <p class="mt-1 text-sm text-slate-500">Dados publicos do usuario e vinculos operacionais.</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('users.index') }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Voltar</a>
                    @can('update', $profileUser)
                        <a href="{{ route('users.edit', $profileUser) }}" class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm">Editar usuario</a>
                    @endcan
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="border-b border-slate-200 pb-4">
                    <h2 class="text-lg font-semibold text-slate-900">Acessos por setor</h2>
                    <p class="mt-1 text-sm text-slate-500">Permissoes e areas vinculadas a esta conta.</p>
                </div>

                <div class="space-y-3 pt-5">
                    @forelse ($profileUser->sectorAccesses as $sectorAccess)
                        <article class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <x-sector-badge :sector="$sectorAccess->sector" mode="dot" />
                                <p class="mt-1 text-xs text-slate-500">{{ $sectorAccess->sector?->company?->name ?? 'Empresa nao informada' }}</p>
                            </div>
                            <span class="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700">
                                {{ $sectorAccess->access_level->label() }}
                            </span>
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                            {{ $profileUser->isGlobalAdmin() ? 'Acesso global sem vinculos setoriais especificos.' : 'Sem vinculos setoriais cadastrados.' }}
                        </div>
                    @endforelse
                </div>
            </section>

            <aside class="space-y-6">
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Resumo</h2>
                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-2xl font-semibold text-slate-950">{{ $profileUser->currentAssets->count() }}</p>
                            <p class="text-xs text-slate-500">Patrimonios</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-2xl font-semibold text-slate-950">{{ $activeLicenseAssignments->count() }}</p>
                            <p class="text-xs text-slate-500">Licencas</p>
                        </div>
                    </div>

                    @if ($profileUser->must_change_password)
                        <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            Troca de senha pendente no proximo acesso.
                        </p>
                    @endif
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Patrimonios vinculados</h2>

                    <div class="mt-5 space-y-3">
                        @forelse ($profileUser->currentAssets->take(4) as $asset)
                            <a href="{{ route('assets.show', $asset) }}" class="block rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 transition hover:border-slate-300 hover:bg-white">
                                <p class="font-medium text-slate-900">{{ $asset->name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $asset->asset_code }}</p>
                            </a>
                        @empty
                            <p class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                Nenhum patrimonio vinculado.
                            </p>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>
    </div>
</x-layouts.portal>
