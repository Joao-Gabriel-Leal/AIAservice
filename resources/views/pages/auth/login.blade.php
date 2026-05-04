<x-layouts::auth.split
    :title="'Entrar'"
    panel-eyebrow="Acesso ao sistema"
    panel-title="Bem-vindo de volta"
    panel-description="Entre com seu email corporativo para acompanhar solicitacoes, prioridades e atualizacoes do atendimento."
>
    @if (session('status'))
        <div class="auth-status">
            {{ session('status') }}
        </div>
    @endif

    @if (! empty($loginHints))
        <div
            class="auth-login-hints"
            x-data="{
                copiedKey: null,
                feedback: '',
                timer: null,
                copy(value, key, label) {
                    if (! navigator.clipboard?.writeText) {
                        this.feedback = 'Nao foi possivel copiar automaticamente neste navegador.'
                        return
                    }

                    navigator.clipboard.writeText(value).then(() => {
                        this.copiedKey = key
                        this.feedback = `${label} copiado.`
                        clearTimeout(this.timer)
                        this.timer = setTimeout(() => {
                            this.copiedKey = null
                            this.feedback = ''
                        }, 1800)
                    }).catch(() => {
                        this.feedback = 'Nao foi possivel copiar automaticamente neste navegador.'
                    })
                },
            }"
        >
            <div class="auth-login-hints-header">
                <p class="auth-login-hints-eyebrow">Acesso rapido temporario</p>
                <h3 class="auth-login-hints-title">Logins principais para validacao</h3>
                <p class="auth-login-hints-copy">
                    Exibido apenas em ambiente local/debug e somente para contas encontradas no banco atual.
                </p>
            </div>

            <div class="auth-login-hints-list">
                @foreach ($loginHints as $loginHint)
                    <article class="auth-login-hint-item">
                        <p class="auth-login-hint-label">{{ $loginHint['label'] }}</p>

                        <div class="auth-login-hint-rows">
                            <div class="auth-login-hint-row">
                                <span class="auth-login-hint-key">Email</span>

                                <div class="auth-login-hint-value-wrap">
                                    <code class="auth-login-hint-value">{{ $loginHint['email'] }}</code>

                                    <button
                                        type="button"
                                        class="auth-copy-button"
                                        data-copy-value="{{ $loginHint['email'] }}"
                                        x-on:click='copy($el.dataset.copyValue, "email-{{ $loop->index }}", "Email")'
                                        x-bind:aria-label="copiedKey === 'email-{{ $loop->index }}' ? 'Email copiado' : 'Copiar email'"
                                    >
                                        <span x-text="copiedKey === 'email-{{ $loop->index }}' ? 'Copiado' : 'Copiar'">Copiar</span>
                                    </button>
                                </div>
                            </div>

                            <div class="auth-login-hint-row">
                                <span class="auth-login-hint-key">Senha</span>

                                <div class="auth-login-hint-value-wrap">
                                    <code class="auth-login-hint-value">{{ $loginHint['password'] }}</code>

                                    <button
                                        type="button"
                                        class="auth-copy-button"
                                        data-copy-value="{{ $loginHint['password'] }}"
                                        x-on:click='copy($el.dataset.copyValue, "password-{{ $loop->index }}", "Senha")'
                                        x-bind:aria-label="copiedKey === 'password-{{ $loop->index }}' ? 'Senha copiada' : 'Copiar senha'"
                                    >
                                        <span x-text="copiedKey === 'password-{{ $loop->index }}' ? 'Copiado' : 'Copiar'">Copiar</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <p class="auth-login-hints-feedback" x-text="feedback" x-bind:data-visible="feedback ? 'true' : 'false'" aria-live="polite"></p>
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
                    placeholder="voce@empresa.com.br"
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
                    <span x-text="showPassword ? 'Ocultar' : 'Mostrar'">Mostrar</span>
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
                <span>Lembrar neste dispositivo</span>
            </label>

            @if (Route::has('password.request'))
                <flux:link class="auth-inline-link text-sm" :href="route('password.request')" wire:navigate>
                    Esqueci minha senha
                </flux:link>
            @endif
        </div>

        <button
            type="submit"
            class="ui-action ui-action-primary auth-submit"
            data-test="login-button"
        >
            Entrar no portal
        </button>
    </form>

    <div class="auth-helper-card">
        O acesso e liberado pelo time administrador. Permissoes novas ou ajustadas aparecem no proximo login.
    </div>
</x-layouts::auth.split>
