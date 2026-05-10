@php($portalMode = $portalMode ?? 'default')
@php($isFocusedForm = $portalMode === 'focused-form')
@php($showHeader = $showHeader ?? true)
@php($showSubtitle = $showSubtitle ?? false)
@php($headerVariant = $headerVariant ?? null)
@php($headerVariant = $headerVariant ?? ($showHeader ? 'quiet' : 'none'))
@php($headerVariant = in_array($headerVariant, ['hero', 'quiet', 'none'], true) ? $headerVariant : 'quiet')
@php($focusedHeaderActionClass = 'ui-action portal-focused-header-action')
@php($user = auth()->user())
@php($unreadNotificationsCount = $user?->unreadNotifications()->count() ?? 0)

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title ?? null])
    </head>
    <body class="portal-shell {{ $isFocusedForm ? 'portal-shell-focused' : '' }} min-h-screen bg-[#e7edf7] text-slate-900 dark:bg-[#07101f] dark:text-slate-100">
        <div class="min-h-screen {{ $isFocusedForm ? 'block' : 'lg:grid lg:grid-cols-[290px_1fr]' }}">
            @unless ($isFocusedForm)
            <aside class="portal-sidebar hidden lg:block">
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

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <p class="min-w-0 truncate text-sm font-semibold text-white">{{ $user->name }}</p>

                                <a
                                    href="{{ route('notifications.index') }}"
                                    class="portal-notification-link {{ request()->routeIs('notifications.*') ? 'portal-notification-link-active' : '' }}"
                                    aria-label="Notificacoes"
                                    title="Notificacoes"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4.5 w-4.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5" />
                                        <path d="M10 20a2 2 0 0 0 4 0" />
                                    </svg>

                                    @if ($unreadNotificationsCount > 0)
                                        <span class="portal-notification-badge">{{ $unreadNotificationsCount > 9 ? '9+' : $unreadNotificationsCount }}</span>
                                    @endif
                                </a>
                            </div>
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
                            @if ($user->isGlobalAdmin())
                                <a href="{{ route('search') }}" class="portal-nav-link {{ request()->routeIs('search') ? 'portal-nav-link-active' : '' }}">Busca global</a>
                            @endif
                            <a href="{{ route('tickets.central') }}" class="portal-nav-link {{ request()->routeIs('tickets.central', 'tickets.create') ? 'portal-nav-link-active' : '' }}">Central de formularios</a>
                            <a href="{{ route('tickets.mine') }}" class="portal-nav-link {{ request()->routeIs('tickets.mine') ? 'portal-nav-link-active' : '' }}">Meus chamados</a>
                            <a href="{{ route('knowledge-base.index') }}" class="portal-nav-link {{ request()->routeIs('knowledge-base.*') ? 'portal-nav-link-active' : '' }}">Base de conhecimento</a>
                            @if ($user->hasOperationalAccess())
                                <a href="{{ route('tickets.index') }}" class="portal-nav-link {{ request()->routeIs('tickets.index', 'tickets.board', 'tickets.board.show', 'tickets.settings', 'tickets.show') ? 'portal-nav-link-active' : '' }}">Quadros</a>
                            @endif
                            @if ($user->isGlobalAdmin())
                                <a href="{{ route('licenses.index') }}" class="portal-nav-link {{ request()->routeIs('licenses.*') ? 'portal-nav-link-active' : '' }}">Licencas</a>
                            @endif
                        </div>
                    </div>

                    @if ($user->isGlobalAdmin())
                        <div>
                            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.22em] text-slate-400/72">Administracao</p>
                            <div class="space-y-2">
                                <a href="{{ route('companies.index') }}" class="portal-nav-link {{ request()->routeIs('companies.*', 'sectors.*', 'rooms.*') ? 'portal-nav-link-active' : '' }}">Empresas</a>
                                <a href="{{ route('sector-templates.index') }}" class="portal-nav-link {{ request()->routeIs('sector-templates.*') ? 'portal-nav-link-active' : '' }}">Templates de setor</a>
                                <a href="{{ route('users.index') }}" class="portal-nav-link {{ request()->routeIs('users.*') ? 'portal-nav-link-active' : '' }}">Usuarios</a>
                                <a href="{{ route('admin.emails.index') }}" class="portal-nav-link {{ request()->routeIs('admin.emails.*') ? 'portal-nav-link-active' : '' }}">E-mails</a>
                                <a href="{{ route('assets.index') }}" class="portal-nav-link {{ request()->routeIs('assets.*') ? 'portal-nav-link-active' : '' }}">Patrimonios</a>
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
                            <a href="{{ route('tickets.central') }}" class="{{ $focusedHeaderActionClass }}">
                                Voltar
                            </a>

                            @if (auth()->user()?->hasOperationalAccess())
                                <a href="{{ route('tickets.index') }}" class="{{ $focusedHeaderActionClass }}">
                                    Quadros
                                </a>
                            @else
                                <a href="{{ route('tickets.central') }}" class="{{ $focusedHeaderActionClass }}">
                                    Central
                                </a>
                            @endif
                        </div>
                    </header>
                @else
                    <header class="portal-mobile-header lg:hidden">
                        <a href="{{ route('dashboard') }}" class="portal-mobile-brand">
                            <span class="portal-mobile-brand-mark" aria-hidden="true">
                                <x-app-logo-icon class="h-full w-full" />
                            </span>
                            <span class="portal-mobile-brand-copy">
                                <span>AIA Service</span>
                                <small>{{ $user?->global_role?->label() ?? 'Portal interno' }}</small>
                            </span>
                        </a>

                        <a
                            href="{{ route('notifications.index') }}"
                            class="portal-mobile-icon-link {{ request()->routeIs('notifications.*') ? 'portal-mobile-icon-link-active' : '' }}"
                            aria-label="Notificacoes"
                        >
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5" />
                                <path d="M10 20a2 2 0 0 0 4 0" />
                            </svg>

                            @if ($unreadNotificationsCount > 0)
                                <span class="portal-mobile-badge">{{ $unreadNotificationsCount > 9 ? '9+' : $unreadNotificationsCount }}</span>
                            @endif
                        </a>
                    </header>
                @endif

                @if (! $isFocusedForm && $headerVariant !== 'none')
                    <header class="px-6 pt-6">
                        <div class="portal-layout-header {{ $headerVariant === 'hero' ? 'portal-layout-header-hero' : 'portal-layout-header-quiet' }}">
                            <div class="flex flex-col gap-4">
                                <div>
                                    <p class="portal-layout-kicker">Portal interno</p>
                                    <h1 class="portal-layout-title">{{ $title ?? config('app.name') }}</h1>
                                    @if ($showSubtitle && ! empty($subtitle))
                                        <p class="portal-layout-subtitle">{{ $subtitle }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </header>
                @endif

                <main class="portal-content {{ $isFocusedForm ? 'px-4 pb-10 pt-6 sm:px-6 lg:px-8' : 'p-6' }}">
                    @if (session('status'))
                        <div class="ui-panel mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                            {{ session('status') }}
                        </div>
                    @endif

                    {{ $slot }}
                </main>

                @unless ($isFocusedForm)
                    <nav class="portal-mobile-bottom-nav lg:hidden" aria-label="Navegacao principal mobile">
                        <a href="{{ route('tickets.central') }}" class="portal-mobile-nav-item {{ request()->routeIs('tickets.central', 'tickets.create') ? 'portal-mobile-nav-item-active' : '' }}">
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v11a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 17.5z" />
                                <path d="M8 9h8M8 13h5" />
                            </svg>
                            <span>Central</span>
                        </a>

                        <a href="{{ route('tickets.mine') }}" class="portal-mobile-nav-item {{ request()->routeIs('tickets.mine') ? 'portal-mobile-nav-item-active' : '' }}">
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M8 7h8M8 12h8M8 17h5" />
                                <path d="M5 4h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z" />
                            </svg>
                            <span>Chamados</span>
                        </a>

                        <a href="{{ route('notifications.index') }}" class="portal-mobile-nav-item {{ request()->routeIs('notifications.*') ? 'portal-mobile-nav-item-active' : '' }}">
                            <span class="portal-mobile-nav-icon-wrap">
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5" />
                                    <path d="M10 20a2 2 0 0 0 4 0" />
                                </svg>
                                @if ($unreadNotificationsCount > 0)
                                    <span class="portal-mobile-nav-badge">{{ $unreadNotificationsCount > 9 ? '9+' : $unreadNotificationsCount }}</span>
                                @endif
                            </span>
                            <span>Alertas</span>
                        </a>

                        <details class="portal-mobile-menu">
                            <summary class="portal-mobile-nav-item">
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 7h16M4 12h16M4 17h16" />
                                </svg>
                                <span>Menu</span>
                            </summary>

                            <div class="portal-mobile-menu-panel">
                                <div class="portal-mobile-menu-user">
                                    <x-user-avatar :user="$user" size="md" />
                                    <div class="min-w-0">
                                        <p>{{ $user?->name }}</p>
                                        <small>{{ $user?->email }}</small>
                                    </div>
                                </div>

                                <div class="portal-mobile-menu-links">
                                    <a href="{{ route('dashboard') }}">Dashboard</a>
                                    @if ($user?->isGlobalAdmin())
                                        <a href="{{ route('search') }}">Busca global</a>
                                    @endif
                                    @if ($user?->hasOperationalAccess())
                                        <a href="{{ route('tickets.index') }}">Quadros</a>
                                    @endif
                                    <a href="{{ route('knowledge-base.index') }}">Base de conhecimento</a>
                                    @if ($user?->isGlobalAdmin())
                                        <a href="{{ route('companies.index') }}">Empresas</a>
                                        <a href="{{ route('users.index') }}">Usuarios</a>
                                        <a href="{{ route('assets.index') }}">Patrimonios</a>
                                    @endif
                                    <a href="{{ route('appearance.edit') }}">Aparencia</a>
                                    <a href="{{ route('profile.edit') }}">Meu perfil</a>
                                </div>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="portal-mobile-menu-logout">Sair</button>
                                </form>
                            </div>
                        </details>
                    </nav>
                @endunless
            </div>
        </div>

        @fluxScripts
        @stack('scripts')
    </body>
</html>
