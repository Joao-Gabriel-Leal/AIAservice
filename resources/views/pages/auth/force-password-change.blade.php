<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'Trocar senha'])
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
        <main class="flex min-h-screen items-center justify-center px-4 py-10">
            <section class="w-full max-w-[440px] rounded-[1.75rem] border border-slate-200 bg-white p-7 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.55)]">
                <div class="mx-auto flex h-14 w-[8rem] items-center justify-center overflow-hidden rounded-[1.15rem] border border-slate-200 bg-white px-2 shadow-sm">
                    <x-app-logo-icon class="h-full w-full" />
                </div>

                <div class="mt-7 text-center">
                    <h1 class="text-2xl font-semibold text-slate-950">Troque sua senha</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Para continuar, defina uma senha com maiuscula, minuscula, numero e caractere especial.
                    </p>
                </div>

                @if (session('status'))
                    <div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('password.force-change.update') }}" class="mt-7 space-y-4">
                    @csrf

                    <label class="block">
                        <span class="mb-2 block text-sm font-medium text-slate-700">Nova senha</span>
                        <input
                            type="password"
                            name="password"
                            autocomplete="new-password"
                            class="ui-input h-12 w-full rounded-2xl border-slate-200"
                            required
                        >
                        @error('password') <span class="mt-2 block text-xs font-medium text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-sm font-medium text-slate-700">Confirmar nova senha</span>
                        <input
                            type="password"
                            name="password_confirmation"
                            autocomplete="new-password"
                            class="ui-input h-12 w-full rounded-2xl border-slate-200"
                            required
                        >
                    </label>

                    <p class="text-sm leading-6 text-slate-500">
                        Minimo de 8 caracteres com letra maiuscula, letra minuscula, numero e simbolo.
                    </p>

                    <button type="submit" class="ui-action ui-action-primary h-12 w-full rounded-2xl text-sm font-semibold">
                        Salvar nova senha
                    </button>
                </form>
            </section>
        </main>

        @fluxScripts
    </body>
</html>
