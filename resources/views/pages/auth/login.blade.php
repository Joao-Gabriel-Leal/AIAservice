<x-layouts::auth.split
    :title="'Entrar'"
    panel-eyebrow=""
    panel-title="Bem-vindo de volta!"
    panel-description="Entre com suas credenciais para acessar o sistema"
    hero-title="Gerencie seus chamados com eficiencia"
    hero-description="Sistema completo de gestao operacional para sua empresa"
>
    @if (session('status'))
        <div class="auth-status">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="auth-form-grid" x-data="{ showPassword: false }">
        @csrf

        <div class="auth-field">
            <label for="email" class="auth-label">Email</label>

            <div class="auth-input-wrap">
                <span class="auth-field-icon" aria-hidden="true">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none">
                        <rect x="3" y="5" width="18" height="14" rx="3" stroke="currentColor" stroke-width="1.8" />
                        <path d="M4.5 7.5L12 12.5L19.5 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
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
                    placeholder="seu@email.com"
                    class="ui-input auth-field-input auth-field-input-icon"
                />
            </div>

            @error('email')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-field">
            <label for="password" class="auth-label">Senha</label>

            <div class="auth-input-wrap">
                <span class="auth-field-icon" aria-hidden="true">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none">
                        <rect x="5" y="10" width="14" height="10" rx="2.6" stroke="currentColor" stroke-width="1.8" />
                        <path d="M8 10V8C8 5.5 9.8 4 12 4C14.2 4 16 5.5 16 8V10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                    </svg>
                </span>

                <input
                    id="password"
                    name="password"
                    type="password"
                    x-bind:type="showPassword ? 'text' : 'password'"
                    required
                    autocomplete="current-password"
                    placeholder="Digite sua senha"
                    class="ui-input auth-field-input auth-field-input-icon auth-field-input-toggle"
                />

                <button
                    type="button"
                    class="auth-field-toggle"
                    x-on:click="showPassword = !showPassword"
                    x-bind:aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                    x-bind:aria-pressed="showPassword.toString()"
                >
                    <span class="sr-only" x-text="showPassword ? 'Ocultar senha' : 'Mostrar senha'">Mostrar senha</span>
                    <svg x-show="! showPassword" class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M2.5 12S6 5.5 12 5.5S21.5 12 21.5 12S18 18.5 12 18.5S2.5 12 2.5 12Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        <circle cx="12" cy="12" r="3.1" stroke="currentColor" stroke-width="1.8" />
                    </svg>
                    <svg x-cloak x-show="showPassword" class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M3 3L21 21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        <path d="M10.7 5.7C11.1 5.6 11.5 5.5 12 5.5C18 5.5 21.5 12 21.5 12C20.7 13.4 19.8 14.6 18.8 15.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M6.6 6.9C4.1 8.8 2.5 12 2.5 12S6 18.5 12 18.5C13.6 18.5 15 18 16.2 17.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M9.9 9.9A3.1 3.1 0 0 0 14.1 14.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                    </svg>
                </button>
            </div>

            @error('password')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-checkbox-row">
            <label class="auth-checkbox" for="remember">
                <input
                    id="remember"
                    type="checkbox"
                    name="remember"
                    value="1"
                    @checked(old('remember'))
                />
                <span>Lembrar-me</span>
            </label>

            @if (Route::has('password.request'))
                <flux:link class="auth-inline-link text-sm" :href="route('password.request')" wire:navigate>
                    Esqueceu a senha?
                </flux:link>
            @endif
        </div>

        <button
            type="submit"
            class="ui-action ui-action-primary auth-submit"
            data-test="login-button"
        >
            <span>Entrar</span>
            <svg class="h-5 w-5 auth-submit-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M10 7L15 12L10 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M15 12H3" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                <path d="M14 5H18C19.7 5 21 6.3 21 8V16C21 17.7 19.7 19 18 19H14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
        </button>
    </form>

</x-layouts::auth.split>
