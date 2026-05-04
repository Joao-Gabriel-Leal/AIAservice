@php($portalMode = $portalMode ?? 'default')
@php($isFocusedForm = $portalMode === 'focused-form')
@php($showHeader = $showHeader ?? true)

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title ?? null])
    </head>
    <body class="portal-shell {{ $isFocusedForm ? 'portal-shell-focused' : '' }} min-h-screen bg-[#e7edf7] text-slate-900 dark:bg-[#07101f] dark:text-slate-100">
        <div class="min-h-screen {{ $isFocusedForm ? 'block' : 'lg:grid lg:grid-cols-[290px_1fr]' }}">
            @unless ($isFocusedForm)
            <aside class="portal-sidebar">
                @php($user = auth()->user())
                @php($unreadNotificationsCount = $user->unreadNotifications()->count())

                <a href="{{ route('dashboard') }}" class="portal-brand-link">
                    <div class="portal-brand-mark">
                        <x-app-logo-icon class="h-full w-full" />
                    </div>

                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.26em] text-[#93b9ff]">AIA Service</p>
                        <p class="mt-1 text-sm text-slate-300/72">Gestao interna modular</p>
                    </div>
                </a>

                <div class="portal-user-panel">
                    <div class="flex items-center gap-3">
                        <x-user-avatar :user="$user" size="md" class="ring-2 ring-white/10" />

                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-white">{{ $user->name }}</p>
                            <p class="mt-1 truncate text-xs text-slate-300/78">{{ $user->global_role?->label() ?? 'Colaborador' }}</p>
                            <p class="mt-1 text-xs text-slate-400/80">{{ $user->accessSummary() }}</p>
                            <p class="mt-1 truncate text-xs text-slate-400/80">{{ $user->email }}</p>
                        </div>
                    </div>
                </div>

                <nav class="relative z-10 mt-8 space-y-8">
                    <div>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-[0.22em] text-slate-400/72">Geral</p>
                        <div class="space-y-2">
                            <a href="{{ route('dashboard') }}" class="portal-nav-link {{ request()->routeIs('dashboard') ? 'portal-nav-link-active' : '' }}">Dashboard</a>
                            @if ($user->isSuperAdmin())
                                <a href="{{ route('search') }}" class="portal-nav-link {{ request()->routeIs('search') ? 'portal-nav-link-active' : '' }}">Busca global</a>
                            @endif
                            <a href="{{ route('tickets.central') }}" class="portal-nav-link {{ request()->routeIs('tickets.central', 'tickets.create') ? 'portal-nav-link-active' : '' }}">Central de formularios</a>
                            <a href="{{ route('knowledge-base.index') }}" class="portal-nav-link {{ request()->routeIs('knowledge-base.index', 'knowledge-base.show') ? 'portal-nav-link-active' : '' }}">Base de conhecimento</a>
                            @if ($user->hasOperationalAccess())
                                <a href="{{ route('tickets.index') }}" class="portal-nav-link {{ request()->routeIs('tickets.index', 'tickets.board', 'tickets.show') ? 'portal-nav-link-active' : '' }}">Quadro</a>
                                <a href="{{ route('licenses.index') }}" class="portal-nav-link {{ request()->routeIs('licenses.*') ? 'portal-nav-link-active' : '' }}">Licencas</a>
                            @endif
                            <a href="{{ route('notifications.index') }}" class="portal-nav-link {{ request()->routeIs('notifications.*') ? 'portal-nav-link-active' : '' }}">
                                <span class="flex items-center justify-between gap-3">
                                    <span>Notificacoes</span>
                                    @if ($unreadNotificationsCount > 0)
                                        <span class="rounded-full bg-white/10 px-2.5 py-1 text-xs font-semibold text-white">{{ $unreadNotificationsCount }}</span>
                                    @endif
                                </span>
                            </a>
                        </div>
                    </div>

                    @if ($user->isSuperAdmin() || $user->isSectorAdmin())
                        <div>
                            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.22em] text-slate-400/72">Administracao</p>
                            <div class="space-y-2">
                                @if ($user->isSuperAdmin())
                                    <a href="{{ route('companies.index') }}" class="portal-nav-link {{ request()->routeIs('companies.*') ? 'portal-nav-link-active' : '' }}">Empresas</a>
                                    <a href="{{ route('sectors.index') }}" class="portal-nav-link {{ request()->routeIs('sectors.*') ? 'portal-nav-link-active' : '' }}">Setores</a>
                                    <a href="{{ route('sector-templates.index') }}" class="portal-nav-link {{ request()->routeIs('sector-templates.*') ? 'portal-nav-link-active' : '' }}">Templates de setor</a>
                                    <a href="{{ route('users.index') }}" class="portal-nav-link {{ request()->routeIs('users.*') ? 'portal-nav-link-active' : '' }}">Usuarios</a>
                                    <a href="{{ route('assets.index') }}" class="portal-nav-link {{ request()->routeIs('assets.*') ? 'portal-nav-link-active' : '' }}">Patrimonios</a>
                                @endif
                                <a href="{{ route('rooms.index') }}" class="portal-nav-link {{ request()->routeIs('rooms.*') ? 'portal-nav-link-active' : '' }}">Salas</a>
                                <a href="{{ route('knowledge-base.manage') }}" class="portal-nav-link {{ request()->routeIs('knowledge-base.manage', 'knowledge-base.create', 'knowledge-base.edit') ? 'portal-nav-link-active' : '' }}">Gerenciar base</a>
                                <a href="{{ route('tickets.settings') }}" class="portal-nav-link {{ request()->routeIs('tickets.settings') ? 'portal-nav-link-active' : '' }}">Configurar quadro</a>
                            </div>
                        </div>
                    @endif
                </nav>

                <div class="relative z-10 mt-8 flex flex-wrap gap-3 border-t border-white/8 pt-6">
                    <a href="{{ route('appearance.edit') }}" class="portal-sidebar-action">Aparencia</a>
                    <a href="{{ route('profile.edit') }}" class="portal-sidebar-action">Meu perfil</a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="portal-sidebar-action">Sair</button>
                    </form>
                </div>
            </aside>
            @endunless

            <div class="portal-main {{ $isFocusedForm ? 'portal-main-focused' : '' }} min-w-0">
                @if ($isFocusedForm)
                    <header class="px-4 pt-5 sm:px-6 lg:px-8">
                        <div class="mx-auto flex max-w-[860px] items-center justify-between gap-4 text-white">
                            <a href="{{ route('tickets.central') }}" class="inline-flex items-center gap-2 rounded-full border border-white/18 bg-white/8 px-4 py-2 text-sm font-medium text-white/92 backdrop-blur-sm transition hover:bg-white/12">
                                Voltar
                            </a>

                            @if (auth()->user()?->hasOperationalAccess())
                                <a href="{{ route('tickets.index') }}" class="inline-flex items-center gap-2 rounded-full border border-white/18 bg-white/8 px-4 py-2 text-sm font-medium text-white/92 backdrop-blur-sm transition hover:bg-white/12">
                                    Quadro
                                </a>
                            @else
                                <a href="{{ route('tickets.central') }}" class="inline-flex items-center gap-2 rounded-full border border-white/18 bg-white/8 px-4 py-2 text-sm font-medium text-white/92 backdrop-blur-sm transition hover:bg-white/12">
                                    Central
                                </a>
                            @endif
                        </div>
                    </header>
                @elseif ($showHeader)
                    <header class="px-6 pt-6">
                        <div class="portal-layout-header">
                            <div class="flex flex-col gap-4">
                                <div>
                                    <p class="portal-layout-kicker">Portal interno</p>
                                    <h1 class="mt-3 text-3xl font-semibold text-white">{{ $title ?? config('app.name') }}</h1>
                                    @if (! empty($subtitle))
                                        <p class="mt-2 max-w-3xl text-sm text-slate-200/78">{{ $subtitle }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </header>
                @endif

                <main class="{{ $isFocusedForm ? 'px-4 pb-10 pt-6 sm:px-6 lg:px-8' : 'p-6' }}">
                    @if (session('status'))
                        <div class="ui-panel mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                            {{ session('status') }}
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>

        @fluxScripts
        @stack('scripts')
    </body>
</html>
