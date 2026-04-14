<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Aparencia da conta')] class extends Component {
    //
}; ?>

<x-layouts.portal title="Aparencia" subtitle="Escolha como o sistema deve apresentar o tema visual da sua conta.">
    <x-profile.shell
        current="appearance"
        heading="Aparencia"
        subheading="Ajuste a preferencia visual da sua conta sem sair do padrao estatico do portal."
    >
        <section class="ui-panel rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-slate-900">Tema visual</h3>
                <p class="text-sm text-slate-500">Defina se a interface deve seguir claro, escuro ou acompanhar a preferencia do dispositivo.</p>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" class="w-full">
                    <flux:radio value="light" icon="sun">{{ __('Claro') }}</flux:radio>
                    <flux:radio value="dark" icon="moon">{{ __('Escuro') }}</flux:radio>
                    <flux:radio value="system" icon="computer-desktop">{{ __('Sistema') }}</flux:radio>
                </flux:radio.group>

                <p class="mt-4 text-sm text-slate-500">
                    A preferencia e aplicada imediatamente e permanece vinculada a sua sessao de uso.
                </p>
            </div>
        </section>
    </x-profile.shell>
</x-layouts.portal>
