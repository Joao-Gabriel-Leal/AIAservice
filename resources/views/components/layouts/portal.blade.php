@props([
    'title' => null,
    'subtitle' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title])
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900">
        <div class="min-h-screen lg:grid lg:grid-cols-[280px_1fr]">
            <aside class="border-b border-slate-800 bg-slate-950 px-6 py-6 text-slate-100 lg:border-b-0 lg:border-r">
                @php($user = auth()->user())
                @php($unreadNotificationsCount = $user->unreadNotifications()->count())

                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    <div class="flex size-11 items-center justify-center rounded-2xl bg-sky-500/20 text-lg font-semibold text-sky-300">AI</div>
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-sky-300">AIA Service</p>
                        <p class="text-sm text-slate-400">Gestao interna modular</p>
                    </div>
                </a>

                <div class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
                    <div class="flex items-center gap-3">
                        <x-user-avatar :user="$user" size="md" class="ring-2 ring-slate-800/80" />

                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ $user->name }}</p>
                            <p class="mt-1 truncate text-xs text-slate-400">{{ $user->global_role?->label() ?? 'Colaborador' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $user->accessSummary() }}</p>
                            <p class="mt-1 truncate text-xs text-slate-500">{{ $user->email }}</p>
                        </div>
                    </div>
                </div>

                <nav class="mt-8 space-y-8">
                    <div>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Geral</p>
                        <div class="space-y-2">
                            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'bg-sky-500/20 text-sky-200' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }} block rounded-xl px-4 py-3 text-sm transition">Dashboard</a>
                            <a href="{{ route('tickets.central') }}" class="{{ request()->routeIs('tickets.central', 'tickets.create') ? 'bg-sky-500/20 text-sky-200' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }} block rounded-xl px-4 py-3 text-sm transition">Central de formularios</a>
                            <a href="{{ route('tickets.index') }}" class="{{ request()->routeIs('tickets.index', 'tickets.show') ? 'bg-sky-500/20 text-sky-200' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }} block rounded-xl px-4 py-3 text-sm transition">Chamados</a>
                            @if ($user->hasOperationalAccess())
                                <a href="{{ route('tickets.board') }}" class="{{ request()->routeIs('tickets.board') ? 'bg-sky-500/20 text-sky-200' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }} block rounded-xl px-4 py-3 text-sm transition">Quadro</a>
                            @endif
                            <a href="{{ route('notifications.index') }}" class="{{ request()->routeIs('notifications.*') ? 'bg-sky-500/20 text-sky-200' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }} flex items-center justify-between rounded-xl px-4 py-3 text-sm transition">
                                <span>Notificacoes</span>
                                @if ($unreadNotificationsCount > 0)
                                    <span class="rounded-full bg-sky-500/20 px-2.5 py-1 text-xs font-semibold text-sky-200">{{ $unreadNotificationsCount }}</span>
                                @endif
                            </a>
                        </div>
                    </div>

                    @if ($user->isSuperAdmin() || $user->isSectorAdmin())
                        <div>
                            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Administracao</p>
                            <div class="space-y-2">
                                @if ($user->isSuperAdmin())
                                    <a href="{{ route('companies.index') }}" class="{{ request()->routeIs('companies.*') ? 'bg-sky-500/20 text-sky-200' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }} block rounded-xl px-4 py-3 text-sm transition">Empresas</a>
                                    <a href="{{ route('sectors.index') }}" class="{{ request()->routeIs('sectors.*') ? 'bg-sky-500/20 text-sky-200' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }} block rounded-xl px-4 py-3 text-sm transition">Setores</a>
                                    <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'bg-sky-500/20 text-sky-200' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }} block rounded-xl px-4 py-3 text-sm transition">Usuarios</a>
                                @endif
                                <a href="{{ route('tickets.settings') }}" class="{{ request()->routeIs('tickets.settings') ? 'bg-sky-500/20 text-sky-200' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }} block rounded-xl px-4 py-3 text-sm transition">Configurar quadro</a>
                            </div>
                        </div>
                    @endif
                </nav>

                <div class="mt-8 flex flex-wrap gap-3 border-t border-slate-800 pt-6">
                    <a href="{{ route('profile.edit') }}" class="rounded-xl border border-slate-800 px-4 py-2 text-sm text-slate-300 transition hover:border-slate-700 hover:text-white">Meu perfil</a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-xl border border-rose-500/30 px-4 py-2 text-sm text-rose-200 transition hover:bg-rose-500/10">Sair</button>
                    </form>
                </div>
            </aside>

            <div class="min-w-0">
                <header class="border-b border-slate-200 bg-white px-6 py-5">
                    <h1 class="text-2xl font-semibold text-slate-900">{{ $title ?? config('app.name') }}</h1>
                    @if ($subtitle)
                        <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
                    @endif
                </header>

                <main class="p-6">
                    @if (session('status'))
                        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
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
