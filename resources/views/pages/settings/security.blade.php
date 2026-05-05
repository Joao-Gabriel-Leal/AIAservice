<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\UpdatesUserPassword;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Seguranca da conta')] class extends Component {
    use PasswordValidationRules;
    use UpdatesUserPassword;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public bool $canManageTwoFactor;
    public bool $twoFactorEnabled;
    public bool $requiresConfirmation;

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication(auth()->user());
            }

            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }
    }

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        $this->updateAuthenticatedUserPassword();

        session()->flash('status', 'Senha atualizada com sucesso.');
    }

    /**
     * Handle the two-factor authentication enabled event.
     */
    #[On('two-factor-enabled')]
    public function onTwoFactorEnabled(): void
    {
        $this->twoFactorEnabled = true;
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(Auth::user());

        $this->twoFactorEnabled = false;
        session()->flash('status', 'Autenticacao em dois fatores desativada com sucesso.');
    }
}; ?>

<x-layouts.portal title="Seguranca" subtitle="Acompanhe os controles extras de acesso e mantenha a conta protegida.">
    <x-profile.shell
        current="security"
        heading="Seguranca da conta"
        subheading="Concentre senha, autenticacao em dois fatores e demais controles de acesso em uma experiencia alinhada ao portal."
    >
        <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-slate-900">Atualizar senha</h3>
                <p class="text-sm text-slate-500">Troque sua senha sempre que houver suspeita de compartilhamento ou reutilizacao indevida.</p>
            </div>

            <form method="POST" wire:submit="updatePassword" class="space-y-4">
                <div class="grid gap-4 {{ auth()->user()->must_change_password ? 'md:grid-cols-2' : 'md:grid-cols-3' }}">
                    @unless (auth()->user()->must_change_password)
                        <label class="block text-sm text-slate-600">
                            <span class="mb-2 block font-medium">Senha atual</span>
                            <input
                                type="password"
                                wire:model="current_password"
                                class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none"
                                autocomplete="current-password"
                                required
                            >
                            @error('current_password') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                    @endunless

                    <label class="block text-sm text-slate-600">
                        <span class="mb-2 block font-medium">Nova senha</span>
                        <input
                            type="password"
                            wire:model="password"
                            class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none"
                            autocomplete="new-password"
                            minlength="12"
                            required
                        >
                        @error('password') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="block text-sm text-slate-600">
                        <span class="mb-2 block font-medium">Confirmar nova senha</span>
                        <input
                            type="password"
                            wire:model="password_confirmation"
                            class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none"
                            autocomplete="new-password"
                            minlength="12"
                            required
                        >
                    </label>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
                    <p class="text-sm text-slate-500">Use pelo menos 12 caracteres, com letras maiusculas e minusculas, numeros e simbolos.</p>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:loading.class="ui-loading"
                        wire:target="updatePassword"
                        class="ui-action rounded-2xl bg-slate-900 px-5 py-3 text-sm font-medium text-white hover:bg-slate-800"
                    >
                        Atualizar senha
                    </button>
                </div>
            </form>
        </section>

        @if ($canManageTwoFactor)
            <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Autenticacao em dois fatores</h3>
                        <p class="mt-1 text-sm text-slate-500">Adicione uma camada extra de protecao com aplicativo autenticador e codigos de recuperacao.</p>
                    </div>

                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $twoFactorEnabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                        {{ $twoFactorEnabled ? 'Ativada' : 'Desativada' }}
                    </span>
                </div>

                @if ($twoFactorEnabled)
                    <div class="mt-5 space-y-5">
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-800">
                            Seu login ja conta com verificacao em duas etapas. Guarde os codigos de recuperacao em um local seguro.
                        </div>

                        <button
                            type="button"
                            wire:click="disable"
                            class="rounded-2xl border border-rose-200 bg-white px-4 py-3 text-sm font-medium text-rose-700 transition hover:border-rose-300 hover:bg-rose-50"
                        >
                            Desativar 2FA
                        </button>

                        <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />
                    </div>
                @else
                    <div class="mt-5 space-y-5">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
                            Ao ativar o recurso, voce precisara informar um codigo gerado no celular durante o login.
                        </div>

                        <flux:modal.trigger name="two-factor-setup-modal">
                            <button
                                type="button"
                                wire:click="$dispatch('start-two-factor-setup')"
                                class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800"
                            >
                                Ativar 2FA
                            </button>
                        </flux:modal.trigger>

                        <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="$requiresConfirmation" />
                    </div>
                @endif
            </section>
        @endif
    </x-profile.shell>
</x-layouts.portal>
