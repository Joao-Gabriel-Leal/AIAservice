<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Aparencia da conta')] class extends Component {
    public string $themePreference = 'light';

    public function mount(): void
    {
        $this->themePreference = Auth::user()->preferredTheme();
    }

    public function updatedThemePreference(string $value): void
    {
        if (! in_array($value, ['light', 'dark'], true)) {
            $this->themePreference = 'light';

            return;
        }

        $user = Auth::user();
        $user->forceFill(['theme_preference' => $value])->save();
        Auth::setUser($user->fresh());

        $this->dispatch('theme-preference-changed', theme: $value);
    }
}; ?>

<div x-data x-on:theme-preference-changed.window="$flux.appearance = $event.detail.theme">
    <x-profile.shell
        current="appearance"
        heading="Aparencia"
        subheading="O tema fica salvo por usuario e continua o mesmo ate voce trocar novamente."
    >
        <section class="ui-panel rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
            <div class="mb-6">
                <h3 class="text-xl font-semibold text-slate-900 dark:text-white">Tema do sistema</h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    Escolha entre modo claro e escuro. A alteracao e aplicada na hora e fica salva no seu perfil.
                </p>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <label class="block cursor-pointer">
                    <input type="radio" wire:model.live="themePreference" value="light" class="peer sr-only">
                    <div class="rounded-[1.8rem] border border-slate-200 bg-[linear-gradient(180deg,#ffffff_0%,#eff5ff_100%)] p-5 shadow-sm transition peer-checked:border-[#2a43ce] peer-checked:ring-4 peer-checked:ring-[#dfe6ff] hover:border-slate-300 dark:border-slate-700 dark:bg-[linear-gradient(180deg,#121c39_0%,#0d152d_100%)] dark:peer-checked:border-[#70b6ff] dark:peer-checked:ring-[#223a73]/60">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-base font-semibold text-slate-900 dark:text-white">Modo claro</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Interface luminosa com fundo suave e contraste limpo.</p>
                            </div>
                            <span class="rounded-full bg-[#e9f8ff] px-3 py-1 text-xs font-semibold text-[#0f9bd5] dark:bg-[#143052] dark:text-[#9fdfff]">Light</span>
                        </div>

                        <div class="mt-5 overflow-hidden rounded-[1.4rem] border border-slate-200 bg-[#f5f8ff] p-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.8)] dark:border-slate-700 dark:bg-[#0a1327]">
                            <div class="rounded-2xl bg-[linear-gradient(135deg,#2439c8_0%,#3551e2_60%,#416bff_100%)] px-4 py-4 text-white">
                                <p class="text-xs uppercase tracking-[0.22em] text-cyan-100">AIA Service</p>
                                <p class="mt-2 text-lg font-semibold">Visual claro padrao</p>
                            </div>
                            <div class="mt-4 grid gap-3">
                                <div class="h-4 w-32 rounded-full bg-slate-200"></div>
                                <div class="h-3 w-full rounded-full bg-slate-100"></div>
                                <div class="h-3 w-3/4 rounded-full bg-slate-100"></div>
                            </div>
                        </div>
                    </div>
                </label>

                <label class="block cursor-pointer">
                    <input type="radio" wire:model.live="themePreference" value="dark" class="peer sr-only">
                    <div class="rounded-[1.8rem] border border-slate-200 bg-[linear-gradient(180deg,#ffffff_0%,#eff5ff_100%)] p-5 shadow-sm transition peer-checked:border-[#2a43ce] peer-checked:ring-4 peer-checked:ring-[#dfe6ff] hover:border-slate-300 dark:border-slate-700 dark:bg-[linear-gradient(180deg,#121c39_0%,#0d152d_100%)] dark:peer-checked:border-[#70b6ff] dark:peer-checked:ring-[#223a73]/60">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-base font-semibold text-slate-900 dark:text-white">Modo escuro</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Visual noturno com contraste alto e menos brilho.</p>
                            </div>
                            <span class="rounded-full bg-[#e8edff] px-3 py-1 text-xs font-semibold text-[#3f54cf] dark:bg-[#143052] dark:text-[#9fdfff]">Dark</span>
                        </div>

                        <div class="mt-5 overflow-hidden rounded-[1.4rem] border border-[#1f2d59] bg-[#091225] p-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.03)]">
                            <div class="rounded-2xl bg-[linear-gradient(135deg,#17289e_0%,#2138c6_62%,#3150f0_100%)] px-4 py-4 text-white">
                                <p class="text-xs uppercase tracking-[0.22em] text-cyan-100">AIA Service</p>
                                <p class="mt-2 text-lg font-semibold">Visual escuro persistente</p>
                            </div>
                            <div class="mt-4 grid gap-3">
                                <div class="h-4 w-32 rounded-full bg-[#28406e]"></div>
                                <div class="h-3 w-full rounded-full bg-[#12203e]"></div>
                                <div class="h-3 w-3/4 rounded-full bg-[#12203e]"></div>
                            </div>
                        </div>
                    </div>
                </label>
            </div>

            <div class="mt-6 rounded-[1.6rem] border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                Tema atual:
                <span class="font-semibold text-slate-900 dark:text-white">
                    {{ $themePreference === 'dark' ? 'Escuro' : 'Claro' }}
                </span>
                . A preferencia e exclusiva da sua conta.
            </div>
        </section>
    </x-profile.shell>
</div>
