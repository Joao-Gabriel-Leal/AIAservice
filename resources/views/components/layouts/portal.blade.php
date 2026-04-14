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
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    <div class="flex size-11 items-center justify-center rounded-2xl bg-sky-500/20 text-lg font-semibold text-sky-300">AI</div>
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-sky-300">AIA Service</p>
                        <p class="text-sm text-slate-400">Gestao interna modular</p>
                    </div>
                </a>

                <div class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
                    <p class="text-sm font-semibold">{{ auth()->user()->name }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ auth()->user()->role->label() }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ auth()->user()->email }}</p>
                </div>

                <nav class="mt-8 space-y-8">
                    <div>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Geral</p>
                        <div class="space-y-2">
                            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'bg-sky-500/20 text-sky-200' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }} block rounded-xl px-4 py-3 text-sm transition">Dashboard</a>
                            <a href="{{ route('tickets.board') }}" class="{{ request()->routeIs('tickets.*') ? 'bg-sky-500/20 text-sky-200' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }} block rounded-xl px-4 py-3 text-sm transition">Chamados</a>
                        </div>
                    </div>

                    @if (auth()->user()->isSuperAdmin() || auth()->user()->isSectorAdmin())
                        <div>
                            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Administracao</p>
                            <div class="space-y-2">
                                @if (auth()->user()->isSuperAdmin())
                                    <a href="{{ route('companies.index') }}" class="{{ request()->routeIs('companies.*') ? 'bg-sky-500/20 text-sky-200' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }} block rounded-xl px-4 py-3 text-sm transition">Empresas</a>
                                    <a href="{{ route('sectors.index') }}" class="{{ request()->routeIs('sectors.*') ? 'bg-sky-500/20 text-sky-200' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }} block rounded-xl px-4 py-3 text-sm transition">Setores</a>
                                @endif
                                <a href="{{ route('rooms.index') }}" class="{{ request()->routeIs('rooms.*') ? 'bg-sky-500/20 text-sky-200' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }} block rounded-xl px-4 py-3 text-sm transition">Salas</a>
                                <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'bg-sky-500/20 text-sky-200' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }} block rounded-xl px-4 py-3 text-sm transition">Usuarios</a>
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
