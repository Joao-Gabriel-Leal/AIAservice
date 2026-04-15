<x-layouts::auth.split
    :title="'Recuperar acesso'"
    panel-eyebrow="Recuperacao guiada"
    panel-title="Esqueceu sua senha?"
    panel-description="Informe o email da conta para receber um link seguro de redefinicao."
>
    @if (session('status'))
        <div class="auth-status">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="auth-form-grid">
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
                    placeholder="voce@empresa.com.br"
                    class="ui-input auth-field-input auth-field-input-icon"
                />
            </div>

            @error('email')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            class="ui-action ui-action-primary auth-submit"
            data-test="email-password-reset-link-button"
        >
            Enviar link de redefinicao
        </button>
    </form>

    <div class="auth-helper-card">
        Se o email estiver vinculado a uma conta valida, voce recebera as instrucoes de recuperacao em instantes.
    </div>

    <p class="auth-secondary-copy">
        Ja lembrou a senha?
        <flux:link class="auth-inline-link" :href="route('login')" wire:navigate>Voltar para o login</flux:link>
    </p>
</x-layouts::auth.split>
