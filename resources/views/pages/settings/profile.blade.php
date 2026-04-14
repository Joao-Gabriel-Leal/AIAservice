<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Concerns\UpdatesUserPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Features;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Meu perfil')] class extends Component {
    use ProfileValidationRules;
    use PasswordValidationRules;
    use UpdatesUserPassword;
    use WithFileUploads;

    public string $name = '';
    public string $email = '';
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';
    public $photo = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            ...$this->profileRules($user->id),
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($this->photo instanceof UploadedFile) {
            $user->updateProfilePhoto($this->photo);
        }

        $this->reset('photo');
        Auth::setUser($user->fresh());

        session()->flash('status', 'Perfil atualizado com sucesso.');
    }

    /**
     * Remove the current profile photo.
     */
    public function removeProfilePhoto(): void
    {
        $user = Auth::user();

        $user->deleteProfilePhoto();
        $this->reset('photo');
        Auth::setUser($user->fresh());

        session()->flash('status', 'Foto de perfil removida com sucesso.');
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
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        session()->flash('status', 'Enviamos um novo link de verificacao para o seu email.');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! (Auth::user() instanceof MustVerifyEmail)
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }

    #[Computed]
    public function canManageTwoFactor(): bool
    {
        return Features::canManageTwoFactorAuthentication();
    }

    #[Computed]
    public function twoFactorEnabled(): bool
    {
        return Auth::user()->hasEnabledTwoFactorAuthentication();
    }
}; ?>

<x-layouts.portal title="Meu perfil" subtitle="Atualize seus dados pessoais, a foto e as configuracoes basicas da sua conta.">
    <x-profile.shell
        current="profile"
        heading="Meu perfil"
        subheading="Padronize sua conta com o mesmo visual do portal e mantenha seus dados sempre atualizados."
    >
        <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-slate-900">Dados pessoais</h3>
                <p class="text-sm text-slate-500">Atualize nome, email e a foto que sera exibida no menu e nos cards da sua conta.</p>
            </div>

            <form wire:submit="updateProfileInformation" class="space-y-6">
                <div class="flex flex-col gap-6 lg:flex-row">
                    <div class="lg:w-72">
                        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                            <p class="text-sm font-medium text-slate-900">Foto de perfil</p>
                            <p class="mt-1 text-sm text-slate-500">Use uma imagem clara para facilitar sua identificacao no sistema.</p>

                            <div class="mt-5 flex justify-center">
                                @if ($photo)
                                    <img
                                        src="{{ $photo->temporaryUrl() }}"
                                        alt="Preview da nova foto de perfil"
                                        class="size-28 rounded-[1.75rem] object-cover shadow-sm ring-4 ring-white"
                                    >
                                @else
                                    <x-user-avatar :user="auth()->user()" size="xl" class="size-28 rounded-[1.75rem] text-2xl shadow-sm ring-4 ring-white" />
                                @endif
                            </div>

                            <div class="mt-5 space-y-3">
                                <label class="flex cursor-pointer items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:bg-slate-100 hover:text-slate-900">
                                    <span>Trocar foto</span>
                                    <input type="file" wire:model="photo" accept=".jpg,.jpeg,.png,.webp" class="sr-only">
                                </label>

                                @if (auth()->user()->hasProfilePhoto() || $photo)
                                    <button
                                        type="button"
                                        wire:click="removeProfilePhoto"
                                        class="w-full rounded-2xl border border-rose-200 bg-white px-4 py-3 text-sm font-medium text-rose-700 transition hover:border-rose-300 hover:bg-rose-50"
                                    >
                                        Remover foto
                                    </button>
                                @endif
                            </div>

                            <p class="mt-4 text-xs text-slate-500">Formatos aceitos: JPG, PNG ou WEBP com ate 2 MB.</p>
                            @error('photo') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            <div wire:loading wire:target="photo" class="mt-2 text-xs text-slate-500">Preparando imagem...</div>
                        </div>
                    </div>

                    <div class="flex-1 space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block text-sm text-slate-600 md:col-span-2">
                                <span class="mb-2 block font-medium">Nome</span>
                                <input
                                    type="text"
                                    wire:model="name"
                                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-sky-500 focus:outline-none"
                                    autocomplete="name"
                                    required
                                >
                                @error('name') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>

                            <label class="block text-sm text-slate-600 md:col-span-2">
                                <span class="mb-2 block font-medium">Email</span>
                                <input
                                    type="email"
                                    wire:model="email"
                                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-sky-500 focus:outline-none"
                                    autocomplete="email"
                                    required
                                >
                                @error('email') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                        </div>

                        @if ($this->hasUnverifiedEmail)
                            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800">
                                <p class="font-medium">Seu email ainda nao foi verificado.</p>
                                <p class="mt-1 text-amber-700">Confirme o endereco para liberar todos os fluxos de seguranca da conta.</p>
                                <button
                                    type="button"
                                    wire:click.prevent="resendVerificationNotification"
                                    class="mt-3 rounded-xl border border-amber-300 bg-white px-3 py-2 text-sm font-medium text-amber-800 transition hover:bg-amber-100"
                                >
                                    Reenviar email de verificacao
                                </button>
                            </div>
                        @endif

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
                            A foto de perfil sera usada no menu lateral, no acesso rapido da conta e nos componentes que exibem seu avatar.
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
                    <p class="text-sm text-slate-500">Salve as alteracoes para refletir o novo avatar e os dados atualizados em toda a interface.</p>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:loading.class="ui-loading"
                        wire:target="updateProfileInformation,photo"
                        class="ui-action rounded-2xl bg-slate-900 px-5 py-3 text-sm font-medium text-white hover:bg-slate-800"
                    >
                        Salvar perfil
                    </button>
                </div>
            </form>
        </section>

        <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-slate-900">Senha de acesso</h3>
                <p class="text-sm text-slate-500">Mantenha uma senha forte e exclusiva para proteger suas credenciais.</p>
            </div>

            <form wire:submit="updatePassword" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-3">
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

                    <label class="block text-sm text-slate-600">
                        <span class="mb-2 block font-medium">Nova senha</span>
                        <input
                            type="password"
                            wire:model="password"
                            class="w-full rounded-2xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none"
                            autocomplete="new-password"
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
                            required
                        >
                    </label>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
                    <p class="text-sm text-slate-500">Sempre que possivel, use uma senha longa com combinacao de letras, numeros e simbolos.</p>
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

        @if ($this->canManageTwoFactor)
            <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Seguranca avancada</h3>
                        <p class="mt-1 text-sm text-slate-500">Gerencie autenticacao em dois fatores e outros recursos extras da sua conta.</p>
                    </div>

                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $this->twoFactorEnabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                        {{ $this->twoFactorEnabled ? '2FA ativada' : '2FA disponivel' }}
                    </span>
                </div>

                <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
                    A tela de seguranca permite ativar a autenticacao em dois fatores, gerar codigos de recuperacao e revisar os controles extras da conta.
                </div>

                <a href="{{ route('security.edit') }}" class="mt-5 inline-flex rounded-2xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 hover:text-slate-900">
                    Abrir seguranca avancada
                </a>
            </section>
        @endif

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-profile.shell>
</x-layouts.portal>
