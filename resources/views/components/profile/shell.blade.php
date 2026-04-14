@props([
    'current' => 'profile',
    'heading' => 'Meu perfil',
    'subheading' => 'Gerencie sua conta com o mesmo padrao visual do portal.',
])

@php
    $user = auth()->user();
    $tabs = [
        ['key' => 'profile', 'label' => 'Meu perfil', 'route' => route('profile.edit')],
        ['key' => 'security', 'label' => 'Seguranca', 'route' => route('security.edit')],
        ['key' => 'appearance', 'label' => 'Aparencia', 'route' => route('appearance.edit')],
    ];
    $emailVerificationEnabled = $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail;
    $emailVerified = ! $emailVerificationEnabled || $user->hasVerifiedEmail();
    $twoFactorEnabled = method_exists($user, 'hasEnabledTwoFactorAuthentication')
        ? $user->hasEnabledTwoFactorAuthentication()
        : false;
@endphp

<div class="space-y-6">
    <section class="ui-panel overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-sky-900/90 px-6 py-6 text-slate-100">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-4">
                    <x-user-avatar :user="$user" size="xl" class="ring-4 ring-white/10" />

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-sky-300">Area pessoal</p>
                        <h2 class="mt-2 text-2xl font-semibold">{{ $heading }}</h2>
                        <p class="mt-1 text-sm text-slate-300">{{ $subheading }}</p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Perfil</p>
                        <p class="mt-2 text-sm font-medium text-white">{{ $user->global_role?->label() ?? 'Colaborador' }}</p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Escopo</p>
                        <p class="mt-2 text-sm font-medium text-white">{{ $user->accessSummary() }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 border-t border-slate-200 px-6 py-4">
            @foreach ($tabs as $tab)
                <a
                    href="{{ $tab['route'] }}"
                    class="{{ $current === $tab['key'] ? 'border-sky-200 bg-sky-50 text-sky-700' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900' }} rounded-2xl border px-4 py-2 text-sm font-medium transition"
                >
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-6">
            {{ $slot }}
        </div>

        <aside class="space-y-6">
            @isset($aside)
                {{ $aside }}
            @else
                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center gap-3">
                        <x-user-avatar :user="$user" size="lg" />
                        <div class="min-w-0">
                            <h3 class="truncate text-lg font-semibold text-slate-900">{{ $user->name }}</h3>
                            <p class="truncate text-sm text-slate-500">{{ $user->email }}</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-4">
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Verificacao</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">
                                {{ $emailVerified ? 'Email verificado' : 'Email pendente de verificacao' }}
                            </p>
                        </div>

                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Acesso</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">{{ $user->accessSummary() }}</p>
                        </div>
                    </div>
                </section>

                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-900">Seguranca da conta</h3>
                    <p class="mt-1 text-sm text-slate-500">Resumo rapido dos recursos ativos na sua conta.</p>

                    <div class="mt-5 space-y-3">
                        <div class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-slate-900">Autenticacao em dois fatores</p>
                                <p class="text-xs text-slate-500">Disponivel via Fortify no painel de seguranca.</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $twoFactorEnabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ $twoFactorEnabled ? 'Ativada' : 'Desativada' }}
                            </span>
                        </div>
                    </div>

                    <a href="{{ route('security.edit') }}" class="mt-5 inline-flex rounded-2xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 hover:text-slate-900">
                        Abrir seguranca avancada
                    </a>
                </section>
            @endisset
        </aside>
    </div>
</div>
