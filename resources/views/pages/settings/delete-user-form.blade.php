<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="mt-10 space-y-6">
    <div class="ui-panel rounded-3xl border border-rose-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Excluir conta</h3>
                <p class="mt-1 text-sm text-slate-500">Use esta acao apenas se realmente quiser remover seu acesso e todos os recursos vinculados.</p>
            </div>

            <flux:modal.trigger name="confirm-user-deletion">
                <button
                    type="button"
                    class="rounded-2xl border border-rose-200 bg-white px-4 py-3 text-sm font-medium text-rose-700 transition hover:border-rose-300 hover:bg-rose-50"
                    data-test="delete-user-button"
                >
                    Excluir conta
                </button>
            </flux:modal.trigger>
        </div>
    </div>

    <livewire:pages::settings.delete-user-modal />
</section>
