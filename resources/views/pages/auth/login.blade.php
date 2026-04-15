<x-layouts::auth :title="__('Entrar')">
    <div class="flex flex-col gap-7">
        <x-auth-header :title="'Bem-vindo de volta!'" :description="'Faca login em sua conta'" />

        <!-- Session Status -->
        <x-auth-session-status class="text-left text-sm text-emerald-600" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
            @csrf

            <div class="space-y-2">
                <label for="email" class="text-[1.05rem] font-semibold text-[#566898]">Email</label>
                <div class="relative">
                    <span class="pointer-events-none absolute left-5 top-1/2 -translate-y-1/2 text-[#a6b0cf]">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <rect x="2.5" y="4.5" width="19" height="15" rx="3.2" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M4.5 7 12 12.5 19.5 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <input
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        type="email"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="Digite seu email"
                        class="h-16 w-full rounded-[1.05rem] border border-[#d8deef] bg-white/98 pl-16 pr-5 text-[1.02rem] text-[#6475a6] shadow-[inset_0_1px_0_rgba(255,255,255,0.92),0_10px_24px_-22px_rgba(61,80,192,0.48)] outline-none placeholder:text-[#98a5c8] focus:border-[#bdd8f8] focus:ring-4 focus:ring-[#bfe8fb]/40"
                    />
                </div>
                @error('email')
                    <p class="text-sm text-rose-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-2">
                <label for="password" class="text-[1.05rem] font-semibold text-[#566898]">Senha</label>
                <div class="relative">
                    <span class="pointer-events-none absolute left-5 top-1/2 -translate-y-1/2 text-[#a6b0cf]">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <rect x="5" y="10" width="14" height="10.5" rx="2.6" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M8 10V7.8C8 5.16 9.98 3.5 12 3.5C14.02 3.5 16 5.16 16 7.8V10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        placeholder="Digite sua senha"
                        class="h-16 w-full rounded-[1.05rem] border border-[#d8deef] bg-white/98 pl-16 pr-14 text-[1.02rem] text-[#6475a6] shadow-[inset_0_1px_0_rgba(255,255,255,0.92),0_10px_24px_-22px_rgba(61,80,192,0.48)] outline-none placeholder:text-[#98a5c8] focus:border-[#bdd8f8] focus:ring-4 focus:ring-[#bfe8fb]/40"
                    />
                    <span class="pointer-events-none absolute right-5 top-1/2 -translate-y-1/2 text-[#aeb7d1]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M2.5 12S6 6.5 12 6.5 21.5 12 21.5 12 18 17.5 12 17.5 2.5 12 2.5 12Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                            <circle cx="12" cy="12" r="2.8" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                    </span>
                </div>
                @error('password')
                    <p class="text-sm text-rose-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-5">
                <label class="inline-flex items-center gap-3 text-[1.05rem] font-semibold text-[#6b79ad]">
                    <input
                        type="checkbox"
                        name="remember"
                        value="1"
                        @checked(old('remember'))
                        class="h-7 w-7 rounded-[0.55rem] border border-[#aebfe8] text-[#28c7e3] shadow-sm focus:ring-4 focus:ring-[#bfe8fb]/45"
                    />
                    <span>Lembrar-me</span>
                </label>

                @if (Route::has('password.request'))
                    <flux:link class="text-[1.02rem] font-medium text-[#00a9eb] hover:text-[#0094d1]" :href="route('password.request')" wire:navigate>
                        Esqueceu sua senha?
                    </flux:link>
                @endif
            </div>

            <div class="flex items-center justify-end">
                <button
                    type="submit"
                    class="h-16 w-full rounded-[1rem] bg-[linear-gradient(90deg,#22bfdc_0%,#28cde8_48%,#1fb6ec_100%)] text-[1.02rem] font-semibold text-white shadow-[0_20px_34px_-24px_rgba(28,183,236,0.92)] transition hover:brightness-105"
                    data-test="login-button"
                >
                    Entrar
                </button>
            </div>
        </form>

        <div class="rounded-t-[1.6rem] border-t border-[#d8e0f2] pt-6 md:pr-52">
            <p class="text-center text-[1.05rem] text-[#667ab0] md:text-left">
                Precisa de ajuda? <span class="font-medium text-[#4d63aa]">Contate o suporte</span>
            </p>
        </div>

        @if (Route::has('register'))
            <div class="space-x-1 text-center text-sm text-[#7e8cb6] rtl:space-x-reverse md:text-left">
                <span>Nao tem uma conta?</span>
                <flux:link class="font-medium text-[#08abe9]" :href="route('register')" wire:navigate>Cadastre-se</flux:link>
            </div>
        @endif
    </div>
</x-layouts::auth>
