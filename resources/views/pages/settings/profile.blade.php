<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\UpdatesUserPassword;
use App\Modules\Assets\Models\Asset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    public string $name = '';
    public string $job_title = '';
    public string $phone = '';
    public string $location = '';
    public string $birth_date = '';
    public string $work_status = 'office';
    public string $themePreference = 'light';
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $canManageTwoFactor = false;
    public bool $twoFactorEnabled = false;
    public bool $requiresTwoFactorConfirmation = false;
    public ?string $inlineSavedField = null;
    public $photo = null;

    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $user = Auth::user();

        $this->name = (string) $user->name;
        $this->job_title = (string) ($user->job_title ?? '');
        $this->phone = (string) ($user->phone ?? '');
        $this->location = (string) ($user->location ?? '');
        $this->birth_date = $user->birth_date?->format('Y-m-d') ?? '';
        $this->work_status = array_key_exists((string) $user->work_status, $this->workStatusOptions())
            ? (string) $user->work_status
            : 'office';
        $this->themePreference = $user->preferredTheme();

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

    public function saveInlineProfileField(string $field): void
    {
        $rules = $this->inlineProfileRules();

        abort_unless(array_key_exists($field, $rules), 404);

        if (in_array($field, ['name', 'job_title', 'phone', 'location'], true)) {
            $this->{$field} = trim((string) $this->{$field});
        }

        $validated = $this->validate(
            [$field => $rules[$field]],
            [],
            $this->inlineProfileAttributes(),
        );

        $value = $validated[$field] ?? null;
        $payloadValue = match ($field) {
            'job_title', 'phone', 'location' => $this->nullableString($value),
            'birth_date' => filled($value) ? $value : null,
            default => $value,
        };

        $user = Auth::user();
        $user->forceFill([$field => $payloadValue])->save();
        $user = $user->fresh();

        Auth::setUser($user);

        $this->{$field} = $this->profileFieldValueFromUser($user, $field);
        $this->inlineSavedField = $field;
    }

    public function resetInlineProfileField(string $field): void
    {
        abort_unless(array_key_exists($field, $this->inlineProfileRules()), 404);

        $this->{$field} = $this->profileFieldValueFromUser(Auth::user()->fresh(), $field);
        $this->resetErrorBag($field);
    }

    public function saveWorkStatus(): void
    {
        $validated = $this->validate(
            ['work_status' => ['required', Rule::in(array_keys($this->workStatusOptions()))]],
            [],
            ['work_status' => 'status do trabalho'],
        );

        $user = Auth::user();
        $user->forceFill(['work_status' => $validated['work_status']])->save();
        Auth::setUser($user->fresh());

        session()->flash('status', 'Status do trabalho atualizado com sucesso.');
    }

    public function selectWorkStatus(string $status): void
    {
        $this->work_status = $status;

        $this->saveWorkStatus();
    }

    public function saveThemePreference(): void
    {
        $validated = $this->validate(
            ['themePreference' => ['required', Rule::in(['light', 'dark'])]],
            [],
            ['themePreference' => 'tema'],
        );

        $user = Auth::user();
        $user->forceFill(['theme_preference' => $validated['themePreference']])->save();
        Auth::setUser($user->fresh());

        $this->dispatch('theme-preference-changed', theme: $validated['themePreference']);
        session()->flash('status', 'Preferencia de aparencia atualizada com sucesso.');
    }

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

    public function removeProfilePhoto(): void
    {
        $user = Auth::user();

        $user->deleteProfilePhoto();
        $this->reset('photo');
        Auth::setUser($user->fresh());

        session()->flash('status', 'Foto de perfil removida com sucesso.');
    }

    public function updatePassword(): void
    {
        $this->updateAuthenticatedUserPassword();

        session()->flash('status', 'Senha atualizada com sucesso.');
    }

    #[On('two-factor-enabled')]
    public function onTwoFactorEnabled(): void
    {
        $this->twoFactorEnabled = true;
        Auth::setUser(Auth::user()->fresh());

        session()->flash('status', 'Autenticacao em dois fatores ativada com sucesso.');
    }

    public function disableTwoFactor(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(Auth::user());

        $this->twoFactorEnabled = false;
        Auth::setUser(Auth::user()->fresh());

        session()->flash('status', 'Autenticacao em dois fatores desativada com sucesso.');
    }

    public function workStatusOptions(): array
    {
        return [
            'office' => ['label' => 'No escritorio', 'detail' => 'Disponivel presencialmente', 'classes' => 'border-sky-300 bg-sky-50 text-sky-700'],
            'home' => ['label' => 'Trabalhando de casa', 'detail' => 'Disponivel remotamente', 'classes' => 'border-indigo-300 bg-indigo-50 text-indigo-700'],
            'external' => ['label' => 'Trabalhando externamente', 'detail' => 'Em atendimento ou deslocamento', 'classes' => 'border-cyan-300 bg-cyan-50 text-cyan-700'],
            'away' => ['label' => 'Ausente', 'detail' => 'Fora por um periodo curto', 'classes' => 'border-slate-300 bg-slate-100 text-slate-700'],
            'do_not_disturb' => ['label' => 'Nao perturbe', 'detail' => 'Foco ou indisponivel', 'classes' => 'border-rose-300 bg-rose-50 text-rose-700'],
            'vacation' => ['label' => 'Ferias', 'detail' => 'Periodo de descanso', 'classes' => 'border-amber-300 bg-amber-50 text-amber-700'],
            'medical_leave' => ['label' => 'Licenca medica', 'detail' => 'Afastamento por saude', 'classes' => 'border-violet-300 bg-violet-50 text-violet-700'],
        ];
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

    public function getRecentSessionsProperty(): Collection
    {
        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', Auth::id())
            ->orderByDesc('last_activity')
            ->limit(5)
            ->get()
            ->map(fn ($session) => [
                'id' => (string) $session->id,
                'ip' => $session->ip_address ?: 'IP indisponivel',
                'agent' => $this->summarizeUserAgent($session->user_agent ?? null),
                'last_activity' => Carbon::createFromTimestamp((int) $session->last_activity),
                'current' => session()->getId() === (string) $session->id,
            ]);
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function inlineProfileRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'location' => ['nullable', 'string', 'max:120'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    private function inlineProfileAttributes(): array
    {
        return [
            'name' => 'nome',
            'job_title' => 'cargo',
            'phone' => 'telefone',
            'location' => 'local',
            'birth_date' => 'data de nascimento',
        ];
    }

    private function profileFieldValueFromUser($user, string $field): string
    {
        return match ($field) {
            'birth_date' => $user->{$field}?->format('Y-m-d') ?? '',
            default => (string) ($user->{$field} ?? ''),
        };
    }

    private function summarizeUserAgent(?string $userAgent): string
    {
        if (! filled($userAgent)) {
            return 'Navegador indisponivel';
        }

        $agent = Str::lower($userAgent);
        $browser = match (true) {
            Str::contains($agent, 'edg/') => 'Edge',
            Str::contains($agent, 'chrome/') => 'Chrome',
            Str::contains($agent, 'firefox/') => 'Firefox',
            Str::contains($agent, 'safari/') => 'Safari',
            default => 'Navegador',
        };
        $platform = match (true) {
            Str::contains($agent, 'windows') => 'Windows',
            Str::contains($agent, 'mac os') || Str::contains($agent, 'macintosh') => 'macOS',
            Str::contains($agent, 'android') => 'Android',
            Str::contains($agent, 'iphone') || Str::contains($agent, 'ipad') => 'iOS',
            Str::contains($agent, 'linux') => 'Linux',
            default => 'dispositivo desconhecido',
        };

        return "{$browser} em {$platform}";
    }
}; ?>

<div x-data x-on:theme-preference-changed.window="$flux.appearance = $event.detail.theme" class="mx-auto max-w-6xl">
    @php
        $user = auth()->user();
        $assignedAssets = $this->assignedAssets;
        $recentSessions = $this->recentSessions;
        $workStatusOptions = $this->workStatusOptions();
        $currentWorkStatus = $workStatusOptions[$work_status] ?? $workStatusOptions['office'];
    @endphp

    @if (session('status'))
        <div class="ui-panel mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    @if ($user->must_change_password)
        <div class="ui-panel mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <p class="font-semibold">Troca de senha obrigatoria</p>
            <p class="mt-1">Antes de continuar usando o portal, atualize a senha da sua conta.</p>
        </div>
    @endif

    <div class="space-y-6">
        <section class="ui-panel overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
                <div class="bg-slate-950 px-6 py-7 text-white">
                    <div class="grid gap-7 lg:grid-cols-[156px_minmax(0,1fr)_360px]">
                        <div class="flex justify-center lg:block">
                            <div class="shrink-0">
                                @if ($photo)
                                    <img src="{{ $photo->temporaryUrl() }}" alt="Preview da nova foto de perfil" class="size-32 rounded-full object-cover ring-4 ring-white/10">
                                @else
                                    <x-user-avatar :user="$user" size="xl" class="size-32 rounded-full text-4xl ring-4 ring-white/10" />
                                @endif
                            </div>
                        </div>

                        <div class="min-w-0">
                            <div x-data="{ editing: false, skipSave: false }" class="group max-w-2xl">
                                <button
                                    type="button"
                                    x-show="! editing"
                                    x-on:click="editing = true; $nextTick(() => $refs.input.focus())"
                                    class="block max-w-full truncate rounded-lg px-1 text-left text-3xl font-semibold text-white outline-none transition hover:bg-white/10 focus:bg-white/10"
                                >
                                    {{ $name ?: 'Adicione um nome' }}
                                </button>
                                <input
                                    x-cloak
                                    x-show="editing"
                                    x-ref="input"
                                    type="text"
                                    wire:model="name"
                                    x-on:keydown.enter.prevent="$refs.input.blur()"
                                    x-on:keydown.escape.prevent="skipSave = true; $wire.resetInlineProfileField('name'); editing = false"
                                    x-on:blur="if (skipSave) { skipSave = false; return; } $wire.saveInlineProfileField('name'); editing = false"
                                    class="w-full rounded-lg border border-blue-400 bg-slate-950 px-2 py-1 text-3xl font-semibold text-white outline-none ring-2 ring-blue-500/30"
                                    maxlength="120"
                                >
                                @error('name') <span class="mt-2 block text-xs text-rose-200">{{ $message }}</span> @enderror
                            </div>

                            <div x-data="{ editing: false, skipSave: false }" class="mt-2 max-w-md">
                                <button
                                    type="button"
                                    x-show="! editing"
                                    x-on:click="editing = true; $nextTick(() => $refs.input.focus())"
                                    class="rounded-md px-1 text-left text-sm text-slate-300 outline-none transition hover:bg-white/10 hover:text-white focus:bg-white/10 focus:text-white"
                                >
                                    {{ $job_title ?: 'Adicione um cargo' }}
                                </button>
                                <input
                                    x-cloak
                                    x-show="editing"
                                    x-ref="input"
                                    type="text"
                                    wire:model="job_title"
                                    x-on:keydown.enter.prevent="$refs.input.blur()"
                                    x-on:keydown.escape.prevent="skipSave = true; $wire.resetInlineProfileField('job_title'); editing = false"
                                    x-on:blur="if (skipSave) { skipSave = false; return; } $wire.saveInlineProfileField('job_title'); editing = false"
                                    class="w-full rounded-md border border-blue-400 bg-slate-950 px-2 py-1 text-sm text-white outline-none"
                                    maxlength="120"
                                    placeholder="Adicione um cargo"
                                >
                                @error('job_title') <span class="mt-2 block text-xs text-rose-200">{{ $message }}</span> @enderror
                            </div>

                            <div class="mt-4 flex flex-wrap items-start gap-2">
                                <span class="rounded-lg bg-blue-600 px-3 py-1 text-xs font-semibold text-white">{{ $user->global_role?->label() ?? 'Colaborador' }}</span>
                                <div x-data="{ open: false }" class="relative">
                                    <button
                                        type="button"
                                        x-on:click="open = ! open"
                                        x-on:keydown.escape.window="open = false"
                                        class="inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-1 text-xs font-medium text-slate-200 outline-none transition hover:border-white/30 hover:bg-white/10 focus:border-blue-300 focus:bg-white/10"
                                        aria-label="Status do trabalho"
                                        aria-haspopup="listbox"
                                        x-bind:aria-expanded="open"
                                    >
                                        <span>{{ $currentWorkStatus['label'] }}</span>
                                        <svg aria-hidden="true" viewBox="0 0 16 16" class="size-3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="m4 6 4 4 4-4" />
                                        </svg>
                                    </button>

                                    <div
                                        x-cloak
                                        x-show="open"
                                        x-transition.origin.top.left
                                        x-on:click.outside="open = false"
                                        class="absolute left-0 z-30 mt-2 w-64 rounded-xl border border-white/10 bg-slate-950 p-2 shadow-2xl ring-1 ring-white/10"
                                        role="listbox"
                                    >
                                        @foreach ($workStatusOptions as $value => $option)
                                            <button
                                                type="button"
                                                wire:click="selectWorkStatus('{{ $value }}')"
                                                x-on:click="open = false"
                                                class="flex w-full items-start justify-between gap-3 rounded-lg px-3 py-2 text-left text-xs transition hover:bg-white/10 {{ $work_status === $value ? 'bg-blue-500/20 text-white' : 'text-slate-300' }}"
                                                role="option"
                                                aria-selected="{{ $work_status === $value ? 'true' : 'false' }}"
                                            >
                                                <span>
                                                    <span class="block font-semibold">{{ $option['label'] }}</span>
                                                    <span class="mt-0.5 block text-[11px] text-slate-400">{{ $option['detail'] }}</span>
                                                </span>
                                                @if ($work_status === $value)
                                                    <span class="mt-0.5 rounded-full bg-blue-500 px-2 py-0.5 text-[10px] font-semibold text-white">Atual</span>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                                @if ($inlineSavedField)
                                    <span class="rounded-lg border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-100">Salvo</span>
                                @endif
                            </div>
                            @error('work_status') <span class="mt-2 block text-xs text-rose-200">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid content-start gap-4 text-sm sm:grid-cols-2 lg:grid-cols-1">
                            <div>
                                <p class="font-semibold text-slate-100">E-mail</p>
                                <p class="mt-1 break-all text-slate-300">{{ $user->email }}</p>
                            </div>
                            @foreach ([
                                ['field' => 'phone', 'label' => 'Telefone', 'placeholder' => 'Adicione um telefone', 'maxlength' => 40, 'type' => 'text'],
                                ['field' => 'location', 'label' => 'Local', 'placeholder' => 'Adicione um local', 'maxlength' => 120, 'type' => 'text'],
                                ['field' => 'birth_date', 'label' => 'Data de nascimento', 'placeholder' => 'Adicione uma data de nascimento', 'maxlength' => null, 'type' => 'date'],
                            ] as $inlineField)
                                @php
                                    $fieldName = $inlineField['field'];
                                    $fieldDisplay = match ($fieldName) {
                                        'birth_date' => $user->birth_date?->format('d/m/Y'),
                                        default => $$fieldName ?: null,
                                    };
                                @endphp
                                <div x-data="{ editing: false, skipSave: false }">
                                    <p class="font-semibold text-slate-100">{{ $inlineField['label'] }}</p>
                                    <button
                                        type="button"
                                        x-show="! editing"
                                        x-on:click="editing = true; $nextTick(() => $refs.input.focus())"
                                        class="mt-1 block max-w-full truncate rounded-md px-1 text-left text-slate-300 outline-none transition hover:bg-white/10 hover:text-white focus:bg-white/10 focus:text-white"
                                    >
                                        {{ $fieldDisplay ?: $inlineField['placeholder'] }}
                                    </button>
                                    <input
                                        x-cloak
                                        x-show="editing"
                                        x-ref="input"
                                        type="{{ $inlineField['type'] }}"
                                        wire:model="{{ $fieldName }}"
                                        x-on:keydown.enter.prevent="$refs.input.blur()"
                                        x-on:keydown.escape.prevent="skipSave = true; $wire.resetInlineProfileField('{{ $fieldName }}'); editing = false"
                                        x-on:blur="if (skipSave) { skipSave = false; return; } $wire.saveInlineProfileField('{{ $fieldName }}'); editing = false"
                                        class="mt-1 w-full rounded-md border border-blue-400 bg-slate-950 px-2 py-1 text-slate-100 outline-none"
                                        @if ($inlineField['maxlength']) maxlength="{{ $inlineField['maxlength'] }}" @endif
                                        placeholder="{{ $inlineField['placeholder'] }}"
                                    >
                                    @error($fieldName) <span class="mt-2 block text-xs text-rose-200">{{ $message }}</span> @enderror
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <form wire:submit="saveProfilePhoto" class="flex flex-col gap-4 px-6 py-5 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Foto de perfil</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Formatos aceitos: JPG, PNG ou WEBP com ate 2 MB.</p>
                        @error('photo') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        <div wire:loading wire:target="photo" class="mt-2 text-xs text-slate-500">Preparando imagem...</div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <label class="ui-action rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                            <span>Escolher foto</span>
                            <input type="file" wire:model="photo" accept=".jpg,.jpeg,.png,.webp" class="sr-only">
                        </label>

                        <button type="submit" wire:loading.attr="disabled" wire:loading.class="ui-loading" wire:target="saveProfilePhoto,photo" class="ui-action ui-action-primary rounded-xl px-4 py-2 text-sm">
                            Salvar foto
                        </button>

                        @if ($user->hasProfilePhoto() || $photo)
                            <button type="button" wire:click="removeProfilePhoto" class="ui-action ui-action-danger rounded-xl px-4 py-2 text-sm">
                                Remover foto
                            </button>
                        @endif
                    </div>
                </form>
            </section>

            <section id="aparencia" class="ui-panel rounded-2xl border border-slate-200 bg-white p-6 shadow-sm scroll-mt-6 dark:border-slate-800 dark:bg-slate-900/80">
                <div class="flex flex-col gap-2 border-b border-slate-200 pb-5 dark:border-slate-800">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Aparencia e notificacoes</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Tema e resumo de notificacoes continuam dentro do seu perfil.</p>
                </div>

                <div class="grid gap-6 pt-5 xl:grid-cols-[minmax(0,1fr)_320px]">
                    <form wire:submit="saveThemePreference" class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block cursor-pointer">
                                <input type="radio" wire:model="themePreference" value="light" class="peer sr-only">
                                <span class="block rounded-xl border border-slate-200 bg-slate-50 p-4 transition peer-checked:border-blue-500 peer-checked:ring-2 peer-checked:ring-blue-100 dark:border-slate-800 dark:bg-slate-950">
                                    <span class="block text-base font-semibold text-slate-900 dark:text-white">Modo claro</span>
                                    <span class="mt-1 block text-sm text-slate-500 dark:text-slate-400">Fundo luminoso e contraste limpo.</span>
                                </span>
                            </label>

                            <label class="block cursor-pointer">
                                <input type="radio" wire:model="themePreference" value="dark" class="peer sr-only">
                                <span class="block rounded-xl border border-slate-200 bg-slate-950 p-4 text-white transition peer-checked:border-blue-500 peer-checked:ring-2 peer-checked:ring-blue-100 dark:border-slate-700">
                                    <span class="block text-base font-semibold">Modo escuro</span>
                                    <span class="mt-1 block text-sm text-slate-300">Menos brilho para uso prolongado.</span>
                                </span>
                            </label>
                        </div>

                        @error('themePreference') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror

                        <button type="submit" wire:loading.attr="disabled" wire:loading.class="ui-loading" wire:target="saveThemePreference" class="ui-action ui-action-primary rounded-xl px-5 py-3 text-sm">
                            Salvar aparencia
                        </button>
                    </form>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/70">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Notificacoes</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Voce tem {{ $user->unreadNotifications()->count() }} notificacao(oes) nao lida(s).
                        </p>
                        <a href="{{ route('notifications.index') }}" class="mt-4 inline-flex rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-white dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900">
                            Abrir notificacoes
                        </a>
                    </div>
                </div>
            </section>

            <section id="seguranca" class="ui-panel rounded-2xl border border-slate-200 bg-white p-6 shadow-sm scroll-mt-6 dark:border-slate-800 dark:bg-slate-900/80">
                <div class="flex flex-col gap-2 border-b border-slate-200 pb-5 dark:border-slate-800">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Seguranca da conta</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $user->must_change_password ? 'Defina uma nova senha para liberar o acesso ao restante do portal.' : 'Troque sua senha e mantenha os controles de autenticacao em um unico lugar.' }}
                    </p>
                </div>

                <div class="space-y-6 pt-5">
                    <form wire:submit="updatePassword" class="space-y-4">
                        <div class="grid gap-4 {{ $user->must_change_password ? 'lg:grid-cols-2' : 'lg:grid-cols-3' }}">
                            @unless ($user->must_change_password)
                                <label class="block text-sm text-slate-600 dark:text-slate-300">
                                    <span class="mb-2 block font-medium">Senha atual</span>
                                    <input type="password" wire:model="current_password" class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-white" autocomplete="current-password" required>
                                    @error('current_password') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                </label>
                            @endunless

                            <label class="block text-sm text-slate-600 dark:text-slate-300">
                                <span class="mb-2 block font-medium">Nova senha</span>
                                <input type="password" wire:model="password" class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-white" autocomplete="new-password" minlength="12" required>
                                @error('password') <span class="mt-2 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>

                            <label class="block text-sm text-slate-600 dark:text-slate-300">
                                <span class="mb-2 block font-medium">Confirmar nova senha</span>
                                <input type="password" wire:model="password_confirmation" class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-white" autocomplete="new-password" minlength="12" required>
                            </label>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                            <p class="text-sm text-slate-500">Use pelo menos 8 caracteres, com letras maiusculas e minusculas, numeros e simbolos.</p>
                            <button type="submit" wire:loading.attr="disabled" wire:loading.class="ui-loading" wire:target="updatePassword" class="ui-action ui-action-primary rounded-xl px-5 py-3 text-sm">
                                Atualizar senha
                            </button>
                        </div>
                    </form>

                    @if ($canManageTwoFactor)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-950/70">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">Autenticacao em dois fatores</h3>
                                    <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                                        Proteja o acesso com aplicativo autenticador e codigos de recuperacao.
                                    </p>
                                </div>

                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $twoFactorEnabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                                    {{ $twoFactorEnabled ? 'Ativada' : 'Desativada' }}
                                </span>
                            </div>

                            @if ($twoFactorEnabled)
                                <div class="mt-5 space-y-5">
                                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-800">
                                        Sua conta ja exige verificacao adicional no login. Guarde os codigos de recuperacao em local seguro.
                                    </div>

                                    <button type="button" wire:click="disableTwoFactor" class="ui-action ui-action-danger rounded-xl px-4 py-3 text-sm">
                                        Desativar 2FA
                                    </button>

                                    <livewire:pages::settings.two-factor.recovery-codes />
                                </div>
                            @else
                                <div class="mt-5 space-y-5">
                                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                                        Ao ativar o recurso, o sistema passa a solicitar um codigo do aplicativo autenticador durante o login.
                                    </div>

                                    <flux:modal.trigger name="two-factor-setup-modal">
                                        <button type="button" wire:click="$dispatch('start-two-factor-setup')" class="ui-action ui-action-primary rounded-xl px-4 py-3 text-sm">
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

            <section id="sessoes" class="ui-panel rounded-2xl border border-slate-200 bg-white p-6 shadow-sm scroll-mt-6 dark:border-slate-800 dark:bg-slate-900/80">
                <div class="flex flex-col gap-2 border-b border-slate-200 pb-5 dark:border-slate-800">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Historico da sessao</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Acompanhe os acessos recentes registrados para sua conta.</p>
                </div>

                <div class="space-y-3 pt-5">
                    @forelse ($recentSessions as $session)
                        <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 md:flex-row md:items-center md:justify-between dark:border-slate-800 dark:bg-slate-950/70">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                    {{ $session['agent'] }}
                                    @if ($session['current'])
                                        <span class="ml-2 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">Sessao atual</span>
                                    @endif
                                </p>
                                <p class="mt-1 text-xs text-slate-500">{{ $session['ip'] }}</p>
                            </div>
                            <p class="text-sm text-slate-500">{{ $session['last_activity']->diffForHumans() }}</p>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-950/70">
                            Nenhuma sessao recente encontrada.
                        </div>
                    @endforelse
                </div>
            </section>

            <section id="patrimonios" class="ui-panel rounded-2xl border border-slate-200 bg-white p-6 shadow-sm scroll-mt-6 dark:border-slate-800 dark:bg-slate-900/80">
                <div class="flex flex-col gap-2 border-b border-slate-200 pb-5 dark:border-slate-800">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Patrimonios vinculados</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Acompanhe os patrimonios atualmente vinculados ao seu usuario, com status, localizacao e historico recente.
                    </p>
                </div>

                <div class="space-y-4 pt-5">
                    @forelse ($assignedAssets as $assignedAsset)
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-950/70">
                            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_112px]">
                                <div class="space-y-4">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ $assignedAsset->name }}</h3>
                                            <p class="mt-1 text-sm text-slate-500">{{ $assignedAsset->asset_code }}</p>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-full px-3 py-1 text-xs font-medium {{ $assignedAsset->status?->badgeClasses() }}">
                                                {{ $assignedAsset->statusLabel() }}
                                            </span>
                                            <a href="{{ route('assets.show', $assignedAsset) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 dark:border-slate-700 dark:text-slate-200">
                                                Abrir detalhe
                                            </a>
                                        </div>
                                    </div>

                                    <div class="grid gap-3 md:grid-cols-3">
                                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                                            <p class="font-medium text-slate-900 dark:text-white">Setor atual</p>
                                            <div class="mt-1">
                                                <x-sector-badge :sector="$assignedAsset->currentSector" mode="dot" />
                                            </div>
                                        </div>
                                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                                            <p class="font-medium text-slate-900 dark:text-white">Sala atual</p>
                                            <p class="mt-1">{{ $assignedAsset->currentRoom?->name ?? 'Sem sala' }}</p>
                                        </div>
                                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                                            <p class="font-medium text-slate-900 dark:text-white">Colaborador</p>
                                            <p class="mt-1">{{ $assignedAsset->currentUser?->name ?? 'Nao vinculado' }}</p>
                                        </div>
                                    </div>

                                    <div>
                                        <p class="text-sm font-medium text-slate-900 dark:text-white">Ultimas movimentacoes</p>
                                        <div class="mt-3 space-y-2">
                                            @forelse ($assignedAsset->movements->take(3) as $movement)
                                                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                                        <p class="font-medium text-slate-900 dark:text-white">{{ $movement->type?->label() ?? 'Movimentacao' }}</p>
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
                                                <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-4 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900">
                                                    Nenhuma movimentacao registrada ainda.
                                                </div>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>

                                <a href="{{ $assignedAsset->qrCodeUrl() }}" class="block self-start rounded-xl border border-slate-200 bg-white p-3 text-center dark:border-slate-800 dark:bg-slate-900" data-qr-target="{{ $assignedAsset->qrCodeUrl() }}">
                                    {!! $assignedAsset->qrCodeSvg(88) !!}
                                    <span class="mt-2 block text-xs text-slate-500">QR do patrimonio</span>
                                </a>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-950/70">
                            Nenhum patrimonio esta vinculado ao seu usuario neste momento.
                        </div>
                    @endforelse
                </div>
            </section>
    </div>
</div>
