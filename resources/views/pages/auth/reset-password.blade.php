<x-layouts::auth.split
    :title="'Redefinir senha'"
    panel-eyebrow="Nova credencial"
    panel-title="Defina uma nova senha"
    panel-description="Use o email da conta e conclua a recuperacao com uma senha nova e segura."
>
    @if (session('status'))
        <div class="auth-status">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="auth-form-grid" x-data="{ showPassword: false, showPasswordConfirmation: false }">
        @csrf

        <input type="hidden" name="token" value="{{ request()->route('token') }}">

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
                    value="{{ old('email', request('email')) }}"
                    type="email"
                    required
                    autocomplete="email"
                    placeholder="voce@empresa.com.br"
                    class="ui-input auth-field-input auth-field-input-icon"
                />
            </div>

            @error('email')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-field">
            <label for="password" class="auth-label">Nova senha</label>

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
                    autocomplete="new-password"
                    placeholder="Crie uma senha forte"
                    minlength="12"
                    class="ui-input auth-field-input auth-field-input-icon auth-field-input-toggle"
                />

                <button
                    type="button"
                    class="auth-field-toggle"
                    x-on:click="showPassword = !showPassword"
                    x-bind:aria-label="showPassword ? 'Ocultar nova senha' : 'Mostrar nova senha'"
                    x-bind:aria-pressed="showPassword.toString()"
                >
                    <span class="sr-only" x-text="showPassword ? 'Ocultar nova senha' : 'Mostrar nova senha'">Mostrar nova senha</span>
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

        <div class="auth-field">
            <label for="password_confirmation" class="auth-label">Confirmar nova senha</label>

            <div class="auth-input-wrap">
                <span class="auth-field-icon" aria-hidden="true">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none">
                        <rect x="5" y="10" width="14" height="10" rx="2.6" stroke="currentColor" stroke-width="1.8" />
                        <path d="M8 10V8C8 5.5 9.8 4 12 4C14.2 4 16 5.5 16 8V10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                    </svg>
                </span>

                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    x-bind:type="showPasswordConfirmation ? 'text' : 'password'"
                    required
                    autocomplete="new-password"
                    placeholder="Repita a nova senha"
                    minlength="12"
                    class="ui-input auth-field-input auth-field-input-icon auth-field-input-toggle"
                />

                <button
                    type="button"
                    class="auth-field-toggle"
                    x-on:click="showPasswordConfirmation = !showPasswordConfirmation"
                    x-bind:aria-label="showPasswordConfirmation ? 'Ocultar confirmacao de senha' : 'Mostrar confirmacao de senha'"
                    x-bind:aria-pressed="showPasswordConfirmation.toString()"
                >
                    <span class="sr-only" x-text="showPasswordConfirmation ? 'Ocultar confirmacao de senha' : 'Mostrar confirmacao de senha'">Mostrar confirmacao de senha</span>
                    <svg x-show="! showPasswordConfirmation" class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M2.5 12S6 5.5 12 5.5S21.5 12 21.5 12S18 18.5 12 18.5S2.5 12 2.5 12Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        <circle cx="12" cy="12" r="3.1" stroke="currentColor" stroke-width="1.8" />
                    </svg>
                    <svg x-cloak x-show="showPasswordConfirmation" class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M3 3L21 21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        <path d="M10.7 5.7C11.1 5.6 11.5 5.5 12 5.5C18 5.5 21.5 12 21.5 12C20.7 13.4 19.8 14.6 18.8 15.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M6.6 6.9C4.1 8.8 2.5 12 2.5 12S6 18.5 12 18.5C13.6 18.5 15 18 16.2 17.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M9.9 9.9A3.1 3.1 0 0 0 14.1 14.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                    </svg>
                </button>
            </div>

            @error('password_confirmation')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            class="ui-action ui-action-primary auth-submit"
            data-test="reset-password-button"
        >
            Salvar nova senha
        </button>
    </form>

    <div class="auth-helper-card">
        Escolha uma senha com pelo menos 8 caracteres, com letras maiusculas e minusculas, numeros e simbolos.
    </div>

    @if (Route::has('password.request'))
        <p class="auth-secondary-copy">
            Problemas com o link?
            <flux:link class="auth-inline-link" :href="route('password.request')" wire:navigate>Solicitar um novo email</flux:link>
        </p>
    @endif
</x-layouts::auth.split>
