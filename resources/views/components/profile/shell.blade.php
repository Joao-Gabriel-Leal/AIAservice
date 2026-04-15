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
    <section class="ui-panel overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
        <div class="bg-[linear-gradient(135deg,#18289c_0%,#2238c2_48%,#3051da_100%)] px-6 py-6 text-slate-100 dark:bg-[linear-gradient(135deg,#101b6f_0%,#16278f_48%,#1f36b6_100%)]">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-4">
                    <x-user-avatar :user="$user" size="xl" class="ring-4 ring-white/10" />

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[#98f6f1]">Area pessoal</p>
                        <h2 class="mt-2 text-2xl font-semibold">{{ $heading }}</h2>
                        <p class="mt-1 text-sm text-blue-100/[0.78]">{{ $subheading }}</p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-white/10 bg-white/[0.06] px-4 py-3 backdrop-blur-sm">
                        <p class="text-xs uppercase tracking-[0.24em] text-blue-100/[0.54]">Perfil</p>
                        <p class="mt-2 text-sm font-medium text-white">{{ $user->global_role?->label() ?? 'Colaborador' }}</p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/[0.06] px-4 py-3 backdrop-blur-sm">
                        <p class="text-xs uppercase tracking-[0.24em] text-blue-100/[0.54]">Escopo</p>
                        <p class="mt-2 text-sm font-medium text-white">{{ $user->accessSummary() }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 border-t border-slate-200 px-6 py-4">
            @foreach ($tabs as $tab)
                <a
                    href="{{ $tab['route'] }}"
                    class="{{ $current === $tab['key'] ? 'border-[#d6e3ff] bg-[#edf3ff] text-[#2742c5] dark:border-[#203969] dark:bg-[#132347] dark:text-[#bfd2ff]' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900 dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-300 dark:hover:border-slate-700 dark:hover:text-white' }} rounded-2xl border px-4 py-2 text-sm font-medium transition"
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
                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                    <div class="flex items-center gap-3">
                        <x-user-avatar :user="$user" size="lg" />
                        <div class="min-w-0">
                            <h3 class="truncate text-lg font-semibold text-slate-900">{{ $user->name }}</h3>
                            <p class="truncate text-sm text-slate-500">{{ $user->email }}</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-4">
                        <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950/70">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Verificacao</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">
                                {{ $emailVerified ? 'Email verificado' : 'Email pendente de verificacao' }}
                            </p>
                        </div>

                        <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950/70">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Acesso</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">{{ $user->accessSummary() }}</p>
                        </div>
                    </div>
                </section>

                <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                    <h3 class="text-lg font-semibold text-slate-900">Seguranca da conta</h3>
                    <p class="mt-1 text-sm text-slate-500">Resumo rapido dos recursos ativos na sua conta.</p>

                    <div class="mt-5 space-y-3">
                        <div class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-800">
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
