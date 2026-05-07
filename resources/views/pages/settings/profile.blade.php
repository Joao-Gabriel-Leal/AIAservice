<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\UpdatesUserPassword;
use App\Modules\Assets\Models\Asset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Meu perfil')] class extends Component {
    use PasswordValidationRules;
    use UpdatesUserPassword;
    use WithFileUploads;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $canManageTwoFactor = false;
    public bool $twoFactorEnabled = false;
    public bool $requiresTwoFactorConfirmation = false;
    public $photo = null;

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $user = Auth::user();

        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if (! $this->canManageTwoFactor) {
            return;
        }

        if (
            Fortify::confirmsTwoFactorAuthentication()
            && filled($user->two_factor_secret)
            && is_null($user->two_factor_confirmed_at)
        ) {
            $disableTwoFactorAuthentication($user);
            $user = $user->fresh();
            Auth::setUser($user);
        }

        $this->twoFactorEnabled = $user->hasEnabledTwoFactorAuthentication();
        $this->requiresTwoFactorConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
    }

    /**
     * Save a new profile photo for the authenticated user.
     */
    public function saveProfilePhoto(): void
    {
        $validated = $this->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = Auth::user();
        $user->updateProfilePhoto($validated['photo']);

        $this->reset('photo');
        Auth::setUser($user->fresh());

        session()->flash('status', 'Foto de perfil atualizada com sucesso.');
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
     * Handle the two-factor authentication enabled event.
     */
    #[On('two-factor-enabled')]
    public function onTwoFactorEnabled(): void
    {
        $this->twoFactorEnabled = true;
        Auth::setUser(Auth::user()->fresh());

        session()->flash('status', 'Autenticacao em dois fatores ativada com sucesso.');
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disableTwoFactor(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(Auth::user());

        $this->twoFactorEnabled = false;
        Auth::setUser(Auth::user()->fresh());

        session()->flash('status', 'Autenticacao em dois fatores desativada com sucesso.');
    }

    public function getAssignedAssetsProperty(): Collection
    {
        return Asset::query()
            ->with([
                'currentSector',
                'currentRoom',
                'currentUser',
                'movements.fromSector',
                'movements.toSector',
                'movements.movedBy',
            ])
            ->where('current_user_id', Auth::id())
            ->latest()
            ->get();
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-6">
    @php($assignedAssets = $this->assignedAssets)

    @if (session('status'))
        <div class="ui-panel rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    @if (auth()->user()->must_change_password)
        <div class="ui-panel rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <p class="font-semibold">Troca de senha obrigatoria</p>
            <p class="mt-1">Antes de continuar usando o portal, atualize a senha da sua conta.</p>
        </div>
    @endif

    <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 pb-5">
            <h2 class="text-lg font-semibold text-slate-900">Foto de perfil</h2>
            <p class="max-w-3xl text-sm text-slate-500">
                Atualize a imagem exibida no menu lateral e nos componentes que mostram seu avatar.
            </p>
        </div>

        <div class="grid gap-6 pt-6 lg:grid-cols-[280px_minmax(0,1fr)]">
            <form wire:submit="saveProfilePhoto" class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                <p class="text-sm font-semibold text-slate-900">Avatar da conta</p>
                <p class="mt-1 text-sm text-slate-500">Use uma imagem clara, quadrada e de facil identificacao.</p>

                <div class="mt-5 flex justify-center">
                    @if ($photo)
                        <img
                            src="{{ $photo->temporaryUrl() }}"
                            alt="Preview da nova foto de perfil"
                            class="size-24 rounded-3xl object-cover ring-4 ring-white"
                        >
                    @else
                        <x-user-avatar :user="auth()->user()" size="xl" class="size-24 rounded-3xl text-xl ring-4 ring-white" />
                    @endif
                </div>

                <div class="mt-5 space-y-3">
                    <label class="flex cursor-pointer items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:bg-slate-100 hover:text-slate-900">
                        <span>Escolher foto</span>
                        <input type="file" wire:model="photo" accept=".jpg,.jpeg,.png,.webp" class="sr-only">
                    </label>

                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:loading.class="ui-loading"
                        wire:target="saveProfilePhoto,photo"
                        class="ui-action ui-action-primary w-full rounded-2xl px-4 py-3 text-sm"
                    >
                        Salvar foto
                    </button>

                    @if (auth()->user()->hasProfilePhoto() || $photo)
                        <button
                            type="button"
                            wire:click="removeProfilePhoto"
                            class="ui-action ui-action-danger w-full rounded-2xl px-4 py-3 text-sm"
                        >
                            Remover foto
                        </button>
                    @endif
                </div>

                <p class="mt-4 text-xs text-slate-500">Formatos aceitos: JPG, PNG ou WEBP com ate 2 MB.</p>
                @error('photo') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                <div wire:loading wire:target="photo" class="mt-2 text-xs text-slate-500">Preparando imagem...</div>
            </form>

            <div class="space-y-4">
                <div class="rounded-3xl border border-slate-200 bg-white p-5">
                    <p class="text-sm font-semibold text-slate-900">Conta administrada pela equipe</p>
                    <p class="mt-2 text-sm text-slate-500">
                        Nome, email, acessos e demais dados cadastrais sao gerenciados apenas pelo super admin.
                    </p>
                </div>

                <div class="rounded-3xl border border-sky-200 bg-sky-50 p-5">
                    <p class="text-sm font-semibold text-sky-900">O que voce pode ajustar aqui</p>
                    <div class="mt-3 grid gap-3 md:grid-cols-3">
                        <div class="rounded-2xl border border-sky-100 bg-white px-4 py-3 text-sm text-slate-600">
                            <p class="font-medium text-slate-900">Foto</p>
                            <p class="mt-1">Atualiza seu avatar no sistema.</p>
                        </div>
                        <div class="rounded-2xl border border-sky-100 bg-white px-4 py-3 text-sm text-slate-600">
                            <p class="font-medium text-slate-900">Senha</p>
                            <p class="mt-1">Mantem suas credenciais protegidas.</p>
                        </div>
                        <div class="rounded-2xl border border-sky-100 bg-white px-4 py-3 text-sm text-slate-600">
                            <p class="font-medium text-slate-900">2FA</p>
                            <p class="mt-1">Adiciona verificacao extra no login.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 pb-5">
            <h2 class="text-lg font-semibold text-slate-900">Patrimonios vinculados</h2>
            <p class="max-w-3xl text-sm text-slate-500">
                Aqui voce acompanha apenas os patrimonios atualmente vinculados ao seu usuario, com status, localizacao e historico recente.
            </p>
        </div>

        <div class="space-y-4 pt-6">
            @forelse ($assignedAssets as $assignedAsset)
                <article class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_112px]">
                        <div class="space-y-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-base font-semibold text-slate-900">{{ $assignedAsset->name }}</h3>
                                    <p class="mt-1 text-sm text-slate-500">{{ $assignedAsset->asset_code }}</p>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-3 py-1 text-xs font-medium {{ $assignedAsset->status?->badgeClasses() }}">
                                        {{ $assignedAsset->statusLabel() }}
                                    </span>
                                    <a href="{{ route('assets.show', $assignedAsset) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700">
                                        Abrir detalhe
                                    </a>
                                </div>
                            </div>

                            <div class="grid gap-3 md:grid-cols-3">
                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                                    <p class="font-medium text-slate-900">Setor atual</p>
                                    <div class="mt-1">
                                        <x-sector-badge :sector="$assignedAsset->currentSector" mode="dot" />
                                    </div>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                                    <p class="font-medium text-slate-900">Sala atual</p>
                                    <p class="mt-1">{{ $assignedAsset->currentRoom?->name ?? 'Sem sala' }}</p>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                                    <p class="font-medium text-slate-900">Colaborador</p>
                                    <p class="mt-1">{{ $assignedAsset->currentUser?->name ?? 'Nao vinculado' }}</p>
                                </div>
                            </div>

                            <div>
                                <p class="text-sm font-medium text-slate-900">Ultimas movimentacoes</p>
                                <div class="mt-3 space-y-2">
                                    @forelse ($assignedAsset->movements->take(3) as $movement)
                                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <p class="font-medium text-slate-900">{{ $movement->type?->label() ?? 'Movimentacao' }}</p>
                                                <p class="text-xs text-slate-500">{{ $movement->moved_at?->format('d/m/Y H:i') }}</p>
                                            </div>
                                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                                @if ($movement->fromSector)
                                                    <x-sector-badge :sector="$movement->fromSector" mode="chip" class="text-[11px]" prefix="Origem" />
                                                @else
                                                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-[11px] font-medium text-slate-600">
                                                        Origem inicial
                                                    </span>
                                                @endif
                                                <span class="text-xs text-slate-400">-></span>
                                                <x-sector-badge :sector="$movement->toSector" mode="chip" class="text-[11px]" prefix="Destino" />
                                                @if ($movement->movedBy)
                                                    <span class="text-xs text-slate-500">por {{ $movement->movedBy->name }}</span>
                                                @endif
                                            </div>
                                            @if ($movement->reason)
                                                <p class="mt-1 text-xs text-slate-500">{{ $movement->reason }}</p>
                                            @endif
                                        </div>
                                    @empty
                                        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-4 text-sm text-slate-500">
                                            Nenhuma movimentacao registrada ainda.
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <a href="{{ $assignedAsset->qrCodeUrl() }}" class="block self-start rounded-3xl border border-slate-200 bg-white p-3 text-center" data-qr-target="{{ $assignedAsset->qrCodeUrl() }}">
                            {!! $assignedAsset->qrCodeSvg(88) !!}
                            <span class="mt-2 block text-xs text-slate-500">QR do patrimonio</span>
                        </a>
                    </div>
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">
                    Nenhum patrimonio esta vinculado ao seu usuario neste momento.
                </div>
            @endforelse
        </div>
    </section>

    <section id="seguranca" class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 pb-5">
            <h2 class="text-lg font-semibold text-slate-900">Seguranca da conta</h2>
            <p class="max-w-3xl text-sm text-slate-500">
                {{ auth()->user()->must_change_password ? 'Defina uma nova senha para liberar o acesso ao restante do portal.' : 'Troque sua senha e mantenha os controles de autenticacao em um unico lugar.' }}
            </p>
        </div>

        <div class="space-y-6 pt-6">
            <form wire:submit="updatePassword" class="space-y-4">
                <div class="grid gap-4 {{ auth()->user()->must_change_password ? 'lg:grid-cols-2' : 'lg:grid-cols-3' }}">
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
                    <p class="text-sm text-slate-500">Use pelo menos 8 caracteres, com letras maiusculas e minusculas, numeros e simbolos.</p>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:loading.class="ui-loading"
                        wire:target="updatePassword"
                        class="ui-action ui-action-primary rounded-2xl px-5 py-3 text-sm"
                    >
                        Atualizar senha
                    </button>
                </div>
            </form>

            @if ($canManageTwoFactor)
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">Autenticacao em dois fatores</h3>
                            <p class="mt-1 max-w-2xl text-sm text-slate-500">
                                Proteja o acesso com aplicativo autenticador e codigos de recuperacao.
                            </p>
                        </div>

                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $twoFactorEnabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                            {{ $twoFactorEnabled ? 'Ativada' : 'Desativada' }}
                        </span>
                    </div>

                    @if ($twoFactorEnabled)
                        <div class="mt-5 space-y-5">
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-800">
                                Sua conta ja exige verificacao adicional no login. Guarde os codigos de recuperacao em local seguro.
                            </div>

                            <button
                                type="button"
                                wire:click="disableTwoFactor"
                                class="ui-action ui-action-danger rounded-2xl px-4 py-3 text-sm"
                            >
                                Desativar 2FA
                            </button>

                            <livewire:pages::settings.two-factor.recovery-codes />
                        </div>
                    @else
                        <div class="mt-5 space-y-5">
                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm text-slate-600">
                                Ao ativar o recurso, o sistema passa a solicitar um codigo do aplicativo autenticador durante o login.
                            </div>

                            <flux:modal.trigger name="two-factor-setup-modal">
                                <button
                                    type="button"
                                    wire:click="$dispatch('start-two-factor-setup')"
                                    class="ui-action ui-action-primary rounded-2xl px-4 py-3 text-sm"
                                >
                                    Ativar 2FA
                                </button>
                            </flux:modal.trigger>

                            <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="$requiresTwoFactorConfirmation" />
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </section>
</div>
