@php
    $portalMode = $portalMode ?? 'default';
    $isFocusedForm = $portalMode === 'focused-form';
    $showHeader = $showHeader ?? true;
    $showSubtitle = $showSubtitle ?? false;
    $headerVariant = $headerVariant ?? null;
    $headerVariant = $headerVariant ?? ($showHeader ? 'quiet' : 'none');
    $headerVariant = in_array($headerVariant, ['hero', 'quiet', 'none'], true) ? $headerVariant : 'quiet';
    $focusedHeaderActionClass = 'ui-action portal-focused-header-action';
    $user = auth()->user();
    $unreadNotificationsCount = $user?->unreadNotifications()->count() ?? 0;
    $companyContext = app(\App\Modules\Shared\Support\CurrentCompanyContext::class);
    $availableCompanies = $user ? $companyContext->availableCompanies($user) : collect();
    $currentCompany = $user ? $companyContext->current($user) : null;

    $portalNavIcon = static function (string $icon): string {
        $attrs = 'aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" style="width:1rem;height:1rem;min-width:1rem;max-width:1rem;min-height:1rem;max-height:1rem;flex:0 0 1rem;display:block" class="portal-nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"';

        return match ($icon) {
            'dashboard' => '<svg '.$attrs.'><rect x="4" y="4" width="6" height="6" rx="1.5" /><rect x="14" y="4" width="6" height="6" rx="1.5" /><rect x="4" y="14" width="6" height="6" rx="1.5" /><rect x="14" y="14" width="6" height="6" rx="1.5" /></svg>',
            'search' => '<svg '.$attrs.'><circle cx="11" cy="11" r="6" /><path d="M16 16L20 20" /></svg>',
            'forms' => '<svg '.$attrs.'><path d="M7 3.5h7l4 4V19a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 6 19V5A1.5 1.5 0 0 1 7.5 3.5Z" /><path d="M14 3.5V8h4" /><path d="M9 12h6M9 16h4" /></svg>',
            'tickets' => '<svg '.$attrs.'><path d="M5 6.5A2.5 2.5 0 0 1 7.5 4h9A2.5 2.5 0 0 1 19 6.5v6A2.5 2.5 0 0 1 16.5 15H10l-4.5 4v-4A2.5 2.5 0 0 1 3 12.5v-6Z" /></svg>',
            'knowledge' => '<svg '.$attrs.'><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H11v16H6.5A2.5 2.5 0 0 0 4 21.5v-16Z" /><path d="M20 5.5A2.5 2.5 0 0 0 17.5 3H13v16h4.5A2.5 2.5 0 0 1 20 21.5v-16Z" /></svg>',
            'boards' => '<svg '.$attrs.'><rect x="4" y="5" width="16" height="14" rx="2" /><path d="M8 5v14M16 5v14M4 10h16" /></svg>',
            'licenses' => '<svg '.$attrs.'><circle cx="8" cy="15" r="3" /><path d="M10.5 12.5 19 4M15.5 7.5 18 10M13.5 9.5 16 12" /></svg>',
            'companies' => '<svg '.$attrs.'><path d="M5 20V7.5A1.5 1.5 0 0 1 6.5 6H11v14" /><path d="M11 20V4.5A1.5 1.5 0 0 1 12.5 3H18v17" /><path d="M3.5 20h17" /><path d="M8 10h.01M8 14h.01M14 7h.01M14 11h.01M14 15h.01" /></svg>',
            'templates' => '<svg '.$attrs.'><path d="M8 4.5h8A1.5 1.5 0 0 1 17.5 6v13A1.5 1.5 0 0 1 16 20.5H8A1.5 1.5 0 0 1 6.5 19V6A1.5 1.5 0 0 1 8 4.5Z" /><path d="M9.5 4.5A2.5 2.5 0 0 1 12 2a2.5 2.5 0 0 1 2.5 2.5" /><path d="M9.5 10h5M9.5 14h5M9.5 17h3" /></svg>',
            'users' => '<svg '.$attrs.'><path d="M16 19v-1.2a3.8 3.8 0 0 0-3.8-3.8H7.8A3.8 3.8 0 0 0 4 17.8V19" /><circle cx="10" cy="8" r="3" /><path d="M20 19v-1a3 3 0 0 0-2.4-2.9" /><path d="M16.5 5.3a3 3 0 0 1 0 5.4" /></svg>',
            'emails' => '<svg '.$attrs.'><rect x="3.5" y="5.5" width="17" height="13" rx="2.5" /><path d="m5 8 7 5 7-5" /></svg>',
            'assets' => '<svg '.$attrs.'><path d="M12 3.5 20 8l-8 4.5L4 8l8-4.5Z" /><path d="M20 12.5 12 17 4 12.5" /><path d="M20 17 12 21.5 4 17" /></svg>',
            'logs' => '<svg '.$attrs.'><path d="M6.5 4.5h11A1.5 1.5 0 0 1 19 6v12a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 5 18V6a1.5 1.5 0 0 1 1.5-1.5Z" /><path d="M8.5 8h7M8.5 12h7M8.5 16h4" /></svg>',
            'appearance' => '<svg '.$attrs.'><path d="M12 3.5a8.5 8.5 0 0 0 0 17h1.2a1.9 1.9 0 0 0 1.3-3.3 1.55 1.55 0 0 1 1.1-2.7H17a4 4 0 0 0 4-4c0-3.8-3.8-7-9-7Z" /><circle cx="8.5" cy="10" r=".7" /><circle cx="11" cy="7.7" r=".7" /><circle cx="14.2" cy="8.2" r=".7" /><circle cx="16" cy="11" r=".7" /></svg>',
            'profile' => '<svg '.$attrs.'><circle cx="12" cy="8" r="3.5" /><path d="M5 20a7 7 0 0 1 14 0" /></svg>',
            'logout' => '<svg '.$attrs.'><path d="M9 5H6.5A1.5 1.5 0 0 0 5 6.5v11A1.5 1.5 0 0 0 6.5 19H9" /><path d="M14 8l4 4-4 4" /><path d="M18 12H9" /></svg>',
            'collapse' => '<svg '.$attrs.'><path d="M15 6l-6 6 6 6" /><path d="M20 4v16" /></svg>',
            default => '<svg '.$attrs.'><circle cx="12" cy="12" r="8" /></svg>',
        };
    };

    $generalNavItems = [
        ['label' => 'Dashboard', 'href' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'icon' => 'dashboard'],
    ];

    $generalNavItems[] = ['label' => 'Central de formularios', 'href' => route('tickets.central'), 'active' => request()->routeIs('tickets.central', 'tickets.create'), 'icon' => 'forms'];
    $generalNavItems[] = ['label' => 'Meus chamados', 'href' => route('tickets.mine'), 'active' => request()->routeIs('tickets.mine'), 'icon' => 'tickets'];
    $generalNavItems[] = ['label' => 'Base de conhecimento', 'href' => route('knowledge-base.index'), 'active' => request()->routeIs('knowledge-base.*'), 'icon' => 'knowledge'];

    if ($user->hasOperationalAccess()) {
        $generalNavItems[] = ['label' => 'Quadros', 'href' => route('tickets.index'), 'active' => request()->routeIs('tickets.index', 'tickets.board', 'tickets.board.show', 'tickets.settings', 'tickets.show'), 'icon' => 'boards'];
    }

    if ($user->isGlobalAdmin()) {
        $generalNavItems[] = ['label' => 'Licenças', 'href' => route('licenses.index'), 'active' => request()->routeIs('licenses.*'), 'icon' => 'licenses'];
    }

    $adminNavItems = [];

    if ($user->isGlobalAdmin()) {
        $adminNavItems = [
            ['label' => 'Busca global', 'href' => route('search'), 'active' => request()->routeIs('search'), 'icon' => 'search'],
            ['label' => 'Empresas', 'href' => route('companies.index'), 'active' => request()->routeIs('companies.*', 'sectors.*', 'rooms.*'), 'icon' => 'companies'],
            ['label' => 'Templates de setor', 'href' => route('sector-templates.index'), 'active' => request()->routeIs('sector-templates.*'), 'icon' => 'templates'],
            ['label' => 'Usuários', 'href' => route('users.index'), 'active' => request()->routeIs('users.*'), 'icon' => 'users'],
            ['label' => 'E-mails', 'href' => route('admin.emails.index'), 'active' => request()->routeIs('admin.emails.*'), 'icon' => 'emails'],
            ['label' => 'Patrimônios', 'href' => route('assets.index'), 'active' => request()->routeIs('assets.*'), 'icon' => 'assets'],
            ['label' => 'Logs', 'href' => route('admin.logs.index'), 'active' => request()->routeIs('admin.logs.*'), 'icon' => 'logs'],
        ];
    }
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title ?? null])
    </head>
    <body class="portal-shell {{ $isFocusedForm ? 'portal-shell-focused' : '' }} min-h-screen bg-[#e7edf7] text-slate-900 dark:bg-[#07101f] dark:text-slate-100">
        <div class="portal-shell-grid min-h-screen {{ $isFocusedForm ? 'block' : 'lg:grid lg:grid-cols-[264px_1fr]' }}">
            @unless ($isFocusedForm)
            <aside class="portal-sidebar hidden lg:flex lg:flex-col">
                <button
                    type="button"
                    class="portal-sidebar-toggle"
                    data-portal-sidebar-toggle
                    aria-label="Recolher menu"
                    aria-expanded="true"
                    title="Recolher menu"
                >
                    {!! $portalNavIcon('collapse') !!}
                </button>

                <div class="portal-user-panel">
                    <div class="portal-user-avatar-wrap">
                        <x-user-avatar :user="$user" size="md" class="portal-user-avatar" />
                        <span class="portal-user-status" aria-hidden="true"></span>
                    </div>

                    <div class="portal-user-copy">
                        <p class="portal-user-name">{{ $user->name }}</p>
                        <p class="portal-user-role">{{ $user->global_role?->label() ?? 'Colaborador' }}</p>
                        <p class="portal-user-access">{{ $user->accessSummary() }}</p>
                    </div>

                    <a
                        href="{{ route('notifications.index') }}"
                        class="portal-notification-link {{ request()->routeIs('notifications.*') ? 'portal-notification-link-active' : '' }}"
                        aria-label="Notificações"
                        title="Notificações"
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

                @if ($currentCompany)
                    <div class="portal-company-switcher">
                        <div class="portal-company-switcher-expanded">
                            <p class="portal-company-switcher-label">Empresa atual</p>
                            @if ($availableCompanies->count() > 1)
                                <details class="portal-company-expanded-menu">
                                    <summary class="portal-company-expanded-trigger" aria-label="Trocar empresa atual: {{ $currentCompany->name }}">
                                        <span>{{ $currentCompany->name }}</span>
                                        <svg aria-hidden="true" viewBox="0 0 20 20" class="portal-company-expanded-chevron" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M5.5 7.5 10 12l4.5-4.5" />
                                        </svg>
                                    </summary>

                                    <div class="portal-company-expanded-panel">
                                        @foreach ($availableCompanies as $companyOption)
                                            <form method="POST" action="{{ route('context.company.store') }}">
                                                @csrf
                                                <input type="hidden" name="company_id" value="{{ $companyOption->id }}">
                                                <button
                                                    type="submit"
                                                    class="portal-company-expanded-option {{ $currentCompany->id === $companyOption->id ? 'portal-company-expanded-option-active' : '' }}"
                                                    @disabled($currentCompany->id === $companyOption->id)
                                                >
                                                    <span>{{ $companyOption->name }}</span>
                                                </button>
                                            </form>
                                        @endforeach
                                    </div>
                                </details>
                            @else
                                <p class="portal-company-current">{{ $currentCompany->name }}</p>
                            @endif
                        </div>

                        @if ($availableCompanies->count() > 1)
                            <details class="portal-company-compact-menu">
                                <summary
                                    class="portal-company-compact-trigger"
                                    aria-label="Trocar empresa atual: {{ $currentCompany->name }}"
                                    title="Trocar empresa atual: {{ $currentCompany->name }}"
                                >
                                    {!! $portalNavIcon('companies') !!}
                                </summary>

                                <div class="portal-company-compact-panel">
                                    <p class="portal-company-compact-heading">Empresa atual</p>
                                    <div class="portal-company-compact-list">
                                        @foreach ($availableCompanies as $companyOption)
                                            <form method="POST" action="{{ route('context.company.store') }}">
                                                @csrf
                                                <input type="hidden" name="company_id" value="{{ $companyOption->id }}">
                                                <button
                                                    type="submit"
                                                    class="portal-company-compact-option {{ $currentCompany->id === $companyOption->id ? 'portal-company-compact-option-active' : '' }}"
                                                    @disabled($currentCompany->id === $companyOption->id)
                                                >
                                                    <span>{{ $companyOption->name }}</span>
                                                </button>
                                            </form>
                                        @endforeach
                                    </div>
                                </div>
                            </details>
                        @else
                            <span
                                class="portal-company-compact-current"
                                aria-label="Empresa atual: {{ $currentCompany->name }}"
                                title="Empresa atual: {{ $currentCompany->name }}"
                            >
                                {!! $portalNavIcon('companies') !!}
                            </span>
                        @endif
                    </div>
                @endif

                <nav class="portal-nav" aria-label="Navegação principal">
                    <section class="portal-nav-section">
                        <p class="portal-nav-heading">Geral</p>
                        <div class="portal-nav-list">
                            @foreach ($generalNavItems as $item)
                                <a
                                    href="{{ $item['href'] }}"
                                    class="portal-nav-link {{ $item['active'] ? 'portal-nav-link-active' : '' }}"
                                    title="{{ $item['label'] }}"
                                    @if ($item['active']) aria-current="page" @endif
                                >
                                    {!! $portalNavIcon($item['icon']) !!}
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </section>

                    @if ($user->isGlobalAdmin())
                        <section class="portal-nav-section">
                            <p class="portal-nav-heading">Administração</p>
                            <div class="portal-nav-list">
                                @foreach ($adminNavItems as $item)
                                    <a
                                        href="{{ $item['href'] }}"
                                        class="portal-nav-link {{ $item['active'] ? 'portal-nav-link-active' : '' }}"
                                        title="{{ $item['label'] }}"
                                        @if ($item['active']) aria-current="page" @endif
                                    >
                                        {!! $portalNavIcon($item['icon']) !!}
                                        <span>{{ $item['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endif
                </nav>

                <div class="portal-sidebar-actions">
                    <div class="portal-sidebar-actions-grid">
                        <a href="{{ route('profile.edit') }}" class="portal-sidebar-action portal-sidebar-action-wide {{ request()->routeIs('profile.*') ? 'portal-sidebar-action-active' : '' }}" title="Perfil">
                            {!! $portalNavIcon('profile') !!}
                            <span>Perfil</span>
                        </a>
                    </div>

                    <form method="POST" action="{{ route('logout') }}" class="portal-sidebar-logout-form">
                        @csrf
                        <button type="submit" class="portal-sidebar-action portal-sidebar-action-wide portal-sidebar-action-danger" title="Sair">
                            {!! $portalNavIcon('logout') !!}
                            <span>Sair</span>
                        </button>
                    </form>
                </div>
            </aside>
            @endunless

            <div class="portal-main {{ $isFocusedForm ? 'portal-main-focused' : '' }} min-w-0">
                @if ($isFocusedForm)
                    <header class="px-4 pb-3 pt-5 sm:px-6 lg:px-8">
                        <div class="mx-auto flex max-w-[1120px] items-center">
                            <a href="{{ route('tickets.central') }}" class="{{ $focusedHeaderActionClass }}" aria-label="Voltar para central de formularios">
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M15 18l-6-6 6-6" />
                                    <path d="M9 12h10" />
                                </svg>
                                Voltar
                            </a>
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
                                @if ($currentCompany)
                                    <small>{{ $currentCompany->name }}</small>
                                @endif
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
                                    @if ($currentCompany)
                                        <form method="POST" action="{{ route('context.company.store') }}" class="portal-mobile-company-form">
                                            @csrf
                                            <label for="mobile-company-id">Empresa atual</label>
                                            @if ($availableCompanies->count() > 1)
                                                <select id="mobile-company-id" name="company_id" onchange="this.form.submit()">
                                                    @foreach ($availableCompanies as $companyOption)
                                                        <option value="{{ $companyOption->id }}" @selected($currentCompany->id === $companyOption->id)>{{ $companyOption->name }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <p>{{ $currentCompany->name }}</p>
                                            @endif
                                        </form>
                                    @endif
                                    <a href="{{ route('dashboard') }}">Dashboard</a>
                                    @if ($user?->hasOperationalAccess())
                                        <a href="{{ route('tickets.index') }}">Quadros</a>
                                    @endif
                                    <a href="{{ route('knowledge-base.index') }}">Base de conhecimento</a>
                                    @if ($user?->isGlobalAdmin())
                                        <a href="{{ route('search') }}">Busca global</a>
                                        <a href="{{ route('companies.index') }}">Empresas</a>
                                        <a href="{{ route('users.index') }}">Usuarios</a>
                                        <a href="{{ route('assets.index') }}">Patrimonios</a>
                                        <a href="{{ route('admin.logs.index') }}">Logs</a>
                                    @endif
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
